<?php
// =====================================================================
//  SEGURANÇA DO SISTEMA (SanyTech)
//  - Sessão com cookie protegido (HttpOnly, SameSite) e tempo de inatividade
//  - Cabeçalhos de segurança (clickjacking, sniffing, CSP)
//  - Token CSRF em todos os formulários
//  - Rate limiting: 4 tentativas nas ações sensíveis + limite de requisições
//    por minuto em páginas, API, downloads e formulários (HTTP 429)
//  - Campo-isca invisível + tempo mínimo de preenchimento (anti-robô)
//  - Controle de acesso por perfil (SECRETARIA / DENTISTA)
// =====================================================================

const SESSAO_INATIVIDADE_MIN = 30;     // desloga após 30 min sem uso

function https_ativo() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

function iniciar_sessao() {
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('GDM_SESSAO');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => https_ativo(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function cabecalhos_seguranca() {
    if (headers_sent() || PHP_SAPI === 'cli') return;
    header_remove('X-Powered-By');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; "
         . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
         . "img-src 'self' data: blob: https://images.unsplash.com https://images.pexels.com; connect-src 'self' https://viacep.com.br; "
         . "frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'");
    if (https_ativo()) header('Strict-Transport-Security: max-age=31536000');
}

iniciar_sessao();
cabecalhos_seguranca();

function ip_cliente() {
    // REMOTE_ADDR não pode ser falsificado pelo navegador (X-Forwarded-For pode)
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

// ---------- Proteção CSRF (todos os formulários POST) ----------
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_campo() {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}
function csrf_ok() {
    return !empty($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', (string) $_POST['csrf']);
}
function csrf_validar() {
    if (!csrf_ok()) {
        http_response_code(400);
        die('<div style="font-family:sans-serif;padding:30px"><h2>Formulário expirado</h2><p>Por segurança, volte à página anterior, recarregue e tente de novo.</p><a href="javascript:history.back()">Voltar</a></div>');
    }
}

// =====================================================================
//  RATE LIMITING (limitação de taxa) – substitui o captcha de letras
//  Controla quantas requisições/tentativas cada cliente (IP) pode fazer
//  dentro de um intervalo de tempo. Protege o servidor (CPU, banco e rede),
//  dificulta força bruta, robôs de spam/scraping e negação de serviço (DoS)
//  e garante equidade: um único cliente não consome os recursos de todos.
//
//  Duas técnicas são usadas:
//  1) JANELA DESLIZANTE (tabela limite_acao) – para ações sensíveis:
//     login, agendamento, contato, avaliação e troca de senha.
//     Regra principal: 4 TENTATIVAS por janela de tempo.
//  2) JANELA FIXA POR MINUTO (tabela limite_requisicao) – para o tráfego
//     em geral: páginas, API de horários, downloads e envios de formulário.
//     Ao estourar, o servidor responde HTTP 429 (Too Many Requests) com o
//     cabeçalho Retry-After, como fazem as APIs profissionais.
// =====================================================================
const LIMITE_TENTATIVAS      = 4;      // regra do projeto: 4 tentativas
const LOGIN_MAX_TENTATIVAS   = LIMITE_TENTATIVAS;
const LOGIN_JANELA_MIN       = 15;     // ...dentro de 15 minutos

// Ações sensíveis: [máximo, janela em minutos, texto para o usuário]
const LIMITES_ACAO = [
    'login'       => [LIMITE_TENTATIVAS, LOGIN_JANELA_MIN, 'tentativas de login'],
    'login_ti'    => [LIMITE_TENTATIVAS, LOGIN_JANELA_MIN, 'tentativas de login da área técnica'],
    'agendamento' => [LIMITE_TENTATIVAS, 60, 'agendamentos'],
    'contato'     => [LIMITE_TENTATIVAS, 30, 'mensagens'],
    'avaliacao'   => [LIMITE_TENTATIVAS, 60, 'avaliações'],
    'troca_senha' => [LIMITE_TENTATIVAS, 15, 'tentativas de troca de senha'],
];
// Tráfego geral: [máximo de requisições, por quantos segundos]
const LIMITES_TRAFEGO = [
    'pagina'  => [120, 60],   // páginas do site/sistema por IP
    'api'     => [40, 60],    // consultas de horários livres (agendamento)
    'arquivo' => [60, 60],    // fotos e exames do prontuário
    'envio'   => [20, 60],    // qualquer formulário enviado (POST)
];

function regra_limite($acao, $max = null, $janelaMin = null) {
    $r = LIMITES_ACAO[$acao] ?? [LIMITE_TENTATIVAS, 15, 'tentativas'];
    return [(int) ($max ?? $r[0]), (int) ($janelaMin ?? $r[1]), $r[2]];
}

// Situação do cliente em uma ação: quantas usou, quantas restam e quando libera
function limite_status(PDO $pdo, $acao, $max = null, $janelaMin = null) {
    [$max, $janelaMin, $rotulo] = regra_limite($acao, $max, $janelaMin);
    $desde = date('Y-m-d H:i:s', time() - $janelaMin * 60);
    $st = $pdo->prepare("SELECT COUNT(*) AS qtd, MIN(criado_em) AS primeira FROM limite_acao WHERE ip = ? AND acao = ? AND criado_em >= ?");
    $st->execute([ip_cliente(), $acao, $desde]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'primeira' => null];
    $usadas = (int) $r['qtd'];
    $liberaEm = ($usadas >= $max && $r['primeira']) ? strtotime($r['primeira']) + $janelaMin * 60 : null;
    return [
        'acao' => $acao, 'max' => $max, 'janela' => $janelaMin, 'rotulo' => $rotulo,
        'usadas' => min($usadas, $max), 'restantes' => max(0, $max - $usadas),
        'bloqueado' => $usadas >= $max, 'libera_em' => $liberaEm,
        'espera_seg' => $liberaEm ? max(1, $liberaEm - time()) : 0,
    ];
}
function limite_excedido(PDO $pdo, $acao, $max = null, $janelaMin = null) {
    return limite_status($pdo, $acao, $max, $janelaMin)['bloqueado'];
}
function limite_registrar(PDO $pdo, $acao) {
    $pdo->prepare("INSERT INTO limite_acao (ip, acao, criado_em) VALUES (?,?,?)")->execute([ip_cliente(), $acao, date('Y-m-d H:i:s')]);
    // limpeza ocasional de registros antigos (mantém 7 dias para o painel de segurança)
    if (random_int(1, 50) === 1) $pdo->prepare("DELETE FROM limite_acao WHERE criado_em < ?")->execute([date('Y-m-d H:i:s', time() - 7 * 86400)]);
}
function limite_limpar(PDO $pdo, $acao, $ip = null) {
    $pdo->prepare("DELETE FROM limite_acao WHERE ip = ? AND acao = ?")->execute([$ip ?? ip_cliente(), $acao]);
}
function tempo_espera_txt($seg) {
    $seg = (int) $seg;
    if ($seg < 60) return $seg . ' segundo' . ($seg === 1 ? '' : 's');
    $min = (int) ceil($seg / 60);
    return $min . ' minuto' . ($min === 1 ? '' : 's');
}
function limite_mensagem(array $s) {
    return 'Você atingiu o limite de ' . $s['max'] . ' ' . $s['rotulo'] . ' em ' . $s['janela']
         . ' minutos. Por segurança, aguarde ' . tempo_espera_txt($s['espera_seg']) . ' e tente novamente.';
}

// ---------- Janela fixa por minuto (tráfego geral) ----------
function limite_trafego(PDO $pdo, $chave) {
    [$max, $seg] = LIMITES_TRAFEGO[$chave] ?? [120, 60];
    $janela = intdiv(time(), $seg);
    $ip = ip_cliente();
    $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? "INSERT INTO limite_requisicao (ip, chave, janela, total) VALUES (?,?,?,1) ON CONFLICT(ip, chave, janela) DO UPDATE SET total = total + 1"
        : "INSERT INTO limite_requisicao (ip, chave, janela, total) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE total = total + 1";
    try {
        $pdo->prepare($sql)->execute([$ip, $chave, $janela]);
        $st = $pdo->prepare("SELECT total FROM limite_requisicao WHERE ip = ? AND chave = ? AND janela = ?");
        $st->execute([$ip, $chave, $janela]);
        $total = (int) $st->fetchColumn();
        if (random_int(1, 100) === 1) $pdo->prepare("DELETE FROM limite_requisicao WHERE janela < ?")->execute([$janela - 5]);
    } catch (PDOException $e) {
        error_log('[clinica] rate limit indisponível: ' . $e->getMessage());
        return ['ok' => true, 'max' => $max, 'restantes' => $max, 'reset' => 0];
    }
    $reset = ($janela + 1) * $seg - time();
    // a primeira requisição bloqueada fica registrada para o painel de segurança
    if ($total === $max + 1) limite_registrar($pdo, 'excesso_' . $chave);
    return ['ok' => $total <= $max, 'max' => $max, 'restantes' => max(0, $max - $total), 'reset' => $reset];
}

// Aplica o limite e, se estourar, responde 429 (HTML ou JSON)
function aplicar_limite_trafego(PDO $pdo, $chave, $json = false) {
    if (PHP_SAPI === 'cli') return;
    $s = limite_trafego($pdo, $chave);
    if (!headers_sent()) {
        header('X-RateLimit-Limit: ' . $s['max']);
        header('X-RateLimit-Remaining: ' . $s['restantes']);
        header('X-RateLimit-Reset: ' . $s['reset']);
    }
    if ($s['ok']) return;
    http_response_code(429);
    header('Retry-After: ' . max(1, $s['reset']));
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'horarios' => [], 'msg' => 'Muitas consultas em pouco tempo. Aguarde ' . tempo_espera_txt($s['reset']) . '.']);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Muitas requisições</title><meta http-equiv="refresh" content="' . max(5, $s['reset']) . '"></head>'
       . '<body style="font-family:system-ui,sans-serif;background:#f5f9f9;color:#0f2236;display:grid;place-items:center;min-height:100vh;margin:0;padding:1rem">'
       . '<main style="max-width:460px;background:#fff;border:1px solid #e3ebef;border-radius:8px;padding:2rem;text-align:center">'
       . '<p style="font-size:.8rem;letter-spacing:.14em;text-transform:uppercase;color:#0b6f6d;font-weight:700;margin:0">Erro 429 · Rate limiting</p>'
       . '<h1 style="font-size:1.5rem;color:#06244b">Muitas requisições em pouco tempo</h1>'
       . '<p>Para proteger o sistema, este aparelho fez mais de ' . (int) $s['max'] . ' acessos por minuto. A página será liberada em <strong>' . e(tempo_espera_txt($s['reset'])) . '</strong> e recarrega sozinha.</p>'
       . '<p><a href="index.php" style="color:#0b6f6d">Voltar ao início</a></p></main></body></html>';
    exit;
}

// ---------- Proteção visível nos formulários (no lugar do captcha) ----------
// Mostra ao usuário quantas tentativas ainda tem e, de forma invisível,
// adiciona duas defesas contra robôs: campo-isca e tempo mínimo de preenchimento.
function protecao_campo($form) {
    global $pdo;
    $form = preg_replace('/[^a-z_]/', '', $form);
    $_SESSION['form_inicio'][$form] = time();
    $s = $pdo instanceof PDO ? limite_status($pdo, $form) : regra_limite($form) + ['restantes' => LIMITE_TENTATIVAS];
    $max = $s['max'] ?? LIMITE_TENTATIVAS;
    $rest = $s['restantes'] ?? $max;
    $pontos = '';
    for ($i = 1; $i <= $max; $i++) $pontos .= '<span class="' . ($i <= $max - $rest ? 'usado' : '') . '"></span>';
    $id = 'rl_' . $form;
    // Mensagem discreta para o visitante: só o cadeado e as tentativas restantes
    $txt = !empty($s['bloqueado'])
        ? 'Limite atingido. Tente novamente em ' . tempo_espera_txt($s['espera_seg']) . '.'
        : 'Restam ' . $rest . ' de ' . $max . ' tentativas.';
    return '<div class="rl-box' . (!empty($s['bloqueado']) ? ' rl-bloqueado' : '') . '" id="' . $id . '">'
        . '<i class="bi bi-lock-fill" aria-hidden="true"></i>'
        . '<span><strong>Protegido</strong> · ' . $txt . '</span>'
        . '<span class="rl-dots" aria-hidden="true">' . $pontos . '</span>'
        // campo-isca: invisível para pessoas, robôs costumam preencher
        . '<div class="hp-field" aria-hidden="true"><label>Não preencha este campo<input type="text" name="site_url" tabindex="-1" autocomplete="off"></label></div>'
        . '</div>';
}

// Retorna '' se passou ou a mensagem de erro (campo-isca e envio rápido demais = robô)
function protecao_validar($form, $tempoMinimo = 3) {
    $form = preg_replace('/[^a-z_]/', '', $form);
    $inicio = $_SESSION['form_inicio'][$form] ?? 0;
    unset($_SESSION['form_inicio'][$form]);
    if (!empty($_POST['site_url'])) return 'Não foi possível validar o envio. Tente novamente.';
    if ($inicio && time() - $inicio < $tempoMinimo) return 'Envio rápido demais. Confira os dados e tente novamente.';
    return '';
}

// ---------- Controle de acesso por perfil ----------
function usuario_logado() {
    return !empty($_SESSION['logado']) ? $_SESSION : null;
}
function perfil_atual() {
    return $_SESSION['perfil'] ?? '';
}
function pagina_inicial_perfil($perfil) {
    if ($perfil === 'TI') return 'ti_painel.php';
    return $perfil === 'DENTISTA' ? 'dent_painel.php' : 'painel.php';
}

// Máscara de IP para telas da área técnica (mostra só o necessário)
function ip_mascarado($ip) {
    if (strpos($ip, ':') !== false) { $p = explode(':', $ip); return $p[0] . ':' . ($p[1] ?? '') . ':…'; }
    $p = explode('.', $ip);
    return count($p) === 4 ? $p[0] . '.' . $p[1] . '.•••.' . $p[3] : '•••';
}

// ---------- Métricas de funcionamento e velocidade (área técnica SanyTech) ----------
// Guarda só: página, método, código HTTP e tempo de resposta. Nenhum dado pessoal.
function registrar_metrica(PDO $pdo) {
    if (PHP_SAPI === 'cli') return;
    register_shutdown_function(function () use ($pdo) {
        try {
            $ms = (int) round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000);
            $pag = substr(preg_replace('/[^a-z0-9_\/.]/i', '', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), 0, 60);
            $pdo->prepare("INSERT INTO metrica_requisicao (pagina, metodo, status, ms, criado_em) VALUES (?,?,?,?,?)")
                ->execute([$pag ?: '/', substr($_SERVER['REQUEST_METHOD'] ?? 'GET', 0, 6), (int) (http_response_code() ?: 200), $ms, date('Y-m-d H:i:s')]);
            if (random_int(1, 200) === 1) $pdo->prepare("DELETE FROM metrica_requisicao WHERE criado_em < ?")->execute([date('Y-m-d H:i:s', time() - 7 * 86400)]);
        } catch (Throwable $t) { /* métrica nunca derruba a página */ }
    });
}

// Chame no topo das páginas restritas: exigir_login(['SECRETARIA']) ou exigir_login(['DENTISTA'])
function exigir_login(array $perfis) {
    // a área técnica tem a própria tela de login
    $login = $perfis === ['TI'] ? 'sanytech.php' : 'login.php';
    if (empty($_SESSION['logado'])) { header('Location: ' . $login); exit; }
    // tempo de inatividade
    if (time() - ($_SESSION['ultimo_uso'] ?? 0) > SESSAO_INATIVIDADE_MIN * 60) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: ' . $login . '?expirou=1');
        exit;
    }
    // sessão presa ao navegador (dificulta roubo de sessão)
    if (($_SESSION['agente'] ?? '') !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
        $_SESSION = [];
        header('Location: ' . $login);
        exit;
    }
    $_SESSION['ultimo_uso'] = time();
    if (!in_array($_SESSION['perfil'] ?? '', $perfis, true)) {
        http_response_code(403);
        header('Location: ' . pagina_inicial_perfil($_SESSION['perfil'] ?? ''));
        exit;
    }
    // páginas restritas não ficam em cache do navegador
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

// Política de senha forte
function senha_forte($s) {
    if (mb_strlen($s) < 10) return 'A senha precisa ter pelo menos 10 caracteres.';
    if (!preg_match('/[a-z]/', $s) || !preg_match('/[A-Z]/', $s)) return 'Use letras maiúsculas e minúsculas.';
    if (!preg_match('/\d/', $s)) return 'Inclua pelo menos um número.';
    if (!preg_match('/[^a-zA-Z0-9]/', $s)) return 'Inclua pelo menos um símbolo (ex.: ! @ # % &).';
    return '';
}

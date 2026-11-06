<?php
// =====================================================================
//  SEGURANÇA DO SISTEMA (SanyTech)
//  - Sessão com cookie protegido (HttpOnly, SameSite) e tempo de inatividade
//  - Cabeçalhos de segurança (clickjacking, sniffing, CSP)
//  - Token CSRF em todos os formulários
//  - Verificação "não sou um robô" (captcha próprio + campo-isca + tempo mínimo)
//  - Limite de tentativas (login, agendamento, contato, avaliação)
//  - Controle de acesso por perfil (SECRETARIA / DENTISTA)
// =====================================================================

const SESSAO_INATIVIDADE_MIN = 30;     // desloga após 30 min sem uso
const LOGIN_MAX_TENTATIVAS   = 5;      // erros permitidos...
const LOGIN_JANELA_MIN       = 15;     // ...dentro de 15 minutos

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
         . "img-src 'self' data: blob: https://images.unsplash.com; connect-src 'self' https://viacep.com.br; "
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

// ---------- Verificação "não sou um robô" ----------
// Três camadas: (1) código em imagem que só humanos leem, (2) campo-isca invisível
// que robôs preenchem, (3) tempo mínimo de preenchimento.
const CAPTCHA_CARACTERES = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // sem 0/O, 1/I/L

function captcha_novo($form) {
    $cod = '';
    for ($i = 0; $i < 5; $i++) $cod .= CAPTCHA_CARACTERES[random_int(0, strlen(CAPTCHA_CARACTERES) - 1)];
    $_SESSION['captcha'][$form] = ['codigo' => $cod, 'gerado' => time()];
    return $cod;
}

function captcha_campo($form, $rotulo = 'Verificação de segurança') {
    $form = preg_replace('/[^a-z_]/', '', $form);
    $_SESSION['form_inicio'][$form] = time();
    $id = 'cap_' . $form;
    return '<div class="captcha-box" data-form="' . $form . '">'
        . '<label class="form-label" for="' . $id . '"><i class="bi bi-shield-check text-teal me-1" aria-hidden="true"></i>' . e($rotulo) . ' <span class="text-muted fw-normal">— não sou um robô</span></label>'
        . '<div class="captcha-row">'
        . '<img src="captcha.php?f=' . $form . '&amp;r=' . bin2hex(random_bytes(4)) . '" alt="Código de verificação com 5 letras e números" class="captcha-img" width="170" height="56">'
        . '<button type="button" class="btn btn-icon btn-ghost captcha-novo" aria-label="Gerar outro código" title="Gerar outro código"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>'
        . '<input type="text" id="' . $id . '" name="captcha" class="form-control captcha-inp" required maxlength="5" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Digite o código" aria-describedby="' . $id . '_dica">'
        . '</div>'
        . '<div class="form-text" id="' . $id . '_dica">Digite as 5 letras/números da imagem (não diferencia maiúsculas).</div>'
        // campo-isca: escondido de pessoas, robôs costumam preencher
        . '<div class="hp-field" aria-hidden="true"><label>Não preencha este campo<input type="text" name="site_url" tabindex="-1" autocomplete="off"></label></div>'
        . '</div>';
}

// Retorna '' se passou ou a mensagem de erro
function captcha_validar($form, $tempoMinimo = 3) {
    $form = preg_replace('/[^a-z_]/', '', $form);
    $dados = $_SESSION['captcha'][$form] ?? null;
    unset($_SESSION['captcha'][$form]);                     // código vale uma única vez
    $inicio = $_SESSION['form_inicio'][$form] ?? 0;

    if (!empty($_POST['site_url'])) return 'Não foi possível validar o envio. Tente novamente.';
    if ($inicio && time() - $inicio < $tempoMinimo) return 'Envio rápido demais. Confira os dados e tente novamente.';
    if (!$dados || time() - $dados['gerado'] > 900) return 'O código de verificação expirou. Digite o novo código.';
    $digitado = strtoupper(preg_replace('/\s+/', '', (string) ($_POST['captcha'] ?? '')));
    if (!hash_equals($dados['codigo'], $digitado)) return 'Código de verificação incorreto. Tente o novo código.';
    return '';
}

// ---------- Limite de tentativas / envios por IP ----------
function limite_excedido(PDO $pdo, $acao, $max, $janelaMin) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM limite_acao WHERE ip = ? AND acao = ? AND criado_em >= ?");
    $st->execute([ip_cliente(), $acao, date('Y-m-d H:i:s', time() - $janelaMin * 60)]);
    return (int) $st->fetchColumn() >= $max;
}
function limite_registrar(PDO $pdo, $acao) {
    $pdo->prepare("INSERT INTO limite_acao (ip, acao, criado_em) VALUES (?,?,?)")->execute([ip_cliente(), $acao, date('Y-m-d H:i:s')]);
    // limpeza ocasional de registros antigos
    if (random_int(1, 50) === 1) $pdo->prepare("DELETE FROM limite_acao WHERE criado_em < ?")->execute([date('Y-m-d H:i:s', time() - 86400)]);
}
function limite_limpar(PDO $pdo, $acao) {
    $pdo->prepare("DELETE FROM limite_acao WHERE ip = ? AND acao = ?")->execute([ip_cliente(), $acao]);
}

// ---------- Controle de acesso por perfil ----------
function usuario_logado() {
    return !empty($_SESSION['logado']) ? $_SESSION : null;
}
function perfil_atual() {
    return $_SESSION['perfil'] ?? '';
}
function pagina_inicial_perfil($perfil) {
    return $perfil === 'DENTISTA' ? 'dent_painel.php' : 'painel.php';
}

// Chame no topo das páginas restritas: exigir_login(['SECRETARIA']) ou exigir_login(['DENTISTA'])
function exigir_login(array $perfis) {
    if (empty($_SESSION['logado'])) { header('Location: login.php'); exit; }
    // tempo de inatividade
    if (time() - ($_SESSION['ultimo_uso'] ?? 0) > SESSAO_INATIVIDADE_MIN * 60) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: login.php?expirou=1');
        exit;
    }
    // sessão presa ao navegador (dificulta roubo de sessão)
    if (($_SESSION['agente'] ?? '') !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
        $_SESSION = [];
        header('Location: login.php');
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

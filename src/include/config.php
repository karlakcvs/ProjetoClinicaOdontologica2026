<?php
// =====================================================================
//  CONFIGURAÇÕES GERAIS - Clínica Garcia Di Mase
//  Sistema desenvolvido pela SanyTech (Projeto Integrador 2026.2 - DSM)
// =====================================================================
date_default_timezone_set('America/Sao_Paulo');

$nome_clinica   = "Garcia Di Mase";
$slogan_clinica = "Odontologia Especializada";
$contato        = "(19) 0000-1234";          // FICTÍCIO (nenhum telefone começa com 0)
$whatsapp       = "5519900001234";           // FICTÍCIO – só números, com DDI 55
$endereco       = "Rua Fictícia dos Sorrisos, 000 - Centro, Araras/SP - CEP 00000-000"; // FICTÍCIO
$email_clinica  = "contato@garciadimase.example";   // FICTÍCIO (.example nunca existe)
$empresa_dev    = "SanyTech";
$dominio_login  = "garciadimase.example";     // usado nos logins: secretaria@garciadimase.example (domínio fictício)

// Mantido do projeto original (cabecalho antigo usava $nome_barbearia)
$nome_barbearia = $nome_clinica;

// Intervalo (em minutos) entre um horário oferecido e o próximo
const PASSO_AGENDA = 15;

// Status possíveis de uma consulta (enum StatusConsulta do diagrama de classes)
$STATUS_CONSULTA = [
    'AGENDADA'   => ['Agendada',   'status-agendada',   'bi-calendar-event'],
    'CONFIRMADA' => ['Confirmada', 'status-confirmada', 'bi-check2-circle'],
    'REALIZADA'  => ['Realizada',  'status-realizada',  'bi-patch-check'],
    'CANCELADA'  => ['Cancelada',  'status-cancelada',  'bi-x-circle'],
    'FALTOU'     => ['Faltou',     'status-faltou',     'bi-person-x'],
];

// Formas de pagamento aceitas (pagamento feito na clínica, no dia do atendimento)
$FORMAS_PAGAMENTO = ['PIX', 'Dinheiro', 'Cartão de débito', 'Cartão de crédito'];
$FORMAS_ICONES    = ['PIX' => 'bi-qr-code', 'Dinheiro' => 'bi-cash', 'Cartão de débito' => 'bi-credit-card-2-front', 'Cartão de crédito' => 'bi-credit-card'];

$DIAS_SEMANA = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 0 => 'Domingo'];

$UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

// Antes e depois no perfil de cada dentista (IMAGENS ILUSTRATIVAS – fotos gratuitas do Unsplash).
// O "antes" é a mesma foto com um filtro amarelado (CSS). Chave = primeiro nome do dentista em minúsculas.
// Para trocar: copie o código da foto do endereço images.unsplash.com/photo-XXXX.
$CASOS_ANTES_DEPOIS = [
    'helena' => [
        ['1598256989809-394fa4f6cd26', 'Limpeza e polimento durante o aparelho', 'Profilaxia · 1 sessão'],
        ['1609840114035-3c981b782dfe', 'Remoção de manchas antes dos alinhadores', 'Limpeza + polimento'],
    ],
    'rafael' => [
        ['1670250492416-570b5b7343b1', 'Limpeza e polimento após o implante', 'Profilaxia · 1 sessão'],
        ['1656514894252-fb336a3ad6a6', 'Clareamento depois da reabilitação', '2 sessões'],
    ],
    'camila' => [
        ['1677026010083-78ec7f1b84ed', 'Clareamento em consultório', '2 sessões'],
        ['1769559893692-c6d0623bf8e4', 'Clareamento + limpeza', '1 sessão de cada'],
    ],
];
function foto_unsplash($id, $largura = 600) {
    return 'https://images.unsplash.com/photo-' . rawurlencode($id) . '?w=' . (int) $largura . '&q=70&auto=format&fit=crop';
}

// Segurança: sessão protegida, cabeçalhos, CSRF, captcha e limite de tentativas
require_once __DIR__ . '/seguranca.php';

// Função para saudação baseada na hora (mantida do projeto da barbearia)
function saudar() {
    $hora = (int) date('H');
    if ($hora < 12) return "Bom dia";
    if ($hora < 18) return "Boa tarde";
    return "Boa noite";
}

// Formata valores em reais
function moeda($valor) {
    return "R$\u{00A0}" . number_format((float) $valor, 2, ',', '.');
}

// Escapa texto para HTML (evita XSS)
function e($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// JSON seguro para colocar dentro de <script>
function js($valor) {
    return json_encode($valor, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

// "08:30:00" -> "08:30"
function hm($hora) {
    return substr((string) $hora, 0, 5);
}

function data_br($data) {
    return $data ? date('d/m/Y', strtotime($data)) : '';
}

// Iniciais para o avatar ("Dra. Helena Duarte" -> "HD")
function iniciais($nome) {
    $nome = preg_replace('/^(Dr\.|Dra\.)\s*/u', '', (string) $nome);
    $partes = preg_split('/\s+/', trim($nome));
    $ini = mb_substr($partes[0] ?? '', 0, 1);
    if (count($partes) > 1) $ini .= mb_substr(end($partes), 0, 1);
    return mb_strtoupper($ini);
}

// "Dra. Helena Duarte" -> "Helena"
function primeiro_nome($nome) {
    $p = preg_split('/\s+/', trim(preg_replace('/^(Dr\.|Dra\.)\s*/u', '', (string) $nome)));
    return $p[0] ?? '';
}

function duracao_txt($min) {
    $min = (int) $min;
    if ($min < 60) return $min . ' min';
    $h = intdiv($min, 60); $m = $min % 60;
    return $h . 'h' . ($m ? str_pad($m, 2, '0', STR_PAD_LEFT) : '');
}

function nome_mes($n) {
    $m = [1=>'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    return $m[(int)$n];
}

function idade($nasc) {
    if (!$nasc) return null;
    return (int) date_diff(date_create($nasc), date_create('today'))->y;
}

// Logo oficial da clínica (assets/img). $variante: 'cor' (fundo claro) ou 'branco' (fundo escuro)
function logo_clinica($classe = 'brand-logo', $variante = 'cor') {
    global $nome_clinica, $slogan_clinica;
    $arq = $variante === 'branco' ? 'logo-horizontal-branco.png' : 'logo-horizontal.png';
    return '<img src="assets/img/' . $arq . '" alt="' . e($nome_clinica . ' – ' . $slogan_clinica) . '" class="' . e($classe) . '-img" width="268" height="46">';
}

// Foto do dentista (ou ilustração padrão)
function foto_dentista($d) {
    if (empty($d['foto'])) return '';
    $arq = rawurlencode(basename($d['foto']));
    // fotos enviadas pela secretaria ficam em uploads/dentistas; as ilustrações padrão em assets
    return (strpos($d['foto'], 'up_') === 0 ? 'uploads/dentistas/' : 'assets/img/dentistas/') . $arq;
}

function estrelas($nota, $classe = '') {
    $nota = max(0, min(5, (float) $nota));
    $cheias = (int) round($nota);
    $h = '<span class="stars ' . e($classe) . '" role="img" aria-label="Nota ' . number_format($nota, 1, ',', '') . ' de 5">';
    for ($i = 1; $i <= 5; $i++) $h .= '<span class="' . ($i <= $cheias ? 'on' : 'off') . '" aria-hidden="true">★</span>';
    return $h . '</span>';
}

// Só permite redirecionar para páginas do próprio sistema
function url_local($url, $padrao = 'lista_agendamentos.php') {
    $url = (string) $url;
    return preg_match('#^[a-z_]+\.php(\?[^\s<>"]*)?(\#[a-z0-9_-]*)?$#i', $url) ? $url : $padrao;
}

function flash($msg = null, $tipo = 'success') {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $tipo]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// Validação de CPF (dígitos verificadores)
function cpf_valido($cpf) {
    $cpf = preg_replace('/\D/', '', (string) $cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $s = 0;
        for ($i = 0; $i < $t; $i++) $s += (int) $cpf[$i] * (($t + 1) - $i);
        $d = ((10 * $s) % 11) % 10;
        if ((int) $cpf[$t] !== $d) return false;
    }
    return true;
}

function cpf_formatar($cpf) {
    $c = preg_replace('/\D/', '', (string) $cpf);
    return strlen($c) === 11 ? substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9) : $cpf;
}

// Cadastro completo = tem CPF, nascimento e endereço com CEP
function cadastro_completo($p) {
    return !empty($p['cpf']) && !empty($p['data_nascimento']) && !empty($p['cep']) && !empty($p['logradouro']) && !empty($p['numero']) && !empty($p['cidade']);
}

// Avisos dos formulários públicos (mostrados na própria seção) + dados digitados de volta
function aviso_publico($secao, $msg = null, $tipo = 'success', $antigos = null) {
    if ($msg !== null) {
        $_SESSION['aviso'][$secao] = [$tipo, $msg];
        if ($antigos !== null) $_SESSION['antigo'][$secao] = $antigos;
        return null;
    }
    $a = $_SESSION['aviso'][$secao] ?? null;
    unset($_SESSION['aviso'][$secao]);
    return $a;
}
function antigo($secao, $campo) {
    return $_SESSION['antigo'][$secao][$campo] ?? '';
}
function limpar_antigos($secao) {
    unset($_SESSION['antigo'][$secao]);
}
function aviso_html($a) {
    if (!$a) return '';
    $ic = $a[0] === 'success' ? 'check-circle' : 'exclamation-triangle';
    return '<div class="alert alert-' . e($a[0]) . ' d-flex gap-2" role="alert"><i class="bi bi-' . $ic . '" aria-hidden="true"></i><div>' . e($a[1]) . '</div></div>';
}

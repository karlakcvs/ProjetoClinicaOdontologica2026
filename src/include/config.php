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
$dominio_login  = "garciadimase.example";
// Redes sociais (a clínica ainda não tem: os ícones já ficam prontos; troque "#" pelo endereço real)
$instagram      = "#";
$facebook       = "#";     // usado nos logins: secretaria@garciadimase.example (domínio fictício)

// Mapa da seção "Contato" (Google Maps). Local HIPOTÉTICO: região central de Araras/SP.
// Para trocar, mude só as coordenadas (latitude, longitude) abaixo.
$mapa_coord     = "-22.3573,-47.3842";
$mapa_embed     = "https://maps.google.com/maps?q=" . $mapa_coord . "&z=16&hl=pt-BR&output=embed";
$mapa_link      = "https://www.google.com/maps/search/?api=1&query=" . $mapa_coord;

// Perguntas frequentes do site (baseadas nas dúvidas mais comuns em sites de clínicas odontológicas)
$FAQ = [
    ['Vocês estão aceitando novos pacientes?',
     'Sim. Atendemos crianças, adultos e idosos. Você pode agendar pelo site em poucos passos ou falar com a recepção pelo WhatsApp; normalmente conseguimos uma primeira consulta na mesma semana.'],
    ['O que acontece na primeira consulta?',
     'O especialista faz uma avaliação completa da boca, conversa sobre o que te incomoda e, se necessário, pede radiografias. No fim você recebe o plano de tratamento com as etapas e os valores, sem compromisso. Traga um documento com foto e exames anteriores, se tiver.'],
    ['Quais são as formas de pagamento? Dá para parcelar?',
     'O pagamento é feito na clínica, no dia do atendimento: PIX, dinheiro, cartão de débito ou de crédito. Tratamentos com várias sessões (aparelho, implantes, lentes) podem ser parcelados no cartão de crédito.'],
    ['Vocês atendem convênio?',
     'O atendimento é particular. Se o seu plano odontológico oferece reembolso, emitimos recibo e relatório do tratamento para você solicitar o ressarcimento.'],
    ['A partir de que idade a criança pode ir ao ortodontista?',
     'A primeira avaliação ortodôntica é indicada por volta dos 7 anos, quando começam a nascer os dentes permanentes. Nessa fase dá para corrigir a mordida e o crescimento dos ossos com tratamentos mais simples.'],
    ['Quanto tempo dura o tratamento com aparelho ou alinhadores?',
     'Depende de cada caso: em geral de 12 a 36 meses, com consultas de manutenção a cada 30 a 45 dias. Depois que o aparelho sai, a contenção é obrigatória para os dentes não voltarem para a posição antiga.'],
    ['O implante dentário dói? Quanto tempo leva?',
     'A cirurgia é feita com anestesia local e o paciente não sente dor durante o procedimento; nos dias seguintes o desconforto costuma ser leve e controlado com medicação. O implante leva de 2 a 6 meses para se integrar ao osso antes da coroa definitiva — em alguns casos é possível fazer carga imediata.'],
    ['O clareamento deixa os dentes sensíveis? O resultado dura?',
     'Pode haver sensibilidade passageira, que o dentista controla ajustando o gel e com produtos dessensibilizantes. Antes de clarear é feita uma avaliação para tratar cáries e gengiva. Com boa higiene, limpezas regulares e moderação no café e no vinho, o resultado dura anos.'],
    ['De quanto em quanto tempo devo fazer limpeza?',
     'Para a maioria das pessoas, a cada 6 meses. Quem usa aparelho, tem gengivite ou implantes pode precisar voltar a cada 3 ou 4 meses — o dentista indica o intervalo ideal para você.'],
    ['Como remarco ou cancelo minha consulta?',
     'Pelo WhatsApp ou telefone da clínica, de preferência com 24 horas de antecedência. Um dia antes da consulta nossa secretaria envia um lembrete pelo WhatsApp para você confirmar.'],
    ['Vocês atendem urgência, como dor de dente?',
     'Sim. Em caso de dor, inchaço ou dente quebrado, chame no WhatsApp: encaixamos o atendimento no mesmo dia sempre que a agenda permitir.'],
];

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

// Cores da agenda por TIPO de procedimento (para não ficar tudo da mesma cor)
const CATEGORIAS_PROC = [
    'avaliacao' => ['Avaliação / 1ª consulta', '#0b3a75'],
    'prevencao' => ['Limpeza e prevenção',     '#12a3a0'],
    'orto'      => ['Ortodontia',              '#7a5cc4'],
    'cirurgia'  => ['Cirurgia e implante',     '#d9534f'],
    'estetica'  => ['Estética e restauração',  '#d4900a'],
];
function categoria_proc($nome) {
    $n = mb_strtolower((string) $nome);
    if (preg_match('/avalia|primeira/u', $n)) return 'avaliacao';
    if (preg_match('/aparelho|alinhador|ortod/u', $n)) return 'orto';
    if (preg_match('/implante|extra|cirurg|siso|enxerto/u', $n)) return 'cirurgia';
    if (preg_match('/limpeza|profilaxia|flúor|fluor/u', $n)) return 'prevencao';
    return 'estetica';
}

// Odontograma: situação de cada dente [rótulo, cor]
const SITUACOES_DENTE = [
    'HIGIDO'     => ['Hígido (saudável)', '#ffffff'],
    'CARIE'      => ['Cárie',             '#d9534f'],
    'RESTAURADO' => ['Restaurado',        '#3a7bd5'],
    'CANAL'      => ['Tratamento de canal', '#7a5cc4'],
    'COROA'      => ['Coroa / lente',     '#d4900a'],
    'IMPLANTE'   => ['Implante',          '#12a3a0'],
    'AUSENTE'    => ['Ausente',           '#9aa5b1'],
    'APARELHO'   => ['Com aparelho',      '#0b3a75'],
    'EXTRAIR'    => ['Indicado extrair',  '#8b1e2b'],
];

// Formas de pagamento aceitas (pagamento feito na clínica, no dia do atendimento)
$FORMAS_PAGAMENTO = ['PIX', 'Dinheiro', 'Cartão de débito', 'Cartão de crédito'];
$FORMAS_ICONES    = ['PIX' => 'bi-qr-code', 'Dinheiro' => 'bi-cash', 'Cartão de débito' => 'bi-credit-card-2-front', 'Cartão de crédito' => 'bi-credit-card'];

$DIAS_SEMANA = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 0 => 'Domingo'];

$UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

// =====================================================================
//  FOTOS DO SITE (bancos de imagens gratuitos: Pexels e Unsplash)
//  Formato: 'pexels:ID' ou 'unsplash:ID' (o código que aparece no endereço da foto).
//  QUER USAR FOTOS SUAS (ex.: feitas com IA)? Salve o arquivo em
//  assets/img/fotos/<nome-do-espaço>.jpg  (ex.: assets/img/fotos/dentista-helena.jpg)
//  e ele passa a ser usado no lugar da foto da internet, sem mexer no código.
// =====================================================================
$FOTOS_SITE = [
    // topo da página inicial: dentista com pacientes sorridentes
    'hero-principal'   => 'pexels:7800568',    // dentista com menina sorrindo na cadeira
    'hero-paciente-1'  => 'pexels:3845759',    // paciente sorrindo com o espelho
    'hero-paciente-2'  => 'pexels:7800561',    // criança sorridente na cadeira
    // foto ao lado do agendamento
    'agendar'          => 'pexels:5355703',    // dentista mostrando o tablet à paciente
    'agendar-inicio'   => 'pexels:20596945',   // paciente sorrindo no consultório
    // dentistas (usadas enquanto a secretaria não enviar outra foto em "Dentistas")
    'dentista-helena'  => 'pexels:19596247',
    'dentista-rafael'  => 'pexels:37458054',
    'dentista-camila'  => 'pexels:31043313',
];

// Antes e depois no perfil de cada dentista: SIMULAÇÕES ILUSTRATIVAS enviadas pela equipe
// (assets/img/antes-depois). Chave = primeiro nome do dentista em minúsculas.
// [foto antes, foto depois, título do caso, detalhe]
$CASOS_ANTES_DEPOIS = [
    'helena' => [
        ['assets/img/antes-depois/helena-1-antes.jpg', 'assets/img/antes-depois/helena-1-depois.jpg', 'Correção de dentes apinhados', 'Aparelho fixo estético · 18 meses'],
        ['assets/img/antes-depois/helena-2-antes.jpg', 'assets/img/antes-depois/helena-2-depois.jpg', 'Fechamento de diastema', 'Alinhadores invisíveis · 9 meses'],
    ],
    'rafael' => [
        ['assets/img/antes-depois/rafael-1-antes.jpg', 'assets/img/antes-depois/rafael-1-depois.jpg', 'Implante do incisivo lateral', 'Carga imediata · coroa em cerâmica'],
        ['assets/img/antes-depois/rafael-2-antes.jpg', 'assets/img/antes-depois/rafael-2-depois.jpg', 'Implante na região do canino', 'Enxerto ósseo + coroa · 5 meses'],
    ],
    'camila' => [
        ['assets/img/antes-depois/camila-1-antes.jpg', 'assets/img/antes-depois/camila-1-depois.jpg', 'Clareamento em consultório', '2 sessões'],
        ['assets/img/antes-depois/camila-2-antes.jpg', 'assets/img/antes-depois/camila-2-depois.jpg', 'Lentes de contato dental', 'Dentes lascados e diastema · 2 sessões'],
    ],
];

// Foto "fora do consultório" no perfil de cada dentista (opcional).
// Salve o arquivo em assets/img/fotos/pessoal-<nome>.jpg e ela aparece sozinha.
$FOTO_PESSOAL = [
    'helena' => 'Com o marido e a filha nas férias de julho.',
    'rafael' => 'Com o Thor, companheiro das caminhadas de domingo.',
    'camila' => 'Em Paris, depois de um congresso de estética do sorriso.',
];

// Monta o endereço de uma foto: 'pexels:ID', 'unsplash:ID' ou caminho local
function url_foto($ref, $largura = 600, $altura = null) {
    $ref = (string) $ref;
    if (strncmp($ref, 'pexels:', 7) === 0) {
        $id = preg_replace('/\D/', '', substr($ref, 7));
        return 'https://images.pexels.com/photos/' . $id . '/pexels-photo-' . $id . '.jpeg?auto=compress&cs=tinysrgb&w=' . (int) $largura
             . ($altura ? '&h=' . (int) $altura . '&fit=crop' : '');
    }
    if (strncmp($ref, 'unsplash:', 9) === 0) {
        return 'https://images.unsplash.com/photo-' . rawurlencode(substr($ref, 9)) . '?w=' . (int) $largura
             . ($altura ? '&h=' . (int) $altura : '') . '&q=72&auto=format&fit=crop';
    }
    return $ref;
}
// Mantido para compatibilidade com a versão anterior
function foto_unsplash($id, $largura = 600) { return url_foto('unsplash:' . $id, $largura); }

// Foto de um "espaço" do site: usa o arquivo local (assets/img/fotos/<espaço>.jpg) se existir
function foto_site($espaco, $largura = 900, $altura = null) {
    global $FOTOS_SITE;
    foreach (['jpg', 'jpeg', 'webp', 'png'] as $ext) {
        if (is_file(__DIR__ . '/../assets/img/fotos/' . $espaco . '.' . $ext)) return 'assets/img/fotos/' . $espaco . '.' . $ext;
    }
    return url_foto($FOTOS_SITE[$espaco] ?? '', $largura, $altura);
}

// Segurança: sessão protegida, cabeçalhos, CSRF e RATE LIMITING (4 tentativas + limite por minuto)
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

// "2026-03-14 10:00:00" -> "março de 2026"
function mes_ano($data) {
    if (!$data) return '';
    $t = strtotime((string) $data);
    return mb_strtolower(nome_mes(date('n', $t))) . ' de ' . date('Y', $t);
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

// Foto do dentista: enviada pela secretaria > arquivo local em assets/img/fotos > banco de imagens
function foto_dentista($d, $largura = 700) {
    if (empty($d['foto'])) return '';
    if (strpos($d['foto'], 'up_') === 0) return 'uploads/dentistas/' . rawurlencode(basename($d['foto']));
    if (preg_match('/^(pexels|unsplash):/', $d['foto'])) {
        $espaco = 'dentista-' . mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', primeiro_nome($d['nome'])));
        foreach (['jpg', 'jpeg', 'webp', 'png'] as $ext) {
            if (is_file(__DIR__ . '/../assets/img/fotos/' . $espaco . '.' . $ext)) return 'assets/img/fotos/' . $espaco . '.' . $ext;
        }
        return url_foto($d['foto'], $largura);
    }
    return 'assets/img/dentistas/' . rawurlencode(basename($d['foto']));
}

// Avatar de paciente: foto (cadastros completos de pacientes antigos) ou iniciais.
// Se a foto não carregar (sem internet), as iniciais continuam aparecendo por baixo.
function avatar_paciente($p, $tam = 'sm', $nomeCampo = 'nome') {
    $nome = (string) ($p[$nomeCampo] ?? $p['paciente'] ?? '');
    $foto = $p['foto'] ?? ($p['foto_paciente'] ?? '');
    $cores = ['#4f7cc4', '#12a3a0', '#0b3a75', '#7a5cc4', '#c47a3a', '#2f8f5b'];
    $cor = $cores[abs(crc32($nome)) % count($cores)];
    $h = '<span class="avatar avatar-' . e($tam) . ' avatar-pac" style="--c:' . $cor . '" aria-hidden="true"><span>' . e(iniciais($nome)) . '</span>';
    if ($foto) {
        $w = ['xs' => 80, 'sm' => 120, 'md' => 200, 'lg' => 320][$tam] ?? 160;
        $h .= '<img src="' . e(url_foto($foto, $w, $w)) . '" alt="" loading="lazy" onerror="this.remove()">';
    }
    return $h . '</span>';
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

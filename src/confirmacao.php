<?php
// 1. Configurações, conexão e a classe Agendamento (POO, como no projeto original)
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/agendamento.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: agendar.php');
    exit;
}

$modoSecretaria = ($_POST['modo'] ?? '') === 'secretaria' && perfil_atual() === 'SECRETARIA';
csrf_validar();
$voltar = $modoSecretaria ? 'nova_consulta.php' : 'agendar.php';

// Paciente pelo site: verificação "não sou um robô" e limite de agendamentos por IP
$erro = '';
if (!$modoSecretaria) {
    if (limite_excedido($pdo, 'agendamento', 4, 60)) $erro = 'Muitos agendamentos feitos deste aparelho na última hora. Para marcar mais consultas, fale com a clínica pelo telefone ou WhatsApp.';
    elseif ($m = captcha_validar('agendamento')) $erro = $m;
}

// 2. Validação básica dos campos
$nome  = trim($_POST['nome_paciente'] ?? '');
$tel   = trim($_POST['telefone_paciente'] ?? '');
$email = trim($_POST['email_paciente'] ?? '');
$data  = $_POST['data_agendamento'] ?? '';
$hora  = $_POST['hora_agendamento'] ?? '';
if ($erro) {}
elseif (mb_strlen($nome) < 3)                                  $erro = 'Informe o nome completo.';
elseif (strlen(preg_replace('/\D/', '', $tel)) < 10)       $erro = 'Informe um telefone válido com DDD.';
elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'E-mail inválido.';
elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !preg_match('/^\d{2}:\d{2}$/', $hora)) $erro = 'Escolha a data e o horário.';

// 3. INSTANCIAÇÃO e ATRIBUIÇÃO
$obj = new Agendamento($pdo);
$obj->paciente        = $nome;
$obj->telefone        = $tel;
$obj->email           = $email;
$obj->id_dentista     = (int) ($_POST['id_dentista'] ?? 0);
$obj->id_procedimento = (int) ($_POST['id_procedimento'] ?? 0);
$obj->data            = $data;
$obj->hora            = $hora;
$obj->observacoes     = trim($_POST['observacoes'] ?? '');
$obj->origem          = $modoSecretaria ? 'SECRETARIA' : 'SITE';
$obj->status          = $modoSecretaria ? 'CONFIRMADA' : 'AGENDADA';
$obj->primeira_consulta = ($_POST['primeira_consulta'] ?? '0') === '1';
if (mb_strlen($obj->observacoes) > 500) $obj->observacoes = mb_substr($obj->observacoes, 0, 500);
if (mb_strlen($nome) > 120) $erro = 'Nome muito longo.';

// 4. EXECUÇÃO: salvar() já verifica conflito de horário (UC06)
if ($erro || !$obj->salvar()) {
    $erro = $erro ?: $obj->erro;
    aviso_publico('agendar', $erro, 'danger');
    header('Location: ' . $voltar);
    exit;
}

if (!$modoSecretaria) limite_registrar($pdo, 'agendamento');
if ($modoSecretaria) {
    flash('Consulta de ' . $nome . ' agendada para ' . data_br($data) . ' às ' . $hora . '.');
    header('Location: lista_agendamentos.php?data=' . $data);
    exit;
}

$c = $obj->buscarPorId($obj->id);
$titulo = 'Agendamento confirmado';
include __DIR__ . '/include/cabecalho.php';

// link para adicionar na agenda do Google
$ini = date('Ymd\THis', strtotime($c['data'] . ' ' . $c['hora_inicio']));
$fim = date('Ymd\THis', strtotime($c['data'] . ' ' . $c['hora_fim']));
$gcal = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . urlencode($c['procedimento'] . ' - ' . $nome_clinica)
      . '&dates=' . $ini . '/' . $fim . '&ctz=America/Sao_Paulo&details=' . urlencode('Com ' . $c['dentista']) . '&location=' . urlencode($endereco);
$dias = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
?>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">
            <div class="card-soft p-4 p-md-5 text-center">
                <div class="success-badge mb-4"><i class="bi bi-check2"></i></div>
                <h1 class="section-title mb-2">Agendamento recebido!</h1>
                <p class="text-muted mb-4">Obrigado, <strong><?= e($nome) ?></strong>. Seu horário está reservado e nossa secretaria vai confirmar com você pelo WhatsApp um dia antes.</p>

                <div class="ticket text-start p-4 mb-4">
                    <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                        <span class="avatar avatar-sm" style="--c:<?= e($c['cor']) ?>" aria-hidden="true"><?= e(iniciais($c['dentista'])) ?></span>
                        <div><strong><?= e($c['dentista']) ?></strong><div class="small text-muted"><?= e($c['especialidade']) ?></div></div>
                        <span class="status status-agendada ms-auto">#<?= $c['id_consulta'] ?></span>
                        <?php if ($c['primeira_consulta']): ?><span class="tag"><i class="bi bi-stars" aria-hidden="true"></i> 1ª consulta</span><?php endif; ?>
                    </div>
                    <div class="row g-3 small">
                        <div class="col-6"><div class="text-muted">Procedimento</div><strong><?= e($c['procedimento']) ?></strong></div>
                        <div class="col-6"><div class="text-muted">Duração</div><strong><?= duracao_txt($c['duracao_min']) ?></strong></div>
                        <div class="col-6"><div class="text-muted">Data</div><strong><?= data_br($c['data']) ?></strong><div class="text-muted"><?= $dias[(int) date('w', strtotime($c['data']))] ?></div></div>
                        <div class="col-6"><div class="text-muted">Horário</div><strong><?= hm($c['hora_inicio']) ?> às <?= hm($c['hora_fim']) ?></strong></div>
                        <div class="col-12"><div class="text-muted">Local</div><strong><?= e($endereco) ?></strong></div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="<?= e($gcal) ?>" target="_blank" rel="noopener" class="btn btn-soft"><i class="bi bi-calendar-plus me-1"></i> Salvar na agenda</a>
                    <a href="agendar.php" class="btn btn-ghost">Novo agendamento</a>
                    <a href="index.php" class="btn btn-teal">Voltar ao início</a>
                </div>
                <div class="info-pagamento text-start mb-3"><i class="bi bi-shop" aria-hidden="true"></i><div><strong>Pagamento na clínica</strong>, no dia do atendimento (<?= e(implode(', ', $FORMAS_PAGAMENTO)) ?>). Valor de referência: <strong><?= moeda($c['valor_tabela']) ?></strong>.<?= $c['primeira_consulta'] ? ' Por ser sua primeira consulta, traga um documento com foto e exames anteriores, se tiver.' : '' ?></div></div>
                <p class="small text-muted mt-4 mb-0">Precisa remarcar? Fale com a gente: <?= e($contato) ?></p>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/include/rodape.php'; ?>

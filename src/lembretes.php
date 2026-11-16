<?php
// RF07 / UC07 - Lembretes de consulta (24h antes) pelo WhatsApp
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/agendamento.php';
require_once __DIR__ . '/include/card_consulta.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $id = (int) $_POST['id'];
    $existe = $pdo->prepare("SELECT id_lembrete FROM lembrete WHERE id_consulta = ?");
    $existe->execute([$id]);
    if ($existe->fetchColumn()) {
        $pdo->prepare("UPDATE lembrete SET enviado = 1 WHERE id_consulta = ?")->execute([$id]);
    } else {
        $pdo->prepare("INSERT INTO lembrete (id_consulta, canal, data_envio, enviado) VALUES (?, 'WHATSAPP', ?, 1)")->execute([$id, date('Y-m-d H:i:s')]);
    }
    flash('Passo 2 feito: lembrete marcado como enviado. Quando o paciente responder, faça o passo 3 (Paciente confirmou).');
    header('Location: lembretes.php?data=' . urlencode($_POST['data'] ?? ''));
    exit;
}

// por padrão mostra o próximo dia útil
$padrao = date('Y-m-d', strtotime('+1 day'));
while (in_array((int) date('w', strtotime($padrao)), [0, 6])) $padrao = date('Y-m-d', strtotime("$padrao +1 day"));
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'] ?? '') ? $_GET['data'] : $padrao;

$ag = new Agendamento($pdo);
$lista = array_filter($ag->buscar('', $data), fn($c) => in_array($c['status'], ['AGENDADA', 'CONFIRMADA']));
$enviados = [];
foreach ($pdo->query("SELECT id_consulta FROM lembrete WHERE enviado = 1")->fetchAll() as $r) $enviados[$r['id_consulta']] = true;

$nEnv = count(array_filter($lista, fn($c) => $c['status'] === 'CONFIRMADA' || !empty($enviados[$c['id_consulta']])));
$nConf = count(array_filter($lista, fn($c) => $c['status'] === 'CONFIRMADA'));

$titulo = 'Lembretes';
$pagina = 'lembretes';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="card-soft p-3 p-lg-4 mb-4">
    <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-3">
        <div>
            <h2 class="h6 mb-1"><i class="bi bi-whatsapp text-success me-1" aria-hidden="true"></i>Como confirmar as consultas do dia seguinte</h2>
            <p class="small text-muted mb-0">Faça os 3 passos para cada paciente, nesta ordem:</p>
        </div>
        <form class="d-flex flex-wrap gap-2 align-items-center">
            <label class="small text-muted text-nowrap" for="fData">Consultas do dia</label>
            <input id="fData" type="date" name="data" class="form-control" value="<?= e($data) ?>">
            <button class="btn btn-navy">Ver</button>
        </form>
    </div>
    <ol class="passos-lembrete">
        <li><span class="n">1</span><div><strong>Enviar a mensagem</strong><span>Abre o WhatsApp com o texto pronto (data, horário, dentista e endereço). É só apertar enviar.</span></div></li>
        <li><span class="n">2</span><div><strong>Marcar como enviado</strong><span>Registra no sistema que o paciente já recebeu o lembrete, para ninguém mandar de novo.</span></div></li>
        <li><span class="n">3</span><div><strong>Confirmar presença</strong><span>Quando o paciente responder “sim”, clique aqui: a consulta fica <em>confirmada</em> na agenda.</span></div></li>
    </ol>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h2 class="h6 mb-0 me-auto"><?= ucfirst(['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'][(int) date('w', strtotime($data))]) ?>, <?= data_br($data) ?> · <?= count($lista) ?> paciente(s)</h2>
    <?php if ($lista): ?>
    <span class="tag"><i class="bi bi-send-check" aria-hidden="true"></i> <?= $nEnv ?>/<?= count($lista) ?> lembretes enviados</span>
    <span class="status status-confirmada"><i class="bi bi-check2-circle" aria-hidden="true"></i><?= $nConf ?>/<?= count($lista) ?> confirmadas</span>
    <?php endif; ?>
</div>

<?php foreach ($lista as $c): $conf = $c['status'] === 'CONFIRMADA'; $env = $conf || !empty($enviados[$c['id_consulta']]);   // confirmada = os passos 1 e 2 já foram feitos ?>
    <div class="appt lembrete-item" style="--c:<?= e($c['cor']) ?>">
        <div class="time"><?= hm($c['hora_inicio']) ?><small>até <?= hm($c['hora_fim']) ?></small></div>
        <div class="min-w-0">
            <div class="d-flex flex-wrap gap-2 align-items-center"><span class="who-wrap"><?= avatar_paciente($c, 'xs', 'paciente') ?><span class="who"><?= e($c['paciente']) ?></span></span>
                <span class="status <?= $STATUS_CONSULTA[$c['status']][1] ?>"><?= $STATUS_CONSULTA[$c['status']][0] ?></span></div>
            <div class="what"><?= e($c['procedimento']) ?> · <?= e($c['dentista']) ?> · <?= e($c['telefone']) ?></div>
        </div>
        <div class="passos-acoes" role="group" aria-label="Passos do lembrete de <?= e($c['paciente']) ?>">
            <a href="<?= e(link_lembrete($c)) ?>" target="_blank" rel="noopener" class="passo <?= $env ? 'feito' : 'atual' ?>">
                <span class="n"><?= $env ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : '1' ?></span><?= $env ? 'Enviar de novo' : 'Enviar no WhatsApp' ?></a>
            <?php if ($env): ?>
                <span class="passo feito"><span class="n"><i class="bi bi-check-lg" aria-hidden="true"></i></span>Enviado</span>
            <?php else: ?>
                <form method="POST"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $c['id_consulta'] ?>"><input type="hidden" name="data" value="<?= e($data) ?>">
                    <button class="passo"><span class="n">2</span>Marcar como enviado</button></form>
            <?php endif; ?>
            <?php if ($conf): ?>
                <span class="passo feito"><span class="n"><i class="bi bi-check-lg" aria-hidden="true"></i></span>Confirmada</span>
            <?php else: ?>
                <form method="POST" action="acao_consulta.php"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $c['id_consulta'] ?>"><input type="hidden" name="acao" value="CONFIRMADA"><input type="hidden" name="voltar" value="lembretes.php?data=<?= e($data) ?>">
                    <button class="passo <?= $env ? 'atual' : '' ?>"><span class="n">3</span>Paciente confirmou</button></form>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php if (!$lista): ?><div class="empty-state"><i class="bi bi-bell-slash"></i><p class="mt-2 mb-0">Nenhuma consulta para lembrar nesta data.</p></div><?php endif; ?>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

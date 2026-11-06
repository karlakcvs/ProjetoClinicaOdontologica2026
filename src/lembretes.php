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
    flash('Lembrete marcado como enviado.');
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

$titulo = 'Lembretes';
$pagina = 'lembretes';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="card-soft p-3 mb-4 d-flex flex-wrap gap-3 align-items-center">
    <div class="feature-ico"><i class="bi bi-whatsapp"></i></div>
    <div class="flex-grow-1 small"><strong>Lembretes de 24h.</strong> Clique em “Enviar” para abrir o WhatsApp com a mensagem pronta para o paciente e depois marque como enviado. Ao responder, confirme a consulta.</div>
    <form class="d-flex gap-2 align-items-center">
        <input type="date" name="data" class="form-control" value="<?= e($data) ?>">
        <button class="btn btn-navy">Ver</button>
    </form>
</div>

<h2 class="h6 mb-3"><?= ucfirst(['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'][(int) date('w', strtotime($data))]) ?>, <?= data_br($data) ?> · <?= count($lista) ?> paciente(s)</h2>

<?php foreach ($lista as $c): $env = !empty($enviados[$c['id_consulta']]); ?>
    <div class="appt" style="--c:<?= e($c['cor']) ?>">
        <div class="time"><?= hm($c['hora_inicio']) ?><small>até <?= hm($c['hora_fim']) ?></small></div>
        <div>
            <div class="d-flex flex-wrap gap-2 align-items-center"><span class="who"><?= e($c['paciente']) ?></span>
                <span class="status <?= $STATUS_CONSULTA[$c['status']][1] ?>"><?= $STATUS_CONSULTA[$c['status']][0] ?></span>
                <?php if ($env): ?><span class="tag"><i class="bi bi-check2-all"></i> lembrete enviado</span><?php endif; ?></div>
            <div class="what"><?= e($c['procedimento']) ?> · <?= e($c['dentista']) ?> · <?= e($c['telefone']) ?></div>
        </div>
        <div class="acts">
            <a href="<?= e(link_lembrete($c)) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-teal"><i class="bi bi-whatsapp me-1"></i> Enviar</a>
            <?php if (!$env): ?>
            <form method="POST"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $c['id_consulta'] ?>"><input type="hidden" name="data" value="<?= e($data) ?>">
                <button class="btn btn-sm btn-ghost"><i class="bi bi-check2"></i> Marcar enviado</button></form>
            <?php endif; ?>
            <?php if ($c['status'] === 'AGENDADA'): ?>
            <form method="POST" action="acao_consulta.php"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $c['id_consulta'] ?>"><input type="hidden" name="acao" value="CONFIRMADA"><input type="hidden" name="voltar" value="lembretes.php?data=<?= e($data) ?>">
                <button class="btn btn-sm btn-soft"><i class="bi bi-check2-circle"></i> Confirmar</button></form>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php if (!$lista): ?><div class="empty-state"><i class="bi bi-bell-slash"></i><p class="mt-2 mb-0">Nenhuma consulta para lembrar nesta data.</p></div><?php endif; ?>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

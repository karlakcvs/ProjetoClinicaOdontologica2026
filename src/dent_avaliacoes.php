<?php
// Área do DENTISTA: avaliações feitas SOBRE ele no site. Ele aprova (publica) ou oculta.
// Avaliações de outros dentistas ou da clínica em geral nunca aparecem aqui.
require_once __DIR__ . '/include/check_dentista.php';
require_once __DIR__ . '/include/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = $_POST['acao'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if (in_array($acao, ['aprovar', 'ocultar'], true)) {
        // o WHERE com id_dentista garante que só altera avaliações dele
        $st = $pdo->prepare("UPDATE avaliacao SET status = ? WHERE id_avaliacao = ? AND id_dentista = ?");
        $st->execute([$acao === 'aprovar' ? 'APROVADA' : 'OCULTA', $id, $ID_DENTISTA]);
        if ($st->rowCount()) flash($acao === 'aprovar' ? 'Avaliação publicada no seu perfil.' : 'Avaliação ocultada do site.');
        else flash('Você só pode alterar avaliações sobre você.', 'danger');
    }
    header('Location: dent_avaliacoes.php?f=' . urlencode($_POST['f'] ?? 'PENDENTE'));
    exit;
}

$filtros = ['PENDENTE' => 'Aguardando', 'APROVADA' => 'Publicadas', 'OCULTA' => 'Ocultas'];
$filtroAv = array_key_exists($_GET['f'] ?? '', $filtros) ? $_GET['f'] : 'PENDENTE';
$st = $pdo->prepare("SELECT * FROM avaliacao WHERE id_dentista = ? AND status = ? ORDER BY criado_em DESC");
$st->execute([$ID_DENTISTA, $filtroAv]);
$lista = $st->fetchAll();
$st = $pdo->prepare("SELECT status, COUNT(*) FROM avaliacao WHERE id_dentista = ? GROUP BY status");
$st->execute([$ID_DENTISTA]);
$cont = $st->fetchAll(PDO::FETCH_KEY_PAIR);
$st = $pdo->prepare("SELECT AVG(nota) FROM avaliacao WHERE id_dentista = ? AND status = 'APROVADA'");
$st->execute([$ID_DENTISTA]);
$media = $st->fetchColumn();
if (!isset($_GET['f']) && empty($cont['PENDENTE'])) { $filtroAv = 'APROVADA'; $st = $pdo->prepare("SELECT * FROM avaliacao WHERE id_dentista = ? AND status = 'APROVADA' ORDER BY criado_em DESC"); $st->execute([$ID_DENTISTA]); $lista = $st->fetchAll(); }

$titulo = 'Minhas avaliações';
$pagina = 'dent_avaliacoes';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="card-soft p-3 mb-3 d-flex flex-wrap gap-3 align-items-center">
    <div class="feature-ico"><i class="bi bi-star" aria-hidden="true"></i></div>
    <div class="flex-grow-1 small">Aqui aparecem só as avaliações que os pacientes escreveram <strong>sobre você</strong>. Elas entram no seu perfil do site depois que você (ou a secretaria) <strong>publica</strong>.
        <?php if ($media): ?><br>Sua nota no site: <strong><?= number_format((float) $media, 1, ',', '') ?></strong> <?= estrelas($media) ?> · <?= (int) ($cont['APROVADA'] ?? 0) ?> publicada(s)<?php endif; ?></div>
    <a href="especialista.php?id=<?= $ID_DENTISTA ?>#avaliacoes-dentista" target="_blank" rel="noopener" class="btn btn-sm btn-ghost"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Ver no site</a>
</div>
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach ($filtros as $k => $v): ?>
        <a href="?f=<?= $k ?>" class="btn btn-sm <?= $filtroAv === $k ? 'btn-navy' : 'btn-ghost' ?>" <?= $filtroAv === $k ? 'aria-current="page"' : '' ?>><?= $v ?> (<?= (int) ($cont[$k] ?? 0) ?>)</a>
    <?php endforeach; ?>
</div>
<div class="row g-3">
<?php foreach ($lista as $a): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card-soft review">
            <div class="d-flex justify-content-between align-items-center gap-2"><?= estrelas($a['nota']) ?><span class="review-data"><?= e(mes_ano($a['criado_em'])) ?></span></div>
            <blockquote>“<?= e($a['comentario']) ?>”</blockquote>
            <div class="small"><strong><?= e($a['nome']) ?></strong><?php if ($a['paciente_desde']): ?> <span class="paciente-desde"><i class="bi bi-award" aria-hidden="true"></i> paciente desde <?= (int) $a['paciente_desde'] ?></span><?php endif; ?></div>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <?php if ($a['status'] !== 'APROVADA'): ?>
                <form method="POST"><?= csrf_campo() ?><input type="hidden" name="acao" value="aprovar"><input type="hidden" name="id" value="<?= (int) $a['id_avaliacao'] ?>"><input type="hidden" name="f" value="<?= e($filtroAv) ?>">
                    <button class="btn btn-sm btn-teal"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Publicar no meu perfil</button></form>
                <?php endif; ?>
                <?php if ($a['status'] !== 'OCULTA'): ?>
                <form method="POST"><?= csrf_campo() ?><input type="hidden" name="acao" value="ocultar"><input type="hidden" name="id" value="<?= (int) $a['id_avaliacao'] ?>"><input type="hidden" name="f" value="<?= e($filtroAv) ?>">
                    <button class="btn btn-sm btn-ghost"><i class="bi bi-eye-slash me-1" aria-hidden="true"></i>Ocultar</button></form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php if (!$lista): ?><div class="empty-state"><i class="bi bi-star" aria-hidden="true"></i><p class="mt-2 mb-0">Nenhuma avaliação <?= mb_strtolower($filtros[$filtroAv]) ?>.</p></div><?php endif; ?>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

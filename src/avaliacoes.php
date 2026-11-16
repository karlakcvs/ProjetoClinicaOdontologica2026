<?php
// Moderação das avaliações enviadas pelos pacientes no site
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = $_POST['acao'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($acao === 'aprovar' || $acao === 'ocultar') {
        $pdo->prepare("UPDATE avaliacao SET status = ? WHERE id_avaliacao = ?")->execute([$acao === 'aprovar' ? 'APROVADA' : 'OCULTA', $id]);
        flash($acao === 'aprovar' ? 'Avaliação publicada no site.' : 'Avaliação ocultada do site.');
    } elseif ($acao === 'excluir') {
        $pdo->prepare("DELETE FROM avaliacao WHERE id_avaliacao = ?")->execute([$id]);
        flash('Avaliação excluída.', 'warning');
    }
    header('Location: avaliacoes.php' . (isset($_POST['f']) ? '?f=' . urlencode($_POST['f']) : ''));
    exit;
}

$filtros = ['PENDENTE' => 'Aguardando', 'APROVADA' => 'Publicadas', 'OCULTA' => 'Ocultas'];
$filtroAv = array_key_exists($_GET['f'] ?? '', $filtros) ? $_GET['f'] : 'PENDENTE';
$st = $pdo->prepare("SELECT a.*, d.nome AS dentista FROM avaliacao a LEFT JOIN dentista d ON d.id_dentista = a.id_dentista WHERE a.status = ? ORDER BY a.criado_em DESC");
$st->execute([$filtroAv]);
$lista = $st->fetchAll();
$cont = $pdo->query("SELECT status, COUNT(*) AS n FROM avaliacao GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$media = $pdo->query("SELECT AVG(nota) FROM avaliacao WHERE status = 'APROVADA'")->fetchColumn();

$titulo = 'Avaliações';
$pagina = 'avaliacoes';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="card-soft p-3 mb-3 d-flex flex-wrap gap-3 align-items-center">
    <div class="feature-ico"><i class="bi bi-star" aria-hidden="true"></i></div>
    <div class="flex-grow-1 small">As avaliações enviadas no site só aparecem para o público depois de <strong>aprovadas</strong> aqui. Cada dentista também pode aprovar as avaliações sobre ele na própria área. Média publicada: <strong><?= $media ? number_format((float) $media, 1, ',', '') : '—' ?></strong> <?= $media ? estrelas($media) : '' ?></div>
</div>
<div class="d-flex flex-wrap gap-2 mb-3" role="tablist">
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
            <div class="small"><strong><?= e($a['nome']) ?></strong><?php if ($a['paciente_desde']): ?> <span class="paciente-desde"><i class="bi bi-award" aria-hidden="true"></i> paciente desde <?= (int) $a['paciente_desde'] ?></span><?php endif; ?><br><span class="text-muted">sobre <?= $a['dentista'] ? e($a['dentista']) : 'a clínica em geral' ?></span></div>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <?php if ($a['status'] !== 'APROVADA'): ?>
                <form method="POST"><?= csrf_campo() ?><input type="hidden" name="acao" value="aprovar"><input type="hidden" name="id" value="<?= (int) $a['id_avaliacao'] ?>"><input type="hidden" name="f" value="<?= e($filtroAv) ?>">
                    <button class="btn btn-sm btn-teal"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Publicar</button></form>
                <?php endif; ?>
                <?php if ($a['status'] !== 'OCULTA'): ?>
                <form method="POST"><?= csrf_campo() ?><input type="hidden" name="acao" value="ocultar"><input type="hidden" name="id" value="<?= (int) $a['id_avaliacao'] ?>"><input type="hidden" name="f" value="<?= e($filtroAv) ?>">
                    <button class="btn btn-sm btn-ghost"><i class="bi bi-eye-slash me-1" aria-hidden="true"></i>Ocultar</button></form>
                <?php endif; ?>
                <form method="POST" data-confirm="Excluir esta avaliação definitivamente?"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $a['id_avaliacao'] ?>"><input type="hidden" name="f" value="<?= e($filtroAv) ?>">
                    <button class="btn btn-sm btn-ghost text-danger" aria-label="Excluir avaliação de <?= e($a['nome']) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php if (!$lista): ?><div class="empty-state"><i class="bi bi-star" aria-hidden="true"></i><p class="mt-2 mb-0">Nenhuma avaliação <?= mb_strtolower($filtros[$filtroAv]) ?>.</p></div><?php endif; ?>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

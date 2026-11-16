<?php
// Área do DENTISTA: o próprio perfil no site (biografia, formação, foto, tratamentos e preços).
// Cada dentista só consegue editar o PRÓPRIO perfil (o id vem da sessão, nunca do formulário).
require_once __DIR__ . '/include/check_dentista.php';
require_once __DIR__ . '/include/conexao.php';

$st = $pdo->prepare("SELECT * FROM dentista WHERE id_dentista = ?");
$st->execute([$ID_DENTISTA]);
$d = $st->fetch();
if (!$d) { header('Location: dent_painel.php'); exit; }

$st = $pdo->prepare("SELECT p.id_procedimento, p.nome, p.duracao_min, p.valor AS valor_padrao, dp.valor
                     FROM dentista_procedimento dp JOIN procedimento p ON p.id_procedimento = dp.id_procedimento
                     WHERE dp.id_dentista = ? ORDER BY CASE WHEN p.nome LIKE '%Primeira consulta%' THEN 0 ELSE 1 END, p.nome");
$st->execute([$ID_DENTISTA]);
$meusProcs = $st->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $txt = fn($k, $max) => mb_substr(trim(strip_tags((string) ($_POST[$k] ?? ''))), 0, $max);

    // foto nova (opcional): só imagens reais JPG/PNG/WEBP até 5 MB
    $novaFoto = null;
    $arq = $_FILES['foto'] ?? null;
    if ($arq && $arq['error'] !== UPLOAD_ERR_NO_FILE) {
        $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $arq['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($arq['tmp_name']) : '';
        if ($arq['error'] !== UPLOAD_ERR_OK || $arq['size'] > 5 * 1024 * 1024) { flash('A foto deve ter no máximo 5 MB.', 'danger'); header('Location: dent_perfil.php'); exit; }
        if (!isset($tipos[$mime]) || !@getimagesize($arq['tmp_name'])) { flash('Envie a foto em JPG, PNG ou WEBP.', 'danger'); header('Location: dent_perfil.php'); exit; }
        $novaFoto = 'up_' . $ID_DENTISTA . '_' . bin2hex(random_bytes(6)) . '.' . $tipos[$mime];
        $dir = __DIR__ . '/uploads/dentistas/';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!move_uploaded_file($arq['tmp_name'], $dir . $novaFoto)) { flash('Não foi possível salvar a foto (permissão da pasta uploads/dentistas).', 'danger'); header('Location: dent_perfil.php'); exit; }
        if (strpos((string) $d['foto'], 'up_') === 0) @unlink($dir . basename($d['foto']));
    }

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE dentista SET bio = ?, formacao = ?, publico = ?, condicoes = ?, anos_experiencia = ? WHERE id_dentista = ?")->execute([
        $txt('bio', 2000), $txt('formacao', 255), $txt('publico', 255), $txt('condicoes', 2000),
        max(0, min(60, (int) ($_POST['anos_experiencia'] ?? 0))) ?: null, $ID_DENTISTA]);
    if ($novaFoto) $pdo->prepare("UPDATE dentista SET foto = ? WHERE id_dentista = ?")->execute([$novaFoto, $ID_DENTISTA]);
    // preços: só dos procedimentos que ESTE dentista já realiza
    $upP = $pdo->prepare("UPDATE dentista_procedimento SET valor = ? WHERE id_dentista = ? AND id_procedimento = ?");
    foreach ($meusProcs as $p) {
        $v = trim((string) ($_POST['preco'][$p['id_procedimento']] ?? ''));
        $v = $v === '' ? null : (float) str_replace(',', '.', $v);
        if ($v !== null && ($v < 0 || $v > 100000)) continue;
        $upP->execute([$v, $ID_DENTISTA, $p['id_procedimento']]);
    }
    $pdo->commit();
    flash('Seu perfil foi atualizado e já aparece no site.');
    header('Location: dent_perfil.php');
    exit;
}

$ft = foto_dentista($d);
$titulo = 'Meu perfil no site';
$pagina = 'dent_perfil';
$acoes_topo = '<a href="especialista.php?id=' . $ID_DENTISTA . '" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Ver como o paciente vê</span></a>';
include __DIR__ . '/include/admin_topo.php';
?>
<form method="POST" enctype="multipart/form-data" data-enviando="Salvando…">
    <?= csrf_campo() ?>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card-soft panel mb-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <?php if ($ft): ?><img src="<?= e($ft) ?>" alt="" class="avatar avatar-foto" style="width:72px;height:72px"><?php else: ?><span class="avatar" style="--c:<?= e($d['cor']) ?>"><?= e(iniciais($d['nome'])) ?></span><?php endif; ?>
                    <div class="lh-sm"><h2 class="h5 mb-1"><?= e($d['nome']) ?></h2><span class="tag"><?= e($d['especialidade']) ?></span> <span class="small text-muted ms-1"><?= e($d['cro']) ?></span></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label" for="fFoto">Trocar foto <span class="text-muted fw-normal">(JPG/PNG/WEBP até 5 MB)</span></label><input id="fFoto" type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label" for="fAnos">Anos de experiência</label><input id="fAnos" type="number" min="0" max="60" name="anos_experiencia" class="form-control" value="<?= e($d['anos_experiencia']) ?>"></div>
                    <div class="col-12"><label class="form-label" for="fBio">Sobre você <span class="text-muted fw-normal">(aparece no topo do seu perfil)</span></label><textarea id="fBio" name="bio" class="form-control" rows="5" maxlength="2000"><?= e($d['bio']) ?></textarea></div>
                    <div class="col-12"><label class="form-label" for="fForm">Formação</label><textarea id="fForm" name="formacao" class="form-control" rows="2" maxlength="255"><?= e($d['formacao']) ?></textarea></div>
                    <div class="col-12"><label class="form-label" for="fPub">Público que atende</label><input id="fPub" name="publico" class="form-control" maxlength="255" value="<?= e($d['publico']) ?>"></div>
                    <div class="col-12"><label class="form-label" for="fCond">Principais tratamentos <span class="text-muted fw-normal">(um por linha)</span></label><textarea id="fCond" name="condicoes" class="form-control" rows="6" maxlength="2000"><?= e($d['condicoes']) ?></textarea></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card-soft panel">
                <h2 class="panel-title mb-1">Meus preços</h2>
                <p class="small text-muted">Valor que você cobra em cada procedimento. Em branco = valor padrão da clínica. No site aparece o menor preço entre os especialistas, como “a partir de”.</p>
                <?php foreach ($meusProcs as $i => $p): ?>
                    <div class="d-flex align-items-center gap-2 py-2 <?= $i ? 'border-top' : '' ?>">
                        <label class="small flex-grow-1 lh-sm" for="pr<?= (int) $p['id_procedimento'] ?>"><?= e($p['nome']) ?><br><span class="text-muted"><?= duracao_txt($p['duracao_min']) ?> · padrão <?= moeda($p['valor_padrao']) ?></span></label>
                        <input id="pr<?= (int) $p['id_procedimento'] ?>" type="number" name="preco[<?= (int) $p['id_procedimento'] ?>]" min="0" step="0.01" class="form-control form-control-sm" style="max-width:120px"
                               placeholder="<?= number_format((float) $p['valor_padrao'], 2, ',', '.') ?>" value="<?= $p['valor'] !== null ? number_format((float) $p['valor'], 2, '.', '') : '' ?>">
                    </div>
                <?php endforeach; ?>
                <?php if (!$meusProcs): ?><p class="small text-muted mb-0">Nenhum procedimento vinculado. Fale com a secretaria.</p><?php endif; ?>
                <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Nome, CRO, especialidade, horários e quais procedimentos você realiza são alterados pela secretaria.</p>
            </div>
            <button class="btn btn-teal btn-lg w-100 mt-3"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Salvar perfil</button>
        </div>
    </div>
</form>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

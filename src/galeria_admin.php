<?php
// Espaço para postar fotos no site (JPG, PNG, WEBP ou GIF)
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';

$pasta = __DIR__ . '/uploads/galeria/';
if (!is_dir($pasta)) @mkdir($pasta, 0775, true);
$tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$limiteMB = 8;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    if (($_POST['acao'] ?? '') === 'excluir') {
        $st = $pdo->prepare("SELECT arquivo FROM galeria_foto WHERE id_foto = ?");
        $st->execute([(int) $_POST['id']]);
        if ($arq = $st->fetchColumn()) {
            @unlink($pasta . basename($arq));
            $pdo->prepare("DELETE FROM galeria_foto WHERE id_foto = ?")->execute([(int) $_POST['id']]);
            flash('Foto removida.', 'warning');
        }
    } else {
        $titulo_f = trim($_POST['titulo'] ?? '');
        $desc = trim($_POST['descricao'] ?? '');
        $arq = $_FILES['foto'] ?? null;
        if (!$arq || $arq['error'] === UPLOAD_ERR_NO_FILE) {
            flash('Escolha uma foto para enviar.', 'danger');
        } elseif (in_array($arq['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
            flash('A foto é maior que o limite permitido pelo servidor (' . ini_get('upload_max_filesize') . ').', 'danger');
        } elseif ($arq['error'] !== UPLOAD_ERR_OK) {
            flash('Erro no envio da foto (código ' . $arq['error'] . ').', 'danger');
        } elseif ($arq['size'] > $limiteMB * 1024 * 1024) {
            flash("A foto deve ter no máximo {$limiteMB} MB.", 'danger');
        } else {
            $info = @getimagesize($arq['tmp_name']);
            $mime = $info['mime'] ?? '';
            if (!isset($tipos[$mime])) {
                flash('Formato não aceito. Envie JPG, PNG, WEBP ou GIF.', 'danger');
            } else {
                $nome = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $tipos[$mime];
                if (move_uploaded_file($arq['tmp_name'], $pasta . $nome)) {
                    $pdo->prepare("INSERT INTO galeria_foto (titulo, descricao, arquivo) VALUES (?,?,?)")
                        ->execute([$titulo_f ?: 'Foto da clínica', $desc ?: null, $nome]);
                    flash('Foto publicada no site!');
                } else {
                    flash('Não foi possível salvar a foto. Verifique a permissão da pasta uploads/galeria.', 'danger');
                }
            }
        }
    }
    header('Location: galeria_admin.php');
    exit;
}

$fotos = $pdo->query("SELECT * FROM galeria_foto ORDER BY criado_em DESC")->fetchAll();
$titulo = 'Fotos do site';
$pagina = 'galeria_admin';
$acoes_topo = '<a href="galeria.php" target="_blank" class="btn btn-ghost btn-sm"><i class="bi bi-eye me-1"></i><span class="d-none d-sm-inline">Ver galeria</span></a>';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <form method="POST" enctype="multipart/form-data" class="card-soft panel">
            <?= csrf_campo() ?>
            <h2 class="panel-title mb-3">Publicar nova foto</h2>
            <label class="dropzone mb-3" id="drop">
                <input type="file" name="foto" id="foto" accept="image/jpeg,image/png,image/webp,image/gif" hidden required>
                <img id="prev" alt="" class="img-fluid rounded-3 mb-2 d-none" style="max-height:200px">
                <i class="bi bi-cloud-arrow-up" id="dropIco"></i>
                <div class="fw-semibold mt-1" id="dropTxt">Toque para escolher a foto</div>
                <div class="small text-muted">JPG, PNG, WEBP ou GIF · até <?= $limiteMB ?> MB</div>
            </label>
            <div class="mb-3"><label class="form-label">Título</label><input name="titulo" class="form-control" maxlength="120" placeholder="Ex.: Nossa recepção"></div>
            <div class="mb-3"><label class="form-label">Descrição <span class="text-muted fw-normal">(opcional)</span></label><input name="descricao" class="form-control" maxlength="255" placeholder="Uma frase curta"></div>
            <button class="btn btn-teal w-100"><i class="bi bi-upload me-1"></i> Publicar no site</button>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="panel-title"><?= count($fotos) ?> foto(s) publicada(s)</h2></div>
        <?php if ($fotos): ?>
        <div class="row g-3">
            <?php foreach ($fotos as $f): ?>
                <div class="col-6 col-md-4">
                    <div class="thumb">
                        <img src="uploads/galeria/<?= e($f['arquivo']) ?>" alt="<?= e($f['titulo']) ?>" loading="lazy">
                        <div class="meta d-flex align-items-start gap-2">
                            <div class="flex-grow-1 min-w-0"><strong class="d-block text-truncate"><?= e($f['titulo']) ?></strong><span class="text-muted small"><?= data_br($f['criado_em']) ?></span></div>
                            <form method="POST" data-confirm="Remover esta foto do site?"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $f['id_foto'] ?>">
                                <button class="btn btn-icon btn-ghost text-danger" title="Remover"><i class="bi bi-trash"></i></button></form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state"><i class="bi bi-images"></i><p class="mt-2 mb-0">Nenhuma foto ainda. As fotos publicadas aparecem na página inicial e na galeria do site.</p></div>
        <?php endif; ?>
    </div>
</div>
<?php
$scripts_extra = '<script>
(() => {
  const inp = document.getElementById("foto"), prev = document.getElementById("prev"), drop = document.getElementById("drop");
  const mostrar = f => { if (!f) return; prev.src = URL.createObjectURL(f); prev.classList.remove("d-none"); document.getElementById("dropIco").classList.add("d-none"); document.getElementById("dropTxt").textContent = f.name; };
  inp.addEventListener("change", () => mostrar(inp.files[0]));
  ["dragenter","dragover"].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add("drag"); }));
  ["dragleave","drop"].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove("drag"); }));
  drop.addEventListener("drop", e => { inp.files = e.dataTransfer.files; mostrar(inp.files[0]); });
})();
</script>';
include __DIR__ . '/include/admin_fim.php';

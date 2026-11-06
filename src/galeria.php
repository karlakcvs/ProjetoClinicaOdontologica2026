<?php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';
$fotos = $pdo->query("SELECT * FROM galeria_foto ORDER BY criado_em DESC")->fetchAll();
$titulo = 'Galeria';
$pagina = 'galeria';
include __DIR__ . '/include/cabecalho.php';
?>
<section class="page-head">
    <div class="container">
        <span class="eyebrow"><i class="bi bi-images"></i> Nosso espaço</span>
        <h1 class="section-title mt-3 mb-2">Galeria de fotos</h1>
        <p class="section-sub mb-0">Conheça a clínica, a equipe e um pouco do nosso dia a dia.</p>
    </div>
</section>
<main class="container pb-5">
    <?php if ($fotos): ?>
        <div class="gallery">
            <?php foreach ($fotos as $f): ?>
                <a class="gallery-item" id="foto<?= $f['id_foto'] ?>" href="#" data-bs-toggle="modal" data-bs-target="#fotoModal"
                   data-src="uploads/galeria/<?= e($f['arquivo']) ?>" data-titulo="<?= e($f['titulo']) ?>" data-desc="<?= e($f['descricao']) ?>">
                    <img src="uploads/galeria/<?= e($f['arquivo']) ?>" alt="<?= e($f['titulo']) ?>" loading="lazy">
                    <span class="cap"><strong><?= e($f['titulo']) ?></strong><?= e($f['descricao']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state"><i class="bi bi-images"></i><p class="mt-2 mb-0">Ainda não há fotos publicadas.</p></div>
    <?php endif; ?>
</main>

<div class="modal fade" id="fotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content overflow-hidden">
            <img id="mImg" src="" alt="" class="w-100" style="max-height:75vh;object-fit:contain;background:#06244b">
            <div class="p-3 d-flex justify-content-between align-items-start gap-3">
                <div><h5 class="mb-1" id="mTit"></h5><p class="small text-muted mb-0" id="mDesc"></p></div>
                <button type="button" class="btn btn-ghost btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('fotoModal').addEventListener('show.bs.modal', ev => {
    const a = ev.relatedTarget;
    document.getElementById('mImg').src = a.dataset.src;
    document.getElementById('mTit').textContent = a.dataset.titulo;
    document.getElementById('mDesc').textContent = a.dataset.desc;
});
</script>
<?php include __DIR__ . '/include/rodape.php'; ?>

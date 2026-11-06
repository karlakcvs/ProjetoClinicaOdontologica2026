        <footer class="admin-footer">
            <div class="d-lg-none mb-3"><?php include __DIR__ . '/acessibilidade.php'; ?></div>
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <span><?= e($nome_clinica) ?> · <?= e($slogan_clinica) ?></span>
                <span class="d-inline-flex align-items-center gap-1">Desenvolvido por <img src="assets/img/sanytech-simbolo.png" alt="" width="14" height="16"> <strong class="text-teal"><?= e($empresa_dev) ?></strong></span>
            </div>
        </footer>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/site.js?v=10"></script>
<?= $scripts_extra ?? '' ?>
</body>
</html>

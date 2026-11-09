<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="mb-3"><?= logo_clinica('brand-logo', 'branco') ?></div>
                <p class="small mb-0" style="max-width:300px">Cuidado especializado para cada fase do seu sorriso, com tecnologia, conforto e atendimento humanizado. Atendimento 100% presencial.</p>
            </div>
            <div class="col-sm-6 col-lg-3">
                <h2 class="h6 text-white mb-3">Contato</h2>
                <ul class="list-unstyled small d-grid gap-2">
                    <li><i class="bi bi-telephone me-2" aria-hidden="true"></i><?= e($contato) ?></li>
                    <li><a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>WhatsApp</a></li>
                    <li><i class="bi bi-envelope me-2" aria-hidden="true"></i><?= e($email_clinica) ?></li>
                </ul>
            </div>
            <div class="col-sm-6 col-lg-3">
                <h2 class="h6 text-white mb-3">Atendimento</h2>
                <ul class="list-unstyled small d-grid gap-2">
                    <li>Segunda a sexta, 07h30 às 19h00</li>
                    <li><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= e($endereco) ?></li>
                    <li><i class="bi bi-credit-card me-1" aria-hidden="true"></i>Pagamento na clínica: PIX, dinheiro, débito ou crédito</li>
                </ul>
            </div>
            <div class="col-lg-2">
                <h2 class="h6 text-white mb-3">Links</h2>
                <ul class="list-unstyled small d-grid gap-2">
                    <li><a href="agendar.php">Agendar consulta</a></li>
                    <li><a href="index.php#procedimentos">Tratamentos</a></li>
                    <li><a href="index.php#avaliacoes">Deixar avaliação</a></li>
                </ul>
            </div>
        </div>
        <hr class="border-light opacity-10 my-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 small">
            <span>© <?= date('Y') ?> <?= e($nome_clinica) ?> · <?= e($slogan_clinica) ?></span>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= e($instagram) ?>" class="social-ico" aria-label="Instagram da clínica" <?= $instagram !== '#' ? 'target="_blank" rel="noopener"' : '' ?>><i class="bi bi-instagram" aria-hidden="true"></i></a>
                <a href="<?= e($facebook) ?>" class="social-ico" aria-label="Facebook da clínica" <?= $facebook !== '#' ? 'target="_blank" rel="noopener"' : '' ?>><i class="bi bi-facebook" aria-hidden="true"></i></a>
                <a href="https://wa.me/<?= e($whatsapp) ?>" class="social-ico" aria-label="WhatsApp da clínica" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i></a>
            </div>
            <span class="dev-badge"><img src="assets/img/sanytech-simbolo.png" alt="" width="16" height="18"> Desenvolvido por <b><?= e($empresa_dev) ?></b></span>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/site.js?v=12"></script>
<?= $scripts_extra ?? '' ?>
</body>
</html>

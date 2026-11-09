<?php
// Cabeçalho do SITE PÚBLICO (paciente). Antes de incluir, defina $titulo e $pagina.
$titulo = $titulo ?? $nome_clinica;
$pagina = $pagina ?? '';
$titulo_pagina = $titulo . ' · ' . $nome_clinica;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<?php include __DIR__ . '/head.php'; ?>
    <meta name="description" content="<?= e($nome_clinica) ?> – <?= e($slogan_clinica) ?>. Ortodontia, implantes e estética dental em Araras/SP. Agende sua consulta online.">
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<div class="site-topstrip">
    <div class="container d-flex justify-content-between align-items-center gap-2 py-1">
        <span class="d-none d-md-inline"><i class="bi bi-telephone me-1" aria-hidden="true"></i><?= e($contato) ?> · <i class="bi bi-clock ms-2 me-1" aria-hidden="true"></i>Seg a Sex, 07h30 às 19h</span>
        <span class="d-md-none small"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Atendimento presencial</span>
        <?php include __DIR__ . '/acessibilidade.php'; ?>
    </div>
</div>

<?php
$links_menu = [
    ['index.php', 'Início', 'bi-house', $pagina === 'inicio'],
    ['index.php#especialistas', 'Especialistas', 'bi-person-badge', $pagina === 'especialista'],
    ['index.php#procedimentos', 'Tratamentos', 'bi-clipboard2-pulse', false],
    ['index.php#avaliacoes', 'Avaliações', 'bi-star', false],
    ['index.php#contato', 'Contato', 'bi-chat-dots', false],
];
?>
<nav class="navbar site-nav sticky-top py-2" aria-label="Menu do site">
    <div class="container flex-nowrap gap-2">
        <button class="menu-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuSite" aria-controls="menuSite" aria-label="Abrir menu">
            <span class="menu-btn-linhas" aria-hidden="true"></span>
        </button>
        <a class="navbar-brand me-auto me-lg-0" href="index.php" aria-label="<?= e($nome_clinica) ?> – página inicial"><?= logo_clinica() ?></a>
        <ul class="nav-desktop d-none d-lg-flex mx-auto">
            <?php foreach ($links_menu as [$href, $txt, $ico, $ativo]): ?>
                <li><a class="nav-link <?= $ativo ? 'active' : '' ?>" href="<?= $href ?>" <?= $ativo ? 'aria-current="page"' : '' ?>><?= $txt ?></a></li>
            <?php endforeach; ?>
        </ul>
        <a href="agendar.php" class="btn btn-teal btn-sm text-nowrap nav-agendar" aria-label="Agendar consulta"><i class="bi bi-calendar2-plus" aria-hidden="true"></i><span class="d-none d-md-inline ms-1">Agendar consulta</span></a>
    </div>
</nav>

<!-- Menu lateral (abre pelo botão ☰ à esquerda do logo) -->
<div class="offcanvas offcanvas-start menu-lateral" tabindex="-1" id="menuSite" aria-labelledby="menuSiteTitulo">
    <div class="offcanvas-header">
        <h2 class="visually-hidden" id="menuSiteTitulo">Menu</h2>
        <?= logo_clinica() ?>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar menu"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <ul class="list-unstyled d-grid gap-1 mb-4">
            <?php foreach ($links_menu as [$href, $txt, $ico, $ativo]): ?>
                <li><a class="menu-lateral-link <?= $ativo ? 'active' : '' ?>" href="<?= $href ?>"><i class="bi <?= $ico ?>" aria-hidden="true"></i><?= $txt ?></a></li>
            <?php endforeach; ?>
        </ul>
        <a href="agendar.php" class="btn btn-teal mb-2"><i class="bi bi-calendar2-plus me-1" aria-hidden="true"></i> Agendar consulta</a>
        <?php if (!empty($_SESSION['logado'])): ?>
            <a href="<?= pagina_inicial_perfil(perfil_atual()) ?>" class="btn btn-ghost"><i class="bi bi-grid me-1" aria-hidden="true"></i> Meu painel</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-ghost"><i class="bi bi-lock me-1" aria-hidden="true"></i> Acesso restrito</a>
        <?php endif; ?>
        <div class="d-flex gap-2 mt-4">
            <a href="<?= e($instagram) ?>" class="social-ico social-claro" aria-label="Instagram da clínica"><i class="bi bi-instagram" aria-hidden="true"></i></a>
            <a href="<?= e($facebook) ?>" class="social-ico social-claro" aria-label="Facebook da clínica"><i class="bi bi-facebook" aria-hidden="true"></i></a>
        </div>
        <div class="mt-auto pt-4 small text-muted d-grid gap-1">
            <span><i class="bi bi-telephone me-2" aria-hidden="true"></i><?= e($contato) ?></span>
            <span><i class="bi bi-clock me-2" aria-hidden="true"></i>Seg a Sex, 07h30 às 19h</span>
            <span><i class="bi bi-geo-alt me-2" aria-hidden="true"></i><?= e($endereco) ?></span>
        </div>
    </div>
</div>
<div id="conteudo" tabindex="-1"></div>

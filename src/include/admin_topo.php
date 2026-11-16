<?php
// Layout da ÁREA RESTRITA (secretaria e dentistas). Antes de incluir, defina $titulo e $pagina.
$titulo = $titulo ?? 'Painel';
$pagina = $pagina ?? '';
$perfil = perfil_atual();

if ($perfil === 'TI') {
    $menu = [
        ['Monitoramento', null, null],
        ['ti_painel',          'Funcionamento',      'bi-activity'],
        ['ti_velocidade',      'Velocidade',         'bi-speedometer2'],
        ['ti_seguranca',       'Segurança',          'bi-shield-lock'],
        ['Conta', null, null],
        ['alterar_senha',      'Alterar senha',      'bi-key'],
    ];
    $papel = 'SanyTech · TI';
} elseif ($perfil === 'DENTISTA') {
    $stPend = $pdo->prepare("SELECT COUNT(*) FROM avaliacao WHERE status = 'PENDENTE' AND id_dentista = ?");
    $stPend->execute([(int) ($_SESSION['id_dentista'] ?? 0)]);
    $menu = [
        ['Meu consultório', null, null],
        ['dent_painel',        'Visão geral',        'bi-grid-1x2'],
        ['agenda_semana',      'Agenda',             'bi-calendar-week'],
        ['dent_pacientes',     'Meus pacientes',     'bi-people'],
        ['dent_atestado',      'Atestados',          'bi-file-earmark-medical'],
        ['dent_financeiro',    'Financeiro',         'bi-graph-up-arrow'],
        ['Meu perfil no site', null, null],
        ['dent_perfil',        'Editar perfil',      'bi-person-badge'],
        ['dent_avaliacoes',    'Minhas avaliações',  'bi-star', (int) $stPend->fetchColumn()],
        ['Conta', null, null],
        ['alterar_senha',      'Alterar senha',      'bi-key'],
    ];
    $papel = 'Dentista';
} else {
    $pendAval = (int) $pdo->query("SELECT COUNT(*) FROM avaliacao WHERE status = 'PENDENTE'")->fetchColumn();
    $naoLidas = (int) $pdo->query("SELECT COUNT(*) FROM mensagem_contato WHERE lida = 0")->fetchColumn();
    $menu = [
        ['Principal', null, null],
        ['painel',             'Visão geral',        'bi-grid-1x2'],
        ['agenda_semana',      'Agenda da clínica',  'bi-calendar-week'],
        ['lista_agendamentos', 'Lista de consultas', 'bi-list-check'],
        ['nova_consulta',      'Nova consulta',      'bi-calendar-plus'],
        ['lembretes',          'Lembretes',          'bi-bell'],
        ['Cadastros', null, null],
        ['pacientes',          'Pacientes',          'bi-people'],
        ['dentistas',          'Dentistas',          'bi-person-badge'],
        ['Gestão', null, null],
        ['financeiro',         'Financeiro',         'bi-graph-up-arrow'],
        ['avaliacoes',         'Avaliações',         'bi-star', $pendAval],
        ['mensagens',          'Mensagens do site',  'bi-envelope', $naoLidas],
        ['Conta', null, null],
        ['alterar_senha',      'Alterar senha',      'bi-key'],
    ];
    $papel = 'Secretaria';
}
$inicio = pagina_inicial_perfil($perfil);
$f = flash();
$titulo_pagina = $titulo . ' · ' . $papel . ' · ' . $nome_clinica;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<?php include __DIR__ . '/head.php'; ?>
    <meta name="robots" content="noindex">
</head>
<body class="admin-body">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<aside class="sidebar" id="sidebar" aria-label="Menu principal">
    <div class="sidebar-head">
        <a href="<?= $inicio ?>" class="sidebar-logo"><?php if ($perfil === 'TI'): ?><img src="assets/img/sanytech.png" alt="SanyTech – área técnica" class="brand-logo-img"><?php else: ?><?= logo_clinica() ?><?php endif; ?></a>
        <button class="btn btn-icon btn-ghost mobile-only" type="button" data-menu-toggle aria-label="Fechar menu" aria-controls="sidebar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <nav class="sidebar-scroll">
        <ul class="side-nav">
            <?php foreach ($menu as $m): ?>
                <?php if ($m[1] === null): ?>
                    <li class="lbl"><?= e($m[0]) ?></li>
                <?php else: ?>
                    <li><a href="<?= $m[0] ?>.php" class="<?= $pagina === $m[0] ? 'active' : '' ?>" <?= $pagina === $m[0] ? 'aria-current="page"' : '' ?>>
                        <i class="bi <?= $m[2] ?>" aria-hidden="true"></i><span><?= e($m[1]) ?></span>
                        <?php if (!empty($m[3])): ?><span class="side-badge" aria-label="<?= (int) $m[3] ?> pendente(s)"><?= (int) $m[3] ?></span><?php endif; ?>
                    </a></li>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($perfil !== 'TI'): ?><li class="lbl">Site</li><?php endif; ?>
            <li><a href="index.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span>Ver site</span></a></li>
        </ul>
    </nav>
    <div class="side-user">
        <span class="avatar avatar-sm" style="--c:#12a3a0" aria-hidden="true"><?= e(iniciais($_SESSION['nome'] ?? 'S')) ?></span>
        <div class="small lh-sm flex-grow-1 min-w-0"><strong class="d-block text-truncate"><?= e($_SESSION['nome'] ?? '') ?></strong><span class="text-muted"><?= $papel ?></span></div>
        <form method="POST" action="logout.php" class="m-0"><?= csrf_campo() ?>
            <button class="btn btn-icon btn-ghost" title="Sair do sistema" aria-label="Sair do sistema"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></button>
        </form>
    </div>
</aside>
<div class="sidebar-backdrop" id="backdrop"></div>

<div class="main">
    <header class="topbar">
        <button class="btn btn-icon btn-menu" type="button" data-menu-toggle aria-controls="sidebar" aria-label="Mostrar ou esconder o menu" title="Mostrar/esconder menu">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="flex-grow-1 min-w-0">
            <div class="small text-muted d-none d-md-block"><?= saudar() ?>, <?= e(primeiro_nome($_SESSION['nome'] ?? '')) ?> · <?= data_br(date('Y-m-d')) ?></div>
            <h1 class="text-truncate"><?= e($titulo) ?></h1>
        </div>
        <div class="topbar-acts">
            <?= $acoes_topo ?? '' ?>
            <div class="d-none d-lg-flex"><?php include __DIR__ . '/acessibilidade.php'; ?></div>
        </div>
    </header>
    <main class="content" id="conteudo" tabindex="-1">
        <?php if ($f): ?>
            <div class="alert alert-<?= e($f[1]) ?> alert-dismissible fade show d-flex align-items-center gap-2" role="status">
                <i class="bi bi-<?= $f[1] === 'success' ? 'check-circle' : ($f[1] === 'danger' ? 'exclamation-triangle' : 'info-circle') ?>" aria-hidden="true"></i><div><?= e($f[0]) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar aviso"></button>
            </div>
        <?php endif; ?>

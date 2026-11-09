<?php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';
if (!empty($_SESSION['logado'])) { header('Location: ' . pagina_inicial_perfil(perfil_atual())); exit; }

$msgs = [
    '1'         => ['danger',  'bi-exclamation-circle', 'E-mail ou senha inválidos.'],
    'robo'      => ['warning', 'bi-shield-exclamation', 'Não foi possível validar o envio. Aguarde alguns segundos e tente novamente.'],
    'bloqueado' => ['danger',  'bi-lock', 'Limite de ' . LOGIN_MAX_TENTATIVAS . ' tentativas atingido. Por segurança, o acesso deste aparelho ficou bloqueado temporariamente.'],
    'expirado'  => ['warning', 'bi-clock-history', 'A página ficou aberta por muito tempo. Tente entrar novamente.'],
];
$aviso = $msgs[$_GET['erro'] ?? ''] ?? null;
// RATE LIMITING: situação deste aparelho (IP) nas tentativas de login
$lim = limite_status($pdo, 'login');
if ($lim['bloqueado']) $aviso = $msgs['bloqueado'];
elseif (($_GET['erro'] ?? '') === '1' && $lim['usadas']) {
    $aviso = ['danger', 'bi-exclamation-circle', 'E-mail ou senha inválidos. Restam ' . $lim['restantes'] . ' de ' . $lim['max'] . ' tentativas.'];
}
if (isset($_GET['saiu']))    $aviso = ['success', 'bi-check-circle', 'Você saiu do sistema com segurança.'];
if (isset($_GET['expirou'])) $aviso = ['info', 'bi-hourglass-split', 'Sua sessão expirou após ' . SESSAO_INATIVIDADE_MIN . ' minutos sem uso. Entre novamente.'];
$titulo_pagina = 'Acesso restrito · ' . $nome_clinica;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<?php include __DIR__ . '/include/head.php'; ?>
    <meta name="robots" content="noindex">
</head>
<body class="login-page">
<a class="skip-link" href="#conteudo">Pular para o formulário</a>
<?php include __DIR__ . '/include/acessibilidade.php'; ?>

<main class="login-center" id="conteudo">
    <div class="login-box">
        <div class="login-brand">
            <img src="assets/img/logo-simbolo.png" alt="" class="login-symbol" width="72" height="63">
            <h1 class="login-clinic">Garcia <span>Di Mase</span></h1>
            <p class="login-slogan"><?= e($slogan_clinica) ?></p>
        </div>

        <div class="card-soft login-card">
            <div class="text-center mb-4">
                <span class="eyebrow"><i class="bi bi-shield-lock" aria-hidden="true"></i> Acesso restrito</span>
                <h2 class="h3 mt-3 mb-1"><?= saudar() ?>!</h2>
                <p class="text-muted mb-0">Secretaria e dentistas: entre com seu e-mail profissional.</p>
            </div>

            <?php if ($aviso): ?>
                <div class="alert alert-<?= $aviso[0] ?> py-2 small d-flex gap-2" role="alert"><i class="bi <?= $aviso[1] ?>" aria-hidden="true"></i><div><?= e($aviso[2]) ?></div></div>
            <?php endif; ?>

            <form action="auth.php" method="POST" data-enviando="Entrando…">
                <?= csrf_campo() ?>
                <div class="mb-3">
                    <label class="form-label" for="u">E-mail de acesso</label>
                    <div class="input-icon"><i class="bi bi-person" aria-hidden="true"></i>
                        <input type="email" id="u" name="usuario" class="form-control" required autocomplete="username" autofocus
                               placeholder="nome@<?= e($dominio_login) ?>" maxlength="120" inputmode="email"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="s">Senha</label>
                    <div class="input-icon input-senha"><i class="bi bi-key" aria-hidden="true"></i>
                        <input type="password" id="s" name="senha" class="form-control" required autocomplete="current-password" maxlength="128">
                        <button type="button" class="btn-ver-senha" aria-label="Mostrar senha" aria-controls="s" onclick="const i=document.getElementById('s');const v=i.type==='password';i.type=v?'text':'password';this.setAttribute('aria-label',v?'Ocultar senha':'Mostrar senha');this.firstElementChild.className=v?'bi bi-eye-slash':'bi bi-eye'"><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <?php $_SESSION['form_inicio']['login'] = time(); ?>
                <div class="hp-field" aria-hidden="true"><label>Não preencha este campo<input type="text" name="site_url" tabindex="-1" autocomplete="off"></label></div>

                <!-- Limite de tentativas (rate limiting): 4 a cada 15 minutos -->
                <div class="rl-login<?= $lim['bloqueado'] ? ' rl-bloqueado' : '' ?>" <?= $lim['bloqueado'] ? 'data-libera-em="' . (int) $lim['libera_em'] . '" data-botao="btnEntrar"' : '' ?>>
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <span class="small fw-bold"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i>Acesso protegido</span>
                        <span class="small text-muted">Tentativas: <?= $lim['usadas'] ?> de <?= $lim['max'] ?></span>
                    </div>
                    <div class="rl-barra mt-2" aria-hidden="true">
                        <?php for ($i = 1; $i <= $lim['max']; $i++): ?><span class="<?= $i <= $lim['usadas'] ? 'usado' : '' ?>"></span><?php endfor; ?>
                    </div>
                    <?php if ($lim['bloqueado']): ?>
                        <p class="small mb-0 mt-2" aria-live="polite">Acesso bloqueado temporariamente. Tente novamente em <strong data-contador><?= gmdate('i:s', $lim['espera_seg']) ?></strong>.</p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-teal w-100 btn-lg mt-4" id="btnEntrar" <?= $lim['bloqueado'] ? 'disabled' : '' ?>>Entrar no sistema</button>
            </form>
        </div>

        <div class="text-center mt-4 d-flex flex-column gap-3 align-items-center">
            <a href="index.php" class="small text-muted"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Voltar ao site</a>
            <span class="dev-badge dev-light"><img src="assets/img/sanytech-simbolo.png" alt="" width="16" height="18"> Desenvolvido por <b><?= e($empresa_dev) ?></b></span>
        </div>
    </div>
</main>
<script src="assets/js/site.js?v=12"></script>
</body>
</html>

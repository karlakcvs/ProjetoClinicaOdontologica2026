<?php
// =====================================================================
//  ÁREA TÉCNICA SanyTech – login exclusivo da equipe de desenvolvimento.
//  Secretaria e dentistas NÃO entram aqui (e a equipe técnica não entra
//  na área clínica). Senha forte + limite de 4 tentativas (rate limiting).
// =====================================================================
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';

if (!empty($_SESSION['logado'])) {
    header('Location: ' . pagina_inicial_perfil(perfil_atual()));
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $erro = 'A página ficou aberta por muito tempo. Tente novamente.';
    } elseif (limite_excedido($pdo, 'login_ti')) {
        $erro = 'bloqueado';
    } elseif (protecao_validar('login_ti', 1) !== '') {
        limite_registrar($pdo, 'login_ti');
        $erro = 'Não foi possível validar o envio. Tente novamente.';
    } else {
        $user = mb_strtolower(trim((string) ($_POST['usuario'] ?? '')));
        $pass = (string) ($_POST['senha'] ?? '');
        $st = $pdo->prepare("SELECT * FROM usuario WHERE LOWER(email) = ? AND ativo = 1 AND perfil = 'TI'");
        $st->execute([$user]);
        $u = $st->fetch();
        $hash = $u['senha'] ?? '$2y$10$usuarioInexistenteXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
        if (!$u || !password_verify($pass, $hash)) {
            limite_registrar($pdo, 'login_ti');
            $erro = limite_excedido($pdo, 'login_ti') ? 'bloqueado' : 'Usuário ou senha inválidos.';
        } else {
            limite_limpar($pdo, 'login_ti');
            $pdo->prepare("UPDATE usuario SET ultimo_acesso = ? WHERE id_usuario = ?")->execute([date('Y-m-d H:i:s'), $u['id_usuario']]);
            session_regenerate_id(true);
            $_SESSION = [
                'logado' => true, 'usuario_id' => (int) $u['id_usuario'], 'nome' => $u['nome'], 'perfil' => 'TI',
                'id_dentista' => null, 'ultimo_uso' => time(), 'agente' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''),
            ];
            header('Location: ti_painel.php');
            exit;
        }
    }
}
$lim = limite_status($pdo, 'login_ti');
$_SESSION['form_inicio']['login_ti'] = time();
$titulo_pagina = 'Área técnica · ' . $empresa_dev;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<?php include __DIR__ . '/include/head.php'; ?>
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="login-page login-ti">
<a class="skip-link" href="#conteudo">Pular para o formulário</a>
<?php include __DIR__ . '/include/acessibilidade.php'; ?>

<main class="login-center" id="conteudo">
    <div class="login-box">
        <div class="login-brand">
            <img src="assets/img/sanytech.png" alt="SanyTech" width="260" height="80" class="ti-logo">
            <p class="login-slogan">Área técnica · desenvolvimento web</p>
        </div>

        <div class="card-soft login-card">
            <div class="text-center mb-4">
                <span class="eyebrow"><i class="bi bi-cpu" aria-hidden="true"></i> Somente equipe SanyTech</span>
                <h1 class="h3 mt-3 mb-1">Monitoramento do sistema</h1>
                <p class="text-muted mb-0 small">Funcionamento, velocidade e segurança. Sem acesso a dados de pacientes.</p>
            </div>

            <?php if (isset($_GET['saiu'])): ?>
                <div class="alert alert-success py-2 small d-flex gap-2" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><div>Você saiu da área técnica com segurança.</div></div>
            <?php elseif (isset($_GET['expirou'])): ?>
                <div class="alert alert-info py-2 small d-flex gap-2" role="status"><i class="bi bi-hourglass-split" aria-hidden="true"></i><div>Sessão encerrada por inatividade. Entre novamente.</div></div>
            <?php endif; ?>
            <?php if ($erro && $erro !== 'bloqueado'): ?>
                <div class="alert alert-danger py-2 small d-flex gap-2" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><div><?= e($erro) ?><?= $lim['restantes'] && !$lim['bloqueado'] ? ' Restam ' . $lim['restantes'] . ' de ' . $lim['max'] . ' tentativas.' : '' ?></div></div>
            <?php endif; ?>

            <form method="POST" data-enviando="Entrando…" autocomplete="off">
                <?= csrf_campo() ?>
                <div class="mb-3">
                    <label class="form-label" for="u">Usuário</label>
                    <div class="input-icon"><i class="bi bi-person-gear" aria-hidden="true"></i>
                        <input type="email" id="u" name="usuario" class="form-control" required autocomplete="username" autofocus maxlength="120" inputmode="email"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="s">Senha</label>
                    <div class="input-icon input-senha"><i class="bi bi-key" aria-hidden="true"></i>
                        <input type="password" id="s" name="senha" class="form-control" required autocomplete="current-password" maxlength="128">
                        <button type="button" class="btn-ver-senha" aria-label="Mostrar senha" aria-controls="s" onclick="const i=document.getElementById('s');const v=i.type==='password';i.type=v?'text':'password';this.setAttribute('aria-label',v?'Ocultar senha':'Mostrar senha');this.firstElementChild.className=v?'bi bi-eye-slash':'bi bi-eye'"><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="hp-field" aria-hidden="true"><label>Não preencha este campo<input type="text" name="site_url" tabindex="-1" autocomplete="off"></label></div>

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
                <button type="submit" class="btn btn-navy w-100 btn-lg mt-4" id="btnEntrar" <?= $lim['bloqueado'] ? 'disabled' : '' ?>><i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Entrar na área técnica</button>
            </form>
        </div>

        <div class="text-center mt-4">
            <a href="index.php" class="small text-muted"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Voltar ao site da clínica</a>
        </div>
    </div>
</main>
<script src="assets/js/site.js?v=12"></script>
</body>
</html>

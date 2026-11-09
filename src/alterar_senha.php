<?php
// Cada usuário (secretaria ou dentista) troca a própria senha
require_once __DIR__ . '/include/config.php';
exigir_login(['SECRETARIA', 'DENTISTA', 'TI']);
require_once __DIR__ . '/include/conexao.php';

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $atual = (string) ($_POST['atual'] ?? '');
    $nova  = (string) ($_POST['nova'] ?? '');
    $conf  = (string) ($_POST['confirma'] ?? '');
    $st = $pdo->prepare("SELECT senha FROM usuario WHERE id_usuario = ?");
    $st->execute([$_SESSION['usuario_id']]);
    $hash = (string) $st->fetchColumn();

    if (perfil_atual() === 'TI' && mb_strlen($nova) < 14) $erro = 'Na área técnica a senha precisa ter pelo menos 14 caracteres.';
    elseif (($lim = limite_status($pdo, 'troca_senha'))['bloqueado']) $erro = limite_mensagem($lim);   // rate limiting: 4 tentativas / 15 min
    elseif (!password_verify($atual, $hash)) {
        limite_registrar($pdo, 'troca_senha');
        $rest = limite_status($pdo, 'troca_senha')['restantes'];
        $erro = 'A senha atual está incorreta. ' . ($rest ? "Restam $rest de " . LIMITE_TENTATIVAS . ' tentativas.' : 'Limite atingido: aguarde 15 minutos.');
    }
    elseif ($nova !== $conf) $erro = 'A confirmação não é igual à nova senha.';
    elseif ($nova === $atual) $erro = 'A nova senha deve ser diferente da atual.';
    elseif ($m = senha_forte($nova)) $erro = $m;
    else {
        $pdo->prepare("UPDATE usuario SET senha = ? WHERE id_usuario = ?")->execute([password_hash($nova, PASSWORD_DEFAULT), $_SESSION['usuario_id']]);
        session_regenerate_id(true);
        flash('Senha alterada com sucesso. Use a nova senha no próximo acesso.');
        header('Location: ' . pagina_inicial_perfil(perfil_atual()));
        exit;
    }
}

$st = $pdo->prepare("SELECT email FROM usuario WHERE id_usuario = ?");
$st->execute([$_SESSION['usuario_id']]);
$email = $st->fetchColumn();

$titulo = 'Alterar senha';
$pagina = 'alterar_senha';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-5">
        <?php if ($erro): ?><div class="alert alert-danger d-flex gap-2" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><div><?= e($erro) ?></div></div><?php endif; ?>
        <form method="POST" class="card-soft panel" autocomplete="off">
            <?= csrf_campo() ?>
            <p class="small text-muted">Login: <strong class="text-body"><?= e($email) ?></strong></p>
            <div class="mb-3"><label class="form-label" for="atual">Senha atual</label><input type="password" id="atual" name="atual" class="form-control" required autocomplete="current-password"></div>
            <div class="mb-3"><label class="form-label" for="nova">Nova senha</label><input type="password" id="nova" name="nova" class="form-control" required minlength="10" autocomplete="new-password" aria-describedby="regras"></div>
            <div class="mb-3"><label class="form-label" for="confirma">Repita a nova senha</label><input type="password" id="confirma" name="confirma" class="form-control" required minlength="10" autocomplete="new-password"></div>
            <ul class="small text-muted ps-3 mb-4" id="regras">
                <li>No mínimo 10 caracteres</li>
                <li>Letras maiúsculas e minúsculas</li>
                <li>Pelo menos um número e um símbolo (! @ # % &amp; *)</li>
            </ul>
            <button class="btn btn-teal w-100"><i class="bi bi-shield-check me-1" aria-hidden="true"></i> Salvar nova senha</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

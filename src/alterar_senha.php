<?php
// Cada usuário (secretaria ou dentista) troca a própria senha
require_once __DIR__ . '/include/check_area.php';
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

    if (limite_excedido($pdo, 'troca_senha', 5, 15)) $erro = 'Muitas tentativas. Aguarde alguns minutos.';
    elseif (!password_verify($atual, $hash)) { $erro = 'A senha atual está incorreta.'; limite_registrar($pdo, 'troca_senha'); }
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

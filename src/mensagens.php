<?php
// Mensagens enviadas pelo formulário "Fale conosco" do site
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $id = (int) ($_POST['id'] ?? 0);
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'lida')     $pdo->prepare("UPDATE mensagem_contato SET lida = 1 WHERE id_mensagem = ?")->execute([$id]);
    if ($acao === 'naolida')  $pdo->prepare("UPDATE mensagem_contato SET lida = 0 WHERE id_mensagem = ?")->execute([$id]);
    if ($acao === 'excluir') { $pdo->prepare("DELETE FROM mensagem_contato WHERE id_mensagem = ?")->execute([$id]); flash('Mensagem excluída.', 'warning'); }
    header('Location: mensagens.php');
    exit;
}

$lista = $pdo->query("SELECT * FROM mensagem_contato ORDER BY lida ASC, criado_em DESC LIMIT 200")->fetchAll();
$titulo = 'Mensagens do site';
$pagina = 'mensagens';
include __DIR__ . '/include/admin_topo.php';
?>
<p class="text-muted small mb-3">Mensagens do formulário “Fale conosco”. Todas passaram pela verificação “não sou um robô”. Responda pelo e-mail ou WhatsApp do paciente e marque como lida.</p>
<div class="d-grid gap-3">
<?php foreach ($lista as $m):
    $tel = preg_replace('/\D/', '', (string) $m['telefone']);
    $mailto = 'mailto:' . rawurlencode($m['email']) . '?subject=' . rawurlencode('Re: ' . $m['assunto'] . ' – ' . $nome_clinica); ?>
    <article class="card-soft msg-item <?= $m['lida'] ? '' : 'nao-lida' ?>">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
            <div><strong><?= e($m['nome']) ?></strong> <?php if (!$m['lida']): ?><span class="status status-confirmada">nova</span><?php endif; ?>
                <div class="small text-muted"><?= e($m['email']) ?><?= $m['telefone'] ? ' · ' . e($m['telefone']) : '' ?></div></div>
            <div class="small text-muted text-end"><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?><br><span class="tag tag-navy"><?= e($m['assunto']) ?></span></div>
        </div>
        <p class="note mb-3"><?= e($m['mensagem']) ?></p>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e($mailto) ?>" class="btn btn-sm btn-soft"><i class="bi bi-reply me-1" aria-hidden="true"></i>Responder por e-mail</a>
            <?php if (strlen($tel) >= 10): ?><a href="https://wa.me/55<?= e($tel) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost"><i class="bi bi-whatsapp me-1 text-success" aria-hidden="true"></i>WhatsApp</a><?php endif; ?>
            <form method="POST"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int) $m['id_mensagem'] ?>"><input type="hidden" name="acao" value="<?= $m['lida'] ? 'naolida' : 'lida' ?>">
                <button class="btn btn-sm btn-ghost"><i class="bi bi-<?= $m['lida'] ? 'envelope' : 'check2' ?> me-1" aria-hidden="true"></i><?= $m['lida'] ? 'Marcar como não lida' : 'Marcar como lida' ?></button></form>
            <form method="POST" data-confirm="Excluir esta mensagem?"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int) $m['id_mensagem'] ?>"><input type="hidden" name="acao" value="excluir">
                <button class="btn btn-sm btn-ghost text-danger" aria-label="Excluir mensagem de <?= e($m['nome']) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php if (!$lista): ?><div class="empty-state"><i class="bi bi-envelope-open" aria-hidden="true"></i><p class="mt-2 mb-0">Nenhuma mensagem recebida pelo site ainda.</p></div><?php endif; ?>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

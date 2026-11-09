<?php
// ÁREA TÉCNICA SanyTech – SEGURANÇA: rate limiting, bloqueios ativos e eventos.
// IPs aparecem mascarados; nenhum e-mail, senha ou dado de paciente é exibido.
require_once __DIR__ . '/include/check_ti.php';
require_once __DIR__ . '/include/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    if (($_POST['acao'] ?? '') === 'liberar_todos') {
        $pdo->exec("DELETE FROM limite_acao WHERE acao IN ('" . implode("','", array_keys(LIMITES_ACAO)) . "')");
        $pdo->exec("DELETE FROM limite_requisicao");
        flash('Todos os bloqueios foram liberados.');
    } else {
        // o IP real não vai para a tela: o botão envia só um código (hash) do IP
        $alvo = (string) ($_POST['ref'] ?? '');
        $acao = (string) ($_POST['acao'] ?? '');
        if (isset(LIMITES_ACAO[$acao])) {
            $st = $pdo->prepare("SELECT DISTINCT ip FROM limite_acao WHERE acao = ?");
            $st->execute([$acao]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $ip) {
                if (hash_equals(hash_hmac('sha256', $ip, csrf_token()), $alvo)) { limite_limpar($pdo, $acao, $ip); flash('Aparelho ' . ip_mascarado($ip) . ' liberado.'); }
            }
        }
    }
    header('Location: ti_seguranca.php');
    exit;
}

$bloqueados = [];
foreach (LIMITES_ACAO as $acao => [$max, $janela, $rotulo]) {
    $st = $pdo->prepare("SELECT ip, COUNT(*) AS qtd, MIN(criado_em) AS primeira FROM limite_acao
                         WHERE acao = ? AND criado_em >= ? GROUP BY ip HAVING COUNT(*) >= ?");
    $st->execute([$acao, date('Y-m-d H:i:s', time() - $janela * 60), $max]);
    foreach ($st->fetchAll() as $r) {
        $bloqueados[] = ['ip' => $r['ip'], 'acao' => $acao, 'rotulo' => $rotulo, 'qtd' => (int) $r['qtd'], 'max' => $max, 'libera' => strtotime($r['primeira']) + $janela * 60];
    }
}
$st = $pdo->prepare("SELECT acao, COUNT(*) AS qtd, COUNT(DISTINCT ip) AS ips FROM limite_acao WHERE criado_em >= ? GROUP BY acao ORDER BY qtd DESC");
$st->execute([date('Y-m-d H:i:s', time() - 86400)]);
$eventos = $st->fetchAll();
$st = $pdo->prepare("SELECT ip, acao, criado_em FROM limite_acao WHERE criado_em >= ? ORDER BY criado_em DESC LIMIT 20");
$st->execute([date('Y-m-d H:i:s', time() - 86400)]);
$recentes = $st->fetchAll();
$nomes = [
    'login' => 'Senha errada (área da clínica)', 'login_ti' => 'Senha errada (área técnica)', 'agendamento' => 'Agendamento pelo site',
    'contato' => 'Mensagem pelo site', 'avaliacao' => 'Avaliação enviada', 'troca_senha' => 'Troca de senha errada',
    'excesso_pagina' => 'Excesso de páginas (429)', 'excesso_api' => 'Excesso na API de horários (429)',
    'excesso_arquivo' => 'Excesso de downloads (429)', 'excesso_envio' => 'Excesso de formulários (429)',
];
$protecoes = [
    ['bi-speedometer', 'Rate limiting', '4 tentativas nas ações sensíveis e limite por minuto no tráfego (HTTP 429).'],
    ['bi-key', 'Senhas', 'Hash bcrypt, política de senha forte e troca só com a senha atual.'],
    ['bi-ui-checks', 'CSRF', 'Token em todos os formulários; saída do sistema só por POST.'],
    ['bi-person-lock', 'Sessão', 'Cookie HttpOnly/SameSite, novo ID no login, expira em ' . SESSAO_INATIVIDADE_MIN . ' min, presa ao navegador.'],
    ['bi-people', 'Perfis', 'Secretaria, dentista e TI separados; dentista só vê os próprios pacientes; TI não vê dados clínicos.'],
    ['bi-database-lock', 'Banco', 'Consultas preparadas (sem SQL injection); porta só no próprio computador.'],
    ['bi-file-earmark-lock', 'Arquivos', 'Tipo real conferido, nome aleatório, prontuário fora do acesso direto.'],
    ['bi-window-stack', 'Navegador', 'CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy.'],
];

$titulo = 'Segurança';
$pagina = 'ti_seguranca';
$acoes_topo = '<form method="POST" class="m-0" data-confirm="Liberar todos os bloqueios agora?">' . csrf_campo() . '<input type="hidden" name="acao" value="liberar_todos"><button class="btn btn-sm btn-ghost"><i class="bi bi-unlock me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Liberar todos</span></button></form>';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat stat-hero"><span class="ico"><i class="bi bi-shield-lock" aria-hidden="true"></i></span><div class="val"><?= LIMITE_TENTATIVAS ?> tentativas</div><div class="lab">regra das ações sensíveis</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-red"><i class="bi bi-slash-circle" aria-hidden="true"></i></span><div class="val"><?= count($bloqueados) ?></div><div class="lab">bloqueio(s) ativo(s)</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-navy"><i class="bi bi-activity" aria-hidden="true"></i></span><div class="val"><?= array_sum(array_column($eventos, 'qtd')) ?></div><div class="lab">eventos nas últimas 24 h</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-teal"><i class="bi bi-person-x" aria-hidden="true"></i></span><div class="val"><?= (int) (array_column($eventos, 'qtd', 'acao')['login'] ?? 0) + (int) (array_column($eventos, 'qtd', 'acao')['login_ti'] ?? 0) ?></div><div class="lab">senhas erradas (24 h)</div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card-soft panel mb-4">
            <h2 class="panel-title mb-3">Bloqueados agora</h2>
            <?php if (!$bloqueados): ?>
                <div class="empty-state py-3"><i class="bi bi-shield-check" aria-hidden="true"></i><p class="mt-2 mb-0">Nenhum aparelho bloqueado.</p></div>
            <?php else: ?>
            <table class="table table-clean table-stack">
                <thead><tr><th>Aparelho</th><th>Motivo</th><th>Tentativas</th><th>Libera às</th><th><span class="visually-hidden">Ação</span></th></tr></thead>
                <tbody>
                <?php foreach ($bloqueados as $b): ?>
                    <tr>
                        <td class="td-main"><code><?= e(ip_mascarado($b['ip'])) ?></code></td>
                        <td data-label="Motivo"><?= e(ucfirst($b['rotulo'])) ?></td>
                        <td data-label="Tentativas"><?= $b['qtd'] ?> / <?= $b['max'] ?></td>
                        <td data-label="Libera às"><?= date('H:i', $b['libera']) ?></td>
                        <td class="td-acoes text-end"><form method="POST" class="d-inline"><?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="<?= e($b['acao']) ?>"><input type="hidden" name="ref" value="<?= e(hash_hmac('sha256', $b['ip'], csrf_token())) ?>">
                            <button class="btn btn-sm btn-ghost"><i class="bi bi-unlock" aria-hidden="true"></i> Liberar</button></form></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <div class="card-soft panel">
            <h2 class="panel-title mb-3">Eventos das últimas 24 horas</h2>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($eventos as $ev): ?><span class="tag tag-navy"><?= e($nomes[$ev['acao']] ?? $ev['acao']) ?>: <?= (int) $ev['qtd'] ?></span><?php endforeach; ?>
                <?php if (!$eventos): ?><span class="small text-muted">Nenhum evento registrado.</span><?php endif; ?>
            </div>
            <?php if ($recentes): ?>
            <table class="table table-clean table-stack small">
                <thead><tr><th>Quando</th><th>Aparelho</th><th>Evento</th></tr></thead>
                <tbody>
                <?php foreach ($recentes as $r): ?>
                    <tr><td class="td-main"><?= date('d/m H:i:s', strtotime($r['criado_em'])) ?></td><td data-label="Aparelho"><code><?= e(ip_mascarado($r['ip'])) ?></code></td><td data-label="Evento"><?= e($nomes[$r['acao']] ?? $r['acao']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card-soft panel mb-4">
            <h2 class="panel-title mb-2">Rate limiting</h2>
            <p class="small text-muted">Controla quantas requisições cada aparelho pode fazer em um intervalo de tempo: protege CPU, banco e rede, dificulta força bruta, robôs e negação de serviço, e evita que um só cliente use todos os recursos.</p>
            <?php foreach (LIMITES_ACAO as [$max, $janela, $rotulo]): ?>
                <div class="regra-lim"><span class="n"><?= $max ?></span><div><strong><?= e(ucfirst($rotulo)) ?></strong><br><span class="text-muted small">a cada <?= $janela ?> min · janela deslizante</span></div></div>
            <?php endforeach; ?>
            <?php $nt = ['pagina' => 'Páginas', 'api' => 'API de horários', 'arquivo' => 'Arquivos do prontuário', 'envio' => 'Formulários (POST)'];
            foreach (LIMITES_TRAFEGO as $ch => [$max, $seg]): ?>
                <div class="regra-lim"><span class="n"><?= $max ?></span><div><strong><?= e($nt[$ch] ?? $ch) ?></strong><br><span class="text-muted small">por <?= $seg ?> s · depois HTTP 429 + Retry-After</span></div></div>
            <?php endforeach; ?>
        </div>
        <div class="card-soft panel">
            <h2 class="panel-title mb-2">Proteções ativas</h2>
            <ul class="list-unstyled mb-0 check-lista">
                <?php foreach ($protecoes as [$ic, $tit, $txt]): ?><li class="chk-ok"><i class="bi <?= $ic ?>" aria-hidden="true"></i><span><strong><?= e($tit) ?></strong><br><span class="small text-muted"><?= e($txt) ?></span></span></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

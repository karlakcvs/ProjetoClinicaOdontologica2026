<?php
// Painel de SEGURANÇA (secretaria): regras de rate limiting, aparelhos bloqueados agora
// e histórico das últimas 24 horas. Permite liberar um IP bloqueado por engano.
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $ip = substr(trim((string) ($_POST['ip'] ?? '')), 0, 45);
    $acao = (string) ($_POST['acao'] ?? '');
    if (filter_var($ip, FILTER_VALIDATE_IP) && isset(LIMITES_ACAO[$acao])) {
        limite_limpar($pdo, $acao, $ip);
        flash('Aparelho ' . $ip . ' liberado para ' . LIMITES_ACAO[$acao][2] . '.');
    }
    header('Location: seguranca_painel.php');
    exit;
}

// Bloqueados agora: IP + ação com tentativas >= limite dentro da janela
$bloqueados = [];
foreach (LIMITES_ACAO as $acao => [$max, $janela, $rotulo]) {
    $st = $pdo->prepare("SELECT ip, COUNT(*) AS qtd, MIN(criado_em) AS primeira, MAX(criado_em) AS ultima FROM limite_acao
                         WHERE acao = ? AND criado_em >= ? GROUP BY ip HAVING COUNT(*) >= ? ORDER BY MAX(criado_em) DESC");
    $st->execute([$acao, date('Y-m-d H:i:s', time() - $janela * 60), $max]);
    foreach ($st->fetchAll() as $r) {
        $r['acao'] = $acao; $r['rotulo'] = $rotulo; $r['max'] = $max;
        $r['libera'] = strtotime($r['primeira']) + $janela * 60;
        $bloqueados[] = $r;
    }
}

// Eventos das últimas 24h por tipo
$st = $pdo->prepare("SELECT acao, COUNT(*) AS qtd, COUNT(DISTINCT ip) AS ips FROM limite_acao WHERE criado_em >= ? GROUP BY acao ORDER BY qtd DESC");
$st->execute([date('Y-m-d H:i:s', time() - 86400)]);
$eventos = $st->fetchAll();
$st = $pdo->prepare("SELECT ip, acao, criado_em FROM limite_acao WHERE criado_em >= ? ORDER BY criado_em DESC LIMIT 25");
$st->execute([date('Y-m-d H:i:s', time() - 86400)]);
$recentes = $st->fetchAll();
$nomesAcao = [
    'login' => 'Login errado', 'agendamento' => 'Agendamento pelo site', 'contato' => 'Mensagem pelo site',
    'avaliacao' => 'Avaliação enviada', 'troca_senha' => 'Troca de senha errada',
    'excesso_pagina' => 'Excesso de páginas (429)', 'excesso_api' => 'Excesso na API de horários (429)',
    'excesso_arquivo' => 'Excesso de downloads (429)', 'excesso_envio' => 'Excesso de formulários (429)',
];
$meuIp = ip_cliente();

$titulo = 'Segurança · Rate limiting';
$pagina = 'seguranca_painel';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat stat-hero"><span class="ico"><i class="bi bi-shield-lock" aria-hidden="true"></i></span><div class="val"><?= LIMITE_TENTATIVAS ?> tentativas</div><div class="lab">regra principal (login e formulários)</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-red"><i class="bi bi-slash-circle" aria-hidden="true"></i></span><div class="val"><?= count($bloqueados) ?></div><div class="lab">bloqueio(s) ativo(s) agora</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-navy"><i class="bi bi-activity" aria-hidden="true"></i></span><div class="val"><?= array_sum(array_column($eventos, 'qtd')) ?></div><div class="lab">eventos nas últimas 24 h</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-teal"><i class="bi bi-pc-display" aria-hidden="true"></i></span><div class="val" style="font-size:1.1rem"><?= e($meuIp) ?></div><div class="lab">seu IP (identifica o aparelho)</div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-5">
        <div class="card-soft panel mb-4">
            <h2 class="panel-title mb-1">O que é rate limiting?</h2>
            <p class="small text-muted">Técnica que controla quantas requisições um cliente pode fazer ao servidor dentro de um intervalo de tempo. Substitui o antigo código de letras (captcha).</p>
            <ul class="small mb-0 ps-3 d-grid gap-1">
                <li><strong>Protege o servidor:</strong> evita sobrecarga de CPU, banco de dados e rede.</li>
                <li><strong>Mitiga ataques:</strong> força bruta de senha, raspagem de dados (scraping) e negação de serviço (DoS/DDoS).</li>
                <li><strong>Garante equidade:</strong> um único usuário ou robô não consome os recursos de todos.</li>
            </ul>
        </div>
        <div class="card-soft panel mb-4">
            <h2 class="panel-title mb-2">Ações sensíveis <span class="text-muted fw-normal small">· janela deslizante</span></h2>
            <?php foreach (LIMITES_ACAO as $acao => [$max, $janela, $rotulo]): ?>
                <div class="regra-lim"><span class="n"><?= $max ?></span><div><strong><?= e(ucfirst($rotulo)) ?></strong><br><span class="text-muted small">a cada <?= $janela ?> minutos por aparelho (IP)</span></div></div>
            <?php endforeach; ?>
        </div>
        <div class="card-soft panel">
            <h2 class="panel-title mb-2">Tráfego geral <span class="text-muted fw-normal small">· HTTP 429</span></h2>
            <?php $nomesTr = ['pagina' => 'Páginas abertas', 'api' => 'Consultas de horários (API)', 'arquivo' => 'Fotos/exames do prontuário', 'envio' => 'Formulários enviados'];
            foreach (LIMITES_TRAFEGO as $ch => [$max, $seg]): ?>
                <div class="regra-lim"><span class="n"><?= $max ?></span><div><strong><?= e($nomesTr[$ch] ?? $ch) ?></strong><br><span class="text-muted small">por <?= $seg ?> segundos · depois responde 429 com Retry-After</span></div></div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card-soft panel mb-4">
            <h2 class="panel-title mb-3">Bloqueados agora</h2>
            <?php if (!$bloqueados): ?>
                <div class="empty-state py-3"><i class="bi bi-shield-check" aria-hidden="true"></i><p class="mt-2 mb-0">Nenhum aparelho bloqueado no momento.</p></div>
            <?php else: ?>
            <table class="table table-clean table-stack">
                <thead><tr><th>Aparelho (IP)</th><th>Ação</th><th>Tentativas</th><th>Libera às</th><th><span class="visually-hidden">Liberar</span></th></tr></thead>
                <tbody>
                <?php foreach ($bloqueados as $b): ?>
                    <tr>
                        <td class="td-main"><strong><?= e($b['ip']) ?></strong><?= $b['ip'] === $meuIp ? ' <span class="tag">você</span>' : '' ?></td>
                        <td data-label="Ação"><?= e(ucfirst($b['rotulo'])) ?></td>
                        <td data-label="Tentativas"><?= (int) $b['qtd'] ?> / <?= (int) $b['max'] ?></td>
                        <td data-label="Libera às"><?= date('H:i', $b['libera']) ?></td>
                        <td class="td-acoes text-end">
                            <form method="POST" class="d-inline" data-confirm="Liberar este aparelho agora?"><?= csrf_campo() ?>
                                <input type="hidden" name="ip" value="<?= e($b['ip']) ?>"><input type="hidden" name="acao" value="<?= e($b['acao']) ?>">
                                <button class="btn btn-sm btn-ghost"><i class="bi bi-unlock" aria-hidden="true"></i> Liberar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <div class="card-soft panel">
            <h2 class="panel-title mb-3">Últimas 24 horas</h2>
            <?php if (!$eventos): ?><p class="small text-muted mb-0">Nenhum evento registrado.</p><?php endif; ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($eventos as $ev): ?>
                    <span class="tag tag-navy"><?= e($nomesAcao[$ev['acao']] ?? $ev['acao']) ?>: <?= (int) $ev['qtd'] ?> (<?= (int) $ev['ips'] ?> IP)</span>
                <?php endforeach; ?>
            </div>
            <?php if ($recentes): ?>
            <table class="table table-clean table-stack small">
                <thead><tr><th>Quando</th><th>Aparelho (IP)</th><th>Evento</th></tr></thead>
                <tbody>
                <?php foreach ($recentes as $r): ?>
                    <tr><td class="td-main"><?= date('d/m H:i:s', strtotime($r['criado_em'])) ?></td><td data-label="IP"><?= e($r['ip']) ?></td><td data-label="Evento"><?= e($nomesAcao[$r['acao']] ?? $r['acao']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

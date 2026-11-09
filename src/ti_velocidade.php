<?php
// ÁREA TÉCNICA SanyTech – VELOCIDADE: tempo de resposta das páginas (últimas 24 h ou 7 dias).
require_once __DIR__ . '/include/check_ti.php';
require_once __DIR__ . '/include/conexao.php';

$periodo = ($_GET['p'] ?? '24h') === '7d' ? '7d' : '24h';
$desde = date('Y-m-d H:i:s', time() - ($periodo === '7d' ? 7 * 86400 : 86400));

$st = $pdo->prepare("SELECT pagina, ms, status, criado_em FROM metrica_requisicao WHERE criado_em >= ? ORDER BY criado_em");
$st->execute([$desde]);
$linhas = $st->fetchAll();

function percentil(array $v, $p) {
    if (!$v) return 0;
    sort($v);
    return $v[(int) min(count($v) - 1, floor(count($v) * $p))];
}
$todos = array_map(fn($r) => (int) $r['ms'], $linhas);
$media = $todos ? array_sum($todos) / count($todos) : 0;
$p95 = percentil($todos, .95);
$lentas = count(array_filter($todos, fn($m) => $m > 800));
$c429 = count(array_filter($linhas, fn($r) => (int) $r['status'] === 429));
$c5xx = count(array_filter($linhas, fn($r) => (int) $r['status'] >= 500));

// por página
$porPag = [];
foreach ($linhas as $r) $porPag[$r['pagina']][] = (int) $r['ms'];
$tabela = [];
foreach ($porPag as $pag => $v) $tabela[] = ['pag' => $pag, 'n' => count($v), 'media' => array_sum($v) / count($v), 'p95' => percentil($v, .95), 'max' => max($v)];
usort($tabela, fn($a, $b) => $b['media'] <=> $a['media']);

// gráfico: por hora (24 h) ou por dia (7 dias)
$fmt = $periodo === '7d' ? 'd/m' : 'H\h';
$grupos = [];
$passo = $periodo === '7d' ? 86400 : 3600;
$inicio = $periodo === '7d' ? strtotime('today -6 days') : strtotime(date('Y-m-d H:00:00')) - 23 * 3600;
for ($t = $inicio; $t <= time(); $t += $passo) $grupos[date($fmt, $t)] = [];
foreach ($linhas as $r) { $k = date($fmt, strtotime($r['criado_em'])); if (isset($grupos[$k])) $grupos[$k][] = (int) $r['ms']; }
$rotulos = array_keys($grupos);
$qtd = array_map('count', array_values($grupos));
$med = array_map(fn($v) => $v ? round(array_sum($v) / count($v), 1) : 0, array_values($grupos));

$titulo = 'Velocidade';
$pagina = 'ti_velocidade';
$acoes_topo = '<div class="btn-group btn-group-sm" role="group" aria-label="Período">'
    . '<a href="ti_velocidade.php?p=24h" class="btn ' . ($periodo === '24h' ? 'btn-navy' : 'btn-ghost') . '">24 horas</a>'
    . '<a href="ti_velocidade.php?p=7d" class="btn ' . ($periodo === '7d' ? 'btn-navy' : 'btn-ghost') . '">7 dias</a></div>';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="card-soft stat stat-hero"><span class="ico"><i class="bi bi-speedometer2" aria-hidden="true"></i></span><div class="val"><?= number_format($media, 0, ',', '.') ?> ms</div><div class="lab">tempo médio de resposta</div></div></div>
    <div class="col-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-navy"><i class="bi bi-bar-chart-steps" aria-hidden="true"></i></span><div class="val"><?= number_format($p95, 0, ',', '.') ?> ms</div><div class="lab">95% das páginas abrem em até</div></div></div>
    <div class="col-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-gold"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span><div class="val"><?= $lentas ?></div><div class="lab">respostas lentas (&gt; 800 ms)</div></div></div>
    <div class="col-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-red"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i></span><div class="val"><?= $c5xx ?> / <?= $c429 ?></div><div class="lab">erros 5xx / limitadas (429)</div></div></div>
</div>

<div class="card-soft panel mb-4">
    <h2 class="panel-title mb-3">Requisições e tempo médio <span class="text-muted fw-normal small">· <?= $periodo === '7d' ? 'por dia' : 'por hora' ?></span></h2>
    <div class="chart-box"><canvas id="gVel" role="img" aria-label="Gráfico de quantidade de requisições e tempo médio de resposta"></canvas></div>
</div>

<div class="card-soft">
    <div class="panel pb-0"><h2 class="panel-title">Páginas mais lentas</h2><p class="small text-muted mb-2">Média, percentil 95 e pior tempo de cada página no período.</p></div>
    <table class="table table-clean table-stack">
        <thead><tr><th>Página</th><th>Acessos</th><th>Média</th><th>P95</th><th>Pior</th><th class="d-none d-md-table-cell">Situação</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($tabela, 0, 12) as $r): $sit = $r['p95'] > 800 ? ['status-faltou', 'lenta'] : ($r['p95'] > 300 ? ['status-agendada', 'atenção'] : ['status-realizada', 'rápida']); ?>
            <tr>
                <td class="td-main"><code><?= e($r['pag']) ?></code></td>
                <td data-label="Acessos"><?= $r['n'] ?></td>
                <td data-label="Média"><?= number_format($r['media'], 0, ',', '.') ?> ms</td>
                <td data-label="P95"><?= number_format($r['p95'], 0, ',', '.') ?> ms</td>
                <td data-label="Pior"><?= number_format($r['max'], 0, ',', '.') ?> ms</td>
                <td data-label="Situação" class="d-none d-md-table-cell"><span class="status <?= $sit[0] ?>"><?= $sit[1] ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$tabela): ?><tr><td colspan="6" class="text-center text-muted py-4">Ainda não há medições neste período.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$scripts_extra = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
if (window.Chart) new Chart(document.getElementById("gVel"), {
  data: { labels: ' . js($rotulos) . ', datasets: [
    { type: "bar", label: "Requisições", data: ' . js($qtd) . ', backgroundColor: "rgba(11,58,117,.22)", borderColor: "#0b3a75", borderWidth: 1, yAxisID: "y" },
    { type: "line", label: "Tempo médio (ms)", data: ' . js($med) . ', borderColor: "#0c7f7c", backgroundColor: "#0c7f7c", tension: .3, yAxisID: "y1" } ] },
  options: { maintainAspectRatio: false, interaction: { mode: "index", intersect: false },
    scales: { y: { beginAtZero: true, title: { display: true, text: "requisições" } }, y1: { beginAtZero: true, position: "right", grid: { drawOnChartArea: false }, title: { display: true, text: "ms" } } } }
});
</script>';
include __DIR__ . '/include/admin_fim.php';

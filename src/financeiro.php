<?php
// RF08 / RF09 / RF10 - Financeiro por dentista (dia, semana, mês) e consolidado da clínica
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/relatorio_financeiro.php';

$dia = $_GET['dia'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) $dia = date('Y-m-d');
$mes = $_GET['mes'] ?? substr($dia, 0, 7);
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');
$mesIni = $mes . '-01';
$mesFim = date('Y-m-t', strtotime($mesIni));
$semIni = date('Y-m-d', strtotime('monday this week', strtotime($dia)));
$semFim = date('Y-m-d', strtotime('sunday this week', strtotime($dia)));
$filtroDent = (int) ($_GET['dentista'] ?? 0) ?: null;

$rel = new RelatorioFinanceiro($pdo);
$clinDia = $rel->totalConsolidado($dia, $dia);
$clinSem = $rel->totalConsolidado($semIni, $semFim);
$clinMes = $rel->totalConsolidado($mesIni, $mesFim);
$porDentista = $rel->totalPorDentista($dia, $mesIni, $mesFim);
foreach ($porDentista as &$d) $d['sem'] = $rel->total($semIni, $semFim, $d['id_dentista']);
unset($d);
$serie = $rel->serieDiaria($mesIni, $mesFim);
$formas = $rel->porFormaPagamento($mesIni, $mesFim);
$procs = $rel->porProcedimento($mesIni, $mesFim);
$verLanc = ($_GET['lanc'] ?? 'dia') === 'mes' ? 'mes' : 'dia';
$lancamentos = $verLanc === 'mes' ? $rel->lancamentos($mesIni, $mesFim, $filtroDent) : $rel->lancamentos($dia, $dia, $filtroDent);

$st = $pdo->prepare("SELECT COUNT(*) FROM consulta WHERE data BETWEEN ? AND ? AND status = 'FALTOU'");
$st->execute([$mesIni, $mesFim]);
$faltasMes = (int) $st->fetchColumn();
$ticket = $clinMes['qtd'] ? $clinMes['total'] / $clinMes['qtd'] : 0;

// mês anterior para comparação
$antIni = date('Y-m-01', strtotime("$mesIni -1 month"));
$antFim = date('Y-m-t', strtotime($antIni));
$clinAnt = $rel->totalConsolidado($antIni, $antFim);
$variacao = $clinAnt['total'] > 0 ? ($clinMes['total'] - $clinAnt['total']) / $clinAnt['total'] * 100 : null;

// dados do gráfico
$labels = []; $datasets = [];
for ($t = strtotime($mesIni); $t <= strtotime($mesFim); $t += 86400) {
    $w = (int) date('w', $t);
    if ($w === 0 || $w === 6) continue;
    $labels[] = date('d/m', $t);
}
foreach ($porDentista as $d) {
    $vals = [];
    for ($t = strtotime($mesIni); $t <= strtotime($mesFim); $t += 86400) {
        $w = (int) date('w', $t);
        if ($w === 0 || $w === 6) continue;
        $vals[] = round($serie[date('Y-m-d', $t)][$d['id_dentista']] ?? 0, 2);
    }
    $datasets[] = ['label' => $d['nome'], 'data' => $vals, 'backgroundColor' => $d['cor'], 'borderRadius' => 4, 'maxBarThickness' => 26];
}
$totalFormas = max(1, array_sum(array_column($formas, 'total')));

$titulo = 'Financeiro';
$pagina = 'financeiro';
$acoes_topo = '<button onclick="window.print()" class="btn btn-ghost btn-sm d-none d-md-inline-flex"><i class="bi bi-printer me-1"></i> Imprimir</button>';
include __DIR__ . '/include/admin_topo.php';
?>

<form method="GET" class="card-soft p-3 mb-4 d-flex flex-wrap gap-3 align-items-end">
    <div><label class="form-label small">Dia</label><input type="date" name="dia" value="<?= e($dia) ?>" class="form-control"></div>
    <div><label class="form-label small">Mês</label><input type="month" name="mes" value="<?= e($mes) ?>" class="form-control"></div>
    <button class="btn btn-navy"><i class="bi bi-arrow-repeat me-1"></i> Atualizar</button>
    <a href="financeiro.php" class="btn btn-ghost">Hoje</a>
    <span class="small text-muted ms-lg-auto">Semana: <?= data_br($semIni) ?> a <?= data_br($semFim) ?></span>
</form>

<!-- Visão geral da clínica -->
<h2 class="h5 mb-3" style="font-family:var(--font-body);font-weight:700">Financeiro geral da clínica</h2>
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3"><div class="card-soft stat stat-hero"><span class="ico"><i class="bi bi-building"></i></span><div class="val"><?= moeda($clinMes['total']) ?></div>
        <div class="lab">Total de <?= nome_mes(substr($mes, 5)) ?> · <?= $clinMes['qtd'] ?> atend.
        <?php if ($variacao !== null): ?><br><span class="<?= $variacao >= 0 ? '' : '' ?>"><i class="bi bi-arrow-<?= $variacao >= 0 ? 'up' : 'down' ?>-right"></i> <?= number_format(abs($variacao), 1, ',', '.') ?>% vs mês anterior</span><?php endif; ?></div></div></div>
    <div class="col-6 col-md-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-teal"><i class="bi bi-calendar-day"></i></span><div class="val"><?= moeda($clinDia['total']) ?></div><div class="lab">Dia <?= data_br($dia) ?> · <?= $clinDia['qtd'] ?> atend.</div></div></div>
    <div class="col-6 col-md-6 col-xl-2"><div class="card-soft stat"><span class="ico ico-navy"><i class="bi bi-calendar-week"></i></span><div class="val"><?= moeda($clinSem['total']) ?></div><div class="lab">Na semana</div></div></div>
    <div class="col-6 col-md-3 col-xl-2"><div class="card-soft stat"><span class="ico ico-gold"><i class="bi bi-receipt"></i></span><div class="val"><?= moeda($ticket) ?></div><div class="lab">Ticket médio</div></div></div>
    <div class="col-6 col-md-3 col-xl-2"><div class="card-soft stat"><span class="ico ico-red"><i class="bi bi-person-x"></i></span><div class="val"><?= $faltasMes ?></div><div class="lab">Faltas no mês</div></div></div>
</div>

<!-- Por dentista -->
<h2 class="h5 mb-3" style="font-family:var(--font-body);font-weight:700">Contagem por dentista</h2>
<div class="row g-3 mb-4">
    <?php foreach ($porDentista as $d): $part = $clinMes['total'] > 0 ? $d['mes']['total'] / $clinMes['total'] * 100 : 0; ?>
    <div class="col-md-6 col-xl-4">
        <div class="card-soft doc-fin" style="--c:<?= e($d['cor']) ?>">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="avatar avatar-sm" style="--c:<?= e($d['cor']) ?>"><?= e(iniciais($d['nome'])) ?></span>
                <div class="lh-sm flex-grow-1"><strong><?= e($d['nome']) ?></strong><br><span class="small text-muted"><?= e($d['especialidade']) ?></span></div>
                <a href="?<?= http_build_query(['dia' => $dia, 'mes' => $mes, 'dentista' => $d['id_dentista'], 'lanc' => 'mes']) ?>#lancamentos" class="btn btn-icon btn-ghost" title="Ver lançamentos"><i class="bi bi-list-ul"></i></a>
            </div>
            <div class="split mb-3">
                <div><div class="k">Hoje / dia</div><div class="fw-bold fs-5"><?= moeda($d['dia']['total']) ?></div><div class="small text-muted"><?= $d['dia']['qtd'] ?> atendimento(s)</div></div>
                <div><div class="k">No mês</div><div class="fw-bold fs-5"><?= moeda($d['mes']['total']) ?></div><div class="small text-muted"><?= $d['mes']['qtd'] ?> atendimento(s)</div></div>
            </div>
            <div class="d-flex justify-content-between small text-muted mb-1"><span>Semana: <strong class="text-body"><?= moeda($d['sem']['total']) ?></strong></span><span><?= number_format($part, 1, ',', '.') ?>% da clínica</span></div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= round($part) ?>%;background:<?= e($d['cor']) ?>"></div></div>
            <div class="small text-muted mt-2"><i class="bi bi-person-x"></i> <?= $d['faltas_mes'] ?> falta(s) no mês · ticket <?= moeda($d['mes']['qtd'] ? $d['mes']['total'] / $d['mes']['qtd'] : 0) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card-soft panel h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h3 class="panel-title">Faturamento diário · <?= nome_mes(substr($mes, 5)) ?>/<?= substr($mes, 0, 4) ?></h3>
                <div class="d-flex flex-wrap gap-3 small">
                    <?php foreach ($porDentista as $d): ?><span><span class="legend-dot me-1" style="background:<?= e($d['cor']) ?>"></span><?= e(primeiro_nome($d['nome'])) ?></span><?php endforeach; ?>
                </div>
            </div>
            <div class="chart-box"><canvas id="grafico" aria-label="Gráfico de faturamento diário por dentista"></canvas></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card-soft panel mb-4">
            <h3 class="panel-title mb-3">Formas de pagamento</h3>
            <?php if (!$formas): ?><p class="small text-muted mb-0">Sem lançamentos no mês.</p><?php endif; ?>
            <?php foreach ($formas as $f): ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1"><span><?= e($f['forma_pagamento']) ?> <span class="text-muted">(<?= $f['qtd'] ?>)</span></span><strong><?= moeda($f['total']) ?></strong></div>
                    <div class="bar-track"><div class="bar-fill" style="width:<?= round($f['total'] / $totalFormas * 100) ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card-soft panel">
            <h3 class="panel-title mb-3">Top procedimentos do mês</h3>
            <?php foreach (array_slice($procs, 0, 5) as $i => $p): ?>
                <div class="d-flex justify-content-between small py-2 <?= $i ? 'border-top' : '' ?>"><span><?= e($p['nome']) ?> <span class="text-muted">×<?= $p['qtd'] ?></span></span><strong><?= moeda($p['total']) ?></strong></div>
            <?php endforeach; ?>
            <?php if (!$procs): ?><p class="small text-muted mb-0">Sem lançamentos no mês.</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- Lançamentos -->
<div class="card-soft" id="lancamentos">
    <div class="p-3 d-flex flex-wrap gap-2 align-items-center border-bottom">
        <h3 class="panel-title me-auto">Lançamentos <?= $verLanc === 'mes' ? 'do mês' : 'do dia ' . data_br($dia) ?><?= $filtroDent ? ' · ' . e(array_values(array_filter($porDentista, fn($x) => (int) $x['id_dentista'] === $filtroDent))[0]['nome'] ?? '') : '' ?></h3>
        <a href="?<?= http_build_query(['dia' => $dia, 'mes' => $mes, 'lanc' => 'dia', 'dentista' => $filtroDent]) ?>#lancamentos" class="btn btn-sm <?= $verLanc === 'dia' ? 'btn-navy' : 'btn-ghost' ?>">Dia</a>
        <a href="?<?= http_build_query(['dia' => $dia, 'mes' => $mes, 'lanc' => 'mes', 'dentista' => $filtroDent]) ?>#lancamentos" class="btn btn-sm <?= $verLanc === 'mes' ? 'btn-navy' : 'btn-ghost' ?>">Mês</a>
        <?php if ($filtroDent): ?><a href="?<?= http_build_query(['dia' => $dia, 'mes' => $mes, 'lanc' => $verLanc]) ?>#lancamentos" class="btn btn-sm btn-soft">Todos os dentistas</a><?php endif; ?>
    </div>
    <div>
        <table class="table table-clean table-stack">
            <thead><tr><th>Data</th><th>Paciente</th><th>Procedimento</th><th>Dentista</th><th>Pagamento</th><th class="text-end">Valor</th></tr></thead>
            <tbody>
            <?php foreach ($lancamentos as $l): ?>
                <tr>
                    <td data-label="Data" class="text-nowrap"><span><?= data_br($l['data']) ?> <span class="text-muted small"><?= hm($l['hora_inicio']) ?></span></span></td>
                    <td data-label="Paciente"><?= e($l['paciente']) ?></td>
                    <td data-label="Procedimento"><?= e($l['procedimento']) ?></td>
                    <td data-label="Dentista" class="text-nowrap"><?= e($l['dentista']) ?></td>
                    <td data-label="Pagamento"><span class="tag tag-navy"><?= e($l['forma_pagamento']) ?></span></td>
                    <td data-label="Valor" class="text-end fw-bold text-nowrap"><?= moeda($l['valor']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$lancamentos): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhum lançamento no período.</td></tr><?php endif; ?>
            </tbody>
            <?php if ($lancamentos): ?>
            <tfoot><tr><td colspan="5" class="text-end fw-bold d-none d-md-table-cell">Total</td><td data-label="Total" class="text-end fw-bold text-nowrap"><?= moeda(array_sum(array_column($lancamentos, 'valor'))) ?></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php
$scripts_extra = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
if (window.Chart) new Chart(document.getElementById("grafico"), {
  type: "bar",
  data: { labels: ' . js($labels) . ', datasets: ' . js($datasets) . ' },
  options: {
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.dataset.label + ": " + c.parsed.y.toLocaleString("pt-BR", {style:"currency", currency:"BRL"}) } } },
    scales: {
      x: { stacked: true, grid: { display: false }, ticks: { color: "#5d6e80", font: { size: 11 } } },
      y: { stacked: true, border: { display: false }, grid: { color: "#eef3f5" }, ticks: { color: "#5d6e80", callback: v => "R$ " + v.toLocaleString("pt-BR") } }
    }
  }
});
</script>';
include __DIR__ . '/include/admin_fim.php';

<?php
// Área do DENTISTA: financeiro completo do próprio dentista (só os seus atendimentos).
// Saiu da Visão geral para uma página própria no menu lateral.
require_once __DIR__ . '/include/check_dentista.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/relatorio_financeiro.php';

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) $mes = date('Y-m');
$mesIni = $mes . '-01';
$mesFim = date('Y-m-t', strtotime($mesIni));
$hoje = date('Y-m-d');
$semIni = date('Y-m-d', strtotime('monday this week'));
$semFim = date('Y-m-d', strtotime('sunday this week'));
$mesAtual = $mes === date('Y-m');

$rel = new RelatorioFinanceiro($pdo);
$kDia = $rel->previsto($ID_DENTISTA, $hoje, $hoje);
$kSem = $rel->previsto($ID_DENTISTA, $semIni, $semFim);
$kMes = $rel->previsto($ID_DENTISTA, $mesIni, $mesFim);
$ticket = $kMes['qtd_recebido'] ? $kMes['recebido'] / $kMes['qtd_recebido'] : 0;

// mês anterior para comparação
$antIni = date('Y-m-01', strtotime("$mesIni -1 month"));
$antFim = date('Y-m-t', strtotime($antIni));
$recAnt = $rel->total($antIni, $antFim, $ID_DENTISTA);
$variacao = $recAnt['total'] > 0 ? ($kMes['recebido'] - $recAnt['total']) / $recAnt['total'] * 100 : null;

// acumulado do ano até o mês escolhido
$anoSel = (int) substr($mes, 0, 4);
$recAno = $rel->total("$anoSel-01-01", $mesFim, $ID_DENTISTA);

// últimos 12 meses (só deste dentista)
$ini12 = date('Y-m-01', strtotime("$mesIni -11 months"));
$serie = $rel->serieMensal($ini12, $mesFim);
$mesesCurto = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$labels12 = []; $valores12 = [];
for ($i = 0; $i < 12; $i++) {
    $m = date('Y-m', strtotime("$ini12 +$i months"));
    $labels12[] = $mesesCurto[(int) substr($m, 5) - 1] . '/' . substr($m, 2, 2);
    $valores12[] = round((float) ($serie[$m][$ID_DENTISTA] ?? 0), 2);
}
$total12 = array_sum($valores12);

// por procedimento e por forma de pagamento (mês escolhido, só deste dentista)
$st = $pdo->prepare("SELECT pr.nome, SUM(f.valor) AS total, COUNT(*) AS qtd FROM registro_financeiro f
                     JOIN consulta c ON c.id_consulta = f.id_consulta AND c.status = 'REALIZADA'
                     JOIN procedimento pr ON pr.id_procedimento = c.id_procedimento
                     WHERE f.id_dentista = ? AND f.data BETWEEN ? AND ? GROUP BY pr.nome ORDER BY total DESC");
$st->execute([$ID_DENTISTA, $mesIni, $mesFim]);
$procs = $st->fetchAll();
$st = $pdo->prepare("SELECT f.forma_pagamento, SUM(f.valor) AS total, COUNT(*) AS qtd FROM registro_financeiro f
                     JOIN consulta c ON c.id_consulta = f.id_consulta AND c.status = 'REALIZADA'
                     WHERE f.id_dentista = ? AND f.data BETWEEN ? AND ? GROUP BY f.forma_pagamento ORDER BY total DESC");
$st->execute([$ID_DENTISTA, $mesIni, $mesFim]);
$formas = $st->fetchAll();
$totalFormas = max(0.01, array_sum(array_column($formas, 'total')));
$totalProcs = max(0.01, array_sum(array_column($procs, 'total')));

// a receber: consultas agendadas/confirmadas do mês, de hoje em diante (valor da tabela)
$st = $pdo->prepare("SELECT c.data, c.hora_inicio, c.status, p.nome AS paciente, pr.nome AS procedimento, COALESCE(dp.valor, pr.valor) AS valor
                     FROM consulta c JOIN paciente p ON p.id_paciente = c.id_paciente
                     JOIN procedimento pr ON pr.id_procedimento = c.id_procedimento
                     LEFT JOIN dentista_procedimento dp ON dp.id_dentista = c.id_dentista AND dp.id_procedimento = c.id_procedimento
                     WHERE c.id_dentista = ? AND c.data BETWEEN ? AND ? AND c.data >= ? AND c.status IN ('AGENDADA','CONFIRMADA')
                     ORDER BY c.data, c.hora_inicio");
$st->execute([$ID_DENTISTA, $mesIni, $mesFim, $hoje]);
$aReceber = $st->fetchAll();

// atendimentos recebidos no mês
$lancamentos = $rel->lancamentos($mesIni, $mesFim, $ID_DENTISTA);

$st = $pdo->prepare("SELECT cor FROM dentista WHERE id_dentista = ?");
$st->execute([$ID_DENTISTA]);
$cor = $st->fetchColumn() ?: '#12a3a0';
$pct = fn($k) => $k['previsto'] > 0 ? round($k['recebido'] / $k['previsto'] * 100) : 0;

$titulo = 'Meu financeiro';
$pagina = 'dent_financeiro';
$acoes_topo = '<button onclick="window.print()" class="btn btn-ghost btn-sm d-none d-md-inline-flex"><i class="bi bi-printer me-1" aria-hidden="true"></i> Imprimir</button>';
include __DIR__ . '/include/admin_topo.php';
?>

<form method="GET" class="card-soft p-3 mb-4 d-flex flex-wrap gap-3 align-items-end">
    <div><label class="form-label small" for="fMes">Mês</label><input type="month" id="fMes" name="mes" value="<?= e($mes) ?>" class="form-control"></div>
    <button class="btn btn-navy"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i> Atualizar</button>
    <?php if (!$mesAtual): ?><a href="dent_financeiro.php" class="btn btn-ghost">Mês atual</a><?php endif; ?>
    <span class="small text-muted ms-lg-auto">Mostrando <strong><?= nome_mes(substr($mes, 5)) ?>/<?= $anoSel ?></strong> · só os seus atendimentos</span>
</form>

<!-- Resumo -->
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card-soft kpi kpi-hero">
            <div class="k"><i class="bi bi-calendar-day me-1" aria-hidden="true"></i>Hoje · previsto</div>
            <div class="v"><?= moeda($kDia['previsto']) ?></div>
            <div class="sub">Recebido <?= moeda($kDia['recebido']) ?> · <?= $kDia['qtd_pendente'] ?> a atender</div>
            <div class="meter" role="img" aria-label="<?= $pct($kDia) ?>% já recebido"><span style="width:<?= $pct($kDia) ?>%"></span></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft kpi">
            <div class="k"><i class="bi bi-calendar-week me-1" aria-hidden="true"></i>Semana · previsto</div>
            <div class="v"><?= moeda($kSem['previsto']) ?></div>
            <div class="sub">Recebido <?= moeda($kSem['recebido']) ?> · <?= date('d/m', strtotime($semIni)) ?> a <?= date('d/m', strtotime($semFim)) ?></div>
            <div class="meter" role="img" aria-label="<?= $pct($kSem) ?>% já recebido"><span style="width:<?= $pct($kSem) ?>%"></span></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft kpi">
            <div class="k"><i class="bi bi-calendar-month me-1" aria-hidden="true"></i><?= nome_mes(substr($mes, 5)) ?> · previsto</div>
            <div class="v"><?= moeda($kMes['previsto']) ?></div>
            <div class="sub">Recebido <?= moeda($kMes['recebido']) ?> · <?= $kMes['qtd_recebido'] ?> atendimentos
                <?php if ($variacao !== null): ?><br><i class="bi bi-arrow-<?= $variacao >= 0 ? 'up' : 'down' ?>-right" aria-hidden="true"></i> <?= number_format(abs($variacao), 1, ',', '.') ?>% <?= $variacao >= 0 ? 'acima' : 'abaixo' ?> do mês anterior<?= $mesAtual ? ' (mês em andamento)' : '' ?><?php endif; ?></div>
            <div class="meter" role="img" aria-label="<?= $pct($kMes) ?>% já recebido"><span style="width:<?= $pct($kMes) ?>%"></span></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft kpi">
            <div class="k"><i class="bi bi-receipt me-1" aria-hidden="true"></i>Ticket médio</div>
            <div class="v"><?= moeda($ticket) ?></div>
            <div class="sub">Por atendimento em <?= mb_strtolower(nome_mes(substr($mes, 5))) ?><br>Acumulado de <?= $anoSel ?>: <strong><?= moeda($recAno['total']) ?></strong></div>
        </div>
    </div>
</div>
<p class="small text-muted mb-4"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><strong>Previsto</strong> = valores já recebidos + consultas agendadas/confirmadas (pelo valor da tabela). O valor final é lançado pela secretaria no atendimento. Faltas e cancelamentos não entram.</p>

<!-- Gráfico 12 meses -->
<div class="card-soft panel mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div><h2 class="panel-title mb-1">Meu faturamento mês a mês</h2><p class="small text-muted mb-0">Últimos 12 meses até <?= mb_strtolower(nome_mes(substr($mes, 5))) ?>/<?= $anoSel ?> · total <?= moeda($total12) ?></p></div>
    </div>
    <div class="chart-box" style="height:300px"><canvas id="gMeus" role="img" aria-label="Gráfico de barras: seu faturamento em cada um dos últimos 12 meses"></canvas></div>
</div>

<div class="row g-4 mb-4">
    <!-- Por procedimento -->
    <div class="col-xl-7">
        <div class="card-soft panel h-100">
            <h2 class="panel-title mb-3">Por procedimento · <?= mb_strtolower(nome_mes(substr($mes, 5))) ?></h2>
            <?php if (!$procs): ?><p class="small text-muted mb-0">Sem atendimentos recebidos no mês.</p><?php endif; ?>
            <?php foreach ($procs as $p): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1"><span><?= e($p['nome']) ?> <span class="text-muted">×<?= (int) $p['qtd'] ?></span></span><strong><?= moeda($p['total']) ?> <span class="text-muted fw-normal">(<?= number_format($p['total'] / $totalProcs * 100, 0, ',', '.') ?>%)</span></strong></div>
                    <div class="bar-track"><div class="bar-fill" style="width:<?= round($p['total'] / $totalProcs * 100) ?>%;background:<?= e($cor) ?>"></div></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- Formas de pagamento -->
    <div class="col-xl-5">
        <div class="card-soft panel h-100">
            <h2 class="panel-title mb-3">Formas de pagamento</h2>
            <?php if (!$formas): ?><p class="small text-muted mb-0">Sem atendimentos recebidos no mês.</p><?php endif; ?>
            <?php foreach ($formas as $f): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1"><span><?= e($f['forma_pagamento']) ?> <span class="text-muted">(<?= (int) $f['qtd'] ?>)</span></span><strong><?= moeda($f['total']) ?></strong></div>
                    <div class="bar-track"><div class="bar-fill" style="width:<?= round($f['total'] / $totalFormas * 100) ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- A receber -->
<div class="card-soft mb-4">
    <div class="p-3 d-flex flex-wrap gap-2 align-items-center border-bottom">
        <h2 class="panel-title me-auto mb-0">A receber em <?= mb_strtolower(nome_mes(substr($mes, 5))) ?></h2>
        <span class="tag"><?= count($aReceber) ?> consulta(s) · <?= moeda(array_sum(array_column($aReceber, 'valor'))) ?></span>
    </div>
    <table class="table table-clean table-stack mb-0">
        <thead><tr><th>Data</th><th>Paciente</th><th>Procedimento</th><th>Situação</th><th class="text-end">Valor da tabela</th></tr></thead>
        <tbody>
        <?php foreach ($aReceber as $r): [$rot, $cls] = $STATUS_CONSULTA[$r['status']]; ?>
            <tr>
                <td data-label="Data" class="text-nowrap"><span><?= data_br($r['data']) ?> <span class="text-muted small"><?= hm($r['hora_inicio']) ?></span></span></td>
                <td data-label="Paciente"><?= e($r['paciente']) ?></td>
                <td data-label="Procedimento"><?= e($r['procedimento']) ?></td>
                <td data-label="Situação"><span class="status <?= $cls ?>"><?= $rot ?></span></td>
                <td data-label="Valor" class="text-end fw-bold text-nowrap"><?= moeda($r['valor']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$aReceber): ?><tr><td colspan="5" class="text-center text-muted py-4">Nenhuma consulta a receber neste mês<?= $mesIni < date('Y-m-01') ? ' (mês já passou)' : '' ?>.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Recebidos -->
<div class="card-soft" id="lancamentos">
    <div class="p-3 d-flex flex-wrap gap-2 align-items-center border-bottom">
        <h2 class="panel-title me-auto mb-0">Atendimentos recebidos · <?= mb_strtolower(nome_mes(substr($mes, 5))) ?></h2>
        <span class="tag tag-navy"><?= count($lancamentos) ?> atendimento(s)</span>
    </div>
    <table class="table table-clean table-stack mb-0">
        <thead><tr><th>Data</th><th>Paciente</th><th>Procedimento</th><th>Pagamento</th><th class="text-end">Valor</th></tr></thead>
        <tbody>
        <?php foreach ($lancamentos as $l): ?>
            <tr>
                <td data-label="Data" class="text-nowrap"><span><?= data_br($l['data']) ?> <span class="text-muted small"><?= hm($l['hora_inicio']) ?></span></span></td>
                <td data-label="Paciente"><?= e($l['paciente']) ?></td>
                <td data-label="Procedimento"><?= e($l['procedimento']) ?></td>
                <td data-label="Pagamento"><span class="tag tag-navy"><?= e($l['forma_pagamento']) ?></span></td>
                <td data-label="Valor" class="text-end fw-bold text-nowrap"><?= moeda($l['valor']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lancamentos): ?><tr><td colspan="5" class="text-center text-muted py-4">Nenhum atendimento recebido no mês.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($lancamentos): ?>
        <tfoot><tr><td colspan="4" class="text-end fw-bold d-none d-md-table-cell">Total</td><td data-label="Total" class="text-end fw-bold text-nowrap"><?= moeda(array_sum(array_column($lancamentos, 'valor'))) ?></td></tr></tfoot>
        <?php endif; ?>
    </table>
</div>

<?php
$scripts_extra = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  if (!window.Chart) return;
  const brl = v => (v || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  const contraste = document.documentElement.classList.contains("alto-contraste");
  new Chart(document.getElementById("gMeus"), {
    type: "bar",
    data: { labels: ' . js($labels12) . ', datasets: [{ label: "Recebido", data: ' . js($valores12) . ', backgroundColor: contraste ? "#ffe14d" : ' . js($cor) . ', borderRadius: 4, maxBarThickness: 46 }] },
    options: { maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => " " + brl(c.parsed.y) } } },
      scales: { x: { grid: { display: false }, ticks: { color: contraste ? "#fff" : "#5d6e80" } },
                y: { beginAtZero: true, border: { display: false }, grid: { color: contraste ? "#333" : "#eef3f5" },
                     ticks: { color: contraste ? "#fff" : "#5d6e80", callback: v => "R$ " + (v >= 1000 ? (v / 1000).toLocaleString("pt-BR") + " mil" : v) } } } }
  });
})();
</script>';
include __DIR__ . '/include/admin_fim.php';

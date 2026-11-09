<?php
// Área do DENTISTA: visão geral limpa com faturamento do dia, semana e mês (previsto) e pacientes do dia
require_once __DIR__ . '/include/check_dentista.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/agendamento.php';
require_once __DIR__ . '/classes/relatorio_financeiro.php';

$hoje = date('Y-m-d');
$semIni = date('Y-m-d', strtotime('monday this week'));
$semFim = date('Y-m-d', strtotime('sunday this week'));
$mesIni = date('Y-m-01'); $mesFim = date('Y-m-t');

$rel = new RelatorioFinanceiro($pdo);
$kDia = $rel->previsto($ID_DENTISTA, $hoje, $hoje);
$kSem = $rel->previsto($ID_DENTISTA, $semIni, $semFim);
$kMes = $rel->previsto($ID_DENTISTA, $mesIni, $mesFim);

$ag = new Agendamento($pdo);
$hojeLista = array_values(array_filter($ag->buscar('', $hoje, (string) $ID_DENTISTA), fn($c) => $c['status'] !== 'CANCELADA'));
$proximos = array_values(array_filter($ag->buscar('', date('Y-m-d', strtotime('+1 day')), (string) $ID_DENTISTA, '', date('Y-m-d', strtotime('+7 days'))), fn($c) => in_array($c['status'], ['AGENDADA', 'CONFIRMADA'])));

$st = $pdo->prepare("SELECT COUNT(DISTINCT id_paciente) FROM consulta WHERE id_dentista = ?");
$st->execute([$ID_DENTISTA]);
$totalPac = (int) $st->fetchColumn();
$st = $pdo->prepare("SELECT * FROM dentista WHERE id_dentista = ?");
$st->execute([$ID_DENTISTA]);
$eu = $st->fetch();

$pct = fn($k) => $k['previsto'] > 0 ? round($k['recebido'] / $k['previsto'] * 100) : 0;
$diasPt = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

$titulo = 'Olá, ' . ($eu ? preg_replace('/\s.*$/', '', $eu['nome']) . ' ' . primeiro_nome($eu['nome']) : 'Dentista');
$pagina = 'dent_painel';
$acoes_topo = '<a href="agenda_semana.php" class="btn btn-ghost btn-sm"><i class="bi bi-calendar-week me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Agenda</span></a>';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="row g-3 mb-4">
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
            <div class="k"><i class="bi bi-calendar-month me-1" aria-hidden="true"></i><?= nome_mes(date('n')) ?> · previsto</div>
            <div class="v"><?= moeda($kMes['previsto']) ?></div>
            <div class="sub">Recebido <?= moeda($kMes['recebido']) ?> · <?= $kMes['qtd_recebido'] ?> atendimentos</div>
            <div class="meter" role="img" aria-label="<?= $pct($kMes) ?>% já recebido"><span style="width:<?= $pct($kMes) ?>%"></span></div>
        </div>
    </div>
    <div class="col-12 col-xl-3">
        <a href="dent_pacientes.php" class="card-soft kpi d-block text-reset card-hover">
            <div class="k"><i class="bi bi-people me-1" aria-hidden="true"></i>Meus pacientes</div>
            <div class="v"><?= $totalPac ?></div>
            <div class="sub">Fotos, exames e observações <i class="bi bi-arrow-right" aria-hidden="true"></i></div>
        </a>
    </div>
</div>
<p class="small text-muted mb-4"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><strong>Previsto</strong> = valores já recebidos + consultas agendadas/confirmadas (pelo valor da tabela). O valor final é lançado pela secretaria no atendimento.</p>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card-soft panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="panel-title">Pacientes de hoje <span class="text-muted fw-normal small">· <?= data_br($hoje) ?></span></h2>
                <span class="tag"><?= count($hojeLista) ?> consulta(s)</span>
            </div>
            <div class="pac-list">
            <?php foreach ($hojeLista as $c): [$rot, $cls] = $STATUS_CONSULTA[$c['status']]; ?>
                <a href="dent_paciente.php?id=<?= (int) $c['id_paciente'] ?>" class="pac-item">
                    <div class="text-center" style="width:58px"><div class="fw-bold text-navy"><?= hm($c['hora_inicio']) ?></div><div class="small text-muted"><?= hm($c['hora_fim']) ?></div></div>
                    <div class="min-w-0">
                        <div class="nm text-truncate d-flex align-items-center gap-2"><?= avatar_paciente($c, 'xs', 'paciente') ?><span class="text-truncate"><?= e($c['paciente']) ?></span> <?php if ($c['primeira_consulta']): ?><span class="tag tag-navy ms-1"><i class="bi bi-stars" aria-hidden="true"></i> 1ª consulta</span><?php endif; ?></div>
                        <div class="meta text-truncate"><?= e($c['procedimento']) ?><?= $c['data_nascimento'] ? ' · ' . idade($c['data_nascimento']) . ' anos' : '' ?></div>
                    </div>
                    <span class="status <?= $cls ?>"><?= $rot ?></span>
                </a>
            <?php endforeach; ?>
            <?php if (!$hojeLista): ?><div class="empty-state py-4"><i class="bi bi-cup-hot" aria-hidden="true"></i><p class="mt-2 mb-0">Nenhum paciente agendado para hoje.</p></div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card-soft panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="panel-title">Próximos 7 dias</h2>
                <a href="agenda_semana.php" class="small fw-semibold">Ver agenda <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
            <?php foreach (array_slice($proximos, 0, 8) as $c): ?>
                <a href="dent_paciente.php?id=<?= (int) $c['id_paciente'] ?>" class="d-flex align-items-center gap-3 py-2 border-bottom text-reset">
                    <div class="text-center" style="width:44px"><div class="fw-bold text-navy lh-1"><?= date('d', strtotime($c['data'])) ?></div><div class="small text-muted"><?= $diasPt[(int) date('w', strtotime($c['data']))] ?></div></div>
                    <?= avatar_paciente($c, 'xs', 'paciente') ?>
                    <div class="small flex-grow-1 lh-sm min-w-0"><strong class="d-block text-truncate"><?= e($c['paciente']) ?></strong><span class="text-muted"><?= hm($c['hora_inicio']) ?> · <?= e($c['procedimento']) ?></span></div>
                    <span class="small fw-semibold text-nowrap"><?= moeda($c['valor_tabela']) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if (!$proximos): ?><p class="small text-muted mb-0">Nada agendado para os próximos dias.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

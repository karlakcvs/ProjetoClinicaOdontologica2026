<?php
// Área do DENTISTA: visão geral focada no dia (agenda de hoje primeiro).
// O financeiro do dentista fica em uma página própria: dent_financeiro.php (menu lateral).
require_once __DIR__ . '/include/check_dentista.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/agendamento.php';

$hoje = date('Y-m-d');
$agora = date('H:i:s');

$ag = new Agendamento($pdo);
$hojeLista = array_values(array_filter($ag->buscar('', $hoje, (string) $ID_DENTISTA), fn($c) => $c['status'] !== 'CANCELADA'));
$proximos = array_values(array_filter($ag->buscar('', date('Y-m-d', strtotime('+1 day')), (string) $ID_DENTISTA, '', date('Y-m-d', strtotime('+7 days'))), fn($c) => in_array($c['status'], ['AGENDADA', 'CONFIRMADA'])));

// resumo do dia (sem valores: o dinheiro fica no Financeiro)
$qtdRealizadas = count(array_filter($hojeLista, fn($c) => $c['status'] === 'REALIZADA'));
$qtdAtender = count(array_filter($hojeLista, fn($c) => in_array($c['status'], ['AGENDADA', 'CONFIRMADA'])));
$qtdFaltas = count(array_filter($hojeLista, fn($c) => $c['status'] === 'FALTOU'));
// próximo paciente = primeira consulta ainda a atender que não terminou
$idProximo = null;
foreach ($hojeLista as $c) {
    if (in_array($c['status'], ['AGENDADA', 'CONFIRMADA']) && $c['hora_fim'] >= $agora) { $idProximo = (int) $c['id_consulta']; break; }
}

$st = $pdo->prepare("SELECT COUNT(DISTINCT id_paciente) FROM consulta WHERE id_dentista = ?");
$st->execute([$ID_DENTISTA]);
$totalPac = (int) $st->fetchColumn();
$st = $pdo->prepare("SELECT * FROM dentista WHERE id_dentista = ?");
$st->execute([$ID_DENTISTA]);
$eu = $st->fetch();

$diasPt = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

$titulo = 'Olá, ' . ($eu ? preg_replace('/\s.*$/', '', $eu['nome']) . ' ' . primeiro_nome($eu['nome']) : 'Dentista');
$pagina = 'dent_painel';
$acoes_topo = '<a href="dent_atestado.php" class="btn btn-ghost btn-sm"><i class="bi bi-file-earmark-medical me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Atestado</span></a>'
            . '<a href="agenda_semana.php" class="btn btn-ghost btn-sm"><i class="bi bi-calendar-week me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Agenda</span></a>';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="row g-4">
    <!-- AGENDA DE HOJE (primeira coisa que o dentista vê) -->
    <div class="col-xl-8">
        <div class="card-soft panel">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="panel-title mb-0">Agenda de hoje <span class="text-muted fw-normal small">· <?= data_br($hoje) ?></span></h2>
                <div class="d-flex flex-wrap gap-2">
                    <span class="tag"><?= count($hojeLista) ?> consulta(s)</span>
                    <span class="tag tag-navy"><?= $qtdAtender ?> a atender</span>
                    <?php if ($qtdRealizadas): ?><span class="tag"><i class="bi bi-patch-check" aria-hidden="true"></i> <?= $qtdRealizadas ?> realizada(s)</span><?php endif; ?>
                    <?php if ($qtdFaltas): ?><span class="tag"><i class="bi bi-person-x" aria-hidden="true"></i> <?= $qtdFaltas ?> falta(s)</span><?php endif; ?>
                </div>
            </div>
            <div class="pac-list">
            <?php foreach ($hojeLista as $c): [$rot, $cls] = $STATUS_CONSULTA[$c['status']]; $ehProx = (int) $c['id_consulta'] === $idProximo; ?>
                <div class="pac-item pac-item-acoes <?= $ehProx ? 'pac-proximo' : '' ?>">
                    <div class="text-center" style="width:58px"><div class="fw-bold text-navy"><?= hm($c['hora_inicio']) ?></div><div class="small text-muted"><?= hm($c['hora_fim']) ?></div></div>
                    <a href="dent_paciente.php?id=<?= (int) $c['id_paciente'] ?>" class="min-w-0 text-reset" title="Abrir prontuário">
                        <div class="nm text-truncate d-flex align-items-center gap-2"><?= avatar_paciente($c, 'xs', 'paciente') ?><span class="text-truncate"><?= e($c['paciente']) ?></span>
                            <?php if ($ehProx): ?><span class="tag tag-proximo ms-1"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i> Próximo</span><?php endif; ?>
                            <?php if ($c['primeira_consulta']): ?><span class="tag tag-navy ms-1"><i class="bi bi-stars" aria-hidden="true"></i> 1ª consulta</span><?php endif; ?></div>
                        <div class="meta text-truncate"><?= e($c['procedimento']) ?><?= $c['data_nascimento'] ? ' · ' . idade($c['data_nascimento']) . ' anos' : '' ?></div>
                    </a>
                    <div class="d-flex align-items-center gap-2 justify-content-end">
                        <span class="status <?= $cls ?>"><?= $rot ?></span>
                        <a href="dent_paciente.php?id=<?= (int) $c['id_paciente'] ?>" class="btn btn-icon btn-ghost btn-sm" title="Prontuário" aria-label="Prontuário de <?= e($c['paciente']) ?>"><i class="bi bi-folder2-open" aria-hidden="true"></i></a>
                        <a href="dent_atestado.php?consulta=<?= (int) $c['id_consulta'] ?>" class="btn btn-icon btn-ghost btn-sm" title="Emitir atestado" aria-label="Emitir atestado para <?= e($c['paciente']) ?>"><i class="bi bi-file-earmark-medical" aria-hidden="true"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$hojeLista): ?><div class="empty-state py-4"><i class="bi bi-cup-hot" aria-hidden="true"></i><p class="mt-2 mb-0">Nenhum paciente agendado para hoje.</p></div><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- COLUNA DA DIREITA: atalhos + próximos dias -->
    <div class="col-xl-4">
        <div class="atalhos mb-4">
            <a href="dent_pacientes.php" class="card-soft atalho card-hover">
                <span class="ico"><i class="bi bi-people" aria-hidden="true"></i></span>
                <span><strong><?= $totalPac ?></strong> Meus pacientes</span>
            </a>
            <a href="dent_atestado.php" class="card-soft atalho card-hover">
                <span class="ico"><i class="bi bi-file-earmark-medical" aria-hidden="true"></i></span>
                <span><strong>Atestado</strong> Emitir agora</span>
            </a>
            <a href="agenda_semana.php" class="card-soft atalho card-hover">
                <span class="ico"><i class="bi bi-calendar-week" aria-hidden="true"></i></span>
                <span><strong>Agenda</strong> Semana completa</span>
            </a>
            <a href="dent_financeiro.php" class="card-soft atalho card-hover">
                <span class="ico"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
                <span><strong>Financeiro</strong> Detalhes e gráficos</span>
            </a>
        </div>

        <div class="card-soft panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="panel-title mb-0">Próximos 7 dias</h2>
                <a href="agenda_semana.php" class="small fw-semibold">Ver agenda <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
            <?php foreach (array_slice($proximos, 0, 8) as $c): ?>
                <a href="dent_paciente.php?id=<?= (int) $c['id_paciente'] ?>" class="d-flex align-items-center gap-3 py-2 border-bottom text-reset">
                    <div class="text-center" style="width:44px"><div class="fw-bold text-navy lh-1"><?= date('d', strtotime($c['data'])) ?></div><div class="small text-muted"><?= $diasPt[(int) date('w', strtotime($c['data']))] ?></div></div>
                    <?= avatar_paciente($c, 'xs', 'paciente') ?>
                    <div class="small flex-grow-1 lh-sm min-w-0"><strong class="d-block text-truncate"><?= e($c['paciente']) ?></strong><span class="text-muted"><?= hm($c['hora_inicio']) ?> · <?= e($c['procedimento']) ?></span></div>
                </a>
            <?php endforeach; ?>
            <?php if (count($proximos) > 8): ?><p class="small text-muted mt-2 mb-0">+ <?= count($proximos) - 8 ?> consulta(s) na agenda.</p><?php endif; ?>
            <?php if (!$proximos): ?><p class="small text-muted mb-0">Nada agendado para os próximos dias.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

<?php
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/agendamento.php';
require_once __DIR__ . '/include/card_consulta.php';

// 1. Filtros
$hoje = date('Y-m-d');
$periodo    = $_GET['periodo'] ?? '';
$busca_nome = trim($_GET['busca_nome'] ?? '');
$dentista   = $_GET['dentista'] ?? '';
$status     = $_GET['status'] ?? '';
$ini = $_GET['data'] ?? $hoje;
$fim = $_GET['ate'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini)) $ini = $hoje;
if ($fim !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) $fim = '';

switch ($periodo) {
    case 'amanha':   $ini = date('Y-m-d', strtotime('+1 day')); $fim = $ini; break;
    case 'semana':   $ini = date('Y-m-d', strtotime('monday this week')); $fim = date('Y-m-d', strtotime('friday this week')); break;
    case 'proximos': $ini = $hoje; $fim = date('Y-m-d', strtotime('+30 days')); break;
    case 'mes':      $ini = date('Y-m-01'); $fim = date('Y-m-t'); break;
    case 'hoje':     $ini = $hoje; $fim = ''; break;
}
if ($fim !== '' && $fim < $ini) $fim = $ini;
if ($fim === $ini) $fim = '';

// 2. Busca (classe Agendamento, como no projeto original)
$objAgendamento = new Agendamento($pdo);
$agendamentos = $objAgendamento->buscar($busca_nome, $ini, $dentista, $status, $fim);

// 3. Resumo
$porDia = [];
foreach ($agendamentos as $a) $porDia[$a['data']][] = $a;
$contagem = array_count_values(array_column($agendamentos, 'status'));
$previsto = array_sum(array_map(fn($a) => in_array($a['status'], ['CANCELADA','FALTOU']) ? 0 : (float) ($a['valor_pago'] ?? $a['valor_tabela']), $agendamentos));
$dentistas = $pdo->query("SELECT * FROM dentista ORDER BY id_dentista")->fetchAll();

$voltar = 'lista_agendamentos.php?' . http_build_query(array_filter(['data' => $ini, 'ate' => $fim, 'busca_nome' => $busca_nome, 'dentista' => $dentista, 'status' => $status]));
$diasNome = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
$navAnt = date('Y-m-d', strtotime("$ini -1 day")); $navProx = date('Y-m-d', strtotime("$ini +1 day"));
$q = fn($extra) => 'lista_agendamentos.php?' . http_build_query(array_filter(array_merge(['busca_nome' => $busca_nome, 'dentista' => $dentista, 'status' => $status], $extra)));

$titulo = 'Agenda';
$pagina = 'lista_agendamentos';
$acoes_topo = '<a href="nova_consulta.php" class="btn btn-teal btn-sm"><i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Nova consulta</span></a>';
include __DIR__ . '/include/admin_topo.php';
?>

<!-- Navegação rápida -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <?php if ($fim === ''): ?>
        <a href="<?= $q(['data' => $navAnt]) ?>" class="btn btn-icon btn-ghost" title="Dia anterior"><i class="bi bi-chevron-left"></i></a>
        <div class="fw-bold px-1"><?= $diasNome[(int) date('w', strtotime($ini))] ?>, <?= data_br($ini) ?></div>
        <a href="<?= $q(['data' => $navProx]) ?>" class="btn btn-icon btn-ghost" title="Próximo dia"><i class="bi bi-chevron-right"></i></a>
    <?php else: ?>
        <div class="fw-bold px-1"><?= data_br($ini) ?> a <?= data_br($fim) ?></div>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-1 ms-md-auto">
        <?php foreach (['hoje' => 'Hoje', 'amanha' => 'Amanhã', 'semana' => 'Esta semana', 'proximos' => 'Próximos 30 dias', 'mes' => 'Este mês'] as $k => $v): ?>
            <a href="<?= $q(['periodo' => $k]) ?>" class="btn btn-sm <?= $periodo === $k ? 'btn-navy' : 'btn-ghost' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- FORMULÁRIO DE FILTRO -->
<form method="GET" class="card-soft filter-bar mb-3">
    <div class="span-2"><label class="form-label small">Paciente</label><input type="text" name="busca_nome" class="form-control" placeholder="Buscar por nome..." value="<?= e($busca_nome) ?>"></div>
    <div><label class="form-label small">De</label><input type="date" name="data" class="form-control" value="<?= e($ini) ?>"></div>
    <div><label class="form-label small">Até</label><input type="date" name="ate" class="form-control" value="<?= e($fim) ?>"></div>
    <div><label class="form-label small">Dentista</label>
        <select name="dentista" class="form-select"><option value="">Todos</option>
            <?php foreach ($dentistas as $d): ?><option value="<?= $d['id_dentista'] ?>" <?= (string) $dentista === (string) $d['id_dentista'] ? 'selected' : '' ?>><?= e($d['nome']) ?></option><?php endforeach; ?>
        </select></div>
    <div><label class="form-label small">Status</label>
        <select name="status" class="form-select"><option value="">Todos</option>
            <?php foreach ($STATUS_CONSULTA as $k => $s): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $s[0] ?></option><?php endforeach; ?>
        </select></div>
    <div class="d-flex gap-2 span-2"><button class="btn btn-navy w-100"><i class="bi bi-funnel me-1"></i>Filtrar</button><a href="lista_agendamentos.php" class="btn btn-ghost" title="Limpar"><i class="bi bi-x-lg"></i></a></div>
</form>

<!-- Resumo -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <span class="tag tag-navy"><i class="bi bi-list-check"></i> <?= count($agendamentos) ?> consulta(s)</span>
    <?php foreach ($STATUS_CONSULTA as $k => $s): if (!empty($contagem[$k])): ?>
        <span class="status <?= $s[1] ?>"><i class="bi <?= $s[2] ?>"></i><?= $contagem[$k] ?> <?= strtolower($s[0]) ?></span>
    <?php endif; endforeach; ?>
    <span class="tag ms-md-auto"><i class="bi bi-cash"></i> Previsto/recebido: <?= moeda($previsto) ?></span>
</div>

<?php if ($porDia): ?>
    <?php foreach ($porDia as $dia => $lista): ?>
        <section class="day-group">
            <?php if ($fim !== ''): ?>
            <div class="day-head"><h6><?= $diasNome[(int) date('w', strtotime($dia))] ?>, <?= data_br($dia) ?></h6><span><?= count($lista) ?> consulta(s)</span></div>
            <?php endif; ?>
            <?php foreach ($lista as $c) echo card_consulta($c, $voltar); ?>
        </section>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty-state"><i class="bi bi-calendar2-x"></i><p class="mt-2 mb-3">Nenhuma consulta encontrada para esse filtro.</p><a href="nova_consulta.php" class="btn btn-teal btn-sm">Agendar uma consulta</a></div>
<?php endif; ?>

<?= modal_pagamento($voltar) ?>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

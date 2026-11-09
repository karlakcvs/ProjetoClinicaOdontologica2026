<?php
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/agendamento.php';
require_once __DIR__ . '/classes/relatorio_financeiro.php';
require_once __DIR__ . '/include/card_consulta.php';

$hoje = date('Y-m-d');
$mesIni = date('Y-m-01'); $mesFim = date('Y-m-t');
$ag  = new Agendamento($pdo);
$rel = new RelatorioFinanceiro($pdo);

$consultasHoje = $ag->buscar('', $hoje);
$ativasHoje = array_filter($consultasHoje, fn($c) => $c['status'] !== 'CANCELADA');
$aConfirmar = count(array_filter($ag->buscar('', date('Y-m-d', strtotime('+1 day')), '', 'AGENDADA')));
$finHoje = $rel->total($hoje, $hoje);
$finMes  = $rel->total($mesIni, $mesFim);
$porDentista = $rel->totalPorDentista($hoje, $mesIni, $mesFim);
$maxMes = max(1, max(array_map(fn($d) => $d['mes']['total'], $porDentista)));

$proximas = array_slice($ag->buscar('', date('Y-m-d', strtotime('+1 day')), '', '', date('Y-m-d', strtotime('+7 days'))), 0, 6);
$proximas = array_values(array_filter($proximas, fn($c) => $c['status'] !== 'CANCELADA'));
$st = $pdo->prepare("SELECT COUNT(*) FROM consulta WHERE origem = 'SITE' AND status = 'AGENDADA' AND data >= ?");
$st->execute([$hoje]);
$noSite = (int) $st->fetchColumn();
$incompletos = (int) $pdo->query("SELECT COUNT(*) FROM paciente WHERE cpf IS NULL OR cep IS NULL OR numero IS NULL OR data_nascimento IS NULL")->fetchColumn();
$avalPend = (int) $pdo->query("SELECT COUNT(*) FROM avaliacao WHERE status = 'PENDENTE'")->fetchColumn();
$msgPend = (int) $pdo->query("SELECT COUNT(*) FROM mensagem_contato WHERE lida = 0")->fetchColumn();
// Pacientes de longa data (cadastro completo, com foto de perfil) e a próxima consulta de cada um
$st = $pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM consulta c WHERE c.id_paciente = p.id_paciente AND c.status = 'REALIZADA') AS atend,
                            (SELECT MIN(c.data) FROM consulta c WHERE c.id_paciente = p.id_paciente AND c.data >= ? AND c.status IN ('AGENDADA','CONFIRMADA')) AS proxima
                     FROM paciente p WHERE p.foto IS NOT NULL AND p.cpf IS NOT NULL AND p.cep IS NOT NULL ORDER BY p.criado_em LIMIT 8");
$st->execute([$hoje]);
$fieis = array_values(array_filter($st->fetchAll(), 'cadastro_completo'));

$titulo = 'Visão geral';
$pagina = 'painel';
$acoes_topo = '<a href="nova_consulta.php" class="btn btn-teal btn-sm"><i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Nova consulta</span></a>';
include __DIR__ . '/include/admin_topo.php';
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="card-soft stat stat-hero"><span class="ico"><i class="bi bi-cash-stack"></i></span><div class="val"><?= moeda($finHoje['total']) ?></div><div class="lab">Recebido hoje · <?= $finHoje['qtd'] ?> atend.</div></div></div>
    <div class="col-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-teal"><i class="bi bi-calendar-check"></i></span><div class="val"><?= count($ativasHoje) ?></div><div class="lab">Consultas hoje</div></div></div>
    <div class="col-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-navy"><i class="bi bi-graph-up"></i></span><div class="val"><?= moeda($finMes['total']) ?></div><div class="lab">Faturamento de <?= strtolower(nome_mes(date('n'))) ?></div></div></div>
    <div class="col-6 col-xl-3"><a href="lembretes.php" class="card-soft stat d-block text-reset card-hover"><span class="ico ico-gold"><i class="bi bi-bell"></i></span><div class="val"><?= $aConfirmar ?></div><div class="lab">A confirmar para amanhã</div></a></div>
</div>

<?php if ($noSite): ?>
<div class="alert bg-teal-soft d-flex flex-wrap align-items-center gap-2 mb-3">
    <i class="bi bi-globe2 text-teal fs-5" aria-hidden="true"></i>
    <div class="flex-grow-1"><strong><?= $noSite ?> agendamento(s)</strong> feitos pelos pacientes no site aguardando confirmação.</div>
    <a href="lista_agendamentos.php?status=AGENDADA&amp;periodo=proximos" class="btn btn-sm btn-teal">Ver</a>
</div>
<?php endif; ?>
<?php if ($incompletos || $avalPend || $msgPend): ?>
<div class="d-flex flex-wrap gap-2 mb-4">
    <?php if ($incompletos): ?><a href="pacientes.php?f=incompletos" class="btn btn-sm btn-ghost"><i class="bi bi-person-vcard me-1" aria-hidden="true"></i><?= $incompletos ?> cadastro(s) para completar (CPF/CEP)</a><?php endif; ?>
    <?php if ($avalPend): ?><a href="avaliacoes.php" class="btn btn-sm btn-ghost"><i class="bi bi-star me-1" aria-hidden="true"></i><?= $avalPend ?> avaliação(ões) para aprovar</a><?php endif; ?>
    <?php if ($msgPend): ?><a href="mensagens.php" class="btn btn-sm btn-ghost"><i class="bi bi-envelope me-1" aria-hidden="true"></i><?= $msgPend ?> mensagem(ns) nova(s)</a><?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card-soft panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="panel-title">Agenda de hoje <span class="text-muted fw-normal small">· <?= data_br($hoje) ?></span></h2>
                <a href="agenda_semana.php" class="small fw-semibold">Agenda da semana <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
            <?php if ($consultasHoje): ?>
                <?php foreach ($consultasHoje as $c) echo card_consulta($c, 'painel.php'); ?>
            <?php else: ?>
                <div class="empty-state py-4"><i class="bi bi-cup-hot"></i><p class="mt-2 mb-0">Nenhuma consulta para hoje.</p></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card-soft panel mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="panel-title">Por dentista</h2>
                <a href="financeiro.php" class="small fw-semibold">Financeiro <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php foreach ($porDentista as $d): ?>
                <div class="mb-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="avatar avatar-xs" style="--c:<?= e($d['cor']) ?>"><?= e(iniciais($d['nome'])) ?></span>
                        <div class="small flex-grow-1 lh-sm"><strong><?= e($d['nome']) ?></strong><br><span class="text-muted">Hoje <?= moeda($d['dia']['total']) ?> · <?= $d['dia']['qtd'] ?> atend.</span></div>
                        <strong class="small"><?= moeda($d['mes']['total']) ?></strong>
                    </div>
                    <div class="bar-track"><div class="bar-fill" style="width:<?= round($d['mes']['total'] / $maxMes * 100) ?>%;background:<?= e($d['cor']) ?>"></div></div>
                </div>
            <?php endforeach; ?>
            <p class="small text-muted mb-0">Barra = faturamento do mês.</p>
        </div>
        <div class="card-soft panel">
            <h2 class="panel-title mb-3">Próximos dias</h2>
            <?php if (!$proximas): ?><p class="small text-muted mb-0">Nada agendado para a próxima semana.</p><?php endif; ?>
            <?php foreach ($proximas as $c): ?>
                <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                    <div class="text-center" style="width:44px"><div class="fw-bold text-navy lh-1"><?= date('d', strtotime($c['data'])) ?></div><div class="small text-muted"><?= ['dom','seg','ter','qua','qui','sex','sáb'][(int) date('w', strtotime($c['data']))] ?></div></div>
                    <?= avatar_paciente($c, 'xs', 'paciente') ?>
                    <div class="small flex-grow-1 lh-sm min-w-0"><strong class="d-block text-truncate"><?= e($c['paciente']) ?></strong><span class="text-muted"><?= hm($c['hora_inicio']) ?> · <?= e($c['procedimento']) ?></span></div>
                    <span class="avatar avatar-xs" style="--c:<?= e($c['cor']) ?>" title="<?= e($c['dentista']) ?>"><?= e(iniciais($c['dentista'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($fieis): ?>
<div class="card-soft panel mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="panel-title">Pacientes de longa data <span class="text-muted fw-normal small">· cadastro completo</span></h2>
        <a href="pacientes.php" class="small fw-semibold">Todos os pacientes <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    <div class="pac-destaque">
        <?php foreach ($fieis as $p): ?>
            <a href="paciente_resumo.php?id=<?= (int) $p['id_paciente'] ?>">
                <?= avatar_paciente($p, 'lg') ?>
                <span class="nm"><?= e($p['nome']) ?></span>
                <span class="paciente-desde"><i class="bi bi-award" aria-hidden="true"></i> desde <?= date('Y', strtotime($p['criado_em'])) ?></span>
                <span class="mt"><?= (int) $p['atend'] ?> atendimentos<?= $p['proxima'] ? ' · próxima ' . date('d/m', strtotime($p['proxima'])) : '' ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?= modal_pagamento('painel.php') ?>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

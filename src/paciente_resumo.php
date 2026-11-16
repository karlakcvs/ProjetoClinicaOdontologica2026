<?php
// SECRETARIA: resumo administrativo de um paciente (cadastro, consultas, pagamentos).
// Informações clínicas (prontuário, fotos, exames, odontograma) ficam só com os dentistas.
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/conexao.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM paciente WHERE id_paciente = ?");
$st->execute([$id]);
$p = $st->fetch();
if (!$p) { flash('Paciente não encontrado.', 'danger'); header('Location: pacientes.php'); exit; }

$st = $pdo->prepare("SELECT c.*, d.nome AS dentista, d.cor, pr.nome AS procedimento, COALESCE(dp.valor, pr.valor) AS valor_tabela, f.valor AS valor_pago, f.forma_pagamento
                     FROM consulta c JOIN dentista d ON d.id_dentista = c.id_dentista JOIN procedimento pr ON pr.id_procedimento = c.id_procedimento
                     LEFT JOIN dentista_procedimento dp ON dp.id_dentista = c.id_dentista AND dp.id_procedimento = c.id_procedimento
                     LEFT JOIN registro_financeiro f ON f.id_consulta = c.id_consulta
                     WHERE c.id_paciente = ? ORDER BY c.data DESC, c.hora_inicio DESC");
$st->execute([$id]);
$consultas = $st->fetchAll();
$hoje = date('Y-m-d');
$realizadas = array_filter($consultas, fn($c) => $c['status'] === 'REALIZADA');
$faltas = count(array_filter($consultas, fn($c) => $c['status'] === 'FALTOU'));
$futuras = array_reverse(array_values(array_filter($consultas, fn($c) => $c['data'] >= $hoje && in_array($c['status'], ['AGENDADA', 'CONFIRMADA'], true))));
$totalPago = array_sum(array_map(fn($c) => (float) $c['valor_pago'], $realizadas));
$porAno = [];
foreach ($realizadas as $c) { $a = substr($c['data'], 0, 4); $porAno[$a] = ($porAno[$a] ?? 0) + (float) $c['valor_pago']; }
ksort($porAno);
$porDent = [];
foreach ($realizadas as $c) { $porDent[$c['dentista']]['n'] = ($porDent[$c['dentista']]['n'] ?? 0) + 1; $porDent[$c['dentista']]['cor'] = $c['cor']; }
$ok = cadastro_completo($p);
$idade = idade($p['data_nascimento']);
$assid = $consultas ? round((1 - $faltas / max(1, count($realizadas) + $faltas)) * 100) : 100;

$titulo = 'Resumo do paciente';
$pagina = 'pacientes';
$acoes_topo = '<a href="pacientes.php" class="btn btn-ghost btn-sm"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Pacientes</span></a>'
    . ' <a href="nova_consulta.php" class="btn btn-teal btn-sm"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Nova consulta</span></a>';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="card-soft pac-head mb-4">
    <?= avatar_paciente($p, 'lg') ?>
    <div class="flex-grow-1 min-w-0">
        <h2 class="h4 mb-1"><?= e($p['nome']) ?></h2>
        <p class="mb-2"><span class="<?= $ok ? 'badge-ok' : 'badge-inc' ?>"><?= $ok ? 'Cadastro completo' : 'Cadastro incompleto' ?></span>
            <span class="paciente-desde"><i class="bi bi-award" aria-hidden="true"></i> paciente desde <?= mb_strtolower(nome_mes(date('n', strtotime($p['criado_em'])))) ?> de <?= date('Y', strtotime($p['criado_em'])) ?></span></p>
        <div class="small text-muted d-flex flex-wrap gap-3">
            <?php if ($idade !== null): ?><span><i class="bi bi-cake2 me-1" aria-hidden="true"></i><?= $idade ?> anos</span><?php endif; ?>
            <span><i class="bi bi-telephone me-1" aria-hidden="true"></i><?= e($p['telefone']) ?></span>
            <?php if ($p['email']): ?><span><i class="bi bi-envelope me-1" aria-hidden="true"></i><?= e($p['email']) ?></span><?php endif; ?>
            <?php if ($p['cpf']): ?><span><i class="bi bi-person-vcard me-1" aria-hidden="true"></i><?= e($p['cpf']) ?></span><?php endif; ?>
            <?php if ($p['cidade']): ?><span><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= e($p['logradouro'] . ', ' . $p['numero'] . ' – ' . $p['bairro'] . ' · ' . $p['cidade'] . '/' . $p['uf']) ?></span><?php endif; ?>
        </div>
        <?php if ($p['observacoes']): ?><div class="small mt-2"><span class="badge-inc"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Atenção</span> <?= e($p['observacoes']) ?></div><?php endif; ?>
    </div>
    <div class="d-flex flex-column gap-2">
        <a href="pacientes.php?editar=<?= $id ?>" class="btn btn-sm btn-ghost"><i class="bi bi-pencil me-1" aria-hidden="true"></i><?= $ok ? 'Editar cadastro' : 'Completar cadastro' ?></a>
        <a href="https://wa.me/55<?= e(preg_replace('/\D/', '', $p['telefone'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-soft"><i class="bi bi-whatsapp me-1" aria-hidden="true"></i>WhatsApp</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="card-soft kpi kpi-hero"><div class="k">Atendimentos</div><div class="v"><?= count($realizadas) ?></div><div class="sub"><?= count($consultas) ?> consulta(s) no total</div></div></div>
    <div class="col-6 col-xl-3"><div class="card-soft kpi"><div class="k">Total pago</div><div class="v"><?= moeda($totalPago) ?></div><div class="sub">ticket médio <?= moeda($realizadas ? $totalPago / count($realizadas) : 0) ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="card-soft kpi"><div class="k">Assiduidade</div><div class="v"><?= $assid ?>%</div><div class="meter"><span style="width:<?= $assid ?>%"></span></div><div class="sub mt-1"><?= $faltas ?> falta(s)</div></div></div>
    <div class="col-6 col-xl-3"><div class="card-soft kpi"><div class="k">Próxima consulta</div><div class="v" style="font-size:1.2rem"><?= $futuras ? data_br($futuras[0]['data']) . ' · ' . hm($futuras[0]['hora_inicio']) : '—' ?></div><div class="sub"><?= $futuras ? e($futuras[0]['procedimento']) : 'nada agendado' ?></div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card-soft">
            <div class="panel pb-0"><h2 class="panel-title">Histórico de consultas</h2></div>
            <table class="table table-clean table-stack">
                <thead><tr><th>Data</th><th>Procedimento</th><th>Dentista</th><th>Situação</th><th class="text-end">Valor</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($consultas, 0, 25) as $c): [$rot, $cls] = $STATUS_CONSULTA[$c['status']]; ?>
                    <tr>
                        <td class="td-main"><strong><?= data_br($c['data']) ?></strong> <span class="text-muted small"><?= hm($c['hora_inicio']) ?></span></td>
                        <td data-label="Procedimento"><span class="legend-dot me-1" style="background:<?= e(CATEGORIAS_PROC[categoria_proc($c['procedimento'])][1]) ?>"></span><?= e($c['procedimento']) ?></td>
                        <td data-label="Dentista"><span style="color:<?= e($c['cor']) ?>;font-weight:600"><?= e($c['dentista']) ?></span></td>
                        <td data-label="Situação"><span class="status <?= $cls ?>"><?= $rot ?></span></td>
                        <td data-label="Valor" class="text-end text-nowrap"><?= $c['valor_pago'] !== null ? '<strong>' . moeda($c['valor_pago']) . '</strong><br><span class="small text-muted">' . e($c['forma_pagamento']) . '</span>' : '<span class="text-muted">' . moeda($c['valor_tabela']) . '</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$consultas): ?><tr><td colspan="5" class="text-center text-muted py-4">Nenhuma consulta ainda.</td></tr><?php endif; ?>
                </tbody>
            </table>
            <?php if (count($consultas) > 25): ?><p class="small text-muted px-3 pb-3 mb-0">Mostrando as 25 mais recentes de <?= count($consultas) ?>.</p><?php endif; ?>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card-soft panel mb-4">
            <h2 class="panel-title mb-3">Pagamentos por ano</h2>
            <?php if ($porAno): ?><div class="chart-box" style="height:220px"><canvas id="gAno" role="img" aria-label="Gráfico de pagamentos por ano"></canvas></div>
            <?php else: ?><p class="small text-muted mb-0">Sem pagamentos registrados.</p><?php endif; ?>
        </div>
        <div class="card-soft panel">
            <h2 class="panel-title mb-3">Atendido por</h2>
            <?php foreach ($porDent as $nomeD => $dd): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom small"><span><span class="legend-dot me-2" style="background:<?= e($dd['cor']) ?>"></span><?= e($nomeD) ?></span><strong><?= $dd['n'] ?> atend.</strong></div>
            <?php endforeach; ?>
            <?php if (!$porDent): ?><p class="small text-muted mb-0">Nenhum atendimento realizado.</p><?php endif; ?>
            <p class="small text-muted mt-3 mb-0"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Prontuário, fotos e odontograma ficam visíveis só para os dentistas (LGPD).</p>
        </div>
    </div>
</div>
<?php
$scripts_extra = $porAno ? '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>if (window.Chart) new Chart(document.getElementById("gAno"), { type: "bar", data: { labels: ' . js(array_keys($porAno)) . ', datasets: [{ label: "Pago (R$)", data: ' . js(array_map(fn($v) => round($v, 2), array_values($porAno))) . ', backgroundColor: "#0c7f7c" }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } } });</script>' : '';
include __DIR__ . '/include/admin_fim.php';

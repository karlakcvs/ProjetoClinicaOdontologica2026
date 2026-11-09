<?php
// Área do DENTISTA: prontuário do paciente – fotos de cada sessão, exames e observações
require_once __DIR__ . '/include/check_dentista.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/prontuario.php';
require_once __DIR__ . '/include/odontograma.php';

$idPac = (int) ($_GET['id'] ?? $_POST['id_paciente'] ?? 0);
$pr = new Prontuario($pdo, $ID_DENTISTA);
if (!$idPac || !$pr->podeAcessar($idPac)) {
    flash('Você só pode abrir o prontuário de pacientes que têm consulta com você.', 'danger');
    header('Location: dent_pacientes.php');
    exit;
}
$aba = in_array($_GET['aba'] ?? '', ['resumo', 'sessoes', 'exames', 'notas', 'consultas'], true) ? $_GET['aba'] : 'resumo';

// consultas deste paciente com este dentista (para vincular fotos/observações à sessão)
$st = $pdo->prepare("SELECT c.id_consulta, c.data, c.hora_inicio, c.status, c.primeira_consulta, pr.nome AS procedimento, pr.valor
                     FROM consulta c JOIN procedimento pr ON pr.id_procedimento = c.id_procedimento
                     WHERE c.id_paciente = ? AND c.id_dentista = ? ORDER BY c.data DESC, c.hora_inicio DESC");
$st->execute([$idPac, $ID_DENTISTA]);
$consultas = $st->fetchAll();
$idsConsultas = array_map('intval', array_column($consultas, 'id_consulta'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = $_POST['acao'] ?? '';
    $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['data'] ?? '') ? $_POST['data'] : date('Y-m-d');
    if ($data > date('Y-m-d', strtotime('+1 day'))) $data = date('Y-m-d');
    $idC = (int) ($_POST['id_consulta'] ?? 0);
    if (!in_array($idC, $idsConsultas, true)) $idC = 0;      // só aceita consultas deste paciente/dentista
    if ($idC) foreach ($consultas as $c) if ((int) $c['id_consulta'] === $idC) $data = $c['data'];
    $titulo_r = mb_substr(trim(strip_tags((string) ($_POST['titulo'] ?? ''))), 0, 150);
    $texto = mb_substr(trim(strip_tags((string) ($_POST['texto'] ?? ''))), 0, 5000);
    $dados = ['id_consulta' => $idC, 'titulo' => $titulo_r, 'texto' => '', 'data' => $data];
    $ok = 0; $erros = [];

    if ($acao === 'dente') {
        // Odontograma: atualiza a situação de um dente
        $dente = (int) ($_POST['dente'] ?? 0);
        $sit = (string) ($_POST['situacao'] ?? '');
        $validos = array_merge(range(11, 18), range(21, 28), range(31, 38), range(41, 48));
        if (in_array($dente, $validos, true) && isset(SITUACOES_DENTE[$sit])) {
            $pdo->prepare("DELETE FROM odontograma WHERE id_paciente = ? AND dente = ?")->execute([$idPac, $dente]);
            if ($sit !== 'HIGIDO') {
                $pdo->prepare("INSERT INTO odontograma (id_paciente, dente, situacao, observacao, id_dentista, atualizado_em) VALUES (?,?,?,?,?,?)")
                    ->execute([$idPac, $dente, $sit, mb_substr(trim(strip_tags((string) ($_POST['observacao'] ?? ''))), 0, 200) ?: null, $ID_DENTISTA, date('Y-m-d H:i:s')]);
            }
            flash('Dente ' . $dente . ' atualizado: ' . SITUACOES_DENTE[$sit][0] . '.');
        }
        header('Location: dent_paciente.php?id=' . $idPac . '&aba=resumo#odontograma');
        exit;
    }
    if ($acao === 'sessao') {
        if ($texto !== '') { $pr->salvarNota($idPac, ['texto' => $texto] + $dados); $ok++; }
        foreach (Prontuario::arquivos('fotos') as $arq) {
            if ($pr->salvarArquivo($idPac, $arq, 'FOTO', $dados)) $ok++;
            elseif ($pr->erro) { $erros[] = $pr->erro; $pr->erro = ''; }
        }
        $volta = 'sessoes';
    } elseif ($acao === 'exame') {
        if ($titulo_r === '') $erros[] = 'Informe o nome do exame (ex.: Radiografia panorâmica).';
        else foreach (Prontuario::arquivos('arquivos') as $arq) {
            if ($pr->salvarArquivo($idPac, $arq, 'EXAME', ['texto' => $texto] + $dados)) $ok++;
            elseif ($pr->erro) { $erros[] = $pr->erro; $pr->erro = ''; }
        }
        $volta = 'exames';
    } elseif ($acao === 'nota') {
        if (mb_strlen($texto) < 2) $erros[] = 'Escreva a observação.';
        else { $pr->salvarNota($idPac, ['texto' => $texto] + $dados); $ok++; }
        $volta = 'notas';
    } elseif ($acao === 'excluir') {
        $r = $pr->excluir((int) ($_POST['id_registro'] ?? 0));
        $r ? flash('Registro excluído.', 'warning') : flash('Você só pode excluir registros feitos por você.', 'danger');
        header('Location: dent_paciente.php?id=' . $idPac . '&aba=' . ($r && $r['tipo'] === 'EXAME' ? 'exames' : ($r && $r['tipo'] === 'NOTA' && empty($_POST['de_sessao']) ? 'notas' : 'sessoes')));
        exit;
    } else { $volta = 'sessoes'; }

    if ($erros) flash(implode(' ', $erros) . ($ok ? " ($ok item(ns) salvo(s).)" : ''), 'danger');
    elseif ($ok) flash($ok . ' registro(s) salvo(s) no prontuário.');
    else flash('Nada para salvar: escolha fotos/arquivos ou escreva uma observação.', 'warning');
    header('Location: dent_paciente.php?id=' . $idPac . '&aba=' . $volta);
    exit;
}

$st = $pdo->prepare("SELECT * FROM paciente WHERE id_paciente = ?");
$st->execute([$idPac]);
$p = $st->fetch();
$registros = $pr->listar($idPac);
$fotos  = array_filter($registros, fn($r) => $r['tipo'] === 'FOTO');
$exames = array_filter($registros, fn($r) => $r['tipo'] === 'EXAME');
$notas  = array_filter($registros, fn($r) => $r['tipo'] === 'NOTA');

// Sessões = fotos + observações agrupadas por consulta (ou por data)
$sessoes = [];
foreach ($registros as $r) {
    if ($r['tipo'] === 'EXAME') continue;
    $k = $r['id_consulta'] ? 'c' . $r['id_consulta'] : 'd' . $r['data_registro'];
    $sessoes[$k]['data'] = $r['data_registro'];
    $sessoes[$k]['procedimento'] = $sessoes[$k]['procedimento'] ?? $r['procedimento'];
    $sessoes[$k]['cor'] = $r['cor'];
    $sessoes[$k]['itens'][] = $r;
}
uasort($sessoes, fn($a, $b) => strcmp($b['data'], $a['data']));

$proxima = null;
foreach (array_reverse($consultas) as $c) if ($c['data'] >= date('Y-m-d') && in_array($c['status'], ['AGENDADA', 'CONFIRMADA'])) { $proxima = $c; break; }
$idade = idade($p['data_nascimento']);

// ---------- Resumo: números, evolução e financeiro do paciente com este dentista ----------
$realizadas = array_values(array_filter($consultas, fn($c) => $c['status'] === 'REALIZADA'));
$faltas = count(array_filter($consultas, fn($c) => $c['status'] === 'FALTOU'));
$st = $pdo->prepare("SELECT f.data, f.valor, f.forma_pagamento, pr.nome AS procedimento FROM registro_financeiro f
                     JOIN consulta c ON c.id_consulta = f.id_consulta JOIN procedimento pr ON pr.id_procedimento = c.id_procedimento
                     WHERE c.id_paciente = ? AND c.id_dentista = ? ORDER BY f.data DESC");
$st->execute([$idPac, $ID_DENTISTA]);
$pagamentos = $st->fetchAll();
$totalPago = array_sum(array_column($pagamentos, 'valor'));
$aReceber = array_sum(array_map(fn($c) => (float) $c['valor'], array_filter($consultas, fn($c) => in_array($c['status'], ['AGENDADA', 'CONFIRMADA'], true) && $c['data'] >= date('Y-m-d'))));
$porAno = [];
foreach ($realizadas as $c) { $a = substr($c['data'], 0, 4); $porAno[$a]['n'] = ($porAno[$a]['n'] ?? 0) + 1; }
foreach ($pagamentos as $pg) { $a = substr($pg['data'], 0, 4); $porAno[$a]['v'] = ($porAno[$a]['v'] ?? 0) + (float) $pg['valor']; }
ksort($porAno);
$porProc = [];
foreach ($realizadas as $c) $porProc[$c['procedimento']] = ($porProc[$c['procedimento']] ?? 0) + 1;
arsort($porProc);
$mapaDentes = odontograma_mapa($pdo, $idPac);
$contSit = array_count_values(array_column($mapaDentes, 'situacao'));
$optConsultas = function () use ($consultas, $STATUS_CONSULTA) {
    $h = '<option value="">Sem vínculo (usar a data abaixo)</option>';
    foreach ($consultas as $c) {
        $h .= '<option value="' . (int) $c['id_consulta'] . '">' . data_br($c['data']) . ' · ' . hm($c['hora_inicio']) . ' · ' . e($c['procedimento']) . ' (' . e($STATUS_CONSULTA[$c['status']][0]) . ')</option>';
    }
    return $h;
};
$url = fn($a) => 'dent_paciente.php?id=' . $idPac . '&amp;aba=' . $a;

$titulo = 'Prontuário';
$pagina = 'dent_pacientes';
$acoes_topo = '<a href="dent_pacientes.php" class="btn btn-ghost btn-sm"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Pacientes</span></a>';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="card-soft pac-head mb-4">
    <?= avatar_paciente($p, 'lg') ?>
    <div class="flex-grow-1 min-w-0">
        <h2 class="h4 mb-1"><?= e($p['nome']) ?></h2>
        <?php if (cadastro_completo($p)): ?><p class="mb-2"><span class="badge-ok">Cadastro completo</span> <span class="paciente-desde"><i class="bi bi-award" aria-hidden="true"></i> paciente desde <?= mb_strtolower(nome_mes(date('n', strtotime($p['criado_em'])))) ?> de <?= date('Y', strtotime($p['criado_em'])) ?></span> <span class="small text-muted ms-1"><?= count(array_filter($consultas, fn($c) => $c['status'] === 'REALIZADA')) ?> sessões com você</span></p><?php endif; ?>
        <div class="small text-muted d-flex flex-wrap gap-3">
            <?php if ($idade !== null): ?><span><i class="bi bi-cake2 me-1" aria-hidden="true"></i><?= $idade ?> anos (<?= data_br($p['data_nascimento']) ?>)</span><?php endif; ?>
            <span><i class="bi bi-telephone me-1" aria-hidden="true"></i><?= e($p['telefone']) ?></span>
            <?php if ($p['email']): ?><span><i class="bi bi-envelope me-1" aria-hidden="true"></i><?= e($p['email']) ?></span><?php endif; ?>
            <?php if ($p['cidade']): ?><span><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= e($p['bairro'] . ' · ' . $p['cidade'] . '/' . $p['uf']) ?></span><?php endif; ?>
        </div>
        <?php if ($p['observacoes']): ?><div class="small mt-2"><span class="badge-inc"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Recepção</span> <?= e($p['observacoes']) ?></div><?php endif; ?>
    </div>
    <div class="text-md-end small">
        <?php if ($proxima): ?><div class="text-muted">Próxima consulta</div><strong><?= data_br($proxima['data']) ?> às <?= hm($proxima['hora_inicio']) ?></strong><div class="text-muted"><?= e($proxima['procedimento']) ?></div>
        <?php else: ?><span class="text-muted">Sem consulta futura</span><?php endif; ?>
    </div>
</div>

<nav class="ptabs" aria-label="Seções do prontuário">
    <a href="<?= $url('resumo') ?>" class="<?= $aba === 'resumo' ? 'active' : '' ?>" <?= $aba === 'resumo' ? 'aria-current="page"' : '' ?>><i class="bi bi-grid-1x2" aria-hidden="true"></i>Resumo e odontograma</a>
    <a href="<?= $url('sessoes') ?>" class="<?= $aba === 'sessoes' ? 'active' : '' ?>" <?= $aba === 'sessoes' ? 'aria-current="page"' : '' ?>><i class="bi bi-camera" aria-hidden="true"></i>Sessões e fotos <span class="count"><?= count($fotos) ?></span></a>
    <a href="<?= $url('exames') ?>" class="<?= $aba === 'exames' ? 'active' : '' ?>" <?= $aba === 'exames' ? 'aria-current="page"' : '' ?>><i class="bi bi-file-earmark-medical" aria-hidden="true"></i>Exames <span class="count"><?= count($exames) ?></span></a>
    <a href="<?= $url('notas') ?>" class="<?= $aba === 'notas' ? 'active' : '' ?>" <?= $aba === 'notas' ? 'aria-current="page"' : '' ?>><i class="bi bi-journal-text" aria-hidden="true"></i>Observações <span class="count"><?= count($notas) ?></span></a>
    <a href="<?= $url('consultas') ?>" class="<?= $aba === 'consultas' ? 'active' : '' ?>" <?= $aba === 'consultas' ? 'aria-current="page"' : '' ?>><i class="bi bi-calendar-check" aria-hidden="true"></i>Consultas <span class="count"><?= count($consultas) ?></span></a>
</nav>

<?php if ($aba === 'resumo'): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="card-soft kpi kpi-hero"><div class="k">Sessões com você</div><div class="v"><?= count($realizadas) ?></div><div class="sub"><?= $realizadas ? 'desde ' . data_br(end($realizadas)['data']) : 'nenhuma ainda' ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="card-soft kpi"><div class="k">Total pago</div><div class="v"><?= moeda($totalPago) ?></div><div class="sub"><?= count($pagamentos) ?> pagamento(s)</div></div></div>
    <div class="col-6 col-xl-3"><div class="card-soft kpi"><div class="k">Ticket médio</div><div class="v"><?= moeda($pagamentos ? $totalPago / count($pagamentos) : 0) ?></div><div class="sub">por sessão</div></div></div>
    <div class="col-6 col-xl-3"><div class="card-soft kpi"><div class="k">Agendado a receber</div><div class="v"><?= moeda($aReceber) ?></div><div class="sub"><?= $faltas ?> falta(s) no histórico</div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card-soft panel mb-4" id="odontograma">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="panel-title">Odontograma</h2>
                <span class="small text-muted">Clique em um dente para atualizar</span>
            </div>
            <?= odontograma_html($mapaDentes, true) ?>
            <?php if ($contSit): ?>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php foreach ($contSit as $s => $n): ?><span class="tag tag-navy"><?= e(SITUACOES_DENTE[$s][0] ?? $s) ?>: <?= $n ?></span><?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-soft panel">
            <h2 class="panel-title mb-3">Evolução do tratamento <span class="text-muted fw-normal small">· sessões e valores por ano</span></h2>
            <?php if ($porAno): ?><div class="chart-box" style="height:260px"><canvas id="gEvol" role="img" aria-label="Gráfico de sessões e valores pagos por ano"></canvas></div>
            <?php else: ?><p class="small text-muted mb-0">Ainda não há sessões realizadas.</p><?php endif; ?>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card-soft panel mb-4">
            <h2 class="panel-title mb-3">Procedimentos realizados</h2>
            <?php $maxP = max([1] + array_values($porProc)); foreach (array_slice($porProc, 0, 6, true) as $nomeP => $n): ?>
                <div class="mb-2"><div class="d-flex justify-content-between small"><span><?= e($nomeP) ?></span><strong><?= $n ?></strong></div>
                <div class="bar-track"><div class="bar-fill" style="width:<?= round($n / $maxP * 100) ?>%;background:<?= e(CATEGORIAS_PROC[categoria_proc($nomeP)][1]) ?>"></div></div></div>
            <?php endforeach; ?>
            <?php if (!$porProc): ?><p class="small text-muted mb-0">Nenhum procedimento realizado ainda.</p><?php endif; ?>
        </div>
        <div class="card-soft panel">
            <h2 class="panel-title mb-3">Financeiro com você</h2>
            <?php foreach (array_slice($pagamentos, 0, 8) as $pg): ?>
                <div class="d-flex justify-content-between gap-2 py-2 border-bottom small">
                    <span><strong><?= data_br($pg['data']) ?></strong> · <?= e($pg['procedimento']) ?><br><span class="text-muted"><i class="bi <?= $FORMAS_ICONES[$pg['forma_pagamento']] ?? 'bi-wallet2' ?>" aria-hidden="true"></i> <?= e($pg['forma_pagamento']) ?></span></span>
                    <strong class="text-nowrap"><?= moeda($pg['valor']) ?></strong>
                </div>
            <?php endforeach; ?>
            <?php if (!$pagamentos): ?><p class="small text-muted mb-0">Nenhum pagamento registrado.</p><?php elseif (count($pagamentos) > 8): ?><p class="small text-muted mt-2 mb-0">+ <?= count($pagamentos) - 8 ?> pagamento(s) anteriores.</p><?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="mDente" tabindex="-1" aria-labelledby="mDenteTit" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="dente"><input type="hidden" name="id_paciente" value="<?= $idPac ?>"><input type="hidden" name="dente" id="fDente">
            <div class="modal-header"><h2 class="modal-title h5" id="mDenteTit">Dente</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body">
                <label class="form-label" for="fSit">Situação</label>
                <select name="situacao" id="fSit" class="form-select mb-3"><?php foreach (SITUACOES_DENTE as $k => [$nomeS]): ?><option value="<?= $k ?>"><?= e($nomeS) ?></option><?php endforeach; ?></select>
                <label class="form-label" for="fObs">Observação <span class="text-muted fw-normal">(opcional)</span></label>
                <input name="observacao" id="fObs" class="form-control" maxlength="200" placeholder="Ex.: restauração em resina, face oclusal">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-teal">Salvar</button></div>
        </form>
    </div>
</div>

<?php elseif ($aba === 'sessoes'): ?>
<div class="row g-4">
    <div class="col-lg-5 order-lg-2">
        <form method="POST" enctype="multipart/form-data" class="card-soft panel" data-enviando="Enviando fotos…">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="sessao"><input type="hidden" name="id_paciente" value="<?= $idPac ?>">
            <h3 class="panel-title mb-3"><i class="bi bi-plus-circle text-teal me-1" aria-hidden="true"></i>Registrar sessão</h3>
            <div class="mb-3"><label class="form-label" for="sCons">Consulta / sessão</label><select id="sCons" name="id_consulta" class="form-select"><?= $optConsultas() ?></select></div>
            <div class="mb-3"><label class="form-label" for="sData">Data (se não vincular)</label><input type="date" id="sData" name="data" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></div>
            <label class="dropzone dropzone-sm mb-2" for="sFotos">
                <i class="bi bi-camera" aria-hidden="true"></i>
                <div class="fw-semibold mt-1">Fotos da sessão</div>
                <div class="small text-muted">JPG, PNG ou WEBP · até <?= Prontuario::LIMITE_MB ?> MB cada · até 10 por vez</div>
            </label>
            <input type="file" id="sFotos" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple class="form-control mb-1" data-preview="prevFotos">
            <div class="preview-list" id="prevFotos" aria-live="polite"></div>
            <div class="mb-3 mt-3"><label class="form-label" for="sTit">Título <span class="text-muted fw-normal">(opcional)</span></label><input id="sTit" name="titulo" class="form-control" maxlength="150" placeholder="Ex.: Antes do clareamento"></div>
            <div class="mb-3"><label class="form-label" for="sTxt">Observação da sessão</label><textarea id="sTxt" name="texto" class="form-control" rows="3" maxlength="5000" placeholder="Procedimento realizado, evolução, orientações…"></textarea></div>
            <button class="btn btn-teal w-100" type="submit"><i class="bi bi-cloud-arrow-up me-1" aria-hidden="true"></i>Salvar sessão</button>
            <p class="small text-muted mt-2 mb-0"><i class="bi bi-shield-lock" aria-hidden="true"></i> Arquivos protegidos: só dentistas do paciente conseguem abrir.</p>
        </form>
    </div>
    <div class="col-lg-7 order-lg-1">
        <?php if (!$sessoes): ?><div class="empty-state"><i class="bi bi-images" aria-hidden="true"></i><p class="mt-2 mb-0">Nenhuma sessão registrada ainda. Use o formulário para enviar as primeiras fotos.</p></div><?php endif; ?>
        <div class="timeline">
        <?php foreach ($sessoes as $s): $fs = array_filter($s['itens'], fn($r) => $r['tipo'] === 'FOTO'); $ns = array_filter($s['itens'], fn($r) => $r['tipo'] === 'NOTA'); ?>
            <div class="tl-item" style="--c:<?= e($s['cor']) ?>">
                <div class="card-soft tl-card">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                        <div><span class="tl-date"><?= data_br($s['data']) ?></span> <?php if ($s['procedimento']): ?><span class="tag ms-1"><?= e($s['procedimento']) ?></span><?php endif; ?></div>
                        <span class="small text-muted"><?= count($fs) ?> foto(s)</span>
                    </div>
                    <?php foreach ($ns as $n): ?>
                        <div class="d-flex gap-2 mb-2">
                            <div class="flex-grow-1"><?php if ($n['titulo']): ?><strong class="small d-block"><?= e($n['titulo']) ?></strong><?php endif; ?><p class="note mb-0"><?= e($n['texto']) ?></p><span class="small text-muted"><?= e($n['dentista']) ?></span></div>
                            <?php if ((int) $n['id_dentista'] === $ID_DENTISTA): ?>
                            <form method="POST" data-confirm="Excluir esta observação?"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="de_sessao" value="1"><input type="hidden" name="id_paciente" value="<?= $idPac ?>"><input type="hidden" name="id_registro" value="<?= (int) $n['id_registro'] ?>">
                                <button class="btn btn-icon btn-ghost text-danger" aria-label="Excluir observação"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($fs): ?>
                    <div class="photo-grid">
                        <?php foreach ($fs as $f): ?>
                            <figure>
                                <img src="arquivo.php?id=<?= (int) $f['id_registro'] ?>" alt="<?= e($f['titulo'] ?: 'Foto da sessão de ' . data_br($f['data_registro'])) ?>" loading="lazy" data-zoom>
                                <figcaption><?= e($f['titulo'] ?: $f['nome_original']) ?></figcaption>
                                <?php if ((int) $f['id_dentista'] === $ID_DENTISTA): ?>
                                <form method="POST" class="del" data-confirm="Excluir esta foto?"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id_paciente" value="<?= $idPac ?>"><input type="hidden" name="id_registro" value="<?= (int) $f['id_registro'] ?>">
                                    <button aria-label="Excluir foto"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
                                <?php endif; ?>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<?php elseif ($aba === 'exames'): ?>
<div class="row g-4">
    <div class="col-lg-5 order-lg-2">
        <form method="POST" enctype="multipart/form-data" class="card-soft panel" data-enviando="Enviando exame…">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="exame"><input type="hidden" name="id_paciente" value="<?= $idPac ?>">
            <h3 class="panel-title mb-3"><i class="bi bi-plus-circle text-teal me-1" aria-hidden="true"></i>Adicionar exame</h3>
            <div class="mb-3"><label class="form-label" for="eTit">Nome do exame</label><input id="eTit" name="titulo" class="form-control" required maxlength="150" placeholder="Ex.: Radiografia panorâmica, Tomografia"></div>
            <div class="mb-3"><label class="form-label" for="eData">Data do exame</label><input type="date" id="eData" name="data" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></div>
            <div class="mb-3"><label class="form-label" for="eArq">Arquivo(s)</label><input type="file" id="eArq" name="arquivos[]" class="form-control" required multiple accept="image/jpeg,image/png,image/webp,application/pdf">
                <div class="form-text">PDF, JPG, PNG ou WEBP · até <?= Prontuario::LIMITE_MB ?> MB cada.</div></div>
            <div class="mb-3"><label class="form-label" for="eTxt">Laudo / comentário <span class="text-muted fw-normal">(opcional)</span></label><textarea id="eTxt" name="texto" class="form-control" rows="2" maxlength="5000"></textarea></div>
            <button class="btn btn-teal w-100" type="submit"><i class="bi bi-cloud-arrow-up me-1" aria-hidden="true"></i>Salvar exame</button>
        </form>
    </div>
    <div class="col-lg-7 order-lg-1">
        <?php foreach ($exames as $x): $img = strpos((string) $x['mime'], 'image/') === 0; ?>
            <div class="exam-item">
                <span class="ic"><?php if ($img): ?><img src="arquivo.php?id=<?= (int) $x['id_registro'] ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i><?php endif; ?></span>
                <div class="flex-grow-1 min-w-0">
                    <strong class="d-block text-truncate"><?= e($x['titulo']) ?></strong>
                    <span class="small text-muted"><?= data_br($x['data_registro']) ?> · <?= e($x['nome_original']) ?> · <?= number_format($x['tamanho'] / 1048576, 1, ',', '') ?> MB · <?= e($x['dentista']) ?></span>
                    <?php if ($x['texto']): ?><div class="small note mt-1"><?= e($x['texto']) ?></div><?php endif; ?>
                </div>
                <div class="d-flex gap-1">
                    <a href="arquivo.php?id=<?= (int) $x['id_registro'] ?>" target="_blank" rel="noopener" class="btn btn-icon btn-ghost" aria-label="Abrir <?= e($x['titulo']) ?>"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                    <a href="arquivo.php?id=<?= (int) $x['id_registro'] ?>&amp;baixar=1" class="btn btn-icon btn-ghost" aria-label="Baixar <?= e($x['titulo']) ?>"><i class="bi bi-download" aria-hidden="true"></i></a>
                    <?php if ((int) $x['id_dentista'] === $ID_DENTISTA): ?>
                    <form method="POST" data-confirm="Excluir este exame?"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id_paciente" value="<?= $idPac ?>"><input type="hidden" name="id_registro" value="<?= (int) $x['id_registro'] ?>">
                        <button class="btn btn-icon btn-ghost text-danger" aria-label="Excluir exame"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$exames): ?><div class="empty-state"><i class="bi bi-file-earmark-medical" aria-hidden="true"></i><p class="mt-2 mb-0">Nenhum exame anexado.</p></div><?php endif; ?>
    </div>
</div>

<?php elseif ($aba === 'notas'): ?>
<div class="row g-4">
    <div class="col-lg-5 order-lg-2">
        <form method="POST" class="card-soft panel" data-enviando="Salvando…">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="nota"><input type="hidden" name="id_paciente" value="<?= $idPac ?>">
            <h3 class="panel-title mb-3"><i class="bi bi-pencil-square text-teal me-1" aria-hidden="true"></i>Nova observação</h3>
            <div class="mb-3"><label class="form-label" for="nCons">Vincular a uma consulta <span class="text-muted fw-normal">(opcional)</span></label><select id="nCons" name="id_consulta" class="form-select"><?= $optConsultas() ?></select></div>
            <div class="mb-3"><label class="form-label" for="nTit">Título <span class="text-muted fw-normal">(opcional)</span></label><input id="nTit" name="titulo" class="form-control" maxlength="150" placeholder="Ex.: Plano de tratamento, Alergia"></div>
            <div class="mb-3"><label class="form-label" for="nTxt">Observação</label><textarea id="nTxt" name="texto" class="form-control" rows="6" required maxlength="5000"></textarea></div>
            <input type="hidden" name="data" value="<?= date('Y-m-d') ?>">
            <button class="btn btn-teal w-100" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Salvar observação</button>
        </form>
    </div>
    <div class="col-lg-7 order-lg-1">
        <?php foreach ($notas as $n): ?>
            <div class="card-soft panel mb-3">
                <div class="d-flex justify-content-between gap-2 mb-1">
                    <div><strong><?= e($n['titulo'] ?: 'Observação') ?></strong> <?php if ($n['procedimento']): ?><span class="tag ms-1"><?= e($n['procedimento']) ?></span><?php endif; ?></div>
                    <span class="small text-muted text-nowrap"><?= data_br($n['data_registro']) ?></span>
                </div>
                <p class="note mb-1"><?= e($n['texto']) ?></p>
                <div class="d-flex justify-content-between align-items-center"><span class="small text-muted"><?= e($n['dentista']) ?></span>
                <?php if ((int) $n['id_dentista'] === $ID_DENTISTA): ?>
                    <form method="POST" data-confirm="Excluir esta observação?"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id_paciente" value="<?= $idPac ?>"><input type="hidden" name="id_registro" value="<?= (int) $n['id_registro'] ?>">
                        <button class="btn btn-sm btn-ghost text-danger"><i class="bi bi-trash" aria-hidden="true"></i> Excluir</button></form>
                <?php endif; ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (!$notas): ?><div class="empty-state"><i class="bi bi-journal-text" aria-hidden="true"></i><p class="mt-2 mb-0">Nenhuma observação ainda.</p></div><?php endif; ?>
    </div>
</div>

<?php else: ?>
<div class="card-soft">
    <table class="table table-clean table-stack">
        <thead><tr><th>Data</th><th>Procedimento</th><th>Status</th><th class="text-end">Valor</th></tr></thead>
        <tbody>
        <?php foreach ($consultas as $c): [$rot, $cls] = $STATUS_CONSULTA[$c['status']]; ?>
            <tr><td class="td-main"><strong><?= data_br($c['data']) ?></strong> <span class="text-muted small"><?= hm($c['hora_inicio']) ?></span></td>
                <td data-label="Procedimento"><span><?= e($c['procedimento']) ?><?= $c['primeira_consulta'] ? ' <span class="tag tag-navy">1ª consulta</span>' : '' ?></span></td>
                <td data-label="Status"><span class="status <?= $cls ?>"><?= $rot ?></span></td>
                <td data-label="Valor" class="text-end"><?= moeda($c['valor']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Foto ampliada">
    <button type="button" class="btn btn-icon btn-ghost bg-white" aria-label="Fechar foto"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    <img alt="">
</div>
<?php
$scriptsResumo = $aba === 'resumo' ? '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.getElementById("mDente")?.addEventListener("show.bs.modal", ev => {
  const b = ev.relatedTarget; if (!b) return;
  document.getElementById("mDenteTit").textContent = "Dente " + b.dataset.dente;
  document.getElementById("fDente").value = b.dataset.dente;
  document.getElementById("fSit").value = b.dataset.sit;
  document.getElementById("fObs").value = b.dataset.obs || "";
});
const gE = document.getElementById("gEvol");
if (gE && window.Chart) new Chart(gE, { data: { labels: ' . js(array_keys($porAno)) . ', datasets: [
  { type: "bar", label: "Sessões", data: ' . js(array_map(fn($a) => $a['n'] ?? 0, array_values($porAno))) . ', backgroundColor: "rgba(12,127,124,.35)", borderColor: "#0c7f7c", borderWidth: 1, yAxisID: "y" },
  { type: "line", label: "Valor pago (R$)", data: ' . js(array_map(fn($a) => round($a['v'] ?? 0, 2), array_values($porAno))) . ', borderColor: "#0b3a75", backgroundColor: "#0b3a75", tension: .3, yAxisID: "y1" } ] },
  options: { maintainAspectRatio: false, interaction: { mode: "index", intersect: false }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, y1: { beginAtZero: true, position: "right", grid: { drawOnChartArea: false } } } } });
</script>' : '';
$scripts_extra = $scriptsResumo . '<script>
(() => {
  const lb = document.getElementById("lightbox"), img = lb.querySelector("img"), fechar = () => lb.classList.remove("show");
  document.querySelectorAll("[data-zoom]").forEach(i => { i.tabIndex = 0; const abrir = () => { img.src = i.src; img.alt = i.alt; lb.classList.add("show"); lb.querySelector("button").focus(); };
    i.addEventListener("click", abrir); i.addEventListener("keydown", e => { if (e.key === "Enter") abrir(); }); });
  lb.addEventListener("click", e => { if (e.target !== img) fechar(); });
  document.addEventListener("keydown", e => { if (e.key === "Escape") fechar(); });
  document.querySelectorAll("[data-preview]").forEach(inp => inp.addEventListener("change", () => {
    const box = document.getElementById(inp.dataset.preview); box.innerHTML = "";
    [...inp.files].slice(0, 10).forEach(f => { const im = document.createElement("img"); im.src = URL.createObjectURL(f); im.alt = f.name; box.appendChild(im); });
  }));
})();
</script>';
include __DIR__ . '/include/admin_fim.php';

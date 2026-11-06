<?php
// Área do DENTISTA: prontuário do paciente – fotos de cada sessão, exames e observações
require_once __DIR__ . '/include/check_dentista.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/prontuario.php';

$idPac = (int) ($_GET['id'] ?? $_POST['id_paciente'] ?? 0);
$pr = new Prontuario($pdo, $ID_DENTISTA);
if (!$idPac || !$pr->podeAcessar($idPac)) {
    flash('Você só pode abrir o prontuário de pacientes que têm consulta com você.', 'danger');
    header('Location: dent_pacientes.php');
    exit;
}
$aba = in_array($_GET['aba'] ?? '', ['sessoes', 'exames', 'notas', 'consultas'], true) ? $_GET['aba'] : 'sessoes';

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
    <span class="avatar" style="--c:#4f7cc4" aria-hidden="true"><?= e(iniciais($p['nome'])) ?></span>
    <div class="flex-grow-1 min-w-0">
        <h2 class="h4 mb-1"><?= e($p['nome']) ?></h2>
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
    <a href="<?= $url('sessoes') ?>" class="<?= $aba === 'sessoes' ? 'active' : '' ?>" <?= $aba === 'sessoes' ? 'aria-current="page"' : '' ?>><i class="bi bi-camera" aria-hidden="true"></i>Sessões e fotos <span class="count"><?= count($fotos) ?></span></a>
    <a href="<?= $url('exames') ?>" class="<?= $aba === 'exames' ? 'active' : '' ?>" <?= $aba === 'exames' ? 'aria-current="page"' : '' ?>><i class="bi bi-file-earmark-medical" aria-hidden="true"></i>Exames <span class="count"><?= count($exames) ?></span></a>
    <a href="<?= $url('notas') ?>" class="<?= $aba === 'notas' ? 'active' : '' ?>" <?= $aba === 'notas' ? 'aria-current="page"' : '' ?>><i class="bi bi-journal-text" aria-hidden="true"></i>Observações <span class="count"><?= count($notas) ?></span></a>
    <a href="<?= $url('consultas') ?>" class="<?= $aba === 'consultas' ? 'active' : '' ?>" <?= $aba === 'consultas' ? 'aria-current="page"' : '' ?>><i class="bi bi-calendar-check" aria-hidden="true"></i>Consultas <span class="count"><?= count($consultas) ?></span></a>
</nav>

<?php if ($aba === 'sessoes'): ?>
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
$scripts_extra = '<script>
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

<?php
// RF03 - Gerenciamento de dentistas, horários (segunda a sexta) e procedimentos que realizam
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/conexao.php';

$hm = fn($v) => preg_match('/^\d{2}:\d{2}$/', $v ?? '') ? $v . ':00' : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'precos') {
    csrf_validar();
    $up = $pdo->prepare("UPDATE procedimento SET valor = ?, duracao_min = ? WHERE id_procedimento = ?");
    foreach ((array) ($_POST['valor'] ?? []) as $idP => $val) {
        $val = (float) str_replace(',', '.', (string) $val);
        $dur = (int) ($_POST['duracao'][$idP] ?? 0);
        if ($val >= 0 && $val <= 100000 && $dur >= 15 && $dur <= 480 && $dur % 15 === 0) $up->execute([$val, $dur, (int) $idP]);
    }
    flash('Tabela de preços atualizada.');
    header('Location: dentistas.php#precos');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $id = (int) $_POST['id_dentista'];
    $txt = fn($k, $max) => mb_substr(trim(strip_tags((string) ($_POST[$k] ?? ''))), 0, $max);
    if (mb_strlen($txt('nome', 120)) < 3) { flash('Informe o nome do dentista.', 'danger'); header('Location: dentistas.php'); exit; }

    // Foto do dentista (opcional): só imagens reais JPG/PNG/WEBP até 5 MB
    $novaFoto = null;
    $arq = $_FILES['foto'] ?? null;
    if ($arq && $arq['error'] !== UPLOAD_ERR_NO_FILE) {
        $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $arq['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($arq['tmp_name']) : '';
        if ($arq['error'] !== UPLOAD_ERR_OK || $arq['size'] > 5 * 1024 * 1024) { flash('A foto deve ter no máximo 5 MB.', 'danger'); header('Location: dentistas.php'); exit; }
        if (!isset($tipos[$mime]) || !@getimagesize($arq['tmp_name'])) { flash('Envie a foto em JPG, PNG ou WEBP.', 'danger'); header('Location: dentistas.php'); exit; }
        $novaFoto = 'up_' . $id . '_' . bin2hex(random_bytes(6)) . '.' . $tipos[$mime];
        $dir = __DIR__ . '/uploads/dentistas/';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!move_uploaded_file($arq['tmp_name'], $dir . $novaFoto)) { flash('Não foi possível salvar a foto (permissão da pasta uploads/dentistas).', 'danger'); header('Location: dentistas.php'); exit; }
        $antiga = $pdo->prepare("SELECT foto FROM dentista WHERE id_dentista = ?"); $antiga->execute([$id]);
        if (($fa = $antiga->fetchColumn()) && strpos($fa, 'up_') === 0) @unlink($dir . basename($fa));
    }

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE dentista SET nome=?, cro=?, especialidade=?, bio=?, cor=?, anos_experiencia=?, formacao=?, publico=?, condicoes=?, atendimento=?, ativo=? WHERE id_dentista=?")->execute([
        $txt('nome', 120), $txt('cro', 20), $txt('especialidade', 80), $txt('bio', 2000),
        preg_match('/^#[0-9a-f]{6}$/i', $_POST['cor'] ?? '') ? $_POST['cor'] : '#12a3a0',
        max(0, min(60, (int) ($_POST['anos_experiencia'] ?? 0))) ?: null, $txt('formacao', 255), $txt('publico', 255), $txt('condicoes', 2000),
        in_array($_POST['atendimento'] ?? '', ['Presencial', 'Presencial e online'], true) ? $_POST['atendimento'] : 'Presencial',
        empty($_POST['ativo']) ? 0 : 1, $id]);
    if ($novaFoto) $pdo->prepare("UPDATE dentista SET foto = ? WHERE id_dentista = ?")->execute([$novaFoto, $id]);

    // horários: manhã e tarde, aplicados aos dias marcados (segunda a sexta)
    $pdo->prepare("DELETE FROM horario_atendimento WHERE id_dentista = ?")->execute([$id]);
    $ins = $pdo->prepare("INSERT INTO horario_atendimento (id_dentista, dia_semana, hora_inicio, hora_fim) VALUES (?,?,?,?)");
    foreach (($_POST['dias'] ?? []) as $dia) {
        $dia = (int) $dia;
        if ($dia < 1 || $dia > 6) continue;
        foreach ([['m_ini', 'm_fim'], ['t_ini', 't_fim']] as [$a, $b]) {
            $i = $hm($_POST[$a] ?? ''); $f = $hm($_POST[$b] ?? '');
            if ($i && $f && $i < $f) $ins->execute([$id, $dia, $i, $f]);
        }
    }
    $pdo->prepare("DELETE FROM dentista_procedimento WHERE id_dentista = ?")->execute([$id]);
    $insP = $pdo->prepare("INSERT INTO dentista_procedimento (id_dentista, id_procedimento, valor) VALUES (?,?,?)");
    foreach (($_POST['procs'] ?? []) as $p) {
        $vp = trim((string) ($_POST['preco'][(int) $p] ?? ''));
        $vp = $vp === '' ? null : (float) str_replace(',', '.', $vp);
        if ($vp !== null && ($vp < 0 || $vp > 100000)) $vp = null;
        $insP->execute([$id, (int) $p, $vp]);
    }
    $pdo->commit();
    flash('Dados de ' . trim($_POST['nome']) . ' atualizados.');
    header('Location: dentistas.php');
    exit;
}

$dentistas = $pdo->query("SELECT * FROM dentista ORDER BY id_dentista")->fetchAll();
$procedimentos = $pdo->query("SELECT * FROM procedimento ORDER BY nome")->fetchAll();
$horarios = []; $diasD = [];
foreach ($pdo->query("SELECT * FROM horario_atendimento ORDER BY hora_inicio")->fetchAll() as $h) {
    $diasD[$h['id_dentista']][(int) $h['dia_semana']] = true;
    $k = hm($h['hora_inicio']) . '-' . hm($h['hora_fim']);
    $horarios[$h['id_dentista']][$k] = [hm($h['hora_inicio']), hm($h['hora_fim'])];
}
$dp = [];
$dpValor = [];
foreach ($pdo->query("SELECT * FROM dentista_procedimento")->fetchAll() as $r) { $dp[$r['id_dentista']][$r['id_procedimento']] = true; $dpValor[$r['id_dentista']][$r['id_procedimento']] = $r['valor']; }
$logins = [];
foreach ($pdo->query("SELECT id_dentista, email, ultimo_acesso FROM usuario WHERE perfil = 'DENTISTA'")->fetchAll() as $u) $logins[$u['id_dentista']] = $u;

$titulo = 'Dentistas';
$pagina = 'dentistas';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="row g-4">
<?php foreach ($dentistas as $d):
    $turnos = array_values($horarios[$d['id_dentista']] ?? []);
    $m = $turnos[0] ?? ['', '']; $t = $turnos[1] ?? ['', '']; ?>
    <div class="col-xl-4 col-md-6">
        <div class="card-soft doc-fin h-100" style="--c:<?= e($d['cor']) ?>">
            <div class="d-flex align-items-center gap-3 mb-3">
                <?php if ($ft = foto_dentista($d)): ?><img src="<?= e($ft) ?>" alt="" class="avatar avatar-foto"><?php else: ?><span class="avatar" style="--c:<?= e($d['cor']) ?>"><?= e(iniciais($d['nome'])) ?></span><?php endif; ?>
                <div class="lh-sm min-w-0"><h2 class="h5 mb-1"><?= e($d['nome']) ?></h2><span class="tag"><?= e($d['especialidade']) ?></span><?php if (!$d['ativo']): ?> <span class="status status-cancelada">inativo</span><?php endif; ?><div class="small text-muted mt-1"><?= e($d['cro']) ?><?= $d['anos_experiencia'] ? ' · ' . (int) $d['anos_experiencia'] . ' anos' : '' ?></div></div>
            </div>
            <?php if (!empty($logins[$d['id_dentista']])): $lg = $logins[$d['id_dentista']]; ?>
                <div class="small mb-2 text-truncate"><i class="bi bi-person-lock text-teal me-1" aria-hidden="true"></i>Login: <strong><?= e($lg['email']) ?></strong></div>
                <div class="small text-muted mb-2"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Último acesso: <?= $lg['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($lg['ultimo_acesso'])) : 'nunca' ?></div>
            <?php endif; ?>
            <div class="small mb-2"><i class="bi bi-calendar-week text-teal me-1"></i>
                <?php foreach ([1,2,3,4,5,6] as $di): ?><span class="badge rounded-pill <?= !empty($diasD[$d['id_dentista']][$di]) ? 'text-bg-dark' : 'text-bg-light text-muted' ?> me-1"><?= mb_substr($DIAS_SEMANA[$di], 0, 3) ?></span><?php endforeach; ?>
            </div>
            <div class="small mb-3"><i class="bi bi-clock text-teal me-1"></i><?= e(implode(' · ', array_map(fn($x) => $x[0] . '–' . $x[1], $turnos))) ?></div>
            <div class="d-flex flex-wrap gap-1 mb-3">
                <?php foreach ($procedimentos as $p) if (!empty($dp[$d['id_dentista']][$p['id_procedimento']])): ?><span class="tag tag-navy"><?= e($p['nome']) ?> · <?= moeda($dpValor[$d['id_dentista']][$p['id_procedimento']] ?? $p['valor']) ?></span><?php endif; ?>
            </div>
            <button class="btn btn-soft w-100" type="button" data-bs-toggle="collapse" data-bs-target="#ed<?= $d['id_dentista'] ?>" aria-expanded="false" aria-controls="ed<?= $d['id_dentista'] ?>"><i class="bi bi-pencil me-1"></i> Editar dados e horários</button>
            <form method="POST" enctype="multipart/form-data" class="collapse mt-3" id="ed<?= $d['id_dentista'] ?>">
                <?= csrf_campo() ?><input type="hidden" name="id_dentista" value="<?= $d['id_dentista'] ?>">
                <div class="row g-2">
                    <div class="col-12"><label class="form-label small">Nome</label><input name="nome" class="form-control" value="<?= e($d['nome']) ?>" required></div>
                    <div class="col-6"><label class="form-label small">CRO</label><input name="cro" class="form-control" value="<?= e($d['cro']) ?>"></div>
                    <div class="col-6"><label class="form-label small">Cor</label><input type="color" name="cor" class="form-control form-control-color w-100" value="<?= e($d['cor']) ?>"></div>
                    <div class="col-12"><label class="form-label small">Especialidade</label><input name="especialidade" class="form-control" value="<?= e($d['especialidade']) ?>"></div>
                    <div class="col-12"><label class="form-label small">Foto para o site <span class="text-muted fw-normal">(JPG/PNG/WEBP até 5 MB)</span></label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="form-control"></div>
                    <div class="col-6"><label class="form-label small">Anos de experiência</label><input type="number" min="0" max="60" name="anos_experiencia" class="form-control" value="<?= e($d['anos_experiencia']) ?>"></div>
                    <div class="col-6"><label class="form-label small">Formato</label><select name="atendimento" class="form-select"><?php foreach (['Presencial', 'Presencial e online'] as $fo): ?><option <?= ($d['atendimento'] ?: 'Presencial') === $fo ? 'selected' : '' ?>><?= $fo ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label small">Biografia (site)</label><textarea name="bio" class="form-control" rows="4" maxlength="2000"><?= e($d['bio']) ?></textarea></div>
                    <div class="col-12"><label class="form-label small">Formação</label><input name="formacao" class="form-control" maxlength="255" value="<?= e($d['formacao']) ?>"></div>
                    <div class="col-12"><label class="form-label small">Público que atende</label><input name="publico" class="form-control" maxlength="255" value="<?= e($d['publico']) ?>"></div>
                    <div class="col-12"><label class="form-label small">Principais tratamentos <span class="text-muted fw-normal">(um por linha)</span></label><textarea name="condicoes" class="form-control" rows="4" maxlength="2000"><?= e($d['condicoes']) ?></textarea></div>
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="ativo" value="1" id="at<?= $d['id_dentista'] ?>" <?= $d['ativo'] ? 'checked' : '' ?>><label class="form-check-label small" for="at<?= $d['id_dentista'] ?>">Aparece no site e aceita agendamentos</label></div></div>
                    <div class="col-12"><label class="form-label small">Dias de atendimento</label><div class="d-flex flex-wrap gap-2">
                        <?php foreach ([1,2,3,4,5,6] as $di): ?><label class="choice"><input type="checkbox" name="dias[]" value="<?= $di ?>" <?= !empty($diasD[$d['id_dentista']][$di]) ? 'checked' : '' ?>><span class="box py-1 px-2 small fw-semibold"><?= mb_substr($DIAS_SEMANA[$di], 0, 3) ?></span></label><?php endforeach; ?>
                    </div></div>
                    <div class="col-6"><label class="form-label small">Manhã: início</label><input type="time" name="m_ini" class="form-control" value="<?= e($m[0]) ?>"></div>
                    <div class="col-6"><label class="form-label small">Manhã: fim</label><input type="time" name="m_fim" class="form-control" value="<?= e($m[1]) ?>"></div>
                    <div class="col-6"><label class="form-label small">Tarde: início</label><input type="time" name="t_ini" class="form-control" value="<?= e($t[0]) ?>"></div>
                    <div class="col-6"><label class="form-label small">Tarde: fim</label><input type="time" name="t_fim" class="form-control" value="<?= e($t[1]) ?>"></div>
                    <div class="col-12"><label class="form-label small mb-0">Procedimentos que realiza e preço deste dentista</label>
                        <div class="form-text mt-0 mb-2">Deixe o preço em branco para usar o valor da tabela geral.</div>
                        <?php foreach ($procedimentos as $p): $chk = 'p' . $d['id_dentista'] . '_' . $p['id_procedimento']; $vp = $dpValor[$d['id_dentista']][$p['id_procedimento']] ?? null; ?>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <div class="form-check small flex-grow-1 mb-0"><input class="form-check-input" type="checkbox" name="procs[]" value="<?= $p['id_procedimento'] ?>" id="<?= $chk ?>" <?= !empty($dp[$d['id_dentista']][$p['id_procedimento']]) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= $chk ?>"><?= e($p['nome']) ?> <span class="text-muted">(<?= duracao_txt($p['duracao_min']) ?>)</span></label></div>
                                <label class="visually-hidden" for="v<?= $chk ?>">Preço de <?= e($p['nome']) ?> com <?= e($d['nome']) ?></label>
                                <input id="v<?= $chk ?>" type="number" name="preco[<?= (int) $p['id_procedimento'] ?>]" min="0" step="0.01" class="form-control form-control-sm" style="max-width:110px" placeholder="<?= number_format((float) $p['valor'], 0, ',', '.') ?>" value="<?= $vp !== null ? number_format((float) $vp, 2, '.', '') : '' ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-12"><button class="btn btn-teal w-100 mt-2">Salvar</button></div>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

<form method="POST" class="card-soft mt-4" id="precos">
    <?= csrf_campo() ?><input type="hidden" name="acao" value="precos">
    <div class="p-3 border-bottom d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <h2 class="panel-title">Tabela de procedimentos e preços</h2>
        <button class="btn btn-teal btn-sm"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Salvar preços</button>
    </div>
    <p class="small text-muted px-3 pt-3 mb-0">Valor padrão de cada procedimento. Cada dentista pode ter um preço próprio (em "Editar dados e horários"); o site mostra sempre o menor deles como "a partir de". Duração em minutos, de 15 em 15 — ela define os horários oferecidos.</p>
    <table class="table table-clean table-stack">
        <thead><tr><th>Procedimento</th><th style="width:150px">Duração (min)</th><th style="width:170px">Valor padrão (R$)</th><th class="d-none d-md-table-cell">Realizado por</th></tr></thead>
        <tbody><?php foreach ($procedimentos as $p): $pid = (int) $p['id_procedimento']; ?>
            <tr><td class="td-main"><i class="bi <?= e($p['icone']) ?> text-teal me-2" aria-hidden="true"></i><?= e($p['nome']) ?></td>
            <td data-label="Duração (min)"><label class="visually-hidden" for="du<?= $pid ?>">Duração de <?= e($p['nome']) ?></label><input id="du<?= $pid ?>" type="number" name="duracao[<?= $pid ?>]" min="15" max="480" step="15" value="<?= (int) $p['duracao_min'] ?>" class="form-control form-control-sm" style="max-width:120px"></td>
            <td data-label="Valor (R$)"><label class="visually-hidden" for="vl<?= $pid ?>">Valor de <?= e($p['nome']) ?></label><input id="vl<?= $pid ?>" type="number" name="valor[<?= $pid ?>]" min="0" step="0.01" value="<?= number_format((float) $p['valor'], 2, '.', '') ?>" class="form-control form-control-sm" style="max-width:140px"></td>
            <td data-label="Realizado por" class="d-none d-md-table-cell small"><?= e(implode(', ', array_map(fn($d) => primeiro_nome($d['nome']) . ($dpValor[$d['id_dentista']][$p['id_procedimento']] !== null ? ' (' . moeda($dpValor[$d['id_dentista']][$p['id_procedimento']]) . ')' : ''), array_filter($dentistas, fn($d) => !empty($dp[$d['id_dentista']][$p['id_procedimento']]))))) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table>
</form>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

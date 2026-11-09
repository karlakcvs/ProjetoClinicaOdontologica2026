<?php
// RF02 - Cadastro, consulta e atualização de pacientes (a secretaria completa CPF e endereço com busca automática de CEP)
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/conexao.php';

$campos = ['nome', 'telefone', 'email', 'data_nascimento', 'cpf', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'observacoes'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $id = (int) ($_POST['id_paciente'] ?? 0);
    $v = [];
    foreach ($campos as $c) $v[$c] = trim((string) ($_POST[$c] ?? ''));
    $v['cpf']  = preg_replace('/\D/', '', $v['cpf']);
    $v['cep']  = preg_replace('/\D/', '', $v['cep']);
    $v['uf']   = in_array(strtoupper($v['uf']), $UFS, true) ? strtoupper($v['uf']) : '';
    $v['data_nascimento'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['data_nascimento']) && $v['data_nascimento'] <= date('Y-m-d') ? $v['data_nascimento'] : '';

    $erro = '';
    if (mb_strlen($v['nome']) < 3) $erro = 'Informe o nome completo.';
    elseif (strlen(preg_replace('/\D/', '', $v['telefone'])) < 10) $erro = 'Informe um telefone válido com DDD.';
    elseif ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $erro = 'E-mail inválido.';
    elseif ($v['cpf'] !== '' && !cpf_valido($v['cpf'])) $erro = 'CPF inválido. Confira os números.';
    elseif ($v['cep'] !== '' && strlen($v['cep']) !== 8) $erro = 'O CEP deve ter 8 números.';
    if (!$erro && $v['cpf'] !== '') {
        $st = $pdo->prepare("SELECT nome FROM paciente WHERE cpf = ? AND id_paciente <> ?");
        $st->execute([cpf_formatar($v['cpf']), $id]);
        if ($dup = $st->fetchColumn()) $erro = 'Este CPF já está cadastrado para ' . $dup . '.';
    }
    if ($erro) {
        flash($erro, 'danger');
        header('Location: pacientes.php' . ($id ? '?editar=' . $id : ''));
        exit;
    }
    $v['cpf'] = $v['cpf'] !== '' ? cpf_formatar($v['cpf']) : '';
    $v['cep'] = $v['cep'] !== '' ? substr($v['cep'], 0, 5) . '-' . substr($v['cep'], 5) : '';
    $vals = array_map(fn($c) => $v[$c] === '' ? null : mb_substr($v[$c], 0, $c === 'observacoes' ? 2000 : 150), $campos);

    if ($id) {
        $sets = implode(', ', array_map(fn($c) => "$c = ?", $campos));
        $pdo->prepare("UPDATE paciente SET $sets WHERE id_paciente = ?")->execute([...$vals, $id]);
        flash('Cadastro de ' . $v['nome'] . ' atualizado.');
    } else {
        $pdo->prepare("INSERT INTO paciente (" . implode(',', $campos) . ") VALUES (" . implode(',', array_fill(0, count($campos), '?')) . ")")->execute($vals);
        flash('Paciente ' . $v['nome'] . ' cadastrado.');
    }
    header('Location: pacientes.php');
    exit;
}

$busca = trim($_GET['q'] ?? '');
$filtro = ($_GET['f'] ?? '') === 'incompletos' ? 'incompletos' : '';
$sql = "SELECT p.*,
          (SELECT COUNT(*) FROM consulta c WHERE c.id_paciente = p.id_paciente AND c.status = 'REALIZADA') AS atendimentos,
          (SELECT MAX(c.data) FROM consulta c WHERE c.id_paciente = p.id_paciente AND c.status = 'REALIZADA') AS ultima,
          (SELECT MIN(c.data) FROM consulta c WHERE c.id_paciente = p.id_paciente AND c.data >= ? AND c.status IN ('AGENDADA','CONFIRMADA')) AS proxima
        FROM paciente p WHERE 1=1";
$params = [date('Y-m-d')];
if ($busca !== '') { $sql .= " AND (p.nome LIKE ? OR p.telefone LIKE ? OR p.cpf LIKE ?)"; array_push($params, "%$busca%", "%$busca%", "%$busca%"); }
if ($filtro) $sql .= " AND (p.cpf IS NULL OR p.cep IS NULL OR p.numero IS NULL OR p.data_nascimento IS NULL)";
$sql .= " ORDER BY p.nome";
$st = $pdo->prepare($sql);
$st->execute($params);
$pacientes = $st->fetchAll();
$totalInc = (int) $pdo->query("SELECT COUNT(*) FROM paciente WHERE cpf IS NULL OR cep IS NULL OR numero IS NULL OR data_nascimento IS NULL")->fetchColumn();
$abrir = (int) ($_GET['editar'] ?? 0);

$titulo = 'Pacientes';
$pagina = 'pacientes';
$acoes_topo = '<button class="btn btn-teal btn-sm" data-bs-toggle="modal" data-bs-target="#mPac" data-novo="1"><i class="bi bi-person-plus me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Novo paciente</span></button>';
include __DIR__ . '/include/admin_topo.php';
?>
<form class="card-soft p-3 mb-3 d-flex flex-wrap gap-2" role="search">
    <label class="visually-hidden" for="q">Buscar paciente</label>
    <div class="input-icon flex-grow-1" style="min-width:200px"><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="q" name="q" value="<?= e($busca) ?>" class="form-control" placeholder="Nome, telefone ou CPF"></div>
    <?php if ($filtro): ?><input type="hidden" name="f" value="incompletos"><?php endif; ?>
    <button class="btn btn-navy">Buscar</button>
</form>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="pacientes.php" class="btn btn-sm <?= $filtro ? 'btn-ghost' : 'btn-navy' ?>">Todos</a>
    <a href="pacientes.php?f=incompletos" class="btn btn-sm <?= $filtro ? 'btn-navy' : 'btn-ghost' ?>"><i class="bi bi-person-vcard me-1" aria-hidden="true"></i>Cadastro incompleto (<?= $totalInc ?>)</a>
</div>

<div class="card-soft">
    <table class="table table-clean table-stack">
        <thead><tr><th>Paciente</th><th>Contato</th><th class="d-none d-lg-table-cell">Endereço</th><th class="d-none d-md-table-cell">Atend.</th><th>Próxima</th><th><span class="visually-hidden">Ações</span></th></tr></thead>
        <tbody>
        <?php foreach ($pacientes as $p): $ok = cadastro_completo($p); ?>
            <tr>
                <td class="td-main"><div class="d-flex align-items-center gap-2"><?= avatar_paciente($p, $p['foto'] ? 'md' : 'sm') ?>
                    <div class="min-w-0"><a href="paciente_resumo.php?id=<?= (int) $p['id_paciente'] ?>" class="fw-bold text-reset"><?= e($p['nome']) ?></a><br><span class="<?= $ok ? 'badge-ok' : 'badge-inc' ?>"><?= $ok ? 'Cadastro completo' : 'Cadastro incompleto' ?></span>
                    <?php if ($ok && $p['criado_em'] && $p['criado_em'] < date('Y-01-01', strtotime('-1 year'))): ?> <span class="paciente-desde"><i class="bi bi-award" aria-hidden="true"></i> desde <?= date('Y', strtotime($p['criado_em'])) ?></span><?php endif; ?>
                    <?php if ($p['cpf']): ?><span class="small text-muted ms-1"><?= e($p['cpf']) ?></span><?php endif; ?></div></div></td>
                <td data-label="Contato" class="small"><div><?= e($p['telefone']) ?><br><span class="text-muted"><?= e($p['email']) ?></span></div></td>
                <td data-label="Endereço" class="small d-none d-lg-table-cell"><div><?= $p['logradouro'] ? e($p['logradouro'] . ', ' . $p['numero'] . ' – ' . $p['bairro']) . '<br><span class="text-muted">' . e($p['cidade'] . '/' . $p['uf'] . ' · ' . $p['cep']) . '</span>' : '<span class="text-muted">—</span>' ?></div></td>
                <td data-label="Atendimentos" class="d-none d-md-table-cell"><?= (int) $p['atendimentos'] ?></td>
                <td data-label="Próxima consulta" class="small"><?= $p['proxima'] ? '<span class="status status-confirmada">' . data_br($p['proxima']) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                <td class="text-end text-nowrap td-acoes">
                    <a href="paciente_resumo.php?id=<?= (int) $p['id_paciente'] ?>" class="btn btn-sm btn-soft" title="Resumo do paciente"><i class="bi bi-person-lines-fill" aria-hidden="true"></i> Resumo</a>
                    <a href="lista_agendamentos.php?<?= e(http_build_query(['busca_nome' => $p['nome'], 'data' => date('Y-m-d', strtotime('-1 year')), 'ate' => date('Y-m-d', strtotime('+1 year'))])) ?>" class="btn btn-sm btn-ghost" title="Histórico de consultas"><i class="bi bi-clock-history" aria-hidden="true"></i> <span class="d-md-none">Histórico</span></a>
                    <button class="btn btn-sm <?= $ok ? 'btn-ghost' : 'btn-soft' ?>" type="button" data-bs-toggle="modal" data-bs-target="#mPac" data-paciente="<?= e(js(array_intersect_key($p, array_flip(array_merge(['id_paciente'], $campos))))) ?>" id="btnPac<?= (int) $p['id_paciente'] ?>">
                        <i class="bi bi-pencil" aria-hidden="true"></i> <?= $ok ? 'Editar' : 'Completar' ?>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$pacientes): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhum paciente encontrado.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="mPac" tabindex="-1" aria-labelledby="pTit" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down modal-dialog-scrollable">
        <form class="modal-content" method="POST" data-enviando="Salvando…">
            <?= csrf_campo() ?>
            <input type="hidden" name="id_paciente" id="f_id_paciente">
            <div class="modal-header"><h2 class="modal-title h5" id="pTit">Paciente</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 form-section">Dados pessoais</div>
                    <div class="col-md-6"><label class="form-label" for="f_nome">Nome completo</label><input name="nome" id="f_nome" class="form-control" required maxlength="120"></div>
                    <div class="col-6 col-md-3"><label class="form-label" for="f_cpf">CPF</label><input name="cpf" id="f_cpf" class="form-control" data-mask="cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00"></div>
                    <div class="col-6 col-md-3"><label class="form-label" for="f_data_nascimento">Nascimento</label><input type="date" name="data_nascimento" id="f_data_nascimento" class="form-control" max="<?= date('Y-m-d') ?>"></div>

                    <div class="col-12 form-section">Contato</div>
                    <div class="col-md-6"><label class="form-label" for="f_telefone">Telefone / WhatsApp</label><input name="telefone" id="f_telefone" class="form-control" required data-mask="tel" inputmode="tel" maxlength="16"></div>
                    <div class="col-md-6"><label class="form-label" for="f_email">E-mail</label><input type="email" name="email" id="f_email" class="form-control" maxlength="120"></div>

                    <div class="col-12 form-section">Endereço</div>
                    <div class="col-sm-5 col-md-4">
                        <label class="form-label" for="f_cep">CEP</label>
                        <div class="input-group">
                            <input name="cep" id="f_cep" class="form-control" data-mask="cep" data-cep inputmode="numeric" maxlength="9" placeholder="00000-000" aria-describedby="cepMsg">
                            <button class="btn btn-ghost" type="button" data-cep-buscar title="Buscar endereço pelo CEP" aria-label="Buscar endereço pelo CEP"><i class="bi bi-search" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="col-sm-7 col-md-8 d-flex align-items-end"><div class="form-text" id="cepMsg" data-cep-msg aria-live="polite">Digite o CEP: rua, bairro/setor, cidade e UF são preenchidos sozinhos.</div></div>
                    <div class="col-md-8"><label class="form-label" for="f_logradouro">Rua / Avenida</label><input name="logradouro" id="f_logradouro" class="form-control" data-cep-campo="logradouro" maxlength="150"></div>
                    <div class="col-5 col-md-4"><label class="form-label" for="f_numero">Número</label><input name="numero" id="f_numero" class="form-control" data-cep-campo="numero" maxlength="10"></div>
                    <div class="col-7 col-md-5"><label class="form-label" for="f_complemento">Complemento</label><input name="complemento" id="f_complemento" class="form-control" maxlength="60" placeholder="Apto, bloco…"></div>
                    <div class="col-md-7"><label class="form-label" for="f_bairro">Bairro / Setor</label><input name="bairro" id="f_bairro" class="form-control" data-cep-campo="bairro" maxlength="80"></div>
                    <div class="col-8 col-md-9"><label class="form-label" for="f_cidade">Cidade</label><input name="cidade" id="f_cidade" class="form-control" data-cep-campo="cidade" maxlength="80"></div>
                    <div class="col-4 col-md-3"><label class="form-label" for="f_uf">UF</label>
                        <select name="uf" id="f_uf" class="form-select" data-cep-campo="uf"><option value="">—</option><?php foreach ($UFS as $uf): ?><option><?= $uf ?></option><?php endforeach; ?></select></div>

                    <div class="col-12 form-section">Observações</div>
                    <div class="col-12"><label class="form-label visually-hidden" for="f_observacoes">Observações</label><textarea name="observacoes" id="f_observacoes" class="form-control" rows="2" maxlength="2000" placeholder="Alergias, preferências de contato..."></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-teal"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Salvar</button></div>
        </form>
    </div>
</div>
<?php
$scripts_extra = '<script>
(() => {
  const campos = ' . js(array_merge(['id_paciente'], $campos)) . ';
  const modal = document.getElementById("mPac");
  modal.addEventListener("show.bs.modal", ev => {
    const b = ev.relatedTarget;
    const d = b && b.dataset.paciente ? JSON.parse(b.dataset.paciente) : {};
    document.getElementById("pTit").textContent = d.id_paciente ? "Ficha de " + d.nome : "Novo paciente";
    campos.forEach(c => { const el = document.getElementById("f_" + c); if (el) el.value = d[c] || ""; });
    const msg = document.getElementById("cepMsg"); msg.className = "form-text"; msg.textContent = "Digite o CEP: rua, bairro/setor, cidade e UF são preenchidos sozinhos.";
  });
  const abrir = ' . $abrir . ';
  if (abrir) { const b = document.getElementById("btnPac" + abrir); if (b) window.addEventListener("load", () => b.click()); }
})();
</script>';
include __DIR__ . '/include/admin_fim.php';

<?php
// UC09 - Alterar / remarcar consulta (somente secretaria)
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/agendamento.php';

// 1. Captura o ID via GET para saber QUEM vamos editar
$id = (int) ($_GET['id'] ?? 0);
$ag = new Agendamento($pdo);
$dados = $ag->buscarPorId($id);
if (!$dados) { flash('Consulta não encontrada.', 'danger'); header('Location: lista_agendamentos.php'); exit; }

$erro_save = '';
// 2. Lógica de gravação (quando clicar em Salvar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $ag->id              = $id;
    $ag->id_dentista     = (int) $_POST['id_dentista'];
    $ag->id_procedimento = (int) $_POST['id_procedimento'];
    $ag->data            = $_POST['data_agendamento'] ?? '';
    $ag->hora            = $_POST['hora_agendamento'] ?? '';
    $ag->status          = array_key_exists($_POST['status'] ?? '', $STATUS_CONSULTA) ? $_POST['status'] : $dados['status'];
    $ag->observacoes     = trim($_POST['observacoes'] ?? '');

    $nome = trim($_POST['nome_paciente'] ?? '');
    $tel  = trim($_POST['telefone_paciente'] ?? '');

    if (mb_strlen($nome) < 3 || strlen(preg_replace('/\D/', '', $tel)) < 10) {
        $erro_save = 'Informe nome e telefone válidos.';
    } elseif (!preg_match('/^\d{2}:\d{2}/', $ag->hora)) {
        $erro_save = 'Escolha um horário disponível.';
    } else {
        // mesmo horário de antes não conta como conflito; verificação ignora a própria consulta
        $mudouHorario = $ag->data !== $dados['data'] || substr($ag->hora, 0, 5) !== hm($dados['hora_inicio'])
                     || $ag->id_dentista !== (int) $dados['id_dentista'] || $ag->id_procedimento !== (int) $dados['id_procedimento'];
        if ($mudouHorario && !$ag->atualizar()) {
            $erro_save = $ag->erro;
        } else {
            if (!$mudouHorario) {
                $pdo->prepare("UPDATE consulta SET status = ?, observacoes = ? WHERE id_consulta = ?")->execute([$ag->status, $ag->observacoes, $id]);
            }
            if ($ag->status !== 'REALIZADA') $pdo->prepare("DELETE FROM registro_financeiro WHERE id_consulta = ?")->execute([$id]);
            $pdo->prepare("UPDATE paciente SET nome = ?, telefone = ? WHERE id_paciente = ?")->execute([$nome, $tel, $dados['id_paciente']]);
            flash('Consulta #' . $id . ' atualizada.');
            header('Location: lista_agendamentos.php?data=' . $ag->data);
            exit;
        }
    }
    // mantém o que foi digitado
    $dados = array_merge($dados, ['id_dentista' => $ag->id_dentista, 'id_procedimento' => $ag->id_procedimento, 'data' => $ag->data,
                                  'status' => $ag->status, 'observacoes' => $ag->observacoes, 'paciente' => $nome, 'telefone' => $tel]);
}

$dentistas = $pdo->query("SELECT * FROM dentista ORDER BY id_dentista")->fetchAll();
$procedimentos = $pdo->query("SELECT * FROM procedimento ORDER BY nome")->fetchAll();
$mapa = [];
foreach ($pdo->query("SELECT * FROM dentista_procedimento")->fetchAll() as $r) $mapa[$r['id_procedimento']][] = (int) $r['id_dentista'];

$titulo = 'Editar consulta #' . $id;
$pagina = 'lista_agendamentos';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <?php if ($erro_save): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= e($erro_save) ?></div><?php endif; ?>
        <form method="POST" class="card-soft p-4">
            <?= csrf_campo() ?>
            <div class="row g-3">
                <div class="col-md-7"><label class="form-label">Paciente</label><input type="text" name="nome_paciente" class="form-control" value="<?= e($dados['paciente']) ?>" required></div>
                <div class="col-md-5"><label class="form-label">Telefone</label><input type="text" name="telefone_paciente" class="form-control" value="<?= e($dados['telefone']) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Procedimento</label>
                    <select name="id_procedimento" id="eProc" class="form-select">
                        <?php foreach ($procedimentos as $p): ?><option value="<?= $p['id_procedimento'] ?>" <?= (int) $dados['id_procedimento'] === (int) $p['id_procedimento'] ? 'selected' : '' ?>><?= e($p['nome']) ?> (<?= duracao_txt($p['duracao_min']) ?>)</option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-6"><label class="form-label">Dentista</label>
                    <select name="id_dentista" id="eDent" class="form-select">
                        <?php foreach ($dentistas as $d): ?><option value="<?= $d['id_dentista'] ?>" <?= (int) $dados['id_dentista'] === (int) $d['id_dentista'] ? 'selected' : '' ?>><?= e($d['nome']) ?> · <?= e($d['especialidade']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-6"><label class="form-label">Data</label><input type="date" name="data_agendamento" id="eData" class="form-control" value="<?= e($dados['data']) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Horário</label>
                    <select name="hora_agendamento" id="eHora" class="form-select" required data-atual="<?= hm($dados['hora_inicio']) ?>"></select>
                    <div class="form-text" id="eHoraInfo"></div></div>
                <div class="col-md-6"><label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($STATUS_CONSULTA as $k => $s): ?><option value="<?= $k ?>" <?= $dados['status'] === $k ? 'selected' : '' ?>><?= $s[0] ?></option><?php endforeach; ?>
                    </select>
                    <div class="form-text">Para lançar o valor pago use o botão “Atendido” na agenda.</div></div>
                <div class="col-12"><label class="form-label">Observações</label><textarea name="observacoes" class="form-control" rows="3"><?= e($dados['observacoes']) ?></textarea></div>
            </div>
            <hr class="my-4">
            <div class="d-flex justify-content-between gap-2">
                <a href="lista_agendamentos.php?data=<?= e($dados['data']) ?>" class="btn btn-ghost">Cancelar</a>
                <button type="submit" class="btn btn-teal"><i class="bi bi-check2 me-1"></i> Salvar alterações</button>
            </div>
        </form>
    </div>
</div>
<?php
$horaOriginal = hm($dados['hora_inicio']);
$scripts_extra = '<script>
(() => {
  const mapa = ' . js($mapa) . ';
  const id = ' . $id . ', orig = ' . js(['d' => (string) (int) $dados['id_dentista'], 'p' => (string) (int) $dados['id_procedimento'], 'data' => (string) $dados['data'], 'h' => $horaOriginal]) . ';
  const P = document.getElementById("eProc"), D = document.getElementById("eDent"), DT = document.getElementById("eData"), H = document.getElementById("eHora"), info = document.getElementById("eHoraInfo");
  function filtrar() {
    const ok = mapa[P.value] || [];
    [...D.options].forEach(o => o.disabled = !ok.includes(parseInt(o.value)));
    if (D.selectedOptions[0].disabled) { const p = [...D.options].find(o => !o.disabled); if (p) D.value = p.value; }
  }
  async function carregar() {
    const atual = H.value || H.dataset.atual;
    H.innerHTML = "<option>Carregando…</option>";
    const r = await fetch("api/horarios.php?" + new URLSearchParams({dentista: D.value, procedimento: P.value, data: DT.value, ignorar: id}));
    const j = await r.json();
    let lista = j.horarios || [];
    const mesmo = D.value === orig.d && P.value === orig.p && DT.value === orig.data;
    if (mesmo && !lista.find(h => h.inicio === orig.h)) lista.unshift({inicio: orig.h, fim: "(atual)"});
    H.innerHTML = lista.length ? lista.map(h => `<option value="${h.inicio}">${h.inicio} ${h.fim.startsWith("(") ? h.fim : "às " + h.fim}</option>`).join("") : "<option value=\"\">Sem horários livres</option>";
    if (lista.find(h => h.inicio === atual)) H.value = atual;
    info.textContent = lista.length + " horário(s) disponível(is) neste dia.";
  }
  P.addEventListener("change", () => { filtrar(); carregar(); });
  D.addEventListener("change", carregar); DT.addEventListener("change", carregar);
  filtrar(); carregar();
})();
</script>';
include __DIR__ . '/include/admin_fim.php';

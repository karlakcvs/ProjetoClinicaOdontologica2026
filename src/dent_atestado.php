<?php
// Área do DENTISTA: gerador de ATESTADO odontológico (comparecimento ou afastamento).
// Dados do dentista já vêm preenchidos; o paciente pode ser puxado da lista do dentista ou digitado.
// Pode abrir já preenchido a partir de uma consulta (?consulta=ID) ou de um paciente (?paciente=ID).
require_once __DIR__ . '/include/check_dentista.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/agendamento.php';

$st = $pdo->prepare("SELECT * FROM dentista WHERE id_dentista = ?");
$st->execute([$ID_DENTISTA]);
$eu = $st->fetch();

// pacientes do próprio dentista (só quem já teve consulta com ele)
$st = $pdo->prepare("SELECT DISTINCT p.id_paciente, p.nome, p.cpf, p.data_nascimento
                     FROM paciente p JOIN consulta c ON c.id_paciente = p.id_paciente
                     WHERE c.id_dentista = ? ORDER BY p.nome");
$st->execute([$ID_DENTISTA]);
$pacientes = [];
foreach ($st->fetchAll() as $p) {
    $pacientes[] = ['id' => (int) $p['id_paciente'], 'nome' => $p['nome'], 'cpf' => $p['cpf'] ? cpf_formatar($p['cpf']) : ''];
}

// pré-preenchimento
$pre = ['paciente' => '', 'cpf' => '', 'data' => date('Y-m-d'), 'ini' => '', 'fim' => '', 'proc' => ''];
$ag = new Agendamento($pdo);
if (!empty($_GET['consulta'])) {
    $c = $ag->buscarPorId((int) $_GET['consulta']);
    if ($c && (int) $c['id_dentista'] === $ID_DENTISTA) {
        $pre = ['paciente' => $c['paciente'], 'cpf' => $c['cpf'] ? cpf_formatar($c['cpf']) : '', 'data' => $c['data'],
                'ini' => substr($c['hora_inicio'], 0, 5), 'fim' => substr($c['hora_fim'], 0, 5), 'proc' => $c['procedimento']];
    }
} elseif (!empty($_GET['paciente'])) {
    $idP = (int) $_GET['paciente'];
    foreach ($pacientes as $p) if ($p['id'] === $idP) { $pre['paciente'] = $p['nome']; $pre['cpf'] = $p['cpf']; }
    // se o paciente tem consulta hoje com este dentista, já puxa horário e procedimento
    foreach ($ag->buscar('', date('Y-m-d'), (string) $ID_DENTISTA) as $c) {
        if ((int) $c['id_paciente'] === $idP && $c['status'] !== 'CANCELADA') {
            $pre['ini'] = substr($c['hora_inicio'], 0, 5); $pre['fim'] = substr($c['hora_fim'], 0, 5); $pre['proc'] = $c['procedimento']; break;
        }
    }
}

// consultas de hoje para o atalho "puxar da agenda de hoje"
$hojeLista = array_values(array_filter($ag->buscar('', date('Y-m-d'), (string) $ID_DENTISTA), fn($c) => !in_array($c['status'], ['CANCELADA', 'FALTOU'])));
$hojeJs = array_map(fn($c) => ['id' => (int) $c['id_consulta'], 'paciente' => $c['paciente'], 'cpf' => $c['cpf'] ? cpf_formatar($c['cpf']) : '',
    'ini' => substr($c['hora_inicio'], 0, 5), 'fim' => substr($c['hora_fim'], 0, 5), 'proc' => $c['procedimento']], $hojeLista);

// cidade do atestado: tirada do endereço da clínica (ex.: "..., Araras/SP - CEP ...")
$cidade = preg_match('/([A-Za-zÀ-ú\s]+\/[A-Z]{2})/u', $endereco, $mm) ? trim($mm[1]) : 'Araras/SP';
$cidade = trim(preg_replace('/^.*-\s*/u', '', $cidade));
// CRO já vem como "CRO-SP 00.001" no cadastro; só acrescenta "CRO" se faltar
$cro = trim((string) ($eu['cro'] ?? ''));
$croTxt = stripos($cro, 'CRO') === 0 ? $cro : 'CRO ' . $cro;

$titulo = 'Atestado';
$pagina = 'dent_atestado';
$acoes_topo = '<button type="button" class="btn btn-teal btn-sm" data-imprimir><i class="bi bi-printer me-1" aria-hidden="true"></i> Imprimir atestado</button>';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="row g-4 atestado-tela">
    <!-- FORMULÁRIO -->
    <div class="col-xl-5">
        <form class="card-soft panel" id="fAtestado" autocomplete="off" onsubmit="return false">
            <h2 class="panel-title mb-1">Dados do atestado</h2>
            <p class="small text-muted mb-3">Seus dados (<?= e($eu['nome'] ?? '') ?> · <?= e($croTxt) ?>) já entram automaticamente. O documento ao lado atualiza enquanto você digita.</p>

            <?php if ($hojeJs): ?>
            <label class="form-label small fw-semibold" for="aHoje">Puxar da agenda de hoje</label>
            <select id="aHoje" class="form-select mb-3">
                <option value="">— escolha um paciente de hoje (opcional) —</option>
                <?php foreach ($hojeJs as $h): ?><option value="<?= $h['id'] ?>"><?= e($h['ini']) ?> · <?= e($h['paciente']) ?> · <?= e($h['proc']) ?></option><?php endforeach; ?>
            </select>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label small fw-semibold" for="aPaciente">Paciente</label>
                <input id="aPaciente" class="form-control" list="listaPac" value="<?= e($pre['paciente']) ?>" placeholder="Comece a digitar o nome ou escreva livremente" required>
                <datalist id="listaPac"><?php foreach ($pacientes as $p): ?><option value="<?= e($p['nome']) ?>"></option><?php endforeach; ?></datalist>
                <div class="form-text">Puxa da sua lista de pacientes, mas você também pode digitar qualquer nome.</div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-sm-6"><label class="form-label small fw-semibold" for="aCpf">CPF / documento</label><input id="aCpf" class="form-control" value="<?= e($pre['cpf']) ?>" placeholder="Opcional"></div>
                <div class="col-sm-6"><label class="form-label small fw-semibold" for="aData">Data do atendimento</label><input id="aData" type="date" class="form-control" value="<?= e($pre['data']) ?>"></div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6"><label class="form-label small fw-semibold" for="aIni">Das</label><input id="aIni" type="time" class="form-control" value="<?= e($pre['ini']) ?>"></div>
                <div class="col-6"><label class="form-label small fw-semibold" for="aFim">Às</label><input id="aFim" type="time" class="form-control" value="<?= e($pre['fim']) ?>"></div>
            </div>

            <fieldset class="mb-3">
                <legend class="form-label small fw-semibold mb-1">Tipo de atestado</legend>
                <div class="d-flex flex-wrap gap-3">
                    <label class="form-check"><input class="form-check-input" type="radio" name="tipo" value="comparecimento" checked> <span class="form-check-label">Comparecimento (só o horário)</span></label>
                    <label class="form-check"><input class="form-check-input" type="radio" name="tipo" value="afastamento"> <span class="form-check-label">Afastamento (dias de repouso)</span></label>
                </div>
            </fieldset>
            <div class="mb-3" id="blocoDias" hidden>
                <label class="form-label small fw-semibold" for="aDias">Dias de afastamento</label>
                <input id="aDias" type="number" min="1" max="60" value="1" class="form-control" style="max-width:140px">
            </div>

            <div class="row g-2 mb-3">
                <div class="col-sm-5"><label class="form-label small fw-semibold" for="aCod">Código do procedimento</label><input id="aCod" class="form-control" placeholder="Ex.: TUSS 81000065" maxlength="40"></div>
                <div class="col-sm-7"><label class="form-label small fw-semibold" for="aProc">Procedimento realizado</label><input id="aProc" class="form-control" value="<?= e($pre['proc']) ?>" placeholder="Opcional" maxlength="120"></div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold" for="aCid">CID <span class="fw-normal text-muted">(opcional)</span></label>
                <input id="aCid" class="form-control" placeholder="Ex.: K08.8" maxlength="12" style="max-width:180px">
                <div class="form-text"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Só informe o CID com autorização do paciente.</div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-sm-6"><label class="form-label small fw-semibold" for="aCidade">Cidade</label><input id="aCidade" class="form-control" value="<?= e($cidade) ?>"></div>
                <div class="col-sm-6"><label class="form-label small fw-semibold" for="aEmissao">Data de emissão</label><input id="aEmissao" type="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-teal" data-imprimir><i class="bi bi-printer me-1" aria-hidden="true"></i> Imprimir / salvar em PDF</button>
                <button type="reset" class="btn btn-ghost" id="aLimpar"><i class="bi bi-eraser me-1" aria-hidden="true"></i> Limpar</button>
            </div>
            <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Para salvar em PDF, escolha <strong>"Salvar como PDF"</strong> na janela de impressão.</p>
        </form>
    </div>

    <!-- PRÉ-VISUALIZAÇÃO (é isto que sai na impressão) -->
    <div class="col-xl-7">
        <div class="atestado-wrap">
            <article class="atestado-folha" id="folha" aria-label="Pré-visualização do atestado">
                <header class="at-cab">
                    <img src="assets/img/logo-horizontal.png" alt="<?= e($nome_clinica) ?>" class="at-logo">
                    <div class="at-clinica"><?= e($endereco) ?><br><?= e($contato) ?> · <?= e($email_clinica) ?></div>
                </header>
                <h2 class="at-titulo" id="pTitulo">Atestado de comparecimento</h2>
                <p class="at-texto" id="pTexto"></p>
                <p class="at-texto at-extra" id="pExtra"></p>
                <p class="at-local" id="pLocal"></p>
                <div class="at-assin">
                    <div class="at-linha"></div>
                    <strong><?= e($eu['nome'] ?? '') ?></strong>
                    <span>Cirurgiã(o)-dentista · <?= e($croTxt) ?></span>
                    <span><?= e($eu['especialidade'] ?? '') ?></span>
                </div>
                <footer class="at-rod"><?= e($nome_clinica) ?> · <?= e($slogan_clinica) ?></footer>
            </article>
        </div>
    </div>
</div>

<?php
$scripts_extra = '<script>
(function () {
  const pacientes = ' . js($pacientes) . ';
  const hoje = ' . js($hojeJs) . ';
  const $ = id => document.getElementById(id);
  const esc = s => String(s || "").replace(/[&<>"]/g, m => ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[m]));
  const extenso = n => (["zero","um","dois","três","quatro","cinco","seis","sete","oito","nove","dez","onze","doze","treze","catorze","quinze",
    "dezesseis","dezessete","dezoito","dezenove","vinte"][n]) || (n >= 21 && n <= 29 ? "vinte e " + extenso(n - 20) : n === 30 ? "trinta" : String(n));
  const dataBr = v => v ? v.split("-").reverse().join("/") : "___/___/______";
  const meses = ["janeiro","fevereiro","março","abril","maio","junho","julho","agosto","setembro","outubro","novembro","dezembro"];
  const dataExt = v => { if (!v) return ""; const [a, m, d] = v.split("-"); return parseInt(d, 10) + " de " + meses[parseInt(m, 10) - 1] + " de " + a; };
  const tipo = () => document.querySelector("input[name=tipo]:checked").value;

  function atualizar() {
    const nome = $("aPaciente").value.trim(), cpf = $("aCpf").value.trim();
    const ini = $("aIni").value, fim = $("aFim").value, afast = tipo() === "afastamento";
    const dias = Math.max(1, Math.min(60, parseInt($("aDias").value, 10) || 1));
    $("blocoDias").hidden = !afast;
    $("pTitulo").textContent = afast ? "Atestado odontológico" : "Atestado de comparecimento";
    let t = "Atesto, para os devidos fins, que <strong>" + (esc(nome) || "______________________________") + "</strong>"
          + (cpf ? ", portador(a) do documento <strong>" + esc(cpf) + "</strong>," : "")
          + " esteve sob atendimento odontológico neste consultório no dia <strong>" + dataBr($("aData").value) + "</strong>"
          + (ini && fim ? ", das <strong>" + ini + "</strong> às <strong>" + fim + "</strong>" : ini ? ", a partir das <strong>" + ini + "</strong>" : "") + ".";
    if (afast) t += " Em decorrência do tratamento, necessita de <strong>" + dias + " (" + extenso(dias) + ") dia" + (dias > 1 ? "s" : "") + "</strong> de afastamento de suas atividades, a contar desta data.";
    $("pTexto").innerHTML = t;
    const cod = $("aCod").value.trim(), proc = $("aProc").value.trim(), cid = $("aCid").value.trim();
    const linhas = [];
    if (cod || proc) linhas.push("Procedimento realizado: " + (cod ? "<strong>" + esc(cod) + "</strong>" + (proc ? " – " : "") : "") + esc(proc) + ".");
    if (cid) linhas.push("CID: <strong>" + esc(cid) + "</strong> (informado com autorização do paciente).");
    $("pExtra").innerHTML = linhas.join("<br>");
    $("pExtra").hidden = !linhas.length;
    $("pLocal").textContent = ($("aCidade").value.trim() || "________________") + ", " + (dataExt($("aEmissao").value) || "___ de ________ de ______") + ".";
  }

  // ao escolher um paciente da lista, completa o CPF
  $("aPaciente").addEventListener("change", () => {
    const p = pacientes.find(x => x.nome.toLowerCase() === $("aPaciente").value.trim().toLowerCase());
    if (p && p.cpf && !$("aCpf").value) $("aCpf").value = p.cpf;
    atualizar();
  });
  // puxar da agenda de hoje
  if ($("aHoje")) $("aHoje").addEventListener("change", e => {
    const c = hoje.find(x => String(x.id) === e.target.value); if (!c) return;
    $("aPaciente").value = c.paciente; $("aCpf").value = c.cpf; $("aIni").value = c.ini; $("aFim").value = c.fim; $("aProc").value = c.proc;
    const d = new Date(); $("aData").value = d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
    atualizar();
  });
  document.getElementById("fAtestado").addEventListener("input", atualizar);
  document.getElementById("fAtestado").addEventListener("reset", () => setTimeout(atualizar, 0));
  document.querySelectorAll("[data-imprimir]").forEach(b => b.addEventListener("click", () => {
    if (!$("aPaciente").value.trim()) { $("aPaciente").focus(); $("aPaciente").reportValidity(); return; }
    window.print();
  }));
  atualizar();
})();
</script>';
include __DIR__ . '/include/admin_fim.php';

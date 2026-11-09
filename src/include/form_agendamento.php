<?php
// Formulário de agendamento em etapas (usado pelo paciente e pela secretaria)
// Espera: $pdo, $modoSecretaria (bool)
$dentistas = $pdo->query("SELECT * FROM dentista WHERE ativo = 1 ORDER BY id_dentista")->fetchAll();
$procedimentos = $pdo->query("SELECT * FROM procedimento ORDER BY CASE WHEN nome LIKE '%Primeira consulta%' THEN 0 ELSE 1 END, nome")->fetchAll();

$mapa = [];   // procedimento => [dentistas]
foreach ($pdo->query("SELECT * FROM dentista_procedimento")->fetchAll() as $r) $mapa[$r['id_procedimento']][] = (int) $r['id_dentista'];
$diasTrab = [];  // dentista => [dias da semana]
foreach ($pdo->query("SELECT DISTINCT id_dentista, dia_semana FROM horario_atendimento")->fetchAll() as $r) $diasTrab[$r['id_dentista']][] = (int) $r['dia_semana'];

$preProc = (int) ($_GET['procedimento'] ?? 0);
$preDent = (int) ($_GET['dentista'] ?? 0);
$prePrimeira = !empty($_GET['primeira']);
if ($prePrimeira && !$preProc) {
    foreach ($procedimentos as $p) if (stripos($p['nome'], 'Primeira consulta') !== false) $preProc = (int) $p['id_procedimento'];
}
$erroForm = $erroForm ?? '';
$pacientesLista = $modoSecretaria ? $pdo->query("SELECT id_paciente, nome, telefone, email FROM paciente ORDER BY nome")->fetchAll() : [];
?>
<?php if ($erroForm): ?>
    <div class="alert alert-danger d-flex gap-2"><i class="bi bi-exclamation-triangle"></i><div><?= e($erroForm) ?></div></div>
<?php endif; ?>

<div class="stepper" id="stepper">
    <span class="st on" data-s="1"><b>1</b> Procedimento</span>
    <span class="st" data-s="2"><b>2</b> Especialista</span>
    <span class="st" data-s="3"><b>3</b> Data e horário</span>
    <span class="st" data-s="4"><b>4</b> <?= $modoSecretaria ? 'Paciente' : 'Seus dados' ?></span>
</div>

<form action="confirmacao.php" method="POST" id="formAgendar" novalidate>
    <?= csrf_campo() ?>
    <input type="hidden" name="modo" value="<?= $modoSecretaria ? 'secretaria' : 'site' ?>">
    <input type="hidden" name="data_agendamento" id="inpData">
    <input type="hidden" name="hora_agendamento" id="inpHora">

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- 1. Procedimento -->
            <div class="card-soft booking-card mb-3">
                <h3 class="step-title"><span class="n">1</span> Qual procedimento você precisa?</h3>
                <div class="choice-grid">
                    <?php foreach ($procedimentos as $p): ?>
                    <label class="choice">
                        <input type="radio" name="id_procedimento" value="<?= $p['id_procedimento'] ?>"
                               data-nome="<?= e($p['nome']) ?>" data-dur="<?= $p['duracao_min'] ?>" data-valor="<?= moeda($p['valor']) ?>"
                               <?= $preProc === (int) $p['id_procedimento'] ? 'checked' : '' ?>>
                        <span class="box">
                            <span class="proc-ico flex-shrink-0" style="width:38px;height:38px;font-size:1rem"><i class="bi <?= e($p['icone']) ?>"></i></span>
                            <span>
                                <span class="t d-block"><?= e($p['nome']) ?></span>
                                <span class="s"><i class="bi bi-clock" aria-hidden="true"></i> <?= duracao_txt($p['duracao_min']) ?> · a partir de <?= moeda($p['valor']) ?></span>
                            </span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 2. Dentista -->
            <div class="card-soft booking-card mb-3" id="blocoDentista">
                <h3 class="step-title"><span class="n">2</span> Escolha o especialista</h3>
                <p class="small text-muted mb-3" id="dicaDentista">Selecione um procedimento para ver quem realiza.</p>
                <div class="choice-grid">
                    <?php foreach ($dentistas as $d): ?>
                    <label class="choice choice-dent">
                        <input type="radio" name="id_dentista" value="<?= $d['id_dentista'] ?>" data-nome="<?= e($d['nome']) ?>"
                               data-esp="<?= e($d['especialidade']) ?>" <?= $preDent === (int) $d['id_dentista'] ? 'checked' : '' ?>>
                        <span class="box align-items-center">
                            <?php if ($ft = foto_dentista($d)): ?><img src="<?= e($ft) ?>" alt="" class="avatar avatar-sm avatar-foto flex-shrink-0"><?php else: ?><span class="avatar avatar-sm flex-shrink-0" style="--c:<?= e($d['cor']) ?>"><?= e(iniciais($d['nome'])) ?></span><?php endif; ?>
                            <span><span class="t d-block"><?= e($d['nome']) ?></span><span class="s"><?= e($d['especialidade']) ?></span></span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 3. Data e horário -->
            <div class="card-soft booking-card mb-3">
                <h3 class="step-title"><span class="n">3</span> Data e horário</h3>
                <div class="date-strip" id="dateStrip"><span class="small text-muted">Escolha o procedimento e o especialista primeiro.</span></div>
                <div id="slotsArea" class="mt-2"></div>
            </div>

            <!-- 4. Dados -->
            <div class="card-soft booking-card">
                <h3 class="step-title"><span class="n">4</span> <?= $modoSecretaria ? 'Dados do paciente' : 'Seus dados' ?></h3>
                <?php if ($modoSecretaria): ?>
                    <div class="mb-3">
                        <label class="form-label">Paciente já cadastrado (opcional)</label>
                        <select class="form-select" id="selPaciente">
                            <option value="">— Novo paciente —</option>
                            <?php foreach ($pacientesLista as $pc): ?>
                                <option value="<?= $pc['id_paciente'] ?>" data-nome="<?= e($pc['nome']) ?>" data-tel="<?= e($pc['telefone']) ?>" data-email="<?= e($pc['email']) ?>">
                                    <?= e($pc['nome']) ?> · <?= e($pc['telefone']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-12">
                        <span class="form-label d-block" id="lblPrim"><?= $modoSecretaria ? 'É a primeira consulta do paciente na clínica?' : 'É sua primeira consulta na clínica?' ?></span>
                        <div class="d-flex flex-wrap gap-2" role="radiogroup" aria-labelledby="lblPrim">
                            <label class="choice"><input type="radio" name="primeira_consulta" value="1" <?= $prePrimeira ? 'checked' : '' ?>><span class="box py-2 px-3 small fw-semibold"><i class="bi bi-stars" aria-hidden="true"></i> Sim, primeira vez</span></label>
                            <label class="choice"><input type="radio" name="primeira_consulta" value="0" <?= $prePrimeira ? '' : 'checked' ?>><span class="box py-2 px-3 small fw-semibold"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Não, já sou paciente</span></label>
                        </div>
                        <div class="form-text" id="dicaPrim" <?= $prePrimeira ? '' : 'hidden' ?>>Na primeira consulta o especialista faz a avaliação completa e apresenta o plano de tratamento com valores. Traga um documento com foto.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="nome">Nome completo <span class="text-danger" aria-hidden="true">*</span></label>
                        <div class="input-icon"><i class="bi bi-person"></i><input type="text" id="nome" name="nome_paciente" class="form-control" required maxlength="120" placeholder="Nome e sobrenome" autocomplete="name" pattern="\S+(\s+\S+)+" title="Digite o nome completo (nome e sobrenome)" aria-describedby="nomeDica"></div>
                        <div class="form-text" id="nomeDica">Como está no documento: nome e sobrenome.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tel">Telefone / WhatsApp</label>
                        <div class="input-icon"><i class="bi bi-whatsapp"></i><input type="tel" id="tel" name="telefone_paciente" class="form-control" required placeholder="(19) 90000-0000" inputmode="tel" maxlength="16" autocomplete="tel"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">E-mail <span class="text-muted fw-normal">(opcional)</span></label>
                        <div class="input-icon"><i class="bi bi-envelope"></i><input type="email" id="email" name="email_paciente" class="form-control" placeholder="voce@exemplo.example" maxlength="120" autocomplete="email"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="obs">Observações <span class="text-muted fw-normal">(opcional)</span></label>
                        <textarea id="obs" name="observacoes" class="form-control" rows="2" maxlength="500" placeholder="Ex.: sinto sensibilidade ao frio, prefiro ser avisado por WhatsApp..."></textarea>
                    </div>
                    <?php if (!$modoSecretaria): ?>
                    <div class="col-12"><?= protecao_campo('agendamento') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Resumo -->
        <div class="col-lg-4">
            <aside class="card-soft summary">
                <h3 class="h5 mb-3">Resumo do agendamento</h3>
                <div class="row-i"><i class="bi bi-clipboard2-pulse"></i><div><div class="lbl">Procedimento</div><div id="rProc">—</div></div></div>
                <div class="row-i"><i class="bi bi-person-badge"></i><div><div class="lbl">Especialista</div><div id="rDent">—</div></div></div>
                <div class="row-i"><i class="bi bi-calendar3"></i><div><div class="lbl">Data</div><div id="rData">—</div></div></div>
                <div class="row-i"><i class="bi bi-clock"></i><div><div class="lbl">Horário</div><div id="rHora">—</div></div></div>
                <div class="row-i"><i class="bi bi-cash-coin" aria-hidden="true"></i><div><div class="lbl">Valor de referência</div><div id="rValor">—</div></div></div>
                <div class="info-pagamento mt-2"><i class="bi bi-shop" aria-hidden="true"></i><div><strong>Pagamento na clínica</strong>, no dia do atendimento: <?= e(implode(', ', $FORMAS_PAGAMENTO)) ?>. Nada é cobrado pelo site.</div></div>
                <button type="submit" class="btn btn-teal btn-lg w-100 mt-3" id="btnConfirmar" disabled>
                    <i class="bi bi-check2-circle me-1"></i> Confirmar agendamento
                </button>
                <p class="small text-muted text-center mt-2 mb-0" id="faltando">Preencha as etapas para confirmar.</p>
            </aside>
        </div>
    </div>
</form>

<script>
window.AGENDA = {
    mapa: <?= js($mapa) ?>,
    dias: <?= js($diasTrab) ?>,
    diasAFrente: 30
};
</script>
<script src="assets/js/agendar.js?v=11"></script>

<?php
// Agenda da clínica em formato de calendário semanal (secretaria vê todos; dentista vê só a própria agenda)
require_once __DIR__ . '/include/check_area.php';
require_once __DIR__ . '/include/conexao.php';

$ehDentista = perfil_atual() === 'DENTISTA';
$ref = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['semana'] ?? '') ? $_GET['semana'] : date('Y-m-d');
$seg = date('Y-m-d', strtotime('monday this week', strtotime($ref)));
$filtroDent = $ehDentista ? (int) $_SESSION['id_dentista'] : (int) ($_GET['dentista'] ?? 0);
// Cores: por tipo de procedimento (padrão do dentista) ou por dentista (padrão da secretaria)
$corPor = in_array($_GET['cor'] ?? '', ['tipo', 'dentista'], true) ? $_GET['cor'] : ($ehDentista || $filtroDent ? 'tipo' : 'dentista');

$dentistas = $pdo->query("SELECT id_dentista, nome, cor FROM dentista ORDER BY id_dentista")->fetchAll();
$temSabado = (int) $pdo->query("SELECT COUNT(*) FROM horario_atendimento WHERE dia_semana = 6")->fetchColumn() > 0;
$nDias = $temSabado ? 6 : 5;
$dias = [];
for ($i = 0; $i < $nDias; $i++) $dias[] = date('Y-m-d', strtotime("$seg +$i days"));

// faixa de horas exibida = do primeiro início ao último fim de expediente
$faixa = $pdo->query("SELECT MIN(hora_inicio) AS i, MAX(hora_fim) AS f FROM horario_atendimento")->fetch();
$h0 = max(6, (int) substr($faixa['i'] ?? '07:00', 0, 2));
$h1 = min(22, (int) ceil(((int) substr($faixa['f'] ?? '19:00', 0, 2) * 60 + (int) substr($faixa['f'] ?? '19:00', 3, 2)) / 60));
if ($h1 <= $h0) { $h0 = 7; $h1 = 19; }
$PX = 64; // altura de 1 hora em pixels

$sql = "SELECT c.*, p.nome AS paciente, d.nome AS dentista, d.cor, pr.nome AS procedimento
        FROM consulta c JOIN paciente p ON p.id_paciente = c.id_paciente JOIN dentista d ON d.id_dentista = c.id_dentista
        JOIN procedimento pr ON pr.id_procedimento = c.id_procedimento
        WHERE c.data BETWEEN ? AND ?";
$par = [$dias[0], end($dias)];
if ($filtroDent) { $sql .= " AND c.id_dentista = ?"; $par[] = $filtroDent; }
$sql .= " ORDER BY c.hora_inicio";
$st = $pdo->prepare($sql);
$st->execute($par);
$porDia = [];
foreach ($st->fetchAll() as $c) $porDia[$c['data']][] = $c;

// consultas que se sobrepõem no mesmo dia (dentistas diferentes) ficam lado a lado;
// as que não se sobrepõem usam a largura toda da coluna
function colunas_sobrepostas(array $lista) {
    $pos = []; $grupo = []; $fimGrupo = ''; $fimCol = [];
    $fechar = function () use (&$grupo, &$pos, &$fimCol) {
        $n = max(1, count($fimCol));
        foreach ($grupo as $i => $col) $pos[$i] = [$col, $n];
        $grupo = []; $fimCol = [];
    };
    foreach ($lista as $i => $c) {
        if ($grupo && $c['hora_inicio'] >= $fimGrupo) $fechar();
        $col = 0;
        while (isset($fimCol[$col]) && $fimCol[$col] > $c['hora_inicio']) $col++;
        $fimCol[$col] = $c['hora_fim'];
        $grupo[$i] = $col;
        if ($c['hora_fim'] > $fimGrupo || count($grupo) === 1) $fimGrupo = max($fimGrupo, $c['hora_fim']);
    }
    if ($grupo) $fechar();
    return $pos;
}

$hoje = date('Y-m-d');
$dw = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$ant = date('Y-m-d', strtotime("$seg -7 days")); $prox = date('Y-m-d', strtotime("$seg +7 days"));
$q = fn($s, $c = null) => 'agenda_semana.php?' . http_build_query(array_filter(['semana' => $s, 'dentista' => $ehDentista ? null : ($filtroDent ?: null), 'cor' => $c ?? $corPor]));
$total = array_sum(array_map('count', $porDia));
$diaAtivo = in_array($hoje, $dias, true) ? $hoje : $dias[0];

$titulo = $ehDentista ? 'Minha agenda' : 'Agenda da clínica';
$pagina = 'agenda_semana';
$acoes_topo = $ehDentista ? '' : '<a href="nova_consulta.php" class="btn btn-teal btn-sm"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Nova consulta</span></a>';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="cal-head">
    <div class="d-flex align-items-center gap-1">
        <a href="<?= e($q($ant)) ?>" class="btn btn-icon btn-ghost" aria-label="Semana anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
        <a href="<?= e($q($hoje)) ?>" class="btn btn-sm btn-ghost">Hoje</a>
        <a href="<?= e($q($prox)) ?>" class="btn btn-icon btn-ghost" aria-label="Próxima semana"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
    </div>
    <strong class="px-1"><?= date('d/m', strtotime($dias[0])) ?> a <?= date('d/m/Y', strtotime(end($dias))) ?> <span class="text-muted fw-normal small">· <?= $total ?> consulta(s)</span></strong>
    <div class="btn-group btn-group-sm ms-md-auto" role="group" aria-label="Colorir a agenda por">
        <a href="<?= e($q($seg, 'tipo')) ?>" class="btn <?= $corPor === 'tipo' ? 'btn-navy' : 'btn-ghost' ?>" <?= $corPor === 'tipo' ? 'aria-current="true"' : '' ?>>Cor por tipo</a>
        <?php if (!$ehDentista): ?><a href="<?= e($q($seg, 'dentista')) ?>" class="btn <?= $corPor === 'dentista' ? 'btn-navy' : 'btn-ghost' ?>" <?= $corPor === 'dentista' ? 'aria-current="true"' : '' ?>>Cor por dentista</a><?php endif; ?>
    </div>
    <?php if (!$ehDentista): ?>
    <form method="GET" class="d-flex gap-2 align-items-center">
        <input type="hidden" name="semana" value="<?= e($seg) ?>"><input type="hidden" name="cor" value="<?= e($corPor) ?>">
        <label class="visually-hidden" for="fDent">Filtrar por dentista</label>
        <select id="fDent" name="dentista" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Toda a clínica</option>
            <?php foreach ($dentistas as $d): ?><option value="<?= (int) $d['id_dentista'] ?>" <?= $filtroDent === (int) $d['id_dentista'] ? 'selected' : '' ?>><?= e($d['nome']) ?></option><?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-sm btn-navy">Filtrar</button></noscript>
    </form>
    <?php endif; ?>
</div>

<div class="cal-tabs" role="tablist" aria-label="Escolha o dia">
    <?php foreach ($dias as $d): ?>
        <button type="button" class="cal-tab" role="tab" data-dia="<?= $d ?>" aria-selected="<?= $d === $diaAtivo ? 'true' : 'false' ?>">
            <small><?= $d === $hoje ? 'Hoje' : $dw[(int) date('w', strtotime($d))] ?></small><?= date('d', strtotime($d)) ?>
        </button>
    <?php endforeach; ?>
</div>

<div class="card-soft cal-wrap">
    <div class="cal" style="--dias:<?= $nDias ?>;--hora:<?= $PX ?>px">
        <div class="cal-corner"></div>
        <?php foreach ($dias as $d): ?>
            <div class="cal-dayhead <?= $d === $hoje ? 'hoje' : '' ?> <?= $d === $diaAtivo ? 'ativo' : '' ?>" data-dia="<?= $d ?>">
                <div class="dw"><?= $dw[(int) date('w', strtotime($d))] ?></div><div class="dn"><?= date('d', strtotime($d)) ?></div>
            </div>
        <?php endforeach; ?>

        <div class="cal-hours" style="height:<?= ($h1 - $h0) * $PX ?>px" aria-hidden="true">
            <?php for ($h = $h0 + 1; $h < $h1; $h++): ?><span style="top:<?= ($h - $h0) * $PX ?>px"><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00</span><?php endfor; ?>
        </div>
        <?php foreach ($dias as $d): $lista = $porDia[$d] ?? []; $pos = colunas_sobrepostas($lista); ?>
            <div class="cal-col <?= $d === $hoje ? 'hoje' : '' ?> <?= $d === $diaAtivo ? 'ativo' : '' ?>" data-dia="<?= $d ?>" style="height:<?= ($h1 - $h0) * $PX ?>px" role="list" aria-label="Consultas de <?= data_br($d) ?>">
                <?php if ($d === $hoje): $agora = (int) date('H') * 60 + (int) date('i'); if ($agora > $h0 * 60 && $agora < $h1 * 60): ?>
                    <div class="cal-now" style="top:<?= round(($agora - $h0 * 60) / 60 * $PX) ?>px" aria-hidden="true"></div>
                <?php endif; endif; ?>
                <?php foreach ($lista as $i => $c):
                    $ini = (int) substr($c['hora_inicio'], 0, 2) * 60 + (int) substr($c['hora_inicio'], 3, 2);
                    $fim = (int) substr($c['hora_fim'], 0, 2) * 60 + (int) substr($c['hora_fim'], 3, 2);
                    $top = max(0, ($ini - $h0 * 60) / 60 * $PX); $alt = max(26, ($fim - $ini) / 60 * $PX - 3);
                    [$colI, $nCols] = $pos[$i]; $w = 100 / $nCols; $left = $colI * $w;
                    $link = $ehDentista ? 'dent_paciente.php?id=' . (int) $c['id_paciente'] : 'editar.php?id=' . (int) $c['id_consulta'];
                    $stt = $STATUS_CONSULTA[$c['status']][0] ?? $c['status'];
                    $cat = categoria_proc($c['procedimento']);
                    $corEv = $corPor === 'tipo' ? CATEGORIAS_PROC[$cat][1] : $c['cor']; ?>
                    <a href="<?= $link ?>" role="listitem" class="cal-ev st-<?= strtolower($c['status']) ?>"
                       style="--c:<?= e($corEv) ?>;top:<?= round($top) ?>px;height:<?= round($alt) ?>px;left:calc(<?= $left ?>% + 4px);right:auto;width:calc(<?= $w ?>% - 8px)"
                       title="<?= e(hm($c['hora_inicio']) . '–' . hm($c['hora_fim']) . ' · ' . $c['paciente'] . ' · ' . $c['procedimento'] . ' · ' . $c['dentista'] . ' · ' . $stt) ?>">
                        <b><?= hm($c['hora_inicio']) ?></b>
                        <span class="nm"><?= e($c['paciente']) ?></span>
                        <?php if ($alt > 50): ?><span class="pr"><?= e($c['procedimento']) ?></span><?php endif; ?>
                        <span class="visually-hidden"><?= e($c['dentista'] . ', ' . $stt) ?></span>
                        <?php if ($c['status'] === 'CONFIRMADA'): ?><i class="bi bi-check2-circle ico text-success" aria-hidden="true"></i><?php elseif ($c['status'] === 'REALIZADA'): ?><i class="bi bi-patch-check ico" style="color:#217a3c" aria-hidden="true"></i><?php elseif ($c['primeira_consulta']): ?><i class="bi bi-stars ico" aria-hidden="true"></i><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="cal-legend mt-3">
    <?php if ($corPor === 'tipo'): foreach (CATEGORIAS_PROC as [$rot, $cor]): ?><span><span class="legend-dot me-1" style="background:<?= e($cor) ?>"></span><?= e($rot) ?></span><?php endforeach;
          else: foreach ($dentistas as $d): ?><span><span class="legend-dot me-1" style="background:<?= e($d['cor']) ?>"></span><?= e($d['nome']) ?></span><?php endforeach; endif; ?>
    <span><span class="legend-dot me-1" style="background:#fff;border:1px dashed #9aa5b1"></span>cancelada / faltou (apagada)</span>
    <span><i class="bi bi-check2-circle text-success" aria-hidden="true"></i> confirmada</span>
    <span><i class="bi bi-patch-check" style="color:#217a3c" aria-hidden="true"></i> realizada</span>
    <span><i class="bi bi-stars" aria-hidden="true"></i> 1ª consulta</span>
    <span class="text-muted">Clique na consulta para <?= $ehDentista ? 'abrir o prontuário do paciente' : 'editar ou remarcar' ?>.</span>
</div>
<?php
$scripts_extra = '<script>
(() => {
  const tabs = document.querySelectorAll(".cal-tab");
  tabs.forEach(t => t.addEventListener("click", () => {
    tabs.forEach(x => x.setAttribute("aria-selected", x === t ? "true" : "false"));
    document.querySelectorAll(".cal-dayhead, .cal-col").forEach(el => el.classList.toggle("ativo", el.dataset.dia === t.dataset.dia));
  }));
})();
</script>';
include __DIR__ . '/include/admin_fim.php';

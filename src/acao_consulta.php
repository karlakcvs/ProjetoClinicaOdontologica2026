<?php
// Ações rápidas da secretaria sobre uma consulta: confirmar, faltou, cancelar, registrar pagamento
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/agendamento.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: lista_agendamentos.php'); exit; }
csrf_validar();

$id    = (int) ($_POST['id'] ?? 0);
$acao  = $_POST['acao'] ?? '';
$ag    = new Agendamento($pdo);
$c     = $ag->buscarPorId($id);
$volta = url_local($_POST['voltar'] ?? '');

if (!$c) { flash('Consulta não encontrada.', 'danger'); header("Location: $volta"); exit; }

if ($acao === 'PAGAMENTO') {
    $valor = (float) str_replace(',', '.', $_POST['valor'] ?? '0');
    $forma = in_array($_POST['forma'] ?? '', $FORMAS_PAGAMENTO, true) ? $_POST['forma'] : 'PIX';
    if ($valor < 0) $valor = 0;
    $ag->registrarPagamento($id, $valor, $forma);
    flash('Atendimento de ' . $c['paciente'] . ' registrado: ' . moeda($valor) . ' (' . $forma . ').');
} elseif (in_array($acao, ['AGENDADA', 'CONFIRMADA', 'CANCELADA', 'FALTOU'], true)) {
    $ag->alterarStatus($id, $acao);
    flash('Consulta de ' . $c['paciente'] . ' marcada como ' . strtolower($STATUS_CONSULTA[$acao][0]) . '.', $acao === 'CONFIRMADA' ? 'success' : 'info');
}
header("Location: $volta");
exit;

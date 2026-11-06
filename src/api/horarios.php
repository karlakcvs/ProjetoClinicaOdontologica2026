<?php
// Retorna (em JSON) os horários livres de um dentista para um procedimento numa data.
// Ex.: api/horarios.php?dentista=1&procedimento=2&data=2026-11-03
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/conexao.php';
require_once __DIR__ . '/../classes/agendamento.php';

header('Content-Type: application/json; charset=utf-8');

$idDentista = (int) ($_GET['dentista'] ?? 0);
$idProc     = (int) ($_GET['procedimento'] ?? 0);
$data       = $_GET['data'] ?? '';
$ignorar    = isset($_GET['ignorar']) ? (int) $_GET['ignorar'] : null;

$ag = new Agendamento($pdo);
if (!$idDentista || !$idProc || !$ag->dentistaRealiza($idDentista, $idProc)) {
    echo json_encode(['ok' => false, 'horarios' => [], 'msg' => 'Combinação de dentista e procedimento inválida.']);
    exit;
}
echo json_encode(['ok' => true, 'horarios' => $ag->horariosDisponiveis($idDentista, $idProc, $data, $ignorar)]);

<?php
// Exclui uma consulta (somente secretaria, via POST)
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';
require_once __DIR__ . '/classes/agendamento.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id) {
        (new Agendamento($pdo))->excluir($id);
        flash('Consulta excluída.', 'warning');
    }
}
header("Location: " . url_local($_POST["voltar"] ?? ""));
exit;

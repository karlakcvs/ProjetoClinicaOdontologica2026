<?php
// Protege as páginas da área do DENTISTA: cada dentista só vê os próprios pacientes
require_once __DIR__ . '/config.php';
exigir_login(['DENTISTA']);
$ID_DENTISTA = (int) ($_SESSION['id_dentista'] ?? 0);
if (!$ID_DENTISTA) { $_SESSION = []; header('Location: login.php'); exit; }

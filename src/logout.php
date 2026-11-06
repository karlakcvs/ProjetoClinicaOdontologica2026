<?php
// Sai do sistema: só aceita POST com token (um link malicioso não consegue deslogar ninguém)
require_once __DIR__ . '/include/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: login.php?saiu=1');
    exit;
}
header('Location: ' . (!empty($_SESSION['logado']) ? pagina_inicial_perfil(perfil_atual()) : 'login.php'));
exit;

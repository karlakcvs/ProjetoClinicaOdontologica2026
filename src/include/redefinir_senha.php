<?php
// =====================================================================
//  Redefinir senha (substitui o antigo gerar_senha.php, que qualquer pessoa
//  podia abrir no navegador). Este script SÓ roda pelo terminal, dentro do Docker:
//
//  docker exec -it php_apache php /var/www/html/include/redefinir_senha.php secretaria@garciadimase.example "NovaSenha#2026"
// =====================================================================
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';

[$script, $email, $senha] = array_pad($argv, 3, '');
if ($email === '' || $senha === '') {
    echo "Uso: php include/redefinir_senha.php <email> <nova senha>\n";
    echo "Usuários cadastrados:\n";
    foreach ($pdo->query("SELECT email, perfil FROM usuario ORDER BY perfil, email") as $u) echo "  - {$u['email']} ({$u['perfil']})\n";
    exit(1);
}
if ($m = senha_forte($senha)) { echo "Senha fraca: $m\n"; exit(1); }

$st = $pdo->prepare("UPDATE usuario SET senha = ? WHERE LOWER(email) = LOWER(?)");
$st->execute([password_hash($senha, PASSWORD_DEFAULT), $email]);
if ($st->rowCount()) {
    $pdo->prepare("DELETE FROM limite_acao WHERE acao = 'login'")->execute();   // libera bloqueios de login
    echo "Senha de $email alterada com sucesso.\n";
} else {
    echo "Usuário $email não encontrado.\n";
    exit(1);
}

<?php
// Utilitário (igual ao do projeto da barbearia): recria o login da secretaria.
// Acesse http://localhost/gerar_senha.php uma vez caso esqueça a senha.
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';

$usuario    = 'secretaria';
$senha_pura = 'secretaria123';
$senha_hash = password_hash($senha_pura, PASSWORD_DEFAULT);

try {
    $pdo->prepare("DELETE FROM usuario WHERE email = :u")->execute([':u' => $usuario]);
    $pdo->prepare("INSERT INTO usuario (nome, email, senha, perfil) VALUES ('Juliana Prado', :u, :s, 'SECRETARIA')")
        ->execute([':u' => $usuario, ':s' => $senha_hash]);
    echo "<h3>Usuário '$usuario' recriado com sucesso! Senha: $senha_pura</h3><a href='login.php'>Ir para o login</a>";
} catch (PDOException $e) {
    echo "Erro: " . htmlspecialchars($e->getMessage());
}

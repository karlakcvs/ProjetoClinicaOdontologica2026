<?php
// =====================================================================
//  CONEXÃO COM O BANCO
//  As credenciais são fornecidas pelo ambiente (arquivo .env do Docker Compose).
// =====================================================================
$host   = getenv('DB_HOST') ?: "db";
$dbname = getenv('DB_NAME') ?: "barbearia";
$user   = getenv('DB_USER') ?: "";
$pass   = getenv('DB_PASSWORD') ?: "";

$pdo = null;
$ultimo_erro = '';

if (getenv('DB_SQLITE')) {
    // Apenas para testes locais sem Docker
    $pdo = new PDO('sqlite:' . getenv('DB_SQLITE'));
    $pdo->exec('PRAGMA foreign_keys = ON');
} else {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_EMULATE_PREPARES => false]);
    } catch (PDOException $e) {
        $ultimo_erro = $e->getMessage();
        $pdo = null;
    }
}

if (!$pdo) {
    // O detalhe técnico vai só para o log do servidor (não mostra senha/host para o visitante)
    error_log('[clinica] Falha na conexão com o banco: ' . $ultimo_erro);
    http_response_code(503);
    die("<div style='font-family:sans-serif;padding:30px'><h2>Sistema temporariamente indisponível</h2>
         <p>Não foi possível conectar ao banco de dados. Se você é o administrador, verifique se os containers estão rodando:
         <code>docker compose up -d</code> (detalhes em <code>docker compose logs web</code>).</p></div>");
}

// Ativa o modo de erro para exceções
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
    $pdo->exec("SET time_zone = '-03:00'");
}

// Cria as tabelas na primeira vez e aplica as atualizações do banco (migrações)
require_once __DIR__ . '/instalar.php';
instalar_banco($pdo);

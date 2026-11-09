<?php
// Autenticação da área restrita (secretaria e dentistas)
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: login.php'); exit; }

function volta($erro) { header('Location: login.php?erro=' . $erro); exit; }
$volta = 'volta';

// 1. Formulário expirado ou enviado de outro site
if (!csrf_ok()) $volta('expirado');

// 2. RATE LIMITING: 4 tentativas erradas em 15 min bloqueiam este IP (força bruta)
if (limite_excedido($pdo, 'login')) $volta('bloqueado');

// 3. Campo-isca e envio rápido demais = robô (conta como tentativa)
if (protecao_validar('login', 1) !== '') { limite_registrar($pdo, 'login'); $volta('robo'); }

$user = mb_strtolower(trim((string) ($_POST['usuario'] ?? '')));
$pass = (string) ($_POST['senha'] ?? '');

// 4. Busca o usuário (consulta preparada = sem SQL injection)
$stmt = $pdo->prepare("SELECT * FROM usuario WHERE LOWER(email) = :u AND ativo = 1");
$stmt->execute([':u' => $user]);
$u = $stmt->fetch();

// password_verify roda mesmo se o usuário não existir (tempo de resposta igual)
$hash = $u['senha'] ?? '$2y$10$usuarioInexistenteXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
if (!$u || !password_verify($pass, $hash) || !in_array($u['perfil'], ['SECRETARIA', 'DENTISTA'], true)) {
    limite_registrar($pdo, 'login');
    $volta(limite_excedido($pdo, 'login') ? 'bloqueado' : '1');
}

// 5. LOGIN OK: nova sessão (evita sequestro de sessão) e dados mínimos
limite_limpar($pdo, 'login');
if (password_needs_rehash($u['senha'], PASSWORD_DEFAULT)) {
    $pdo->prepare("UPDATE usuario SET senha = ? WHERE id_usuario = ?")->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id_usuario']]);
}
$pdo->prepare("UPDATE usuario SET ultimo_acesso = ? WHERE id_usuario = ?")->execute([date('Y-m-d H:i:s'), $u['id_usuario']]);

session_regenerate_id(true);
$_SESSION = [];
$_SESSION['logado']      = true;
$_SESSION['usuario_id']  = (int) $u['id_usuario'];
$_SESSION['nome']        = $u['nome'];
$_SESSION['perfil']      = $u['perfil'];
$_SESSION['id_dentista'] = $u['perfil'] === 'DENTISTA' ? (int) $u['id_dentista'] : null;
$_SESSION['ultimo_uso']  = time();
$_SESSION['agente']      = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
$_SESSION['acesso_anterior'] = $u['ultimo_acesso'] ?? null;

header('Location: ' . pagina_inicial_perfil($u['perfil']));
exit;

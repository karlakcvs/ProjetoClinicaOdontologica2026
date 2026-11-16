<?php
// Recebe a avaliação (estrelas + comentário) enviada pelo paciente no site.
// A avaliação fica PENDENTE até a secretaria aprovar (evita spam e ofensas).
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php#avaliacoes'); exit; }

$nome = trim(strip_tags((string) ($_POST['nome'] ?? '')));
$com  = trim(strip_tags((string) ($_POST['comentario'] ?? '')));
$nota = (int) ($_POST['nota'] ?? 0);
$idD  = (int) ($_POST['id_dentista'] ?? 0) ?: null;
$desde = (int) ($_POST['paciente_desde'] ?? 0);
if ($desde < 1980 || $desde > (int) date('Y')) $desde = null;
$antigos = ['nome' => $nome, 'comentario' => $com, 'nota' => $nota, 'id_dentista' => $idD, 'paciente_desde' => $desde];

$erro = '';
if (!csrf_ok()) $erro = 'O formulário expirou. Tente novamente.';
elseif (($lim = limite_status($pdo, 'avaliacao'))['bloqueado']) $erro = limite_mensagem($lim);   // rate limiting: 4 por hora
elseif ($m = protecao_validar('avaliacao')) $erro = $m;
elseif ($nota < 1 || $nota > 5) $erro = 'Escolha de 1 a 5 estrelas.';
elseif (mb_strlen($nome) < 2) $erro = 'Informe seu nome.';
elseif (mb_strlen($com) < 10) $erro = 'Escreva um comentário com pelo menos 10 caracteres.';
elseif (preg_match('#https?://|www\.#i', $com)) $erro = 'Por segurança, não é permitido enviar links no comentário.';

if (!$erro && $idD) {
    $st = $pdo->prepare("SELECT 1 FROM dentista WHERE id_dentista = ? AND ativo = 1");
    $st->execute([$idD]);
    if (!$st->fetchColumn()) $idD = null;
}

if ($erro) {
    aviso_publico('avaliacao', $erro, 'danger', $antigos);
} else {
    $pdo->prepare("INSERT INTO avaliacao (id_dentista, nome, nota, comentario, status, paciente_desde) VALUES (?,?,?,?, 'PENDENTE', ?)")
        ->execute([$idD, mb_substr($nome, 0, 80), $nota, mb_substr($com, 0, 600), $desde]);
    limite_registrar($pdo, 'avaliacao');
    aviso_publico('avaliacao', 'Obrigado pela sua avaliação! Ela será publicada após a conferência da clínica.');
}
header('Location: index.php#avaliacoes');
exit;

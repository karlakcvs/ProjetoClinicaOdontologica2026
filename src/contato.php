<?php
// Formulário "Fale conosco": salva a mensagem para a secretaria e tenta enviar e-mail para a clínica.
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php#contato'); exit; }

$nome  = trim(strip_tags((string) ($_POST['nome'] ?? '')));
$email = trim((string) ($_POST['email'] ?? ''));
$tel   = trim(preg_replace('/[^\d() +-]/', '', (string) ($_POST['telefone'] ?? '')));
$assuntos = ['Dúvida sobre tratamento', 'Valores e pagamento', 'Remarcar ou cancelar consulta', 'Elogio ou sugestão', 'Outro assunto'];
$assunto = in_array($_POST['assunto'] ?? '', $assuntos, true) ? $_POST['assunto'] : 'Outro assunto';
$msg   = trim(strip_tags((string) ($_POST['mensagem'] ?? '')));
$antigos = compact('nome', 'email', 'assunto') + ['telefone' => $tel, 'mensagem' => $msg];

$erro = '';
if (!csrf_ok()) $erro = 'O formulário expirou. Tente novamente.';
elseif (limite_excedido($pdo, 'contato', 3, 30)) $erro = 'Você enviou várias mensagens em pouco tempo. Aguarde alguns minutos.';
elseif ($m = captcha_validar('contato')) $erro = $m;
elseif (mb_strlen($nome) < 3) $erro = 'Informe seu nome.';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'Informe um e-mail válido.';
elseif (mb_strlen($msg) < 10) $erro = 'Escreva uma mensagem com pelo menos 10 caracteres.';
elseif (preg_match_all('#https?://#i', $msg) > 2) $erro = 'Mensagem com links demais.';

if ($erro) {
    aviso_publico('contato', $erro, 'danger', $antigos);
    header('Location: index.php#contato');
    exit;
}

$pdo->prepare("INSERT INTO mensagem_contato (nome, email, telefone, assunto, mensagem) VALUES (?,?,?,?,?)")
    ->execute([mb_substr($nome, 0, 120), mb_substr($email, 0, 120), $tel ?: null, $assunto, mb_substr($msg, 0, 2000)]);
$id = (int) $pdo->lastInsertId();
limite_registrar($pdo, 'contato');

// Tenta enviar o e-mail (precisa de um servidor SMTP configurado no PHP; se não houver, a mensagem
// continua salva e aparece em "Mensagens do site" na área da secretaria).
// Os cabeçalhos são montados só com dados validados: sem quebras de linha (evita "header injection").
$limpo = fn($s) => str_replace(["\r", "\n"], ' ', $s);
$corpo = "Nova mensagem pelo site\n\nNome: $nome\nE-mail: $email\nTelefone: $tel\nAssunto: $assunto\n\n$msg\n";
$ok = @mail($email_clinica, '=?UTF-8?B?' . base64_encode('[Site] ' . $limpo($assunto)) . '?=', $corpo,
            "From: site@$dominio_login\r\nReply-To: " . $limpo($email) . "\r\nContent-Type: text/plain; charset=UTF-8");
if ($ok) $pdo->prepare("UPDATE mensagem_contato SET email_enviado = 1 WHERE id_mensagem = ?")->execute([$id]);

aviso_publico('contato', 'Mensagem enviada! Nossa secretaria vai responder em até 1 dia útil.');
header('Location: index.php#contato');
exit;

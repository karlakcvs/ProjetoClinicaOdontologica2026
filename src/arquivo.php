<?php
// Entrega fotos e exames do prontuário SOMENTE para dentistas com acesso ao paciente.
// Os arquivos ficam em uploads/prontuario (bloqueado para acesso direto).
require_once __DIR__ . '/include/check_dentista.php';
$SEM_LIMITE_PAGINA = true;          // downloads têm limite próprio
require_once __DIR__ . '/include/conexao.php';
aplicar_limite_trafego($pdo, 'arquivo');   // rate limiting: 60 arquivos por minuto
require_once __DIR__ . '/classes/prontuario.php';

$id = (int) ($_GET['id'] ?? 0);
$pr = new Prontuario($pdo, $ID_DENTISTA);
$reg = $pr->registro($id);
if (!$reg || !$reg['arquivo'] || !$pr->podeAcessar((int) $reg['id_paciente'])) { http_response_code(404); exit('Arquivo não encontrado.'); }

$caminho = Prontuario::pasta() . (int) $reg['id_paciente'] . '/' . basename($reg['arquivo']);
if (!is_file($caminho)) { http_response_code(404); exit('Arquivo não encontrado.'); }

$permitidos = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$mime = in_array($reg['mime'], $permitidos, true) ? $reg['mime'] : 'application/octet-stream';
$baixar = isset($_GET['baixar']) || $mime === 'application/octet-stream';
$nome = preg_replace('/[^\w.\- ]+/u', '_', $reg['nome_original'] ?: basename($reg['arquivo']));

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: ' . ($baixar ? 'attachment' : 'inline') . '; filename="' . $nome . '"');
header('Cache-Control: private, no-store');
if ($mime === 'application/pdf') header_remove('Content-Security-Policy');   // deixa o leitor de PDF do navegador abrir
else header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
readfile($caminho);

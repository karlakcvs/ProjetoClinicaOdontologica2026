<?php
// Libera TODOS os bloqueios de rate limiting (use se travar o login durante a apresentação).
// Só roda pelo terminal:
//   docker exec -it php_apache php /var/www/html/include/liberar_bloqueios.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
$a = $pdo->exec("DELETE FROM limite_acao");
$b = $pdo->exec("DELETE FROM limite_requisicao");
echo "Bloqueios liberados ($a tentativas e $b contadores apagados).\n";

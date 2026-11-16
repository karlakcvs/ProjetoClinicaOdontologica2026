<?php
// DESATIVADO por segurança: esta página permitia que qualquer pessoa recriasse o login da secretaria.
// Para trocar uma senha, use o terminal (só funciona dentro do Docker):
//   docker exec -it php_apache php /var/www/html/include/redefinir_senha.php <email> "<nova senha>"
http_response_code(404);
exit;

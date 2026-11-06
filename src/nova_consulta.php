<?php
require_once __DIR__ . '/include/check_login.php';
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';

$modoSecretaria = true;
$erroForm = (aviso_publico('agendar') ?? [null, ''])[1];
$titulo = 'Nova consulta';
$pagina = 'nova_consulta';
include __DIR__ . '/include/admin_topo.php';
?>
<p class="text-muted mb-3">Consultas agendadas pela secretaria já entram como <span class="status status-confirmada">Confirmada</span>. O sistema só mostra horários livres para a duração do procedimento.</p>
<?php include __DIR__ . '/include/form_agendamento.php'; ?>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

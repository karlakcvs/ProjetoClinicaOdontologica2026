<?php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';

$modoSecretaria = false;
$erroForm = (aviso_publico('agendar') ?? [null, ''])[1];
$titulo = 'Agendar consulta';
include __DIR__ . '/include/cabecalho.php';
?>
<section class="page-head">
    <div class="container">
        <span class="eyebrow"><i class="bi bi-calendar2-heart"></i> Agendamento online</span>
        <h1 class="section-title mt-3 mb-2">Agende sua consulta</h1>
        <p class="section-sub mb-0">Os horários mostrados já consideram a duração do procedimento e a agenda de cada especialista.</p>
    </div>
</section>
<main class="container pb-5">
    <?php include __DIR__ . '/include/form_agendamento.php'; ?>
</main>
<?php include __DIR__ . '/include/rodape.php'; ?>

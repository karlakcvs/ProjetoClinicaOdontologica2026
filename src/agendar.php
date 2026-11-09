<?php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';

$modoSecretaria = false;
$erroForm = (aviso_publico('agendar') ?? [null, ''])[1];
$titulo = 'Agendar consulta';
include __DIR__ . '/include/cabecalho.php';
?>
<?php $resumo = $pdo->query("SELECT COUNT(*) AS qtd, AVG(nota) AS media FROM avaliacao WHERE status = 'APROVADA'")->fetch(); ?>
<section class="page-head agendar-head">
    <div class="container">
        <div class="agende-split agende-split-topo">
            <div class="agende-foto">
                <img src="<?= e(foto_site('agendar', 1100)) ?>" alt="" onerror="this.remove()">
                <div class="agende-foto-conteudo">
                    <div class="agende-chips">
                        <span><i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Araras/SP</span>
                        <span class="chip-on"><i class="bi bi-circle-fill" aria-hidden="true"></i> Seg a Sex · 07h30 às 19h</span>
                    </div>
                    <div>
                        <h1 class="agende-tit">Agende sua consulta hoje!</h1>
                        <p class="mb-0">Atendimento presencial com especialistas em ortodontia, implantes e estética.</p>
                    </div>
                    <?php if ($resumo['qtd']): ?>
                    <p class="agende-nota mb-0">Nossos pacientes avaliam:
                        <span class="quadros quadros-sm" aria-hidden="true"><?php for ($i = 1; $i <= 5; $i++): ?><span class="<?= $i <= round((float) $resumo['media']) ? 'on' : '' ?>">★</span><?php endfor; ?></span>
                        <strong><?= number_format((float) $resumo['media'], 1, ',', '') ?>/5,0</strong> (<?= (int) $resumo['qtd'] ?> avaliações)</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="agende-passos">
                <span class="eyebrow"><i class="bi bi-calendar2-heart" aria-hidden="true"></i> Agendamento online</span>
                <h2 class="section-title mt-3 mb-2">Como funciona</h2>
                <p class="section-sub mb-4">Os horários mostrados já consideram a duração do procedimento e a agenda de cada especialista.</p>
                <ol class="passos-lista">
                    <li><strong>Escolha o procedimento</strong><span>Com duração e valor de referência.</span></li>
                    <li><strong>Escolha o especialista</strong><span>Só aparecem os que realizam o procedimento.</span></li>
                    <li><strong>Escolha a data e o horário</strong><span>Somente horários realmente livres.</span></li>
                    <li><strong>Informe seus dados</strong><span>A secretaria confirma pelo WhatsApp.</span></li>
                </ol>
                <a href="#formAgendar" class="btn btn-teal mt-2">Começar agora <i class="bi bi-arrow-down ms-1" aria-hidden="true"></i></a>
            </div>
        </div>
    </div>
</section>
<main class="container pb-5">
    <?php include __DIR__ . '/include/form_agendamento.php'; ?>
</main>
<?php include __DIR__ . '/include/rodape.php'; ?>

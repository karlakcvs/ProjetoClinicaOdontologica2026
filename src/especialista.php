<?php
// Perfil completo de um especialista: biografia, formação, público, problemas tratados, horários e avaliações
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM dentista WHERE id_dentista = ? AND ativo = 1");
$st->execute([$id]);
$d = $st->fetch();
if (!$d) { header('Location: index.php#especialistas'); exit; }

$st = $pdo->prepare("SELECT dia_semana, hora_inicio, hora_fim FROM horario_atendimento WHERE id_dentista = ? ORDER BY dia_semana, hora_inicio");
$st->execute([$id]);
$porDia = [];
foreach ($st->fetchAll() as $h) $porDia[(int) $h['dia_semana']][] = hm($h['hora_inicio']) . '–' . hm($h['hora_fim']);

$st = $pdo->prepare("SELECT p.* FROM procedimento p JOIN dentista_procedimento dp ON dp.id_procedimento = p.id_procedimento WHERE dp.id_dentista = ? ORDER BY CASE WHEN p.nome LIKE '%Primeira consulta%' THEN 0 ELSE 1 END, p.valor");
$st->execute([$id]);
$procs = $st->fetchAll();

$st = $pdo->prepare("SELECT * FROM avaliacao WHERE id_dentista = ? AND status = 'APROVADA' ORDER BY criado_em DESC");
$st->execute([$id]);
$avs = $st->fetchAll();
$media = $avs ? array_sum(array_column($avs, 'nota')) / count($avs) : 0;
$cond = array_filter(array_map('trim', explode("\n", (string) $d['condicoes'])));
$ft = foto_dentista($d);
$casos = $CASOS_ANTES_DEPOIS[mb_strtolower(primeiro_nome($d['nome']))] ?? [];

$titulo = $d['nome'] . ' – ' . $d['especialidade'];
$pagina = 'especialista';
include __DIR__ . '/include/cabecalho.php';
?>
<section class="page-head">
    <div class="container">
        <nav aria-label="Você está em" class="small mb-3"><a href="index.php">Início</a> <i class="bi bi-chevron-right mx-1" aria-hidden="true"></i> <a href="index.php#especialistas">Especialistas</a> <i class="bi bi-chevron-right mx-1" aria-hidden="true"></i> <span class="text-muted"><?= e($d['nome']) ?></span></nav>
        <div class="row g-4 align-items-center">
            <div class="col-md-5 col-lg-4 text-center text-md-start">
                <?php if ($ft): ?><img src="<?= e($ft) ?>" alt="Ilustração de <?= e($d['nome']) ?>" class="perfil-foto"><?php endif; ?>
            </div>
            <div class="col-md-7 col-lg-8">
                <span class="tag mb-2"><?= e($d['especialidade']) ?></span>
                <h1 class="section-title mt-2 mb-2"><?= e($d['nome']) ?></h1>
                <p class="text-muted mb-3"><?= e($d['cro']) ?><?php if ($avs): ?> · <?= estrelas($media) ?> <strong><?= number_format($media, 1, ',', '') ?></strong> (<?= count($avs) ?>)<?php endif; ?></p>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <?php if ($d['anos_experiencia']): ?><span class="tag tag-navy"><i class="bi bi-award" aria-hidden="true"></i> <?= (int) $d['anos_experiencia'] ?> anos de experiência</span><?php endif; ?>
                    <span class="tag tag-navy"><i class="bi bi-building" aria-hidden="true"></i> Atendimento <?= e(mb_strtolower($d['atendimento'] ?: 'presencial')) ?></span>
                </div>
                <p class="lead" style="font-size:1.05rem"><?= e($d['bio']) ?></p>
                <a href="agendar.php?dentista=<?= $id ?>" class="btn btn-teal btn-lg mt-2"><i class="bi bi-calendar2-check me-2" aria-hidden="true"></i>Agendar com <?= e(primeiro_nome($d['nome'])) ?></a>
            </div>
        </div>
    </div>
</section>

<main class="container py-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="row g-4">
                <?php if ($d['formacao']): ?>
                <div class="col-12"><div class="card-soft panel">
                    <h2 class="panel-title mb-2"><i class="bi bi-mortarboard text-teal me-2" aria-hidden="true"></i>Formação</h2>
                    <p class="mb-0 text-muted"><?= e($d['formacao']) ?></p>
                </div></div>
                <?php endif; ?>
                <div class="col-md-6"><div class="card-soft panel h-100">
                    <h2 class="panel-title mb-2"><i class="bi bi-people text-teal me-2" aria-hidden="true"></i>Público atendido</h2>
                    <p class="mb-0 text-muted"><?= e($d['publico'] ?: 'Consulte a clínica') ?></p>
                </div></div>
                <div class="col-md-6"><div class="card-soft panel h-100">
                    <h2 class="panel-title mb-2"><i class="bi bi-clock text-teal me-2" aria-hidden="true"></i>Dias e horários</h2>
                    <ul class="list-unstyled small mb-0 d-grid gap-1">
                        <?php foreach ([1,2,3,4,5,6] as $di): if (empty($porDia[$di])) continue; ?>
                            <li class="d-flex justify-content-between gap-2"><strong><?= $DIAS_SEMANA[$di] ?></strong><span class="text-muted"><?= e(implode(' · ', $porDia[$di])) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div></div>
                <?php if ($cond): ?>
                <div class="col-12"><div class="card-soft panel">
                    <h2 class="panel-title mb-3"><i class="bi bi-clipboard2-pulse text-teal me-2" aria-hidden="true"></i>Principais tratamentos</h2>
                    <div class="row g-2">
                        <?php foreach ($cond as $c): ?><div class="col-sm-6"><div class="d-flex gap-2 small"><i class="bi bi-check2-circle text-teal" aria-hidden="true"></i><?= e($c) ?></div></div><?php endforeach; ?>
                    </div>
                </div></div>
                <?php endif; ?>
                <?php if ($casos): ?>
                <div class="col-12">
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                        <h2 class="h4 mb-0">Antes e depois</h2>
                        <span class="demo-tag" title="Fotos de banco de imagens, apenas para ilustrar">imagens ilustrativas</span>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($casos as [$foto, $caso, $detalhe]): ?>
                        <div class="col-md-6"><figure class="card-soft antes-depois m-0">
                            <div class="ad-par">
                                <div class="ad-antes"><img src="<?= e(foto_unsplash($foto, 500)) ?>" alt="Antes: <?= e($caso) ?>" loading="lazy" onerror="this.style.visibility='hidden'"><span>Antes</span></div>
                                <div><img src="<?= e(foto_unsplash($foto, 500)) ?>" alt="Depois: <?= e($caso) ?>" loading="lazy" onerror="this.style.visibility='hidden'"><span>Depois</span></div>
                            </div>
                            <figcaption><strong><?= e($caso) ?></strong><span class="text-muted"> · <?= e($detalhe) ?></span></figcaption>
                        </figure></div>
                        <?php endforeach; ?>
                    </div>
                    <p class="small text-muted mt-2 mb-0">Imagens meramente ilustrativas. Cada tratamento é avaliado individualmente e os resultados variam de pessoa para pessoa.</p>
                </div>
                <?php endif; ?>
                <div class="col-12">
                    <h2 class="h4 mb-3">Avaliações</h2>
                    <?php if (!$avs): ?><p class="text-muted">Ainda não há avaliações publicadas. <a href="index.php#avaliacoes">Seja o primeiro a avaliar.</a></p><?php endif; ?>
                    <div class="row g-3">
                        <?php foreach ($avs as $a): ?>
                        <div class="col-md-6"><figure class="card-soft review m-0">
                            <div class="d-flex justify-content-between"><?= estrelas($a['nota']) ?><?php if ($a['demo']): ?><span class="demo-tag">exemplo</span><?php endif; ?></div>
                            <blockquote>“<?= e($a['comentario']) ?>”</blockquote>
                            <figcaption class="who"><strong><?= e($a['nome']) ?></strong><span class="text-muted">· <?= data_br($a['criado_em']) ?></span></figcaption>
                        </figure></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <aside class="card-soft panel summary">
                <h2 class="panel-title mb-3">Procedimentos e valores</h2>
                <?php foreach ($procs as $i => $p): ?>
                    <a href="agendar.php?dentista=<?= $id ?>&amp;procedimento=<?= (int) $p['id_procedimento'] ?>" class="d-flex justify-content-between gap-2 py-2 small text-reset <?= $i ? 'border-top' : '' ?>">
                        <span><?= e($p['nome']) ?><br><span class="text-muted"><?= duracao_txt($p['duracao_min']) ?></span></span>
                        <strong class="text-nowrap"><?= moeda($p['valor']) ?></strong>
                    </a>
                <?php endforeach; ?>
                <div class="info-pagamento mt-3"><i class="bi bi-cash-coin" aria-hidden="true"></i><div>Pagamento na clínica: <?= e(implode(', ', $FORMAS_PAGAMENTO)) ?>.</div></div>
            </aside>
        </div>
    </div>
</main>
<?php include __DIR__ . '/include/rodape.php'; ?>

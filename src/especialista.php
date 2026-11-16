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

$st = $pdo->prepare("SELECT * FROM avaliacao WHERE id_dentista = ? AND status = 'APROVADA' ORDER BY criado_em DESC");
$st->execute([$id]);
$avs = $st->fetchAll();
$media = $avs ? array_sum(array_column($avs, 'nota')) / count($avs) : 0;
$cond = array_filter(array_map('trim', explode("\n", (string) $d['condicoes'])));
$ft = foto_dentista($d);
$chaveNome = mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', primeiro_nome($d['nome'])));
$casos = $CASOS_ANTES_DEPOIS[$chaveNome] ?? [];
$fotoPessoal = '';
foreach (['jpg', 'jpeg', 'webp', 'png'] as $ext) if (is_file(__DIR__ . "/assets/img/fotos/pessoal-$chaveNome.$ext")) { $fotoPessoal = "assets/img/fotos/pessoal-$chaveNome.$ext"; break; }

$titulo = $d['nome'] . ' – ' . $d['especialidade'];
$pagina = 'especialista';
include __DIR__ . '/include/cabecalho.php';
?>
<section class="page-head">
    <div class="container">
        <nav aria-label="Você está em" class="small mb-3"><a href="index.php">Início</a> <i class="bi bi-chevron-right mx-1" aria-hidden="true"></i> <a href="index.php#especialistas">Especialistas</a> <i class="bi bi-chevron-right mx-1" aria-hidden="true"></i> <span class="text-muted"><?= e($d['nome']) ?></span></nav>
        <div class="row g-4 align-items-center">
            <div class="col-md-5 col-lg-4 text-center text-md-start">
                <div class="perfil-foto-box"><span class="doc-v4-ini" aria-hidden="true"><?= e(iniciais($d['nome'])) ?></span><?php if ($ft): ?><img src="<?= e(foto_dentista($d, 800)) ?>" alt="Foto de <?= e($d['nome']) ?>" class="perfil-foto" onerror="this.remove()"><?php endif; ?></div>
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
                <div class="col-12"><div class="card-soft panel">
                    <h2 class="panel-title mb-2"><i class="bi bi-people text-teal me-2" aria-hidden="true"></i>Público atendido</h2>
                    <p class="mb-0 text-muted"><?= e($d['publico'] ?: 'Consulte a clínica') ?></p>
                </div></div>
                <?php if ($cond): ?>
                <div class="col-12"><div class="card-soft panel">
                    <h2 class="panel-title mb-3"><i class="bi bi-clipboard2-pulse text-teal me-2" aria-hidden="true"></i>Principais tratamentos</h2>
                    <div class="row g-2">
                        <?php foreach ($cond as $c): ?><div class="col-sm-6"><div class="d-flex gap-2 small"><i class="bi bi-check2-circle text-teal" aria-hidden="true"></i><?= e($c) ?></div></div><?php endforeach; ?>
                    </div>
                </div></div>
                <?php endif; ?>
                <?php if ($fotoPessoal): ?>
                <div class="col-12"><figure class="card-soft pessoal m-0">
                    <img src="<?= e($fotoPessoal) ?>" alt="<?= e($d['nome']) ?> fora do consultório" loading="lazy">
                    <figcaption><span class="eyebrow mb-2"><i class="bi bi-heart" aria-hidden="true"></i> Fora do consultório</span><p class="mb-0"><?= e($FOTO_PESSOAL[$chaveNome] ?? '') ?></p></figcaption>
                </figure></div>
                <?php endif; ?>
                <?php if ($casos): ?>
                <div class="col-12">
                    <h2 class="h4 mb-3">Antes e depois</h2>
                    <div class="row g-3">
                        <?php foreach ($casos as [$fAntes, $fDepois, $caso, $detalhe]): ?>
                        <div class="col-md-6"><figure class="card-soft antes-depois m-0">
                            <div class="ad-par">
                                <div class="ad-antes"><img src="<?= e(url_foto($fAntes, 520, 520)) ?>" alt="Antes do tratamento: <?= e($caso) ?>" loading="lazy" onerror="this.style.visibility='hidden'"><span>Antes</span></div>
                                <div class="ad-depois"><img src="<?= e(url_foto($fDepois, 520, 520)) ?>" alt="Depois do tratamento: <?= e($caso) ?>" loading="lazy" onerror="this.style.visibility='hidden'"><span>Depois</span></div>
                            </div>
                            <figcaption><strong><?= e($caso) ?></strong><span class="text-muted"> · <?= e($detalhe) ?></span></figcaption>
                        </figure></div>
                        <?php endforeach; ?>
                    </div>
                    <p class="small text-muted mt-2 mb-0">Cada tratamento é avaliado individualmente e os resultados variam de pessoa para pessoa.</p>
                </div>
                <?php endif; ?>
                <div class="col-12" id="avaliacoes-dentista">
                    <h2 class="h4 mb-3">Avaliações</h2>
                    <?php if (!$avs): ?><p class="text-muted">Ainda não há avaliações publicadas. <a href="index.php#avaliacoes">Seja o primeiro a avaliar.</a></p><?php endif; ?>
                    <div class="row g-3">
                        <?php foreach ($avs as $a): ?>
                        <div class="col-md-6"><figure class="card-soft review m-0">
                            <div class="d-flex justify-content-between align-items-center gap-2"><?= estrelas($a['nota']) ?><span class="review-data"><?= e(mes_ano($a['criado_em'])) ?></span></div>
                            <blockquote>“<?= e($a['comentario']) ?>”</blockquote>
                            <figcaption class="who"><strong><?= e($a['nome']) ?></strong><?php if ($a['paciente_desde']): ?><span class="paciente-desde"><i class="bi bi-award" aria-hidden="true"></i> paciente desde <?= (int) $a['paciente_desde'] ?></span><?php endif; ?></figcaption>
                        </figure></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <aside class="card-soft panel summary">
                <h2 class="panel-title mb-3"><i class="bi bi-clock text-teal me-2" aria-hidden="true"></i>Dias e horários</h2>
                <ul class="list-unstyled small mb-3 d-grid gap-2">
                    <?php foreach ([1,2,3,4,5,6] as $di): if (empty($porDia[$di])) continue; ?>
                        <li class="d-flex justify-content-between gap-2"><strong><?= $DIAS_SEMANA[$di] ?></strong><span class="text-muted"><?= e(implode(' · ', $porDia[$di])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <a href="agendar.php?dentista=<?= $id ?>" class="btn btn-teal w-100"><i class="bi bi-calendar2-check me-1" aria-hidden="true"></i>Ver horários livres</a>
                <p class="small text-muted mt-3 mb-0"><i class="bi bi-tags me-1" aria-hidden="true"></i>Os tratamentos e valores de referência estão em <a href="index.php#procedimentos">Procedimentos e valores</a>. O orçamento final é confirmado na consulta.</p>
                <div class="info-pagamento mt-3"><i class="bi bi-cash-coin" aria-hidden="true"></i><div>Pagamento na clínica: <?= e(implode(', ', $FORMAS_PAGAMENTO)) ?>.</div></div>
            </aside>
        </div>
    </div>
</main>
<?php include __DIR__ . '/include/rodape.php'; ?>

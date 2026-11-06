<?php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';

$dentistas = $pdo->query("SELECT * FROM dentista WHERE ativo = 1 ORDER BY id_dentista")->fetchAll();
// "Avaliação / Primeira consulta" sempre em primeiro; o resto por valor
$procedimentos = $pdo->query("SELECT * FROM procedimento ORDER BY CASE WHEN nome LIKE '%Primeira consulta%' THEN 0 ELSE 1 END, valor, nome")->fetchAll();

// horários de cada dentista agrupados (ex.: 08:00–12:00 / 13:30–18:00)
$turnos = [];
foreach ($pdo->query("SELECT DISTINCT id_dentista, hora_inicio, hora_fim FROM horario_atendimento ORDER BY hora_inicio")->fetchAll() as $h) {
    $turnos[$h['id_dentista']][] = hm($h['hora_inicio']) . '–' . hm($h['hora_fim']);
}
// avaliações aprovadas
$avaliacoes = $pdo->query("SELECT a.*, d.nome AS dentista FROM avaliacao a LEFT JOIN dentista d ON d.id_dentista = a.id_dentista
                           WHERE a.status = 'APROVADA' ORDER BY a.criado_em DESC LIMIT 6")->fetchAll();
$resumo = $pdo->query("SELECT COUNT(*) AS qtd, AVG(nota) AS media FROM avaliacao WHERE status = 'APROVADA'")->fetch();
$notaDent = [];
foreach ($pdo->query("SELECT id_dentista, AVG(nota) AS media, COUNT(*) AS qtd FROM avaliacao WHERE status = 'APROVADA' AND id_dentista IS NOT NULL GROUP BY id_dentista")->fetchAll() as $r) {
    $notaDent[$r['id_dentista']] = $r;
}

$avisoAval = aviso_publico('avaliacao');
$avisoCont = aviso_publico('contato');

$titulo = 'Odontologia Especializada em Araras';
$pagina = 'inicio';
include __DIR__ . '/include/cabecalho.php';
?>

<!-- HERO -->
<header class="hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <span class="eyebrow"><i class="bi bi-stars" aria-hidden="true"></i> <?= saudar() ?>! Agende online em 1 minuto</span>
                <h1 class="mt-3 mb-3">Seu sorriso nas mãos de <em>especialistas</em>.</h1>
                <p class="lead mb-4">Ortodontia, implantes e estética dental em um só lugar. Escolha o procedimento, o profissional e o melhor horário — a gente cuida do resto.</p>
                <a href="agendar.php" class="btn btn-teal btn-lg"><i class="bi bi-calendar2-check me-2" aria-hidden="true"></i>Agendar consulta</a>
                <div class="hero-stats">
                    <div><strong><?= count($dentistas) ?></strong><span>especialistas</span></div>
                    <?php if ($resumo['qtd']): ?><div><strong><?= number_format((float) $resumo['media'], 1, ',', '') ?><span class="ms-1" style="color:#e3a21a" aria-hidden="true">★</span></strong><span><?= (int) $resumo['qtd'] ?> avaliações</span></div><?php endif; ?>
                    <div><strong>Seg–Sex</strong><span>atendimento presencial</span></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-visual">
                    <span class="blob b1"></span><span class="blob b2"></span>
                    <div class="hero-card">
                        <div class="hero-art hero-dente">
                            <canvas data-dente3d role="img" aria-label="Dente em malha 3D, branco e brilhante, girando devagar"></canvas>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div class="d-flex">
                                <?php foreach ($dentistas as $i => $d): $ft = foto_dentista($d); ?>
                                    <?php if ($ft): ?>
                                        <img src="<?= e($ft) ?>" alt="<?= e($d['nome']) ?>" class="avatar avatar-sm avatar-foto" style="<?= $i ? 'margin-left:-10px;' : '' ?>border:2px solid #fff">
                                    <?php else: ?>
                                        <span class="avatar avatar-sm" style="--c:<?= e($d['cor']) ?>;<?= $i ? 'margin-left:-10px;' : '' ?>border:2px solid #fff"><?= e(iniciais($d['nome'])) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <a href="agendar.php" class="small fw-semibold">Ver horários livres <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- DIFERENCIAIS -->
<section class="py-5 bg-white border-top border-bottom" aria-label="Diferenciais">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4"><div class="feature"><span class="feature-ico"><i class="bi bi-calendar-heart" aria-hidden="true"></i></span><div><h3 class="h6 mb-1">Agendamento online</h3><p class="small text-muted mb-0">Horários calculados de acordo com a duração de cada procedimento.</p></div></div></div>
            <div class="col-md-4"><div class="feature"><span class="feature-ico"><i class="bi bi-whatsapp" aria-hidden="true"></i></span><div><h3 class="h6 mb-1">Lembrete da consulta</h3><p class="small text-muted mb-0">Nossa secretaria confirma com você um dia antes pelo WhatsApp.</p></div></div></div>
            <div class="col-md-4"><div class="feature"><span class="feature-ico"><i class="bi bi-building-check" aria-hidden="true"></i></span><div><h3 class="h6 mb-1">Atendimento presencial</h3><p class="small text-muted mb-0">Todos os especialistas atendem na clínica, com estrutura completa.</p></div></div></div>
        </div>
    </div>
</section>

<!-- ESPECIALISTAS -->
<section class="section" id="especialistas">
    <div class="container">
        <div class="text-center mb-5">
            <span class="eyebrow">Corpo clínico</span>
            <h2 class="section-title mt-3">Conheça nossos especialistas</h2>
            <p class="section-sub mx-auto">Atendimento presencial de segunda a sexta-feira, cada profissional na sua especialidade.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($dentistas as $d):
                $ft = foto_dentista($d);
                $cond = array_filter(array_map('trim', explode("\n", (string) $d['condicoes'])));
                $nd = $notaDent[$d['id_dentista']] ?? null; ?>
            <div class="col-md-6 col-lg-4">
                <article class="card-soft card-hover doc-card h-100">
                    <div class="doc-photo" style="background:<?= e($d['cor']) ?>22">
                        <?php if ($ft): ?><img src="<?= e($ft) ?>" alt="Ilustração de <?= e($d['nome']) ?>" loading="lazy"><?php endif; ?>
                        <?php if ($d['anos_experiencia']): ?><span class="doc-xp"><strong><?= (int) $d['anos_experiencia'] ?></strong>anos de experiência</span><?php endif; ?>
                    </div>
                    <div class="doc-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                            <h3 class="h5 mb-0"><?= e($d['nome']) ?></h3>
                            <?php if ($nd): ?><span class="small fw-bold text-nowrap"><span style="color:#e3a21a" aria-hidden="true">★</span> <?= number_format((float) $nd['media'], 1, ',', '') ?></span><?php endif; ?>
                        </div>
                        <div class="mb-3"><span class="tag"><?= e($d['especialidade']) ?></span> <span class="small text-muted ms-1"><?= e($d['cro']) ?></span></div>
                        <p class="text-muted small mb-3"><?= e(mb_strimwidth((string) $d['bio'], 0, 190, '…')) ?></p>
                        <ul class="doc-info">
                            <?php if ($d['publico']): ?><li><i class="bi bi-people" aria-hidden="true"></i><strong>Atende:</strong> <?= e($d['publico']) ?></li><?php endif; ?>
                            <li><i class="bi bi-building" aria-hidden="true"></i><strong>Formato:</strong> <?= e($d['atendimento'] ?: 'Presencial') ?></li>
                            <li><i class="bi bi-clock" aria-hidden="true"></i><strong>Horário:</strong> Seg a Sex · <?= e(implode(' / ', $turnos[$d['id_dentista']] ?? [])) ?></li>
                        </ul>
                        <?php if ($cond): ?>
                        <div class="cond-list" aria-label="Principais tratamentos">
                            <?php foreach (array_slice($cond, 0, 3) as $c): ?><span class="tag tag-navy"><?= e($c) ?></span><?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2 mt-auto">
                            <a href="especialista.php?id=<?= (int) $d['id_dentista'] ?>" class="btn btn-ghost flex-fill">Ver perfil</a>
                            <a href="agendar.php?dentista=<?= (int) $d['id_dentista'] ?>" class="btn btn-soft flex-fill">Agendar</a>
                        </div>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- PROCEDIMENTOS E PREÇOS -->
<section class="section bg-white" id="procedimentos">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-5">
            <div>
                <span class="eyebrow">Tratamentos</span>
                <h2 class="section-title mt-3 mb-2">Procedimentos e valores</h2>
                <p class="section-sub mb-0">Valores de referência. O orçamento final é confirmado pelo especialista na consulta, antes de qualquer procedimento.</p>
            </div>
            <a href="agendar.php" class="btn btn-teal">Agendar agora</a>
        </div>
        <div class="row g-3">
            <?php foreach ($procedimentos as $p): $ehPrimeira = stripos($p['nome'], 'Primeira consulta') !== false; ?>
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <a href="agendar.php?procedimento=<?= (int) $p['id_procedimento'] ?><?= $ehPrimeira ? '&amp;primeira=1' : '' ?>" class="card-soft card-hover proc-card text-reset<?= $ehPrimeira ? ' proc-primeira' : '' ?>">
                    <?php if ($ehPrimeira): ?><span class="proc-selo"><i class="bi bi-stars" aria-hidden="true"></i> Comece por aqui</span><?php endif; ?>
                    <span class="proc-ico mb-3"><i class="bi <?= e($p['icone']) ?>" aria-hidden="true"></i></span>
                    <h3 class="h6 mb-1"><?= e($p['nome']) ?></h3>
                    <p class="small text-muted mb-0"><?= e($p['descricao']) ?></p>
                    <div class="proc-meta">
                        <span class="tag"><i class="bi bi-clock" aria-hidden="true"></i> <?= duracao_txt($p['duracao_min']) ?></span>
                        <span class="tag tag-navy price">a partir de <?= moeda($p['valor']) ?></span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FORMAS DE PAGAMENTO -->
<section class="section" id="pagamento">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-5">
                <span class="eyebrow">Pagamento</span>
                <h2 class="section-title mt-3 mb-2">Formas de pagamento</h2>
                <p class="section-sub mb-3">O pagamento é feito <strong>na clínica, no dia do atendimento</strong>. Nada é cobrado pelo site.</p>
                <div class="info-pagamento"><i class="bi bi-info-circle" aria-hidden="true"></i><div>Tratamentos com várias sessões podem ser parcelados no cartão de crédito — consulte as condições na recepção.</div></div>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <?php foreach ($FORMAS_PAGAMENTO as $fp): ?>
                        <div class="col-sm-6"><div class="card-soft pay-card"><i class="bi <?= $FORMAS_ICONES[$fp] ?? 'bi-wallet2' ?>" aria-hidden="true"></i><div><strong><?= e($fp) ?></strong><br><span class="small text-muted">aceito na recepção</span></div></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- AVALIAÇÕES -->
<section class="section bg-white" id="avaliacoes">
    <div class="container">
        <div class="row g-4 mb-4 align-items-end">
            <div class="col-lg-7">
                <span class="eyebrow">Opiniões</span>
                <h2 class="section-title mt-3 mb-2">O que dizem nossos pacientes</h2>
                <p class="section-sub mb-0">Avaliações publicadas após conferência da clínica.</p>
            </div>
            <?php if ($resumo['qtd']): ?>
            <div class="col-lg-5 d-flex align-items-center gap-3 justify-content-lg-end">
                <span class="rating-big"><?= number_format((float) $resumo['media'], 1, ',', '') ?></span>
                <div><?= estrelas($resumo['media'], 'fs-5') ?><div class="small text-muted"><?= (int) $resumo['qtd'] ?> avaliação(ões)</div></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($avaliacoes): ?>
        <div class="row g-3 mb-5">
            <?php foreach ($avaliacoes as $a): ?>
            <div class="col-md-6 col-lg-4">
                <figure class="card-soft review m-0">
                    <div class="d-flex justify-content-between align-items-center gap-2"><?= estrelas($a['nota']) ?><?php if ($a['demo']): ?><span class="demo-tag" title="Avaliação de exemplo para demonstração do sistema">exemplo</span><?php endif; ?></div>
                    <blockquote>“<?= e($a['comentario']) ?>”</blockquote>
                    <figcaption class="who">
                        <span class="avatar avatar-xs" style="--c:#4f7cc4" aria-hidden="true"><?= e(iniciais($a['nome'])) ?></span>
                        <span><strong><?= e($a['nome']) ?></strong><?php if ($a['dentista']): ?><br><span class="text-muted">sobre <?= e($a['dentista']) ?></span><?php endif; ?></span>
                    </figcaption>
                </figure>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?= aviso_html($avisoAval) ?>
                <form action="avaliar.php" method="POST" class="card-soft panel p-4" data-enviando="Enviando…">
                    <?= csrf_campo() ?>
                    <h3 class="h5 mb-1">Deixe sua opinião</h3>
                    <p class="small text-muted mb-3">Conte como foi seu atendimento. Sua avaliação aparece aqui depois de conferida pela clínica.</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <span class="form-label d-block" id="lblNota">Sua nota</span>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <fieldset class="star-input" aria-labelledby="lblNota">
                                    <?php for ($n = 5; $n >= 1; $n--): ?>
                                        <input type="radio" name="nota" id="nota<?= $n ?>" value="<?= $n ?>" required <?= (int) antigo('avaliacao', 'nota') === $n ? 'checked' : '' ?>>
                                        <label for="nota<?= $n ?>" title="<?= $n ?> estrela(s)"><span aria-hidden="true">★</span><span class="visually-hidden"><?= $n ?> estrela(s)</span></label>
                                    <?php endfor; ?>
                                </fieldset>
                                <span class="star-label small fw-semibold text-muted" aria-live="polite"></span>
                            </div>
                        </div>
                        <div class="col-md-6"><label class="form-label" for="avNome">Seu nome</label><input id="avNome" name="nome" class="form-control" required maxlength="60" placeholder="Ex.: Maria S." value="<?= e(antigo('avaliacao', 'nome')) ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="avDent">Especialista <span class="text-muted fw-normal">(opcional)</span></label>
                            <select id="avDent" name="id_dentista" class="form-select"><option value="">Clínica em geral</option>
                                <?php foreach ($dentistas as $d): ?><option value="<?= (int) $d['id_dentista'] ?>" <?= (string) antigo('avaliacao', 'id_dentista') === (string) $d['id_dentista'] ? 'selected' : '' ?>><?= e($d['nome']) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="col-12"><label class="form-label" for="avCom">Comentário</label><textarea id="avCom" name="comentario" class="form-control" rows="3" required minlength="10" maxlength="600" placeholder="Como foi o atendimento?"><?= e(antigo('avaliacao', 'comentario')) ?></textarea></div>
                        <div class="col-12"><?= captcha_campo('avaliacao') ?></div>
                        <div class="col-12"><button type="submit" class="btn btn-teal"><i class="bi bi-send me-1" aria-hidden="true"></i> Enviar avaliação</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- CONTATO -->
<section class="section" id="contato">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <span class="eyebrow">Fale conosco</span>
                <h2 class="section-title mt-3 mb-2">Contato</h2>
                <p class="section-sub mb-4">Dúvidas sobre tratamentos, valores ou horários? Envie uma mensagem e nossa secretaria responde por e-mail ou telefone.</p>
                <ul class="list-unstyled d-grid gap-3">
                    <li class="d-flex gap-3"><span class="feature-ico"><i class="bi bi-geo-alt" aria-hidden="true"></i></span><div><strong>Endereço</strong><br><span class="text-muted"><?= e($endereco) ?></span></div></li>
                    <li class="d-flex gap-3"><span class="feature-ico"><i class="bi bi-telephone" aria-hidden="true"></i></span><div><strong>Telefone</strong><br><span class="text-muted"><?= e($contato) ?></span></div></li>
                    <li class="d-flex gap-3"><span class="feature-ico"><i class="bi bi-whatsapp" aria-hidden="true"></i></span><div><strong>WhatsApp</strong><br><a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">Conversar agora</a></div></li>
                </ul>
            </div>
            <div class="col-lg-7">
                <?= aviso_html($avisoCont) ?>
                <form action="contato.php" method="POST" class="card-soft p-4" data-enviando="Enviando…">
                    <?= csrf_campo() ?>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="ctNome">Nome</label><input id="ctNome" name="nome" class="form-control" required maxlength="120" autocomplete="name" value="<?= e(antigo('contato', 'nome')) ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="ctTel">Telefone <span class="text-muted fw-normal">(opcional)</span></label><input id="ctTel" name="telefone" class="form-control" data-mask="tel" inputmode="tel" maxlength="16" autocomplete="tel" value="<?= e(antigo('contato', 'telefone')) ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="ctEmail">E-mail</label><input id="ctEmail" type="email" name="email" class="form-control" required maxlength="120" autocomplete="email" value="<?= e(antigo('contato', 'email')) ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="ctAss">Assunto</label>
                            <select id="ctAss" name="assunto" class="form-select" required>
                                <?php foreach (['Dúvida sobre tratamento', 'Valores e pagamento', 'Remarcar ou cancelar consulta', 'Elogio ou sugestão', 'Outro assunto'] as $as): ?>
                                    <option <?= antigo('contato', 'assunto') === $as ? 'selected' : '' ?>><?= e($as) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="col-12"><label class="form-label" for="ctMsg">Mensagem</label><textarea id="ctMsg" name="mensagem" class="form-control" rows="4" required minlength="10" maxlength="2000"><?= e(antigo('contato', 'mensagem')) ?></textarea></div>
                        <div class="col-12"><?= captcha_campo('contato') ?></div>
                        <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                            <button type="submit" class="btn btn-teal"><i class="bi bi-envelope me-1" aria-hidden="true"></i> Enviar mensagem</button>
                            <span class="small text-muted"><i class="bi bi-lock" aria-hidden="true"></i> Seus dados são usados apenas para responder você.</span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="pb-2" aria-label="Agendar">
    <div class="container">
        <div class="cta">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <h2 class="mb-2">Pronto para cuidar do seu sorriso?</h2>
                    <p class="mb-0 opacity-75">Escolha o procedimento e veja na hora os horários livres de cada especialista.</p>
                </div>
                <div class="col-lg-4 text-lg-end d-flex flex-wrap gap-2 justify-content-lg-end">
                    <a href="agendar.php" class="btn btn-teal btn-lg">Agendar</a>
                    <a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener" class="btn btn-outline-light-soft btn-lg"><i class="bi bi-whatsapp me-1" aria-hidden="true"></i> WhatsApp</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php limpar_antigos('avaliacao'); limpar_antigos('contato');
$scripts_extra = '<script src="assets/js/dente3d.js?v=1"></script>';
include __DIR__ . '/include/rodape.php'; ?>

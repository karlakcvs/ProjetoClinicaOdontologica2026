<?php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/conexao.php';

$dentistas = $pdo->query("SELECT * FROM dentista WHERE ativo = 1 ORDER BY id_dentista")->fetchAll();
// "Avaliação / Primeira consulta" sempre em primeiro; o resto por valor
$procedimentos = $pdo->query("SELECT * FROM procedimento ORDER BY CASE WHEN nome LIKE '%Primeira consulta%' THEN 0 ELSE 1 END, valor, nome")->fetchAll();
// cada dentista pode ter o próprio preço: o site mostra o menor ("a partir de")
$aPartir = $pdo->query("SELECT dp.id_procedimento, MIN(COALESCE(dp.valor, p.valor)) AS v FROM dentista_procedimento dp
                        JOIN procedimento p ON p.id_procedimento = dp.id_procedimento JOIN dentista d ON d.id_dentista = dp.id_dentista AND d.ativo = 1
                        GROUP BY dp.id_procedimento")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($procedimentos as &$pp) $pp['valor'] = $aPartir[$pp['id_procedimento']] ?? $pp['valor'];
unset($pp);

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

<!-- HERO: dentista da clínica com pacientes sorridentes -->
<header class="hero hero-v4">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="hero-kicker">Odontologia especializada em Araras/SP</span>
                <h1 class="mt-3 mb-3">Sorrisos saudáveis, cuidados por <em>especialistas</em>.</h1>
<p class="lead mb-4">Ortodontia, implantes e estética dental em um só lugar. Escolha o procedimento, o profissional e o melhor horário.<br>A gente cuida do resto.</p>                <div class="d-flex flex-wrap gap-2">
                    <a href="agendar.php" class="btn btn-teal btn-lg"><i class="bi bi-calendar2-check me-2" aria-hidden="true"></i>Agendar consulta</a>
                    <a href="#especialistas" class="btn btn-ghost btn-lg">Conhecer especialistas</a>
                </div>
                <dl class="hero-stats">
                    <div><dt><?= count($dentistas) ?></dt><dd>especialistas</dd></div>
                    <?php if ($resumo['qtd']): ?><div><dt><?= number_format((float) $resumo['media'], 1, ',', '') ?><span class="estrela-ico" aria-hidden="true">★</span></dt><dd><?= (int) $resumo['qtd'] ?> avaliações</dd></div><?php endif; ?>
                    <div><dt>Seg–Sex</dt><dd>07h30 às 19h</dd></div>
                </dl>
            </div>
            <div class="col-lg-6">
                <div class="hero-fotos hero-uma">
                    <span class="hf-fundo" aria-hidden="true"></span>
                    <figure class="hf-main m-0">
                        <img src="<?= e(foto_site('hero-principal', 1400)) ?>" alt="Dentista da clínica conversando com mãe e filha sorridentes no consultório" width="1400" height="933" fetchpriority="high" onerror="this.closest('figure').classList.add('sem-foto')">
                    </figure>
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
                <article class="doc-v4 h-100">
                    <a href="especialista.php?id=<?= (int) $d['id_dentista'] ?>" class="doc-v4-foto" aria-label="Ver perfil de <?= e($d['nome']) ?>">
                        <?php if ($ft): ?><img src="<?= e($ft) ?>" alt="Foto de <?= e($d['nome']) ?>" loading="lazy" width="420" height="420" onerror="this.remove()"><?php endif; ?>
                        <span class="doc-v4-ini" aria-hidden="true"><?= e(iniciais($d['nome'])) ?></span>
                        <?php if ($d['anos_experiencia']): ?><span class="doc-xp"><strong><?= (int) $d['anos_experiencia'] ?></strong> anos de experiência</span><?php endif; ?>
                    </a>
                    <div class="doc-v4-body">
                        <h3 class="doc-v4-nome"><?= e($d['nome']) ?></h3>
                        <p class="doc-v4-esp"><?= e($d['especialidade']) ?> <span>· <?= e($d['cro']) ?></span></p>
                        <?php if ($nd): ?>
                        <div class="nota-quadros" role="img" aria-label="Nota <?= number_format((float) $nd['media'], 1, ',', '') ?> de 5 em <?= (int) $nd['qtd'] ?> avaliações">
                            <span class="quadros" aria-hidden="true"><?php for ($i = 1; $i <= 5; $i++): ?><span class="<?= $i <= round((float) $nd['media']) ? 'on' : '' ?>">★</span><?php endfor; ?></span>
                            <span class="nota-txt" aria-hidden="true"><?= number_format((float) $nd['media'], 1, ',', '') ?>/5,0 (<?= (int) $nd['qtd'] ?> avaliações)</span>
                        </div>
                        <?php endif; ?>
                        <ul class="doc-info">
                            <?php if ($d['publico']): ?><li><i class="bi bi-people" aria-hidden="true"></i><span><strong>Atende:</strong> <?= e($d['publico']) ?></span></li><?php endif; ?>
                            <li><i class="bi bi-clock" aria-hidden="true"></i><span><strong>Horário:</strong> Seg a Sex · <?= e(implode(' / ', $turnos[$d['id_dentista']] ?? [])) ?></span></li>
                        </ul>
                        <?php if ($cond): ?>
                        <div class="cond-list" aria-label="Principais tratamentos">
                            <?php foreach (array_slice($cond, 0, 3) as $c): ?><span class="tag tag-navy"><?= e($c) ?></span><?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2 mt-auto">
                            <a href="especialista.php?id=<?= (int) $d['id_dentista'] ?>" class="btn btn-ghost flex-fill">Ver perfil<span class="visually-hidden"> de <?= e($d['nome']) ?></span></a>
                            <a href="agendar.php?dentista=<?= (int) $d['id_dentista'] ?>" class="btn btn-teal flex-fill">Agendar<span class="visually-hidden"> com <?= e($d['nome']) ?></span></a>
                        </div>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- PROCEDIMENTOS E PREÇOS -->
<section class="section sec-suave" id="procedimentos">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-5">
            <div>
                <span class="eyebrow">Tratamentos</span>
                <h2 class="section-title mt-3 mb-2">Procedimentos e valores</h2>
                <p class="section-sub mb-0">Valores de referência. O orçamento final é confirmado pelo especialista na consulta, antes de qualquer procedimento.</p>
            </div>
        </div>
        <div class="row g-4 proc-grid">
            <?php foreach ($procedimentos as $p): $ehPrimeira = stripos($p['nome'], 'Primeira consulta') !== false; ?>
            <div class="col-sm-6 col-lg-4">
                <a href="agendar.php?procedimento=<?= (int) $p['id_procedimento'] ?><?= $ehPrimeira ? '&amp;primeira=1' : '' ?>" class="proc-v4<?= $ehPrimeira ? ' proc-primeira' : '' ?>">
                    <?php if ($ehPrimeira): ?><span class="proc-selo"><i class="bi bi-stars" aria-hidden="true"></i> Comece por aqui</span><?php endif; ?>
                    <span class="proc-v4-ico" aria-hidden="true"><i class="bi <?= e($p['icone']) ?>"></i></span>
                    <h3 class="proc-v4-tit"><?= e($p['nome']) ?></h3>
                    <p class="proc-v4-desc"><?= e($p['descricao']) ?></p>
                    <span class="proc-v4-meta">
                        <span><i class="bi bi-clock" aria-hidden="true"></i> <?= duracao_txt($p['duracao_min']) ?></span>
                        <span class="price">a partir de <?= moeda($p['valor']) ?></span>
                    </span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- AGENDAMENTO RÁPIDO: foto ao lado -->
<section class="section" id="agende" aria-labelledby="agendeTit">
    <div class="container">
        <div class="agende-split">
            <div class="agende-foto">
                <img src="<?= e(foto_site('agendar-inicio', 1100)) ?>" alt="" loading="lazy" onerror="this.remove()">
                <div class="agende-foto-conteudo">
                    <div class="agende-chips">
                        <span><i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Araras/SP</span>
                        <span class="chip-on"><i class="bi bi-circle-fill" aria-hidden="true"></i> Seg a Sex · 07h30 às 19h</span>
                    </div>
                    <div>
                        <h2 id="agendeTit" class="agende-tit">Agende sua consulta hoje!</h2>
                        <p class="mb-0">Escolha o tratamento e o especialista: os horários livres aparecem na hora.</p>
                    </div>
                    <?php if ($resumo['qtd']): ?>
                    <p class="agende-nota mb-0">Nossos pacientes avaliam:
                        <span class="quadros quadros-sm" aria-hidden="true"><?php for ($i = 1; $i <= 5; $i++): ?><span class="<?= $i <= round((float) $resumo['media']) ? 'on' : '' ?>">★</span><?php endfor; ?></span>
                        <strong><?= number_format((float) $resumo['media'], 1, ',', '') ?>/5,0</strong> (<?= (int) $resumo['qtd'] ?> avaliações)</p>
                    <?php endif; ?>
                </div>
            </div>
            <form action="agendar.php" method="GET" class="agende-form">
                <p class="eyebrow mb-3"><i class="bi bi-lightning-charge" aria-hidden="true"></i> Agendamento em 1 minuto</p>
                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label" for="agProc">Tratamento</label>
                        <select id="agProc" name="procedimento" class="form-select form-linha">
                            <option value="">Selecione o tratamento</option>
                            <?php foreach ($procedimentos as $p): ?><option value="<?= (int) $p['id_procedimento'] ?>"><?= e($p['nome']) ?> · a partir de <?= moeda($p['valor']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="agDent">Especialista <span class="text-muted fw-normal">(opcional)</span></label>
                        <select id="agDent" name="dentista" class="form-select form-linha">
                            <option value="">Qualquer especialista</option>
                            <?php foreach ($dentistas as $d): ?><option value="<?= (int) $d['id_dentista'] ?>"><?= e($d['nome']) ?> — <?= e($d['especialidade']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <ul class="agende-lista">
                            <li><i class="bi bi-check2-square" aria-hidden="true"></i> Confirmação da secretaria pelo WhatsApp</li>
                            <li><i class="bi bi-check2-square" aria-hidden="true"></i> Pagamento só na clínica, no dia</li>
                            <li><i class="bi bi-lock" aria-hidden="true"></i> Seus dados protegidos</li>
                        </ul>
                    </div>
                    <div class="col-12"><button type="submit" class="btn btn-teal btn-lg w-100"><i class="bi bi-calendar2-week me-2" aria-hidden="true"></i>Ver horários livres</button></div>
                </div>
            </form>
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
                    <div class="d-flex justify-content-between align-items-center gap-2"><?= estrelas($a['nota']) ?><span class="review-data"><?= e(mes_ano($a['criado_em'])) ?></span></div>
                    <blockquote>“<?= e($a['comentario']) ?>”</blockquote>
                    <figcaption class="who">
                        <span class="avatar avatar-xs" style="--c:#4f7cc4" aria-hidden="true"><?= e(iniciais($a['nome'])) ?></span>
                        <span><strong><?= e($a['nome']) ?></strong><?php if ($a['paciente_desde']): ?> <span class="paciente-desde"><i class="bi bi-award" aria-hidden="true"></i> paciente desde <?= (int) $a['paciente_desde'] ?></span><?php endif; ?><?php if ($a['dentista']): ?><br><span class="text-muted">sobre <?= e($a['dentista']) ?></span><?php endif; ?></span>
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
                        <div class="col-md-4"><label class="form-label" for="avNome">Seu nome</label><input id="avNome" name="nome" class="form-control" required maxlength="60" placeholder="Ex.: Maria S." value="<?= e(antigo('avaliacao', 'nome')) ?>"></div>
                        <div class="col-md-4"><label class="form-label" for="avDesde">Paciente desde <span class="text-muted fw-normal">(opcional)</span></label>
                            <select id="avDesde" name="paciente_desde" class="form-select"><option value="">Primeira vez</option>
                                <?php for ($ano = (int) date('Y'); $ano >= 2010; $ano--): ?><option value="<?= $ano ?>" <?= (int) antigo('avaliacao', 'paciente_desde') === $ano ? 'selected' : '' ?>><?= $ano ?></option><?php endfor; ?>
                            </select></div>
                        <div class="col-md-4"><label class="form-label" for="avDent">Especialista <span class="text-muted fw-normal">(opcional)</span></label>
                            <select id="avDent" name="id_dentista" class="form-select"><option value="">Clínica em geral</option>
                                <?php foreach ($dentistas as $d): ?><option value="<?= (int) $d['id_dentista'] ?>" <?= (string) antigo('avaliacao', 'id_dentista') === (string) $d['id_dentista'] ? 'selected' : '' ?>><?= e($d['nome']) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="col-12"><label class="form-label" for="avCom">Comentário</label><textarea id="avCom" name="comentario" class="form-control" rows="3" required minlength="10" maxlength="600" placeholder="Como foi o atendimento?"><?= e(antigo('avaliacao', 'comentario')) ?></textarea></div>
                        <div class="col-12"><?= protecao_campo('avaliacao') ?></div>
                        <div class="col-12"><button type="submit" class="btn btn-teal"><i class="bi bi-send me-1" aria-hidden="true"></i> Enviar avaliação</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- PERGUNTAS FREQUENTES -->
<section class="section faq-sec" id="duvidas" aria-labelledby="faqTit">
    <div class="container">
        <div class="text-center mb-4 mb-lg-5">
            <span class="faq-kicker">Perguntas frequentes</span>
            <h2 id="faqTit" class="faq-tit">Perguntas e Respostas.</h2>
            <p class="section-sub mx-auto mb-0">As dúvidas que mais ouvimos na recepção. Não achou a sua? <a href="#contato">Fale com a gente</a>.</p>
        </div>
        <div class="faq-lista">
            <?php foreach ($FAQ as $i => [$perg, $resp]): ?>
            <details class="faq-item" <?= $i === 0 ? 'open' : '' ?>>
                <summary><span><?= e($perg) ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                <div class="faq-resp"><p class="mb-0"><?= e($resp) ?></p></div>
            </details>
            <?php endforeach; ?>
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
                    <li class="d-flex gap-3"><span class="feature-ico"><i class="bi bi-clock" aria-hidden="true"></i></span><div><strong>Horário</strong><br><span class="text-muted">Segunda a sexta, 07h30 às 19h00</span></div></li>
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
                        <div class="col-12"><?= protecao_campo('contato') ?></div>
                        <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                            <button type="submit" class="btn btn-teal"><i class="bi bi-envelope me-1" aria-hidden="true"></i> Enviar mensagem</button>
                            <span class="small text-muted"><i class="bi bi-lock" aria-hidden="true"></i> Seus dados são usados apenas para responder você.</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-12">
                <div class="mapa-box">
                    <iframe src="<?= e($mapa_embed) ?>" title="Mapa com a localização da clínica <?= e($nome_clinica) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                    <div class="mapa-card">
                        <strong><i class="bi bi-geo-alt-fill text-teal me-1" aria-hidden="true"></i><?= e($nome_clinica) ?></strong>
                        <span class="small text-muted"><?= e($endereco) ?></span>
                        <a href="<?= e($mapa_link) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost mt-2 align-self-start"><i class="bi bi-sign-turn-right me-1" aria-hidden="true"></i>Como chegar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php limpar_antigos('avaliacao'); limpar_antigos('contato');
include __DIR__ . '/include/rodape.php'; ?>

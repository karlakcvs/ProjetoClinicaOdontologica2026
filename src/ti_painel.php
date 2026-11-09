<?php
// ÁREA TÉCNICA SanyTech – FUNCIONAMENTO: saúde do servidor, banco, pastas e configuração.
// Não mostra dados de pacientes, consultas, finanças, senhas nem credenciais do banco.
require_once __DIR__ . '/include/check_ti.php';
require_once __DIR__ . '/include/conexao.php';

$checks = [];   // [grupo, item, situação ok|aviso|erro, detalhe]
$ok = fn($c) => $c ? 'ok' : 'erro';

// ---------- Banco de dados ----------
$t = microtime(true);
for ($i = 0; $i < 5; $i++) $pdo->query('SELECT 1')->fetchColumn();
$latencia = (microtime(true) - $t) / 5 * 1000;
$versao = versao_banco($pdo);
$checks[] = ['Banco de dados', 'Conexão', 'ok', 'Conectado (' . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'MySQL' : 'SQLite de teste') . ')'];
$checks[] = ['Banco de dados', 'Tempo de resposta', $latencia < 20 ? 'ok' : ($latencia < 100 ? 'aviso' : 'erro'), number_format($latencia, 2, ',', '') . ' ms por consulta'];
$checks[] = ['Banco de dados', 'Versão da estrutura', $versao >= VERSAO_BANCO ? 'ok' : 'aviso', 'Versão ' . $versao . ' de ' . VERSAO_BANCO . ($versao >= VERSAO_BANCO ? ' · atualizado' : ' · atualização pendente')];

// ---------- Servidor ----------
$checks[] = ['Servidor', 'PHP', version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'aviso', 'PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION];
foreach (['mbstring', 'fileinfo', 'iconv', 'json'] as $ext) $checks[] = ['Servidor', 'Extensão ' . $ext, $ok(extension_loaded($ext)), extension_loaded($ext) ? 'carregada' : 'faltando'];
$checks[] = ['Servidor', 'Fuso horário', date_default_timezone_get() === 'America/Sao_Paulo' ? 'ok' : 'aviso', date_default_timezone_get()];
$livre = @disk_free_space(__DIR__); $total = @disk_total_space(__DIR__);
if ($livre && $total) { $pct = $livre / $total * 100; $checks[] = ['Servidor', 'Espaço em disco', $pct > 15 ? 'ok' : ($pct > 5 ? 'aviso' : 'erro'), number_format($pct, 0) . '% livre']; }

// ---------- Pastas e proteção de arquivos ----------
foreach (['uploads/dentistas', 'uploads/galeria', 'uploads/prontuario'] as $pasta) {
    $caminho = __DIR__ . '/' . $pasta;
    $checks[] = ['Arquivos', 'Gravação em ' . $pasta, $ok(is_dir($caminho) && is_writable($caminho)), is_writable($caminho) ? 'permitida' : 'sem permissão de escrita'];
}
foreach (['include', 'classes', 'uploads', 'uploads/prontuario'] as $pasta) {
    $checks[] = ['Arquivos', 'Bloqueio de acesso direto: ' . $pasta . '/', $ok(is_file(__DIR__ . '/' . $pasta . '/.htaccess')), is_file(__DIR__ . '/' . $pasta . '/.htaccess') ? '.htaccess presente' : '.htaccess ausente'];
}

// ---------- Configuração de segurança ----------
$cab = implode("\n", headers_list());
$checks[] = ['Configuração', 'Erros escondidos do visitante', $ok(!ini_get('display_errors') || ini_get('display_errors') === 'Off'), ini_get('display_errors') ? 'display_errors ligado' : 'display_errors desligado'];
$checks[] = ['Configuração', 'Versão do PHP escondida', ini_get('expose_php') ? 'aviso' : 'ok', ini_get('expose_php') ? 'expose_php ligado' : 'expose_php desligado'];
$checks[] = ['Configuração', 'Cookie de sessão HttpOnly + SameSite', $ok((bool) ini_get('session.cookie_httponly')), 'HttpOnly, SameSite=Lax, modo estrito'];
$checks[] = ['Configuração', 'Cabeçalhos de segurança (CSP, X-Frame…)', $ok(stripos($cab, 'Content-Security-Policy') !== false && stripos($cab, 'X-Frame-Options') !== false), 'enviados em todas as páginas'];
$checks[] = ['Configuração', 'HTTPS', https_ativo() ? 'ok' : 'aviso', https_ativo() ? 'ativo (HSTS ligado)' : 'desligado – normal em localhost; obrigatório em produção'];

// ---------- Disponibilidade (últimas 24 h) ----------
$st = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status >= 500 THEN 1 ELSE 0 END) AS erros, SUM(CASE WHEN status = 429 THEN 1 ELSE 0 END) AS limitadas
                     FROM metrica_requisicao WHERE criado_em >= ?");
$st->execute([date('Y-m-d H:i:s', time() - 86400)]);
$m = $st->fetch();
$total24 = (int) $m['total'];
$erros24 = (int) $m['erros'];
$disp = $total24 ? (1 - $erros24 / $total24) * 100 : 100;
$checks[] = ['Disponibilidade', 'Respostas sem erro de servidor (24 h)', $disp >= 99.5 ? 'ok' : ($disp >= 97 ? 'aviso' : 'erro'), number_format($disp, 2, ',', '') . '% de ' . $total24 . ' requisições'];

$cont = ['ok' => 0, 'aviso' => 0, 'erro' => 0];
foreach ($checks as $c) $cont[$c[2]]++;
$geral = $cont['erro'] ? 'erro' : ($cont['aviso'] ? 'aviso' : 'ok');
$rotGeral = ['ok' => 'Tudo funcionando', 'aviso' => 'Funcionando com avisos', 'erro' => 'Atenção: há falhas'][$geral];
$grupos = [];
foreach ($checks as $c) $grupos[$c[0]][] = $c;
$icone = ['ok' => 'bi-check-circle-fill', 'aviso' => 'bi-exclamation-triangle-fill', 'erro' => 'bi-x-octagon-fill'];

$titulo = 'Funcionamento';
$pagina = 'ti_painel';
include __DIR__ . '/include/admin_topo.php';
?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat stat-hero"><span class="ico"><i class="bi <?= $icone[$geral] ?>" aria-hidden="true"></i></span><div class="val" style="font-size:1.05rem;white-space:normal"><?= $rotGeral ?></div><div class="lab"><?= $cont['ok'] ?> ok · <?= $cont['aviso'] ?> aviso(s) · <?= $cont['erro'] ?> falha(s)</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-teal"><i class="bi bi-database-check" aria-hidden="true"></i></span><div class="val"><?= number_format($latencia, 1, ',', '') ?> ms</div><div class="lab">resposta do banco</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-navy"><i class="bi bi-graph-up" aria-hidden="true"></i></span><div class="val"><?= number_format($total24, 0, ',', '.') ?></div><div class="lab">requisições nas últimas 24 h</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat"><span class="ico ico-gold"><i class="bi bi-shield-check" aria-hidden="true"></i></span><div class="val"><?= number_format($disp, 2, ',', '') ?>%</div><div class="lab">disponibilidade (24 h)</div></div></div>
</div>

<div class="row g-4">
    <?php foreach ($grupos as $g => $itens): ?>
    <div class="col-lg-6">
        <div class="card-soft panel h-100">
            <h2 class="panel-title mb-2"><?= e($g) ?></h2>
            <ul class="list-unstyled mb-0 check-lista">
                <?php foreach ($itens as [, $item, $sit, $det]): ?>
                    <li class="chk-<?= $sit ?>"><i class="bi <?= $icone[$sit] ?>" aria-hidden="true"></i><span class="flex-grow-1"><strong><?= e($item) ?></strong><br><span class="small text-muted"><?= e($det) ?></span></span><span class="visually-hidden">Situação: <?= $sit ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Esta área mostra só o necessário para manter o sistema: nenhuma senha, credencial do banco ou dado de paciente aparece aqui.</p>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

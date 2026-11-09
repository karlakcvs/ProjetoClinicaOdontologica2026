<?php
// Área do DENTISTA: todos os pacientes atendidos (ou agendados) por ele
require_once __DIR__ . '/include/check_dentista.php';
require_once __DIR__ . '/include/conexao.php';

$busca = trim($_GET['q'] ?? '');
$ordem = ($_GET['ordem'] ?? '') === 'nome' ? 'nome' : 'recentes';
$hoje = date('Y-m-d');
$sql = "SELECT p.id_paciente, p.nome, p.telefone, p.data_nascimento, p.foto, p.criado_em, p.cpf, p.cep, p.logradouro, p.numero, p.cidade,
               MAX(CASE WHEN c.status = 'REALIZADA' THEN c.data END) AS ultima,
               MIN(CASE WHEN c.data >= ? AND c.status IN ('AGENDADA','CONFIRMADA') THEN c.data END) AS proxima,
               SUM(CASE WHEN c.status = 'REALIZADA' THEN 1 ELSE 0 END) AS sessoes,
               (SELECT COUNT(*) FROM prontuario r WHERE r.id_paciente = p.id_paciente AND r.tipo = 'FOTO') AS fotos,
               (SELECT COUNT(*) FROM prontuario r WHERE r.id_paciente = p.id_paciente AND r.tipo = 'EXAME') AS exames
        FROM paciente p JOIN consulta c ON c.id_paciente = p.id_paciente AND c.id_dentista = ?";
$par = [$hoje, $ID_DENTISTA];
if ($busca !== '') { $sql .= " WHERE p.nome LIKE ? OR p.telefone LIKE ?"; $par[] = "%$busca%"; $par[] = "%$busca%"; }
$sql .= " GROUP BY p.id_paciente, p.nome, p.telefone, p.data_nascimento, p.foto, p.criado_em, p.cpf, p.cep, p.logradouro, p.numero, p.cidade";
$sql .= $ordem === 'nome' ? " ORDER BY p.nome" : " ORDER BY MAX(c.data) DESC";
$st = $pdo->prepare($sql);
$st->execute($par);
$pacientes = $st->fetchAll();

$titulo = 'Meus pacientes';
$pagina = 'dent_pacientes';
include __DIR__ . '/include/admin_topo.php';
?>
<form class="card-soft p-3 mb-3 d-flex flex-wrap gap-2" role="search">
    <label class="visually-hidden" for="q">Buscar paciente</label>
    <div class="input-icon flex-grow-1" style="min-width:200px"><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="q" name="q" value="<?= e($busca) ?>" class="form-control" placeholder="Buscar por nome ou telefone"></div>
    <label class="visually-hidden" for="ordem">Ordenar</label>
    <select id="ordem" name="ordem" class="form-select" style="max-width:200px" onchange="this.form.submit()">
        <option value="recentes" <?= $ordem === 'recentes' ? 'selected' : '' ?>>Mais recentes</option>
        <option value="nome" <?= $ordem === 'nome' ? 'selected' : '' ?>>Nome (A–Z)</option>
    </select>
    <button class="btn btn-navy">Buscar</button>
</form>
<p class="small text-muted"><?= count($pacientes) ?> paciente(s). Toque em um paciente para ver e registrar fotos das sessões, exames e observações.</p>

<div class="pac-list">
<?php foreach ($pacientes as $p): $id = idade($p['data_nascimento']); ?>
    <a href="dent_paciente.php?id=<?= (int) $p['id_paciente'] ?>" class="pac-item">
        <?= avatar_paciente($p, $p['foto'] ? 'md' : 'sm') ?>
        <div class="min-w-0">
            <div class="nm text-truncate"><?= e($p['nome']) ?><?php if (cadastro_completo($p) && $p['criado_em'] < date('Y-01-01', strtotime('-1 year'))): ?> <span class="paciente-desde ms-1"><i class="bi bi-award" aria-hidden="true"></i> desde <?= date('Y', strtotime($p['criado_em'])) ?></span><?php endif; ?></div>
            <div class="meta"><?= $id !== null ? $id . ' anos · ' : '' ?><?= e($p['telefone']) ?> · <?= (int) $p['sessoes'] ?> sessão(ões)
                <?= $p['ultima'] ? ' · última ' . data_br($p['ultima']) : '' ?></div>
        </div>
        <div class="d-flex flex-wrap gap-1 justify-content-end">
            <?php if ($p['proxima']): ?><span class="status status-confirmada"><i class="bi bi-calendar-event" aria-hidden="true"></i> <?= date('d/m', strtotime($p['proxima'])) ?></span><?php endif; ?>
            <span class="tag" title="Fotos"><i class="bi bi-camera" aria-hidden="true"></i> <?= (int) $p['fotos'] ?></span>
            <span class="tag tag-navy" title="Exames"><i class="bi bi-file-earmark-medical" aria-hidden="true"></i> <?= (int) $p['exames'] ?></span>
        </div>
    </a>
<?php endforeach; ?>
</div>
<?php if (!$pacientes): ?><div class="empty-state"><i class="bi bi-people" aria-hidden="true"></i><p class="mt-2 mb-0">Nenhum paciente encontrado.</p></div><?php endif; ?>
<?php include __DIR__ . '/include/admin_fim.php'; ?>

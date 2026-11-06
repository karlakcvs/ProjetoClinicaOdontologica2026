<?php
// Cartão de consulta com as ações da secretaria (usado no painel e na agenda)
function card_consulta($c, $voltar, $mostrarData = false) {
    global $STATUS_CONSULTA;
    [$rotulo, $classe, $icone] = $STATUS_CONSULTA[$c['status']] ?? [$c['status'], '', 'bi-circle'];
    $st = $c['status'];
    $tel = preg_replace('/\D/', '', $c['telefone']);
    ob_start(); ?>
    <div class="appt is-<?= strtolower($st) ?>" style="--c:<?= e($c['cor']) ?>">
        <div class="time"><?= hm($c['hora_inicio']) ?><small>até <?= hm($c['hora_fim']) ?></small>
            <?php if ($mostrarData): ?><small><?= date('d/m', strtotime($c['data'])) ?></small><?php endif; ?>
        </div>
        <div class="min-w-0">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="who"><?= e($c['paciente']) ?></span>
                <span class="status <?= $classe ?>"><i class="bi <?= $icone ?>"></i><?= $rotulo ?></span>
                <?php if ($c['origem'] === 'SITE'): ?><span class="tag" title="Agendado pelo próprio paciente no site"><i class="bi bi-globe2" aria-hidden="true"></i> site</span><?php endif; ?>
                <?php if (!empty($c['primeira_consulta'])): ?><span class="tag tag-navy" title="Primeira consulta na clínica"><i class="bi bi-stars" aria-hidden="true"></i> 1ª consulta</span><?php endif; ?>
                <?php if (!cadastro_completo($c)): ?><a href="pacientes.php?editar=<?= (int) $c['id_paciente'] ?>" class="badge-inc" title="Faltam CPF, nascimento ou endereço"><i class="bi bi-person-vcard" aria-hidden="true"></i> completar cadastro</a><?php endif; ?>
            </div>
            <div class="what"><?= e($c['procedimento']) ?> · <span style="color:<?= e($c['cor']) ?>;font-weight:600"><?= e($c['dentista']) ?></span></div>
            <div class="what">
                <i class="bi bi-telephone"></i> <?= e($c['telefone']) ?>
                <?php if ($st === 'REALIZADA' && $c['valor_pago'] !== null): ?>
                    · <strong class="text-success"><?= moeda($c['valor_pago']) ?></strong> <span class="text-muted">(<?= e($c['forma_pagamento']) ?>)</span>
                <?php else: ?>
                    · <?= moeda($c['valor_tabela']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="acts">
            <?php if (in_array($st, ['AGENDADA'])): ?>
                <form method="POST" action="acao_consulta.php" class="d-inline"><?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= $c['id_consulta'] ?>"><input type="hidden" name="acao" value="CONFIRMADA"><input type="hidden" name="voltar" value="<?= e($voltar) ?>">
                    <button class="btn btn-sm btn-soft" title="Confirmar presença"><i class="bi bi-check2"></i> Confirmar</button>
                </form>
            <?php endif; ?>
            <?php if (in_array($st, ['AGENDADA', 'CONFIRMADA', 'REALIZADA'])): ?>
                <button type="button" class="btn btn-sm <?= $st === 'REALIZADA' ? 'btn-ghost' : 'btn-teal' ?>" data-bs-toggle="modal" data-bs-target="#modalPagamento"
                        data-id="<?= $c['id_consulta'] ?>" data-paciente="<?= e($c['paciente']) ?>" data-proc="<?= e($c['procedimento']) ?>"
                        data-valor="<?= number_format((float) ($c['valor_pago'] ?? $c['valor_tabela']), 2, '.', '') ?>" data-forma="<?= e($c['forma_pagamento'] ?? 'PIX') ?>">
                    <i class="bi bi-cash-coin"></i> <?= $st === 'REALIZADA' ? 'Pagamento' : 'Atendido' ?>
                </button>
            <?php endif; ?>
            <?php if (in_array($st, ['AGENDADA', 'CONFIRMADA']) && $tel): ?>
                <a class="btn btn-icon btn-ghost" title="Lembrete no WhatsApp" target="_blank" rel="noopener" href="<?= e(link_lembrete($c)) ?>"><i class="bi bi-whatsapp text-success"></i></a>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-icon btn-ghost" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Mais ações para a consulta de <?= e($c['paciente']) ?>"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item" href="editar.php?id=<?= $c['id_consulta'] ?>"><i class="bi bi-pencil me-2"></i>Editar / remarcar</a></li>
                    <li><a class="dropdown-item" href="pacientes.php?editar=<?= (int) $c['id_paciente'] ?>"><i class="bi bi-person-vcard me-2"></i>Dados do paciente (CEP)</a></li>
                    <?php foreach (['FALTOU' => ['bi-person-x', 'Marcar falta'], 'CANCELADA' => ['bi-x-circle', 'Cancelar consulta'], 'AGENDADA' => ['bi-arrow-counterclockwise', 'Voltar para agendada']] as $novo => [$ic, $txt]): ?>
                        <?php if ($novo !== $st): ?>
                        <li><form method="POST" action="acao_consulta.php"><?= csrf_campo() ?>
                            <input type="hidden" name="id" value="<?= $c['id_consulta'] ?>"><input type="hidden" name="acao" value="<?= $novo ?>"><input type="hidden" name="voltar" value="<?= e($voltar) ?>">
                            <button class="dropdown-item"><i class="bi <?= $ic ?> me-2"></i><?= $txt ?></button></form></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="excluir.php" data-confirm="Excluir definitivamente esta consulta?"><?= csrf_campo() ?>
                        <input type="hidden" name="id" value="<?= $c['id_consulta'] ?>"><input type="hidden" name="voltar" value="<?= e($voltar) ?>">
                        <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Excluir</button></form></li>
                </ul>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}

// Mensagem pronta de lembrete pelo WhatsApp (RF07)
function link_lembrete($c) {
    global $nome_clinica, $endereco;
    $tel = preg_replace('/\D/', '', $c['telefone']);
    if (strlen($tel) <= 11) $tel = '55' . $tel;
    $dias = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
    $msg = "Olá, " . explode(' ', $c['paciente'])[0] . "! Aqui é da clínica $nome_clinica. "
         . "Lembramos da sua consulta de {$c['procedimento']} com {$c['dentista']} na "
         . $dias[(int) date('w', strtotime($c['data']))] . ", " . date('d/m', strtotime($c['data'])) . " às " . hm($c['hora_inicio']) . ". "
         . "Endereço: $endereco. Podemos confirmar sua presença?";
    return 'https://wa.me/' . $tel . '?text=' . rawurlencode($msg);
}

function modal_pagamento($voltar) {
    global $FORMAS_PAGAMENTO;
    ob_start(); ?>
    <div class="modal fade" id="modalPagamento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="acao_consulta.php">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="PAGAMENTO">
                <input type="hidden" name="id" id="pgId">
                <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
                <div class="modal-header">
                    <div><h5 class="modal-title mb-0">Registrar atendimento</h5><small class="text-muted" id="pgInfo"></small></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">A consulta será marcada como <strong>realizada</strong> e o valor entra no financeiro do dentista.</p>
                    <div class="mb-3">
                        <label class="form-label">Valor recebido (R$)</label>
                        <input type="number" step="0.01" min="0" name="valor" id="pgValor" class="form-control form-control-lg" required>
                    </div>
                    <label class="form-label">Forma de pagamento</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($FORMAS_PAGAMENTO as $i => $fp): ?>
                            <label class="choice"><input type="radio" name="forma" value="<?= e($fp) ?>" <?= $i === 0 ? 'checked' : '' ?>><span class="box py-2 px-3 small fw-semibold"><?= e($fp) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-teal"><i class="bi bi-check2-circle me-1"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    document.getElementById('modalPagamento').addEventListener('show.bs.modal', ev => {
        const b = ev.relatedTarget;
        document.getElementById('pgId').value = b.dataset.id;
        document.getElementById('pgValor').value = b.dataset.valor;
        document.getElementById('pgInfo').textContent = b.dataset.paciente + ' · ' + b.dataset.proc;
        const r = document.querySelector('#modalPagamento input[name="forma"][value="' + b.dataset.forma + '"]');
        if (r) r.checked = true;
    });
    </script>
    <?php return ob_get_clean();
}

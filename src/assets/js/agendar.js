// Agendamento em etapas - Garcia Di Mase (SanyTech)
(function () {
  const cfg = window.AGENDA;
  const form = document.getElementById('formAgendar');
  if (!form) return;

  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const DOW = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
  const MES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
  const MES_L = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  const DOW_L = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

  const inpData = $('#inpData'), inpHora = $('#inpHora');
  const strip = $('#dateStrip'), slotsArea = $('#slotsArea');

  const esc = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const proc = () => $('input[name="id_procedimento"]:checked');
  const dent = () => $('input[name="id_dentista"]:checked');
  const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

  function filtrarDentistas() {
    const p = proc();
    const permitidos = p ? (cfg.mapa[p.value] || []) : null;
    $$('input[name="id_dentista"]').forEach(r => {
      const ok = !permitidos || permitidos.includes(parseInt(r.value, 10));
      r.disabled = !ok;
      r.closest('.choice').style.display = ok ? '' : 'none';
      if (!ok && r.checked) r.checked = false;
    });
    const dica = $('#dicaDentista');
    if (p) {
      dica.textContent = permitidos.length === 1 ? 'Este procedimento é realizado por:' : 'Especialistas que realizam este procedimento:';
      // se só um dentista realiza, já seleciona
      if (permitidos.length === 1) { const r = $(`input[name="id_dentista"][value="${permitidos[0]}"]`); if (r) r.checked = true; }
    }
  }

  function montarDatas() {
    inpData.value = ''; inpHora.value = '';
    slotsArea.innerHTML = '';
    const d = dent(), p = proc();
    if (!d || !p) { strip.innerHTML = '<span class="small text-muted">Escolha o procedimento e o especialista primeiro.</span>'; return; }
    const dias = cfg.dias[d.value] || [];
    strip.innerHTML = '';
    const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
    for (let i = 0; i <= cfg.diasAFrente; i++) {
      const dt = new Date(hoje); dt.setDate(hoje.getDate() + i);
      if (!dias.includes(dt.getDay())) continue;
      const lab = document.createElement('label');
      lab.className = 'choice date-pill';
      lab.innerHTML = `<input type="radio" name="_dia" value="${iso(dt)}"><span class="box">
        <span class="dow">${i === 0 ? 'Hoje' : DOW[dt.getDay()]}</span><span class="day">${dt.getDate()}</span><span class="mon">${MES[dt.getMonth()]}</span></span>`;
      strip.appendChild(lab);
    }
    const primeiro = $('input[name="_dia"]', strip);
    if (primeiro) { primeiro.checked = true; carregarHorarios(primeiro.value); }
  }

  async function carregarHorarios(data) {
    inpData.value = data; inpHora.value = '';
    atualizarResumo();
    slotsArea.innerHTML = '<div class="text-muted small py-3"><span class="spinner-border spinner-border-sm me-2"></span>Buscando horários livres…</div>';
    const params = new URLSearchParams({ dentista: dent().value, procedimento: proc().value, data });
    if (cfg.ignorar) params.set('ignorar', cfg.ignorar);
    try {
      const r = await fetch('api/horarios.php?' + params, { credentials: 'same-origin' });
      const j = await r.json();
      if (r.status === 429) {   // rate limiting da API: muitas consultas em pouco tempo
        slotsArea.innerHTML = '<div class="alert alert-warning d-flex gap-2" role="alert"><i class="bi bi-shield-lock" aria-hidden="true"></i><div>' + esc(j.msg || 'Muitas consultas em pouco tempo. Aguarde alguns segundos.') + '</div></div>';
        return;
      }
      renderSlots(j.horarios || []);
    } catch (e) {
      slotsArea.innerHTML = '<div class="alert alert-warning">Não foi possível carregar os horários. Tente novamente.</div>';
    }
  }

  function renderSlots(lista) {
    if (!lista.length) {
      slotsArea.innerHTML = '<div class="empty-state py-4"><i class="bi bi-calendar-x"></i><p class="mt-2 mb-0">Sem horários livres neste dia para este procedimento. Escolha outra data.</p></div>';
      return;
    }
    const grupos = { manha: ['Manhã', 'bi-sunrise'], tarde: ['Tarde', 'bi-sun'], noite: ['Noite', 'bi-moon-stars'] };
    let html = `<p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i>Cada horário já reserva ${esc(proc().dataset.dur)} min para o procedimento.</p>`;
    for (const g in grupos) {
      const itens = lista.filter(h => h.periodo === g);
      if (!itens.length) continue;
      html += `<div class="slot-group-title"><i class="bi ${grupos[g][1]}"></i>${grupos[g][0]}</div><div class="slots">`;
      html += itens.map(h => `<label class="choice slot"><input type="radio" name="_hora" value="${esc(h.inicio)}" data-fim="${esc(h.fim)}">
        <span class="box">${esc(h.inicio)}<small>até ${esc(h.fim)}</small></span></label>`).join('');
      html += '</div>';
    }
    slotsArea.innerHTML = html;
    if (cfg.horaAtual) {
      const r = $(`input[name="_hora"][value="${cfg.horaAtual}"]`, slotsArea);
      if (r) { r.checked = true; inpHora.value = r.value; }
      cfg.horaAtual = null;
      atualizarResumo();
    }
  }

  function atualizarResumo() {
    const p = proc(), d = dent();
    $('#rProc').innerHTML = p ? `${esc(p.dataset.nome)}<br><small class="text-muted">${esc(p.dataset.dur)} min</small>` : '—';
    $('#rValor').textContent = p ? p.dataset.valor : '—';
    $('#rDent').innerHTML = d ? `${esc(d.dataset.nome)}<br><small class="text-muted">${esc(d.dataset.esp)}</small>` : '—';
    if (inpData.value) {
      const [y, m, dd] = inpData.value.split('-').map(Number);
      const dt = new Date(y, m - 1, dd);
      $('#rData').textContent = `${DOW_L[dt.getDay()]}, ${dd} de ${MES_L[m - 1]}`;
    } else $('#rData').textContent = '—';
    const h = $('input[name="_hora"]:checked');
    $('#rHora').textContent = h ? `${h.value} às ${h.dataset.fim}` : '—';

    // etapas
    const nomeOk = /\S+\s+\S+/.test($('#nome').value.trim());   // nome completo: nome e sobrenome
    const telOk = $('#tel').value.replace(/\D/g, '').length >= 10;
    const etapas = [!!p, !!d, !!(inpData.value && inpHora.value), nomeOk && telOk];
    $$('#stepper .st').forEach((el, i) => {
      el.classList.toggle('done', etapas[i]);
      el.classList.toggle('on', !etapas[i] && etapas.slice(0, i).every(Boolean));
    });
    const tudo = etapas.every(Boolean);
    $('#btnConfirmar').disabled = !tudo;
    const falta = ['o procedimento', 'o especialista', 'data e horário', 'nome e telefone'].filter((_, i) => !etapas[i]);
    $('#faltando').textContent = tudo ? 'Tudo certo! Confira e confirme.' : 'Falta escolher: ' + falta.join(', ') + '.';
  }

  // máscara de telefone (site.js)
  $('#tel').dataset.mask = 'tel';

  form.addEventListener('change', e => {
    const n = e.target.name;
    if (n === 'id_procedimento') { filtrarDentistas(); montarDatas(); }
    else if (n === 'id_dentista') montarDatas();
    else if (n === '_dia') carregarHorarios(e.target.value);
    else if (n === '_hora') inpHora.value = e.target.value;
    atualizarResumo();
  });
  form.addEventListener('input', atualizarResumo);

  const sel = $('#selPaciente');
  if (sel) sel.addEventListener('change', () => {
    const o = sel.selectedOptions[0];
    $('#nome').value = o.dataset.nome || ''; $('#tel').value = o.dataset.tel || ''; $('#email').value = o.dataset.email || '';
    atualizarResumo();
  });

  // primeira consulta: mostra a dica e sugere o procedimento "Primeira consulta / Avaliação"
  $$('input[name="primeira_consulta"]').forEach(r => r.addEventListener('change', () => {
    const sim = $('input[name="primeira_consulta"]:checked').value === '1';
    const dica = $('#dicaPrim'); if (dica) dica.hidden = !sim;
    if (sim && !proc()) {
      const pc = $$('input[name="id_procedimento"]').find(i => /primeira consulta/i.test(i.dataset.nome));
      if (pc) { pc.checked = true; filtrarDentistas(); montarDatas(); atualizarResumo(); pc.closest('.booking-card').scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    }
  }));

  form.addEventListener('submit', e => {
    if ($('#btnConfirmar').disabled) { e.preventDefault(); return; }
    $('#btnConfirmar').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Confirmando…';
  });

  // estado inicial (vindo de links como agendar.php?dentista=1)
  filtrarDentistas();
  if (proc() && dent()) montarDatas();
  atualizarResumo();
})();

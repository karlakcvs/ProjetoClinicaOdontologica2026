// Garcia Di Mase · scripts comuns (SanyTech)
(function () {
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const root = document.documentElement;
  const salvar = (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} };

  // ---------- Acessibilidade: alto contraste e tamanho do texto ----------
  function syncA11y() {
    const on = root.classList.contains('alto-contraste');
    $$('[data-a11y="contraste"]').forEach(b => b.setAttribute('aria-pressed', on ? 'true' : 'false'));
  }
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-a11y]');
    if (!b) return;
    const acao = b.dataset.a11y;
    if (acao === 'contraste') {
      root.classList.toggle('alto-contraste');
      salvar('a11y_contraste', root.classList.contains('alto-contraste') ? '1' : '0');
      syncA11y();
    } else {
      let f = parseInt(root.getAttribute('data-fonte') || '0', 10);
      f = Math.max(-1, Math.min(3, f + (acao === 'mais' ? 1 : -1)));
      if (f) root.setAttribute('data-fonte', f); else root.removeAttribute('data-fonte');
      salvar('a11y_fonte', String(f));
    }
  });
  syncA11y();

  // ---------- Menu sanduíche da área restrita (some/aparece em qualquer tela) ----------
  const sb = $('#sidebar'), bd = $('#backdrop');
  const mobile = () => window.matchMedia('(max-width: 991.98px)').matches;
  function alternarMenu(forcarFechar) {
    if (!sb) return;
    if (mobile()) {
      const abrir = forcarFechar ? false : !sb.classList.contains('open');
      sb.classList.toggle('open', abrir);
      bd && bd.classList.toggle('show', abrir);
      document.body.classList.toggle('travar-rolagem', abrir);
      $$('[data-menu-toggle]').forEach(b => b.setAttribute('aria-expanded', abrir));
      if (abrir) setTimeout(() => sb.querySelector('a, button')?.focus(), 50);
    } else {
      root.classList.toggle('menu-oculto');
      const oculto = root.classList.contains('menu-oculto');
      salvar('menu_oculto', oculto ? '1' : '0');
      $$('[data-menu-toggle]').forEach(b => b.setAttribute('aria-expanded', !oculto));
    }
  }
  $$('[data-menu-toggle]').forEach(b => b.addEventListener('click', () => alternarMenu()));
  bd && bd.addEventListener('click', () => alternarMenu(true));
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && sb && sb.classList.contains('open')) alternarMenu(true); });
  $$('[data-menu-toggle]').forEach(b => b.setAttribute('aria-expanded', mobile() ? 'false' : String(!root.classList.contains('menu-oculto'))));

  // ---------- Confirmação antes de ações destrutivas ----------
  $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

  // ---------- Rate limiting: contagem regressiva quando o login está bloqueado ----------
  $$('[data-libera-em]').forEach(el => {
    const fim = parseInt(el.dataset.liberaEm, 10) * 1000;
    const alvo = $('[data-contador]', el) || el;
    const btn = document.getElementById(el.dataset.botao || '');
    const tick = () => {
      const s = Math.max(0, Math.round((fim - Date.now()) / 1000));
      alvo.textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
      if (s <= 0) { clearInterval(t); if (btn) btn.disabled = false; el.classList.add('liberado'); alvo.textContent = 'liberado'; }
    };
    const t = setInterval(tick, 1000); tick();
  });

  // ---------- Máscaras ----------
  const masks = {
    tel: v => { v = v.replace(/\D/g, '').slice(0, 11);
      if (v.length > 6) return `(${v.slice(0, 2)}) ${v.slice(2, v.length - 4)}-${v.slice(-4)}`;
      if (v.length > 2) return `(${v.slice(0, 2)}) ${v.slice(2)}`; return v; },
    cep: v => { v = v.replace(/\D/g, '').slice(0, 8); return v.length > 5 ? v.slice(0, 5) + '-' + v.slice(5) : v; },
    cpf: v => { v = v.replace(/\D/g, '').slice(0, 11);
      return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2'); },
  };
  document.addEventListener('input', e => {
    const m = e.target.dataset && e.target.dataset.mask;
    if (m && masks[m]) e.target.value = masks[m](e.target.value);
  });

  // ---------- CEP: preenche rua, bairro/setor, cidade e UF automaticamente (ViaCEP) ----------
  async function buscarCep(inp) {
    const cep = inp.value.replace(/\D/g, '');
    const form = inp.closest('form') || document;
    const msg = $('[data-cep-msg]', form);
    if (cep.length !== 8) return;
    if (msg) { msg.className = 'form-text'; msg.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Buscando endereço…'; }
    try {
      const r = await fetch('https://viacep.com.br/ws/' + cep + '/json/');
      const j = await r.json();
      if (j.erro) throw new Error('nao');
      const set = (campo, val) => { const el = $('[data-cep-campo="' + campo + '"]', form); if (el && val) { el.value = val; el.classList.add('preenchido'); setTimeout(() => el.classList.remove('preenchido'), 1500); } };
      set('logradouro', j.logradouro); set('bairro', j.bairro); set('cidade', j.localidade); set('uf', j.uf);
      if (msg) { msg.className = 'form-text text-success'; msg.innerHTML = '<i class="bi bi-check-circle"></i> Endereço preenchido. Complete o número.'; }
      const num = $('[data-cep-campo="numero"]', form); if (num) num.focus();
    } catch (e) {
      if (msg) { msg.className = 'form-text text-danger'; msg.innerHTML = '<i class="bi bi-exclamation-circle"></i> CEP não encontrado. Preencha o endereço manualmente.'; }
    }
  }
  document.addEventListener('input', e => { if (e.target.matches('[data-cep]') && e.target.value.replace(/\D/g, '').length === 8) buscarCep(e.target); });
  document.addEventListener('click', e => { const b = e.target.closest('[data-cep-buscar]'); if (b) buscarCep($('[data-cep]', b.closest('form'))); });

  // ---------- Estrelas (avaliação) acessíveis por teclado ----------
  $$('.star-input').forEach(g => {
    const out = $('.star-label', g.parentElement);
    const txt = ['', 'Ruim', 'Regular', 'Bom', 'Muito bom', 'Excelente'];
    g.addEventListener('change', e => { if (out) out.textContent = txt[e.target.value] || ''; });
  });

  // ---------- Botão de envio: evita clique duplo ----------
  $$('form[data-enviando]').forEach(f => f.addEventListener('submit', () => {
    const b = $('[type="submit"]', f);
    if (b && f.checkValidity()) { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + f.dataset.enviando; }
  }));
})();

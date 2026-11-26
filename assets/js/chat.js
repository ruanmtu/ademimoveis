// Chat em "tempo real" por consulta periódica (funciona em qualquer hospedagem PHP)
(() => {
  const box = document.querySelector('[data-chat]');
  if (!box) return;
  const conversa = box.dataset.chat;
  const api = box.dataset.api;
  const somenteLeitura = box.dataset.somenteLeitura === '1';
  const lista = box.querySelector('[data-mensagens]');
  const carregandoEl = box.querySelector('[data-carregando]');
  const form = box.querySelector('[data-chat-form]');
  const sugestoes = box.querySelector('[data-sugestoes]');
  const csrf = document.querySelector('meta[name=csrf-token]').content;
  let ultimo = 0;
  let ultimoDia = '';
  let ultimoAutor = null;
  let hoje = '';
  let primeira = true;
  let carregando = false;
  let temMinha = false;

  const pertoDoFim = () => lista.scrollHeight - lista.scrollTop - lista.clientHeight < 140;
  const rolarFim = () => { lista.scrollTop = lista.scrollHeight; };

  const nomeDia = (dia) => {
    if (dia === hoje) return 'Hoje';
    const ontem = new Date(hoje + 'T12:00:00');
    ontem.setDate(ontem.getDate() - 1);
    if (dia === ontem.toISOString().slice(0, 10)) return 'Ontem';
    const [a, m, d] = dia.split('-');
    return `${d}/${m}/${a}`;
  };

  const vazio = () => {
    const p = document.createElement('div');
    p.className = 'chat-vazio';
    p.dataset.vazio = '';
    const t = document.createElement('strong');
    t.textContent = 'Comece a conversa';
    const s = document.createElement('span');
    s.textContent = somenteLeitura ? 'Nenhuma mensagem ainda.' : 'Escreva abaixo ou toque em uma das sugestões.';
    p.append(t, s);
    lista.appendChild(p);
  };

  const adicionar = (m) => {
    if (m.dia !== ultimoDia) {
      const sep = document.createElement('div');
      sep.className = 'chat-dia';
      sep.textContent = nomeDia(m.dia);
      lista.appendChild(sep);
      ultimoDia = m.dia;
      ultimoAutor = null;
    }
    const autor = somenteLeitura ? m.nome : (m.meu ? 'eu' : 'outro');
    const div = document.createElement('div');
    div.className = 'msg' + (m.meu ? ' meu' : '') + (autor === ultimoAutor ? ' seguida' : '');
    div.dataset.id = m.id;
    if (somenteLeitura && autor !== ultimoAutor) {
      const quem = document.createElement('b');
      quem.className = 'msg-nome';
      quem.textContent = m.nome;
      div.appendChild(quem);
    }
    const texto = document.createElement('span');
    texto.className = 'msg-texto';
    texto.textContent = m.texto; // textContent evita injeção de HTML
    const meta = document.createElement('small');
    meta.className = 'msg-meta';
    meta.textContent = m.hm;
    if (m.meu) {
      const visto = document.createElement('span');
      visto.className = 'msg-visto';
      visto.setAttribute('aria-label', 'Enviada');
      visto.textContent = '✓';
      meta.appendChild(visto);
      temMinha = true;
    }
    div.append(texto, meta);
    lista.appendChild(div);
    ultimoAutor = autor;
  };

  const marcarLidas = (ate) => {
    if (!ate) return;
    lista.querySelectorAll('.msg.meu:not(.lida)').forEach((el) => {
      if (+el.dataset.id <= ate) {
        el.classList.add('lida');
        const v = el.querySelector('.msg-visto');
        if (v) { v.textContent = '✓✓'; v.setAttribute('aria-label', 'Lida'); }
      }
    });
  };

  async function buscar() {
    if (carregando) return;
    carregando = true;
    try {
      const r = await fetch(`${api}?conversa=${conversa}&depois=${ultimo}`, { credentials: 'same-origin' });
      if (!r.ok) return;
      const d = await r.json();
      hoje = d.hoje || hoje;
      if (primeira) {
        carregandoEl?.remove();
        if (!d.mensagens.length) vazio();
      }
      if (d.mensagens.length) {
        lista.querySelector('[data-vazio]')?.remove();
        const rolar = primeira || pertoDoFim();
        d.mensagens.forEach((m) => { adicionar(m); ultimo = Math.max(ultimo, m.id); });
        if (rolar) rolarFim();
      }
      marcarLidas(d.lidas_ate);
      if (sugestoes) sugestoes.hidden = temMinha;
      primeira = false;
    } catch (_) { /* tenta de novo no próximo ciclo */ } finally {
      carregando = false;
    }
  }

  const ajustarAltura = (campo) => {
    campo.style.height = 'auto';
    campo.style.height = Math.min(campo.scrollHeight, 140) + 'px';
  };

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const campo = form.texto;
    const texto = campo.value.trim();
    if (!texto) return;
    const btn = form.querySelector('button');
    btn.disabled = true;
    try {
      const corpo = new FormData();
      corpo.append('conversa', conversa);
      corpo.append('texto', texto);
      const r = await fetch(api, { method: 'POST', body: corpo, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf } });
      const d = await r.json();
      if (!r.ok) { alert(d.erro || 'Não foi possível enviar.'); return; }
      campo.value = '';
      campo.style.height = '';
      await buscar();
      rolarFim();
    } finally {
      btn.disabled = false;
      campo.focus();
    }
  });

  // Enter envia, Shift+Enter quebra linha (no celular, Enter quebra linha e o botão envia)
  const toque = window.matchMedia('(pointer: coarse)').matches;
  form?.texto.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !toque) { e.preventDefault(); form.requestSubmit(); }
  });
  form?.texto.addEventListener('input', function () { ajustarAltura(this); });

  sugestoes?.addEventListener('click', (e) => {
    const b = e.target.closest('[data-sugestao]');
    if (!b || !form) return;
    const campo = form.texto;
    campo.value = campo.value.trim() ? campo.value.trim() + ' ' + b.textContent : b.textContent;
    ajustarAltura(campo);
    campo.focus();
  });

  buscar();
  setInterval(() => { if (!document.hidden) buscar(); }, 3000);
})();

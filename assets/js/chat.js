// Chat em "tempo real" por consulta periódica (funciona em qualquer hospedagem PHP)
(() => {
  const box = document.querySelector('[data-chat]');
  if (!box) return;
  const conversa = box.dataset.chat;
  const api = box.dataset.api;
  const lista = box.querySelector('[data-mensagens]');
  const form = box.querySelector('[data-chat-form]');
  const csrf = document.querySelector('meta[name=csrf-token]').content;
  let ultimo = 0;
  let primeira = true;
  let carregando = false;

  const pertoDoFim = () => lista.scrollHeight - lista.scrollTop - lista.clientHeight < 120;

  const adicionar = (m) => {
    const div = document.createElement('div');
    div.className = 'msg' + (m.meu ? ' meu' : '');
    div.textContent = m.texto; // textContent evita injeção de HTML
    const hora = document.createElement('small');
    hora.textContent = (box.dataset.somenteLeitura === '1' ? m.nome + ' · ' : '') + m.hora;
    div.appendChild(hora);
    lista.appendChild(div);
  };

  async function buscar() {
    if (carregando) return;
    carregando = true;
    try {
      const r = await fetch(`${api}?conversa=${conversa}&depois=${ultimo}`, { credentials: 'same-origin' });
      if (!r.ok) return;
      const d = await r.json();
      if (primeira) {
        lista.innerHTML = d.mensagens.length ? '' : '<p class="chat-carregando">Nenhuma mensagem ainda. Diga olá!</p>';
      }
      if (d.mensagens.length) {
        if (!primeira && lista.querySelector('.chat-carregando')) lista.innerHTML = '';
        const rolar = primeira || pertoDoFim();
        d.mensagens.forEach((m) => { adicionar(m); ultimo = Math.max(ultimo, m.id); });
        if (rolar) lista.scrollTop = lista.scrollHeight;
      }
      primeira = false;
    } catch (_) { /* tenta de novo no próximo ciclo */ } finally {
      carregando = false;
    }
  }

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
      lista.scrollTop = lista.scrollHeight;
    } finally {
      btn.disabled = false;
      campo.focus();
    }
  });

  // Enter envia, Shift+Enter quebra linha
  form?.texto.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
  });
  form?.texto.addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 140) + 'px';
  });

  buscar();
  setInterval(() => { if (!document.hidden) buscar(); }, 3000);
})();

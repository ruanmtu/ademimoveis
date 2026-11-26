// Galeria do imóvel + visualizador em tela cheia com zoom.
// Celular: pinça para ampliar, toque duplo, arrastar para mover, deslizar para trocar de foto.
// Computador: roda do mouse, clique duplo, arrastar, setas do teclado, Esc para fechar.
(() => {
  const g = document.querySelector('[data-galeria]');
  if (!g) return;
  const principal = g.querySelector('[data-galeria-principal]');
  const minis = [...g.querySelectorAll('[data-galeria-mini]')];
  const fotos = minis.length ? minis.map((m) => m.src) : [principal.src];
  const semFoto = principal.dataset.semFoto === '1';
  let atual = 0;

  // ---------- Galeria na página ----------
  const mostrar = (i) => {
    atual = (i + fotos.length) % fotos.length;
    principal.src = fotos[atual];
    minis.forEach((m, k) => m.classList.toggle('ativa', k === atual));
    minis[atual]?.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
    const cont = g.querySelector('[data-galeria-contador]');
    if (cont) cont.textContent = `${atual + 1} / ${fotos.length}`;
  };
  minis.forEach((m, i) => m.addEventListener('click', () => mostrar(i)));
  g.querySelector('[data-galeria-ant]')?.addEventListener('click', (e) => { e.stopPropagation(); mostrar(atual - 1); });
  g.querySelector('[data-galeria-prox]')?.addEventListener('click', (e) => { e.stopPropagation(); mostrar(atual + 1); });

  // deslizar na foto principal troca a foto; toque simples abre a tela cheia
  let x0 = null, y0 = null, t0 = 0;
  principal.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; t0 = Date.now(); }, { passive: true });
  principal.addEventListener('touchend', (e) => {
    if (x0 === null) return;
    const dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
    if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) { mostrar(atual + (dx < 0 ? 1 : -1)); e.preventDefault(); }
    x0 = null;
  });
  if (semFoto) return;
  principal.addEventListener('click', () => abrir(atual));
  g.querySelector('[data-galeria-ampliar]')?.addEventListener('click', (e) => { e.stopPropagation(); abrir(atual); });

  // ---------- Visualizador em tela cheia ----------
  const ico = (d) => `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${d}</svg>`;
  const lb = document.createElement('div');
  lb.className = 'lightbox';
  lb.setAttribute('role', 'dialog');
  lb.setAttribute('aria-modal', 'true');
  lb.setAttribute('aria-label', 'Fotos do imóvel');
  lb.hidden = true;
  lb.innerHTML = `
    <div class="lb-topo">
      <span class="lb-contador" aria-live="polite"></span>
      <div class="lb-acoes">
        <button type="button" class="lb-btn" data-lb="menos" aria-label="Diminuir zoom">${ico('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M8 11h6"/>')}</button>
        <button type="button" class="lb-btn" data-lb="mais" aria-label="Aumentar zoom">${ico('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M8 11h6M11 8v6"/>')}</button>
        <button type="button" class="lb-btn" data-lb="fechar" aria-label="Fechar">${ico('<path d="M6 6l12 12M18 6 6 18"/>')}</button>
      </div>
    </div>
    <div class="lb-palco"><img class="lb-img" alt="" draggable="false"></div>
    <button type="button" class="lb-nav lb-ant" data-lb="ant" aria-label="Foto anterior">${ico('<path d="m15 6-6 6 6 6"/>')}</button>
    <button type="button" class="lb-nav lb-prox" data-lb="prox" aria-label="Próxima foto">${ico('<path d="m9 6 6 6-6 6"/>')}</button>
    <p class="lb-dica">Pinça ou toque duplo para ampliar · deslize para trocar</p>`;
  document.body.appendChild(lb);
  const palco = lb.querySelector('.lb-palco');
  const img = lb.querySelector('.lb-img');
  const contador = lb.querySelector('.lb-contador');
  const btnMenos = lb.querySelector('[data-lb=menos]');
  if (fotos.length < 2) lb.querySelectorAll('.lb-nav').forEach((b) => { b.hidden = true; });

  let idx = 0, escala = 1, tx = 0, ty = 0, focoAntes = null;
  const MAX = 5;

  const aplicar = (animar) => {
    img.style.transition = animar ? 'transform .22s ease' : 'none';
    img.style.transform = `translate(${tx}px, ${ty}px) scale(${escala})`;
    palco.classList.toggle('ampliada', escala > 1.01);
    btnMenos.disabled = escala <= 1.01;
  };
  // mantém a foto dentro do palco ao mover
  const limitar = () => {
    const r = palco.getBoundingClientRect();
    const w = img.offsetWidth * escala, h = img.offsetHeight * escala;
    const mx = Math.max(0, (w - r.width) / 2), my = Math.max(0, (h - r.height) / 2);
    tx = Math.min(mx, Math.max(-mx, tx));
    ty = Math.min(my, Math.max(-my, ty));
  };
  // amplia mantendo o ponto (px, py) da tela no lugar
  const zoomEm = (nova, px, py, animar) => {
    nova = Math.min(MAX, Math.max(1, nova));
    const r = palco.getBoundingClientRect();
    const cx = px - (r.left + r.width / 2), cy = py - (r.top + r.height / 2);
    tx = cx - (cx - tx) * (nova / escala);
    ty = cy - (cy - ty) * (nova / escala);
    escala = nova;
    if (escala <= 1.01) { escala = 1; tx = 0; ty = 0; }
    limitar();
    aplicar(animar);
  };
  const centro = () => { const r = palco.getBoundingClientRect(); return [r.left + r.width / 2, r.top + r.height / 2]; };

  const carregar = (i) => {
    idx = (i + fotos.length) % fotos.length;
    escala = 1; tx = 0; ty = 0; aplicar(false);
    img.src = fotos[idx];
    img.alt = `Foto ${idx + 1} de ${fotos.length}`;
    contador.textContent = `${idx + 1} / ${fotos.length}`;
    [idx + 1, idx - 1].forEach((k) => { const p = new Image(); p.src = fotos[(k + fotos.length) % fotos.length]; });
  };

  function abrir(i) {
    focoAntes = document.activeElement;
    carregar(i);
    lb.hidden = false;
    document.documentElement.classList.add('lb-aberto');
    requestAnimationFrame(() => lb.classList.add('visivel'));
    lb.querySelector('[data-lb=fechar]').focus({ preventScroll: true });
    history.pushState({ lightbox: true }, '');
  }
  const fechar = (doHistorico = true) => {
    if (lb.hidden) return;
    lb.classList.remove('visivel');
    lb.hidden = true;
    document.documentElement.classList.remove('lb-aberto');
    mostrar(idx); // a galeria da página acompanha a última foto vista
    focoAntes?.focus?.({ preventScroll: true });
    if (doHistorico && history.state?.lightbox) history.back();
  };
  // botão "voltar" do celular fecha o visualizador em vez de sair da página
  window.addEventListener('popstate', () => fechar(false));

  lb.addEventListener('click', (e) => {
    const b = e.target.closest('[data-lb]');
    if (!b) return;
    const a = b.dataset.lb;
    if (a === 'fechar') fechar();
    if (a === 'ant') carregar(idx - 1);
    if (a === 'prox') carregar(idx + 1);
    if (a === 'mais') zoomEm(escala * 1.6, ...centro(), true);
    if (a === 'menos') zoomEm(escala / 1.6, ...centro(), true);
  });
  // clique no fundo escuro (fora da foto) fecha
  let arrastou = false; // um arraste não pode virar "clique no fundo"
  // (o alvo do clique não serve: com o ponteiro "capturado" ele é sempre o palco; vale a posição)
  palco.addEventListener('click', (e) => {
    const r = img.getBoundingClientRect();
    const naFoto = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
    if (!naFoto && escala <= 1.01 && !arrastou) fechar();
    arrastou = false;
  });

  document.addEventListener('keydown', (e) => {
    if (lb.hidden) return;
    if (e.key === 'Escape') fechar();
    else if (e.key === 'ArrowRight') carregar(idx + 1);
    else if (e.key === 'ArrowLeft') carregar(idx - 1);
    else if (e.key === '+' || e.key === '=') zoomEm(escala * 1.6, ...centro(), true);
    else if (e.key === '-') zoomEm(escala / 1.6, ...centro(), true);
    else if (e.key === 'Tab') { // mantém o foco dentro do visualizador
      const foco = [...lb.querySelectorAll('button:not([hidden]):not([disabled])')];
      const i = foco.indexOf(document.activeElement);
      if (e.shiftKey && i <= 0) { e.preventDefault(); foco[foco.length - 1].focus(); }
      else if (!e.shiftKey && i === foco.length - 1) { e.preventDefault(); foco[0].focus(); }
    }
  });

  // roda do mouse amplia no ponto do cursor
  palco.addEventListener('wheel', (e) => {
    e.preventDefault();
    zoomEm(escala * Math.exp(-e.deltaY * 0.0022), e.clientX, e.clientY, false);
  }, { passive: false });

  // clique duplo / toque duplo
  // (no celular o toque duplo é tratado no pointerdown; o dblclick do navegador seria um segundo aviso do mesmo gesto)
  let tipoPonteiro = 'mouse';
  palco.addEventListener('pointerdown', (e) => { tipoPonteiro = e.pointerType; }, true);
  palco.addEventListener('dblclick', (e) => { if (tipoPonteiro !== 'touch') zoomEm(escala > 1.01 ? 1 : 2.5, e.clientX, e.clientY, true); });

  // ponteiros: arrastar, pinça, deslizar para trocar
  const pts = new Map();
  let ini = null, ultimoToque = 0;
  const dist = () => { const [a, b] = [...pts.values()]; return Math.hypot(a.x - b.x, a.y - b.y); };
  const meio = () => { const [a, b] = [...pts.values()]; return [(a.x + b.x) / 2, (a.y + b.y) / 2]; };

  palco.addEventListener('pointerdown', (e) => {
    if (e.target.closest('button')) return;
    palco.setPointerCapture(e.pointerId);
    pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pts.size === 1) {
      arrastou = false;
      ini = { x: e.clientX, y: e.clientY, tx, ty, t: Date.now() };
      // toque duplo no celular (o dblclick nem sempre chega em telas de toque)
      if (e.pointerType === 'touch') {
        if (Date.now() - ultimoToque < 300) { zoomEm(escala > 1.01 ? 1 : 2.5, e.clientX, e.clientY, true); ultimoToque = 0; ini = null; }
        else ultimoToque = Date.now();
      }
    } else if (pts.size === 2) {
      ini = { d: dist(), escala, m: meio() };
    }
  });
  palco.addEventListener('pointermove', (e) => {
    if (!pts.has(e.pointerId) || !ini) return;
    pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (ini.x !== undefined && Math.hypot(e.clientX - ini.x, e.clientY - ini.y) > 6) arrastou = true;
    if (pts.size === 2 && ini.d) {
      const [mx, my] = meio();
      zoomEm(ini.escala * (dist() / ini.d), mx, my, false);
    } else if (pts.size === 1 && escala > 1.01) {
      tx = ini.tx + (e.clientX - ini.x);
      ty = ini.ty + (e.clientY - ini.y);
      limitar();
      aplicar(false);
    } else if (pts.size === 1) {
      img.style.transition = 'none';
      img.style.transform = `translate(${e.clientX - ini.x}px, ${Math.max(0, e.clientY - ini.y) * 0.4}px)`;
    }
  });
  const soltar = (e) => {
    if (!pts.has(e.pointerId)) return;
    pts.delete(e.pointerId);
    if (pts.size === 1) { // saiu de uma pinça: continua arrastando com o dedo que ficou
      const [p] = [...pts.values()];
      ini = { x: p.x, y: p.y, tx, ty, t: Date.now() };
      return;
    }
    if (pts.size === 0 && ini && escala <= 1.01 && ini.x !== undefined) {
      const dx = e.clientX - ini.x, dy = e.clientY - ini.y;
      if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) && fotos.length > 1) carregar(idx + (dx < 0 ? 1 : -1));
      else if (dy > 120 && Math.abs(dy) > Math.abs(dx)) fechar();
      else aplicar(true);
    }
    ini = null;
  };
  palco.addEventListener('pointerup', soltar);
  palco.addEventListener('pointercancel', soltar);
  window.addEventListener('resize', () => { if (!lb.hidden) { limitar(); aplicar(false); } });
})();

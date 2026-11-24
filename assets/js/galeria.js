(() => {
  const g = document.querySelector('[data-galeria]');
  if (!g) return;
  const principal = g.querySelector('[data-galeria-principal]');
  const minis = [...g.querySelectorAll('[data-galeria-mini]')];
  if (!minis.length) return;
  let atual = 0;

  const mostrar = (i) => {
    atual = (i + minis.length) % minis.length;
    principal.src = minis[atual].src;
    minis.forEach((m, k) => m.classList.toggle('ativa', k === atual));
    minis[atual].scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
  };

  minis.forEach((m, i) => m.addEventListener('click', () => mostrar(i)));
  g.querySelector('[data-galeria-ant]')?.addEventListener('click', () => mostrar(atual - 1));
  g.querySelector('[data-galeria-prox]')?.addEventListener('click', () => mostrar(atual + 1));
  document.addEventListener('keydown', (e) => {
    if (e.target.closest('input, textarea, select')) return;
    if (e.key === 'ArrowLeft') mostrar(atual - 1);
    if (e.key === 'ArrowRight') mostrar(atual + 1);
  });

  // deslizar no celular
  let x0 = null;
  principal.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
  principal.addEventListener('touchend', (e) => {
    if (x0 === null) return;
    const dx = e.changedTouches[0].clientX - x0;
    if (Math.abs(dx) > 40) mostrar(atual + (dx < 0 ? 1 : -1));
    x0 = null;
  });
})();

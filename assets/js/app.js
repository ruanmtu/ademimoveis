// Menu mobile
document.querySelector('[data-toggle-menu]')?.addEventListener('click', () => {
  document.getElementById('menu')?.classList.toggle('aberto');
});

// Confirmação antes de ações perigosas (botões com data-confirmar)
document.addEventListener('click', (ev) => {
  const el = ev.target.closest('[data-confirmar]');
  if (el && !confirm(el.dataset.confirmar)) {
    ev.preventDefault();
  }
});

// Botões que mostram/escondem um painel (data-alternar="#id")
document.querySelectorAll('[data-alternar]').forEach((btn) => {
  btn.addEventListener('click', () => {
    const alvo = document.querySelector(btn.dataset.alternar);
    if (!alvo) return;
    alvo.hidden = !alvo.hidden;
    btn.setAttribute('aria-expanded', String(!alvo.hidden));
    btn.classList.toggle('ativo', !alvo.hidden);
  });
});

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

// Busca no celular: filtros abrem por cima da tela
(() => {
  const painel = document.querySelector('[data-filtros]');
  const abrir = document.querySelector('[data-abrir-filtros]');
  if (!painel || !abrir) return;
  const set = (aberto) => {
    painel.classList.toggle('aberto', aberto);
    document.documentElement.classList.toggle('lb-aberto', aberto);
    abrir.setAttribute('aria-expanded', String(aberto));
    if (aberto) painel.querySelector('select, input')?.focus({ preventScroll: true });
  };
  abrir.addEventListener('click', () => set(true));
  painel.querySelector('[data-fechar-filtros]')?.addEventListener('click', () => { set(false); abrir.focus(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && painel.classList.contains('aberto')) set(false); });
})();

// Tabelas no celular viram cartões: cada célula ganha o nome da sua coluna
document.querySelectorAll('table.tabela').forEach((t) => {
  const nomes = [...t.querySelectorAll('thead th')].map((th) => th.textContent.trim());
  if (!nomes.length) { t.classList.add('tabela-simples'); return; }
  t.querySelectorAll('tbody tr').forEach((tr) => {
    [...tr.children].forEach((td, i) => { if (nomes[i]) td.dataset.rotulo = nomes[i]; });
  });
});

// Mostra os campos extras só quando a pessoa escolhe "anunciante"
(() => {
  const form = document.querySelector('[data-cadastro]');
  if (!form) return;
  const extra = form.querySelector('[data-so-anunciante]');
  const tel = form.querySelector('[name=telefone]');
  const atualizar = () => {
    const anunc = form.querySelector('[name=tipo]:checked')?.value === 'anunciante';
    extra.hidden = !anunc;
    tel.required = anunc;
  };
  form.querySelectorAll('[name=tipo]').forEach((r) => r.addEventListener('change', atualizar));
  atualizar();
})();

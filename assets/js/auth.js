// Login e cadastro: mostrar senha, força da senha, telefone, tipo de conta e envio.
(() => {
  // Mostrar / esconder senha
  document.querySelectorAll('[data-mostrar-senha]').forEach((btn) => {
    const campo = document.getElementById(btn.dataset.mostrarSenha);
    const olho = btn.innerHTML;
    const fechado = '<svg class="icone" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18M10.6 5.1A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17.4 17.4 0 0 0 2 12s3.6 7 10 7a9.9 9.9 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
    btn.addEventListener('click', () => {
      const mostrar = campo.type === 'password';
      campo.type = mostrar ? 'text' : 'password';
      btn.innerHTML = mostrar ? fechado : olho;
      btn.setAttribute('aria-label', mostrar ? 'Esconder senha' : 'Mostrar senha');
      campo.focus();
    });
  });

  // Força da senha
  const senha = document.querySelector('[data-forca]');
  const barra = document.querySelector('[data-forca-barra]');
  if (senha && barra) {
    const nomes = ['', 'Fraca', 'Razoável', 'Boa', 'Forte'];
    senha.addEventListener('input', () => {
      const v = senha.value;
      let p = 0;
      if (v.length >= 8) p++;
      if (v.length >= 12) p++;
      if (/[A-Z]/.test(v) && /[a-z]/.test(v)) p++;
      if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) p++;
      if (v.length < 8) p = v ? 1 : 0;
      barra.dataset.nivel = p;
      barra.querySelector('span').textContent = v ? nomes[p] : '';
    });
  }

  // Máscara de telefone
  document.querySelectorAll('[data-telefone]').forEach((t) => {
    t.addEventListener('input', () => {
      const d = t.value.replace(/\D/g, '').slice(0, 11);
      t.value = d.length > 10 ? d.replace(/^(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3')
              : d.length > 6 ? d.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3')
              : d.length > 2 ? d.replace(/^(\d{2})(\d*)/, '($1) $2') : d;
    });
  });

  // Cadastro: campos de anunciante aparecem só quando escolhido
  const form = document.querySelector('[data-cadastro]');
  if (form) {
    const extras = form.querySelectorAll('[data-so-anunciante]');
    const botao = form.querySelector('button[type=submit]');
    const textoPadrao = botao.textContent;
    const atualizar = () => {
      const tipo = form.querySelector('input[type=hidden][name=tipo]') || form.querySelector('[name=tipo]:checked');
      const anunc = tipo?.value === 'anunciante';
      extras.forEach((x) => { x.hidden = !anunc; });
      botao.textContent = anunc ? botao.dataset.textoAnunciante : textoPadrao;
    };
    form.querySelectorAll('[name=tipo]').forEach((r) => r.addEventListener('change', atualizar));
    atualizar();
  }

  // CRECI: os campos só aparecem para quem marca que tem registro
  const temCreci = document.querySelector('[data-tem-creci]');
  const camposCreci = document.querySelector('[data-campos-creci]');
  if (temCreci && camposCreci) {
    const mostrar = () => {
      camposCreci.hidden = !temCreci.checked;
      if (temCreci.checked) camposCreci.querySelector('input')?.focus({ preventScroll: true });
    };
    temCreci.addEventListener('change', mostrar);
    camposCreci.hidden = !temCreci.checked;
  }

  // Botão mostra que está enviando (evita clique duplo)
  document.querySelectorAll('[data-form-auth]').forEach((f) => {
    f.addEventListener('submit', () => {
      const b = f.querySelector('[data-carregando]');
      if (!b) return;
      b.disabled = true;
      b.innerHTML = '<span class="girando" aria-hidden="true"></span>' + b.dataset.carregando;
    });
  });
})();

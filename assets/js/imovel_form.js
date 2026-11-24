(() => {
  // Pré-visualização das fotos escolhidas
  const input = document.querySelector('[data-preview]');
  const area = document.querySelector('[data-preview-area]');
  input?.addEventListener('change', () => {
    area.innerHTML = '';
    [...input.files].forEach((f) => {
      if (!f.type.startsWith('image/')) return;
      const img = document.createElement('img');
      img.src = URL.createObjectURL(f);
      img.alt = f.name;
      area.appendChild(img);
    });
  });

  // Preenche bairro/cidade/estado pelo CEP (ViaCEP, serviço gratuito)
  const cep = document.querySelector('[data-cep]');
  cep?.addEventListener('blur', async () => {
    const n = cep.value.replace(/\D/g, '');
    if (n.length !== 8) return;
    try {
      const r = await fetch(`https://viacep.com.br/ws/${n}/json/`);
      const d = await r.json();
      if (d.erro) return;
      const f = cep.form;
      if (!f.endereco.value) f.endereco.value = d.logradouro || '';
      if (!f.bairro.value) f.bairro.value = d.bairro || '';
      f.cidade.value = d.localidade || f.cidade.value;
      f.estado.value = d.uf || f.estado.value;
    } catch (_) { /* sem internet ou serviço fora: o usuário preenche manualmente */ }
  });
})();

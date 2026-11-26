(() => {
  // Fotos: confere o tamanho, reduz no próprio aparelho (até 2560 px) e mostra a prévia.
  // Assim uma foto de 25 MB do celular vira ~1-2 MB e sobe rápido, sem perder qualidade visível.
  const input = document.querySelector('[data-preview]');
  const area = document.querySelector('[data-preview-area]');
  const status = document.querySelector('[data-status-fotos]');
  const form = input?.form;
  let processando = 0;

  const reduzir = (arquivo, maxLado) => new Promise((ok) => {
    if (!/^image\/(jpeg|png|webp)$/.test(arquivo.type) || !window.createImageBitmap) return ok(arquivo);
    createImageBitmap(arquivo, { imageOrientation: 'from-image' }).then((bmp) => {
      const escala = Math.min(1, maxLado / Math.max(bmp.width, bmp.height));
      if (escala === 1 && arquivo.size < 3 * 1048576) { bmp.close(); return ok(arquivo); }
      const c = document.createElement('canvas');
      c.width = Math.round(bmp.width * escala);
      c.height = Math.round(bmp.height * escala);
      const ctx = c.getContext('2d');
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, c.width, c.height);
      ctx.drawImage(bmp, 0, 0, c.width, c.height);
      bmp.close();
      c.toBlob((blob) => {
        if (!blob || blob.size >= arquivo.size) return ok(arquivo);
        ok(new File([blob], arquivo.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
      }, 'image/jpeg', 0.88);
    }).catch(() => ok(arquivo));
  });

  input?.addEventListener('change', async () => {
    const maxMb = +input.dataset.maxMb || 25;
    const maxLado = +input.dataset.maxLado || 2560;
    const originais = [...input.files];
    area.innerHTML = '';
    const grandes = originais.filter((f) => f.size > maxMb * 1048576);
    if (grandes.length) {
      alert(`${grandes.length} foto(s) passam de ${maxMb} MB e foram ignoradas: ` + grandes.map((f) => f.name).join(', '));
    }
    const validas = originais.filter((f) => f.size <= maxMb * 1048576);
    processando++;
    if (status) status.textContent = `Preparando ${validas.length} foto(s)...`;
    const prontas = await Promise.all(validas.map((f) => reduzir(f, maxLado)));
    processando--;
    if (window.DataTransfer) {
      const dt = new DataTransfer();
      prontas.forEach((f) => dt.items.add(f));
      input.files = dt.files;
    }
    const antes = validas.reduce((s, f) => s + f.size, 0);
    const depois = prontas.reduce((s, f) => s + f.size, 0);
    if (status) status.textContent = prontas.length
      ? `${prontas.length} foto(s) prontas: ${(antes / 1048576).toFixed(1)} MB reduzidos para ${(depois / 1048576).toFixed(1)} MB.`
      : 'Nenhuma foto selecionada.';
    prontas.forEach((f) => {
      const img = document.createElement('img');
      img.src = URL.createObjectURL(f);
      img.alt = f.name;
      area.appendChild(img);
    });
  });

  // não deixa salvar enquanto as fotos ainda estão sendo preparadas
  form?.addEventListener('submit', (e) => {
    if (processando > 0) { e.preventDefault(); alert('Aguarde: as fotos ainda estão sendo preparadas.'); }
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

// Contato do anúncio: o campo de WhatsApp só aparece quando a opção "Chat e WhatsApp" está marcada
(() => {
  const opcoes = document.querySelector('[data-opcoes-contato]');
  const campo = document.querySelector('[data-campo-zap]');
  if (opcoes && campo) {
    const atualizar = () => {
      const zap = opcoes.querySelector('input[value=whatsapp]').checked;
      campo.hidden = !zap;
      campo.querySelector('input').required = zap;
    };
    opcoes.addEventListener('change', atualizar);
    atualizar();
  }
  document.querySelectorAll('[data-telefone]').forEach((t) => {
    t.addEventListener('input', () => {
      const d = t.value.replace(/\D/g, '').slice(0, 11);
      t.value = d.length > 10 ? d.replace(/^(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3')
              : d.length > 6 ? d.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3')
              : d.length > 2 ? d.replace(/^(\d{2})(\d*)/, '($1) $2') : d;
    });
  });
})();

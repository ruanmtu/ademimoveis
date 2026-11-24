// Máscara e validação ao vivo de CPF/CNPJ (inclui CNPJ alfanumérico) e CRECI.
// A validação definitiva acontece no servidor; aqui é só para ajudar quem digita.
(() => {
  const limpar = (v) => v.toUpperCase().replace(/[^0-9A-Z]/g, '');

  const cpfValido = (c) => {
    if (!/^\d{11}$/.test(c) || /^(\d)\1{10}$/.test(c)) return false;
    for (let t = 9; t < 11; t++) {
      let s = 0;
      for (let i = 0; i < t; i++) s += +c[i] * (t + 1 - i);
      if (+c[t] !== ((10 * s) % 11) % 10) return false;
    }
    return true;
  };
  const dvCnpj = (base) => {
    const pesos = base.length === 12 ? [5,4,3,2,9,8,7,6,5,4,3,2] : [6,5,4,3,2,9,8,7,6,5,4,3,2];
    let s = 0;
    for (let i = 0; i < base.length; i++) s += (base.charCodeAt(i) - 48) * pesos[i];
    const r = s % 11;
    return r < 2 ? 0 : 11 - r;
  };
  const cnpjValido = (c) => {
    if (!/^[0-9A-Z]{12}\d{2}$/.test(c) || /^(\d)\1{13}$/.test(c)) return false;
    const d1 = dvCnpj(c.slice(0, 12));
    const d2 = dvCnpj(c.slice(0, 12) + d1);
    return +c[12] === d1 && +c[13] === d2;
  };
  const formatar = (c) => {
    const temLetra = /[A-Z]/.test(c);
    if (!temLetra && c.length <= 11) {
      return c.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
    }
    return c.slice(0, 14).replace(/^(.{2})(.)/, '$1.$2').replace(/^(.{2})\.(.{3})(.)/, '$1.$2.$3')
      .replace(/^(.{2})\.(.{3})\.(.{3})(.)/, '$1.$2.$3/$4').replace(/\/(.{4})(.{1,2})$/, '/$1-$2');
  };

  const doc = document.querySelector('[data-documento]');
  const status = document.querySelector('[data-documento-status]');
  const rotuloNome = document.querySelector('[data-rotulo-nome]');
  const ajudaCreci = document.querySelector('[data-creci-ajuda]');
  const creci = document.querySelector('[data-creci]');
  let tipoDoc = 'cpf';

  const avaliar = (final) => {
    if (!doc) return;
    const c = limpar(doc.value);
    let msg = '', cls = '';
    tipoDoc = c.length > 11 || /[A-Z]/.test(c) ? 'cnpj' : 'cpf';
    if (c.length === 11 && tipoDoc === 'cpf') {
      [msg, cls] = cpfValido(c) ? ['CPF válido', 'ok'] : ['CPF inválido', 'erro'];
    } else if (c.length === 14) {
      [msg, cls] = cnpjValido(c) ? ['CNPJ válido', 'ok'] : ['CNPJ inválido', 'erro'];
    } else if (final && c.length) {
      [msg, cls] = ['Número incompleto', 'erro'];
    }
    status.textContent = msg;
    status.className = 'campo-status ' + cls;
    doc.setAttribute('aria-invalid', cls === 'erro' ? 'true' : 'false');
    if (rotuloNome) rotuloNome.textContent = tipoDoc === 'cnpj' ? 'Razão social ou nome da imobiliária' : 'Nome completo';
    if (ajudaCreci) ajudaCreci.textContent = tipoDoc === 'cnpj'
      ? 'Imobiliária: use o registro de pessoa jurídica, que termina em J (ex.: 1234-J).'
      : 'Corretor: use o registro de pessoa física, que termina em F (ex.: 12345-F).';
    if (creci) creci.placeholder = tipoDoc === 'cnpj' ? '1234-J' : '12345-F';
  };

  if (doc) {
    doc.addEventListener('input', () => {
      const pos = doc.value.length;
      doc.value = formatar(limpar(doc.value));
      if (doc.value.length >= pos) doc.setSelectionRange(doc.value.length, doc.value.length);
      avaliar(false);
    });
    doc.addEventListener('blur', () => avaliar(true));
    if (doc.value) { doc.value = formatar(limpar(doc.value)); avaliar(true); }
  }

  if (creci) {
    creci.addEventListener('blur', () => {
      const m = creci.value.toUpperCase().replace(/^\s*CRECI[\s\-\/]*[A-Z]{0,2}[\s\-:]*/, '').replace(/[.\s]/g, '').match(/^(\d{1,7})-?([FJ])?$/);
      if (m) creci.value = m[1].replace(/^0+/, '') + '-' + (tipoDoc === 'cnpj' ? 'J' : 'F');
    });
  }
})();

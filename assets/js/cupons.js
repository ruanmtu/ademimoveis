// Formulário de cupom: mostra/esconde campos e um resumo em português do que o cupom faz
(() => {
  const f = document.querySelector('[data-form-cupom]');
  if (!f) return;
  const tipo = f.querySelector('[data-tipo]');
  const dur = f.querySelector('[data-duracao]');
  const nCob = f.querySelector('[data-n-cobrancas]');
  const unidade = f.querySelector('[data-unidade]');
  const resumo = f.querySelector('[data-resumo-cupom]');

  const atualizar = () => {
    const pct = tipo.value === 'percentual';
    unidade.textContent = pct ? '(%)' : '(R$)';
    nCob.hidden = dur.value !== 'cobrancas';
    const v = (f.valor.value || '').trim();
    const cod = (f.codigo.value || '').toUpperCase().replace(/[^A-Z0-9_-]/g, '');
    if (!v || !cod) { resumo.textContent = ''; return; }
    const desc = pct ? `${v}% de desconto` : `R$ ${v} de desconto`;
    const quando = { primeira: 'na primeira cobrança', cobrancas: `nas ${f.duracao_cobrancas.value} primeiras cobranças`, sempre: 'em todas as cobranças' }[dur.value];
    const plano = f.plano_id.selectedOptions[0].text;
    resumo.textContent = `Resumo: quem usar ${cod} ganha ${desc} ${quando} — ${plano === 'Todos os planos' ? 'em qualquer plano' : 'no plano ' + plano.split(' (')[0]}.`;
  };
  f.addEventListener('input', atualizar);
  f.addEventListener('change', atualizar);
  atualizar();
})();

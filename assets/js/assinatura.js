// Copia o e-mail da conta Mercado Pago para o formulário do plano escolhido
(() => {
  const campo = document.querySelector('[data-email-mp]');
  if (!campo) return;
  const copiar = () => document.querySelectorAll('[data-email-mp-copia]').forEach((h) => { h.value = campo.value.trim(); });
  campo.addEventListener('input', copiar);
  document.querySelectorAll('form.plano').forEach((f) => f.addEventListener('submit', copiar));
})();

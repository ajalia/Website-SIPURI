document.querySelectorAll('[data-password-target]').forEach(button => {
 button.addEventListener('click', () => {
  const input = document.getElementById(button.dataset.passwordTarget);
  if (!input) return;
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  button.setAttribute('aria-pressed', String(show));
  if (!button.hasAttribute('data-icon-toggle')) button.textContent = show ? 'Sembunyikan' : 'Lihat';
  button.setAttribute('aria-label', `${show ? 'Sembunyikan' : 'Tampilkan'} kata sandi`);
 });
});

document.querySelectorAll('[data-submit-form]').forEach(form => {
 form.addEventListener('submit', event => {
  if (!form.checkValidity()) return;
  if (form.dataset.submitting) { event.preventDefault(); return; }
  form.dataset.submitting = 'true';
  const button = event.submitter || form.querySelector('[type="submit"]');
  if (button) {
   const label = button.querySelector('[data-submit-label]') || button;
   button.dataset.originalText = label.textContent;
   button.disabled = true;
   button.setAttribute('aria-busy', 'true');
   label.textContent = button.dataset.busyLabel || 'Menyimpan…';
   button.querySelector('[data-submit-spinner]')?.classList.remove('d-none');
  }
 });
});
window.addEventListener('pageshow', () => {
 document.querySelectorAll('[data-submit-form]').forEach(form => {
  delete form.dataset.submitting;
  form.querySelectorAll('[data-original-text]').forEach(button => {
   button.disabled = false;
   button.removeAttribute('aria-busy');
   const label = button.querySelector('[data-submit-label]') || button;
   label.textContent = button.dataset.originalText;
   button.querySelector('[data-submit-spinner]')?.classList.add('d-none');
  });
 });
});

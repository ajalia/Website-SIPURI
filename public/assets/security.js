// Ephemeral reset response: never retain the displayed secret in a back/forward page snapshot.
const clearTemporaryPasswords = () => {
 document.querySelectorAll('[data-ephemeral-secret]').forEach(input => {
  input.value = '';
  input.removeAttribute('value');
  input.placeholder = 'Sandi sementara tidak dapat ditampilkan ulang.';
 });
};
window.addEventListener('pagehide', clearTemporaryPasswords);
window.addEventListener('pageshow', event => { if (event.persisted) clearTemporaryPasswords(); });
// Only logout trigger forms are intercepted; the shared modal owns the actual POST.
const logoutDialog = document.getElementById('logout-confirmation');
if (logoutDialog) {
 let logoutTrigger;
 let opening = false;
 const modal = bootstrap.Modal.getOrCreateInstance(logoutDialog);
 logoutDialog.addEventListener('shown.bs.modal', () => { opening = false; });
 document.querySelectorAll('[data-logout-trigger-form]').forEach(form => {
  form.addEventListener('submit', event => {
   event.preventDefault();
   if (opening || logoutDialog.classList.contains('show')) return;
   opening = true;
   logoutTrigger = event.submitter || form.querySelector('button');
   const menu = form.closest('.offcanvas, .offcanvas-md');
   if (menu && menu.classList.contains('show')) {
    menu.addEventListener('hidden.bs.offcanvas', () => modal.show(), { once: true });
    bootstrap.Offcanvas.getOrCreateInstance(menu).hide();
   } else modal.show();
  });
 });
 logoutDialog.addEventListener('hidden.bs.modal', () => {
  const menu = logoutTrigger?.closest('.offcanvas, .offcanvas-md');
  if (menu && window.matchMedia('(max-width: 767.98px)').matches && !menu.classList.contains('show')) {
   menu.addEventListener('shown.bs.offcanvas', () => logoutTrigger?.focus(), { once: true });
   bootstrap.Offcanvas.getOrCreateInstance(menu).show();
  } else {
   // Reopen an account dropdown so its trigger remains visible and focusable.
   const dropdown = logoutTrigger?.closest('.dropdown');
   if (dropdown) bootstrap.Dropdown.getOrCreateInstance(dropdown.querySelector('[data-bs-toggle="dropdown"]')).show();
   logoutTrigger?.focus();
  }
 });
}

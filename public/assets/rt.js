// Bootstrap handles focus trapping, Escape, close and cancel buttons.
// A failed server validation reopens only the relevant modal with safe old input.
document.querySelectorAll('.rt-modal[data-auto-open]').forEach(element => {
 const modal = bootstrap.Modal.getOrCreateInstance(element);
 modal.show();
 element.addEventListener('shown.bs.modal', () => {
  element.querySelector('.is-invalid, input:not([type="hidden"]):not([readonly]), textarea')?.focus();
 }, { once: true });
});

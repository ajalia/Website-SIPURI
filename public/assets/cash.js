// Modal behavior is provided by local Bootstrap; only validation reopens a failed form.
// Finance already loads rt.js; Admin uses the same modal component without that script.
if (!document.querySelector('.rt-shell')) document.querySelectorAll('.cash-module .rt-modal[data-auto-open]').forEach(element => {
 if (!element.classList.contains('show')) bootstrap.Modal.getOrCreateInstance(element).show();
});
document.querySelectorAll('[data-cash-print]').forEach(button => button.addEventListener('click', () => window.print()));

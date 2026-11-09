// Local Bootstrap provides focus trapping, Escape, X, and Batal dismissal.
document.querySelectorAll('.staff-module .rt-modal[data-auto-open]').forEach(element => bootstrap.Modal.getOrCreateInstance(element).show());
document.querySelectorAll('[data-staff-role]').forEach(select => {
 const form=select.closest('form'),rt=form.querySelector('[data-staff-rt]'),rw=form.querySelector('[data-staff-rw]'),rtInput=rt.querySelector('select');
 const refresh=()=>{const treasury=select.value==='bendahara';rt.classList.toggle('d-none',treasury);rw.classList.toggle('d-none',!treasury);rtInput.disabled=treasury;rtInput.required=!treasury;};
 select.addEventListener('change',refresh);refresh();
});

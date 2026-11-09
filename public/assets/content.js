document.querySelectorAll('.content-module .rt-modal[data-auto-open]').forEach(element => bootstrap.Modal.getOrCreateInstance(element).show());
document.querySelectorAll('[data-content-area]').forEach(select=>{
 const container=select.closest('form').querySelector('[data-content-rt]'),input=container.querySelector('select');
 const refresh=()=>{const rt=select.value==='RT';container.classList.toggle('d-none',!rt);input.disabled=!rt;input.required=rt;};select.addEventListener('change',refresh);refresh();
});
document.querySelectorAll('[data-content-photo]').forEach(input=>{
 const form=input.closest('form'),img=form.querySelector('[data-content-preview]'),placeholder=form.querySelector('[data-content-placeholder]'),error=form.querySelector('[data-content-photo-error]');let objectUrl;
 const reset=()=>{if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=null;const original=img.dataset.originalSrc;img.hidden=!original;placeholder.hidden=!!original;if(original)img.src=original;else img.removeAttribute('src');};
 input.addEventListener('change',()=>{reset();error.textContent='';const file=input.files[0];if(!file)return;
  if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>5*1024*1024){error.textContent='Pilih gambar JPG/JPEG/PNG/WebP maksimal 5 MB.';input.value='';return;}
  objectUrl=URL.createObjectURL(file);img.src=objectUrl;img.hidden=false;placeholder.hidden=true;
 });
 input.closest('.modal')?.addEventListener('hidden.bs.modal',()=>{input.value='';error.textContent='';reset();});window.addEventListener('pagehide',()=>{if(objectUrl)URL.revokeObjectURL(objectUrl);});
});

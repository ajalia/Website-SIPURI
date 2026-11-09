const {test}=require('node:test');
const assert=require('node:assert/strict');
const {runInNewContext}=require('node:vm');
const {readFileSync}=require('node:fs');
const source=readFileSync('public/assets/content.js','utf8');
function fixture(){
 const element=()=>({handlers:{},addEventListener(name,fn){this.handlers[name]=fn;},emit(name){this.handlers[name]?.();}});
 const modal=element(),window=element(),input=element(),select=element();
 const image={dataset:{originalSrc:'/gambar-konten/original.png'},src:'/gambar-konten/original.png',hidden:false,removeAttribute(){delete this.src;}},placeholder={hidden:true},error={textContent:''},rtInput={disabled:true,required:false};
 const toggles={};const region={classList:{toggle(name,value){toggles[name]=value;}},querySelector:()=>rtInput};
 const form={querySelector(selector){return {'[data-content-preview]':image,'[data-content-placeholder]':placeholder,'[data-content-photo-error]':error,'[data-content-rt]':region}[selector];}};
 input.closest=selector=>selector==='.modal'?modal:form;input.files=[];input.value='';select.closest=()=>form;select.value='RW';let created=0;const revoked=[];
 runInNewContext(source,{document:{querySelectorAll(selector){return selector==='[data-content-photo]'?[input]:(selector==='[data-content-area]'?[select]:[]);}},window,URL:{createObjectURL(){created++;return 'blob:selection-'+created;},revokeObjectURL(url){revoked.push(url);}}});
 return {input,image,placeholder,error,modal,select,rtInput,toggles,revoked,choose(type='image/png',size=100){input.files=[{type,size}];input.value='selected-file';input.emit('change');}};
}
test('a valid selected photo immediately replaces the preview without saving',()=>{
 const f=fixture();f.choose();assert.equal(f.image.src,'blob:selection-1');assert.equal(f.image.hidden,false);assert.equal(f.placeholder.hidden,true);assert.equal(f.error.textContent,'');
});
test('cancel restores the stored photo and releases the temporary preview',()=>{
 const f=fixture();f.choose();f.modal.emit('hidden.bs.modal');assert.equal(f.image.src,'/gambar-konten/original.png');assert.equal(f.input.value,'');assert.deepEqual(f.revoked,['blob:selection-1']);
});
test('invalid file type or oversized file cannot become the preview',()=>{
 const f=fixture();f.choose('image/svg+xml');assert.equal(f.image.src,'/gambar-konten/original.png');assert.equal(f.input.value,'');assert.ok(f.error.textContent);
 f.choose('image/webp',5*1024*1024+1);assert.equal(f.image.src,'/gambar-konten/original.png');assert.ok(f.error.textContent);
});
test('RW submits no RT, while RT selection is shown and required for RT profiles',()=>{
 const f=fixture();assert.equal(f.rtInput.disabled,true);assert.equal(f.rtInput.required,false);assert.equal(f.toggles['d-none'],true);
 f.select.value='RT';f.select.emit('change');assert.equal(f.rtInput.disabled,false);assert.equal(f.rtInput.required,true);assert.equal(f.toggles['d-none'],false);
});

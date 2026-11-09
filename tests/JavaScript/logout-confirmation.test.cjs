const {test}=require('node:test');
const assert=require('node:assert/strict');
const {runInNewContext}=require('node:vm');
const {readFileSync}=require('node:fs');
const source=readFileSync('public/assets/security.js','utf8');
function fixture({mobile=false,dropdown=false}={}) {
 const handlers=new Map();
 const element=()=>({classList:{contains:()=>false},addEventListener(name,fn,options){const list=handlers.get(this)||{};(list[name]??=[]).push({fn,once:options?.once});handlers.set(this,list);},emit(name,event={}){const list=handlers.get(this)||{};for(const h of [...(list[name]||[])]){if(h.once)list[name]=list[name].filter(x=>x!==h);h.fn(event);}}});
 const dialog=element(),form=element(),menu=mobile?element():null,drop=dropdown?{}:null;
 let shown=0,hidden=0,focused=0,reopened=0,dropShown=0;
 const trigger={focus(){focused++;},closest(selector){return selector.includes('offcanvas')?menu:drop;}};
 form.querySelector=()=>trigger;form.closest=()=>menu;
 if(menu)menu.classList.contains=()=>true;
 if(drop)drop.querySelector=()=>({});
 const context={document:{getElementById:()=>dialog,querySelectorAll:()=>[form]},window:{addEventListener(){},matchMedia:()=>({matches:mobile})},bootstrap:{Modal:{getOrCreateInstance:()=>({show(){shown++;}})},Offcanvas:{getOrCreateInstance:()=>({hide(){hidden++;},show(){reopened++;}})},Dropdown:{getOrCreateInstance:()=>({show(){dropShown++;}})}}};
 runInNewContext(source,context);
 const submit=()=>{let prevented=false;form.emit('submit',{submitter:trigger,preventDefault(){prevented=true;}});assert.ok(prevented);};
 return {dialog,menu,submit,counts:()=>({shown,hidden,focused,reopened,dropShown})};
}
test('desktop opens one modal even when triggered repeatedly; dismissal restores focus',()=>{
 const f=fixture();f.submit();f.submit();assert.equal(f.counts().shown,1);
 f.dialog.emit('shown.bs.modal');f.dialog.emit('hidden.bs.modal');assert.equal(f.counts().focused,1);
});
test('mobile closes the sidebar before opening modal, then restores sidebar and trigger focus',()=>{
 const f=fixture({mobile:true});f.submit();f.submit();assert.equal(f.counts().hidden,1);assert.equal(f.counts().shown,0);
 f.menu.emit('hidden.bs.offcanvas');assert.equal(f.counts().shown,1);
 f.dialog.emit('shown.bs.modal');f.menu.classList.contains=()=>false;
 f.dialog.emit('hidden.bs.modal');assert.equal(f.counts().reopened,1);
 f.menu.emit('shown.bs.offcanvas');assert.equal(f.counts().focused,1);
});
test('account dropdown reopens on dismissal so logout trigger can receive focus',()=>{
 const f=fixture({dropdown:true});f.submit();f.dialog.emit('shown.bs.modal');f.dialog.emit('hidden.bs.modal');
 assert.equal(f.counts().dropShown,1);assert.equal(f.counts().focused,1);
});

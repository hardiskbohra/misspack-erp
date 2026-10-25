'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../public/assets/js/master-selects.js'),'utf8');
for(const count of [0,1,2,8,9,50]){
 test('shared Select2 keeps search enabled with '+count+' options',()=>{
  let configured;
  const select={};
  const $select={data:()=>undefined,find:()=>({length:count}),
   select2:options=>{configured=options;},
   next:()=>({length:1,addClass:()=>{}}),attr:()=> 'master-select',on:()=>{}};
  const chain={not:()=>chain,each:callback=>{callback.call(select);}};
  const $=value=>{
   if(typeof value==='function'){value();return;}
   if(value===select)return $select;
   return chain;
  };
  $.fn={select2:()=>{}};
  const document={querySelectorAll:()=>[],addEventListener:()=>{}};
  vm.runInNewContext(source,{window:{jQuery:$},document,WeakSet});
  assert.equal(configured.minimumResultsForSearch,0,'Search must never be hidden by option count');
  assert.equal(configured.width,'100%');
  assert.equal(configured.selectionCssClass,':all:');
 });
}

// The application has one authoritative initializer (vendor sources excluded).
test('ERP Select2 search policy has no competing initializer or hiding attribute',()=>{
 const root=path.resolve(__dirname,'../..');
 const walk=directory=>fs.readdirSync(directory,{withFileTypes:true}).flatMap(entry=>{
  const file=path.join(directory,entry.name);return entry.isDirectory()?walk(file):[file];
 });
 const files=[...walk(path.join(root,'public/assets/js')),...walk(path.join(root,'resources/views'))];
 const initializers=[];
 for(const file of files){
  const text=fs.readFileSync(file,'utf8');
  if(/\.select2\s*\(/.test(text))initializers.push(path.relative(root,file));
  assert(!/data-minimum-results-for-search\s*=/.test(text),'No per-control search suppression in '+file);
 }
 assert.deepEqual(initializers,['public/assets/js/master-selects.js']);
});

test('every Select2 entry point cache-busts the shared search initializer',()=>{
 for(const file of ['layouts/app.blade.php','client_portal/layouts/app.blade.php',
  'leads/public-create.blade.php','cashflows/partials/statement-print-filters.blade.php']){
  const text=fs.readFileSync(path.join(__dirname,'../../resources/views',file),'utf8');
  assert(text.includes("$assetVer('assets/js/master-selects.js')"),'Updated search policy must not remain cached in '+file);
 }
});

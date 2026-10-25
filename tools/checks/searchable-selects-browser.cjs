#!/usr/bin/env node
'use strict';
const {chromium}=require('playwright-core');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..');
const directory=path.resolve(process.argv[2]||'.work/statement-print');
const options=Array.from({length:30},(_,i)=>`<option value="${i}">Choice ${String(i).padStart(2,'0')}</option>`).join('');
const select=(id,opts=options,attrs='')=>`<div class="master-field"><label for="${id}">${id}</label><select id="${id}" class="master-select" ${attrs}>${opts}</select></div>`;
const styles=['app-layout.css','master-index.css','master-form.css','select2-theme.css','design-system.css'];
const harness=`<!doctype html><html data-theme="light"><head><link rel="stylesheet" href="/assets/vendor/select2/css/select2.min.css">${styles.map(f=>`<link rel="stylesheet" href="/assets/css/${f}">`).join('')}</head><body data-ui-shell="app"><main style="max-width:720px;padding:24px">
<form id="testForm">${select('long')}${select('short','<option value="a">Alpha</option><option value="b">Beta</option>')}${select('multi',options,'multiple')}${select('disabled',options,'disabled')}${select('native',options,'data-no-select2')}
<div id="dynamic"></div><button type="button" id="drawerTrigger" data-drawer-open="testDrawer">Drawer</button><button type="button" id="modalTrigger" onclick="MasterModal.open(document.getElementById('testModal'))">Modal</button></form>
<div class="core-drawer-layer" id="testDrawer-layer" data-drawer-layer hidden aria-hidden="true"><button class="core-drawer-backdrop" data-drawer-close></button><aside id="testDrawer" class="core-drawer" role="dialog"><button class="core-drawer-close" data-drawer-close>Close</button><div class="core-drawer-content">${select('drawerSelect')}</div><div class="core-drawer-footer"><button id="drawerLast">Apply</button></div></aside></div>
<div id="testModal" class="master-modal" aria-hidden="true"><div class="master-modal-card"><button data-close-modal="testModal">Close modal</button><div class="master-modal-body">${select('modalSelect')}</div></div></div></main>
<script src="/assets/vendor/jquery/jquery.min.js"></script><script src="/assets/vendor/select2/js/select2.min.js"></script><script src="/assets/js/master-selects.js"></script><script src="/assets/js/master-drawer.js"></script><script src="/assets/js/app-layout.js"></script></body></html>`;
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_EXECUTABLE_PATH,headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 try{
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',route=>{
   const url=new URL(route.request().url());
   if(url.pathname==='/harness')return route.fulfill({body:harness,contentType:'text/html'});
   if(url.hostname==='print.test'&&!url.pathname.includes('.'))return route.fulfill({path:path.join(directory,url.searchParams.get('period')==='custom'?'client-custom.html':'client-all.html'),contentType:'text/html'});
   const file=path.resolve(root,'public','.'+decodeURIComponent(url.pathname));
   if(file.startsWith(root+'/public/')&&fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
   return route.abort();
  });
  async function searchPick(id,term,label,value){
   await page.locator('#'+id+' + .select2 .select2-selection').click();
   const input=page.locator('.select2-container--open .select2-search__field:visible').last();
   assert(await input.isVisible(),id+' must expose search');
   await page.waitForFunction(()=>document.activeElement.matches('.select2-container--open .select2-search__field'));
   await input.fill('');
   await input.pressSequentially(term);
   assert(await input.evaluate(n=>n===document.activeElement),id+' search receives keyboard focus');
   await page.locator('.select2-results').getByRole('option',{name:label,exact:true}).waitFor({state:'visible'});
   if(id==='short'||id==='printCurrency') await input.press('Enter');
   else await page.locator('.select2-results').getByRole('option',{name:label,exact:true}).click();
   assert.deepEqual(await page.locator('#'+id).evaluate(n=>n.multiple?[...n.selectedOptions].map(o=>o.value):n.value),value);
  }
  for(const width of [1440,900,390])for(const theme of ['light','dark']){
   await page.setViewportSize({width,height:1000});await page.goto('http://ui.test/harness',{waitUntil:'networkidle'});
   await page.evaluate(theme=>document.documentElement.dataset.theme=theme,theme);
   await page.evaluate(()=>{window.changes=0;jQuery('#long').on('change',()=>window.changes++);MasterSelects.init(jQuery);});
   await searchPick('long','27','Choice 27','27');
   assert.equal(await page.evaluate(()=>window.changes),1,'No duplicate selection change');
   await searchPick('short','Beta','Beta','b');
   await searchPick('multi','18','Choice 18',['18']);
   await page.keyboard.press('Escape');
   assert.equal(await page.locator('#native + .select2').count(),0,'Native opt-out retained');
   assert.equal(await page.locator('#disabled + .select2').count(),0,'Disabled control untouched');
   await page.evaluate(html=>{document.getElementById('dynamic').innerHTML=html;},select('injected'));
   await page.locator('#injected + .select2').waitFor();await searchPick('injected','29','Choice 29','29');
   await page.locator('#drawerTrigger').click();await searchPick('drawerSelect','22','Choice 22','22');
   await page.locator('#drawerSelect + .select2 .select2-selection').click();
   await page.keyboard.press('Tab');
   assert.equal(await page.locator('.select2-dropdown').count(),0,'Tab closes dropdown');
   assert(await page.locator('#testDrawer').evaluate(n=>n.contains(document.activeElement)),'Tab stays inside drawer');
   await page.locator('#drawerSelect + .select2 .select2-selection').click();
   await page.keyboard.press('Escape');
   assert.equal(await page.locator('#testDrawer-layer').getAttribute('aria-hidden'),'false','First Escape closes dropdown, not drawer');
   assert.equal(await page.locator('.select2-dropdown').count(),0);
   await page.locator('#drawerSelect + .select2 .select2-selection').click();
   await page.locator('.select2-container--open .select2-search__field:visible').last().fill('No such result');
   assert(await page.getByRole('alert').filter({hasText:'No results found'}).isVisible());
   await page.locator('#testDrawer .core-drawer-close').click();
   assert.equal(await page.locator('.select2-dropdown').count(),0,'Closing drawer closes detached options');
   assert(await page.locator('#drawerTrigger').evaluate(n=>n===document.activeElement),'Drawer close restores trigger focus');
   await page.locator('#modalTrigger').click();await searchPick('modalSelect','21','Choice 21','21');
   await page.locator('#modalSelect + .select2 .select2-selection').click();
   await page.evaluate(()=>MasterModal.close(document.getElementById('testModal')));
   await page.locator('.select2-dropdown').waitFor({state:'detached'});
   assert.equal(await page.locator('#long + .select2').count(),1,'Reinitialization remains idempotent');
   console.log('PASS shared Select2 '+width+' '+theme+': long/short/multiple/dynamic/modal/drawer search, events and cleanup');
  }
  for(const width of [1440,900,390]){
   await page.setViewportSize({width,height:1000});await page.goto('http://print.test/print?period=custom',{waitUntil:'networkidle'});
   await page.locator('[data-drawer-open="statementPrintFilters"]').click();
   const ui=await page.evaluate(()=>{
    const from=document.getElementById('printFrom'),period=document.querySelector('#printPeriod + .select2 .select2-selection');
    const label=from.previousElementSibling,close=document.querySelector('#statementPrintFilters .core-drawer-close svg');
    return {gap:from.getBoundingClientRect().top-label.getBoundingClientRect().bottom,height:from.getBoundingClientRect().height,
     border:getComputedStyle(from).borderTopWidth,outerBorder:getComputedStyle(period.closest('.select2-container')).borderTopWidth,selectHeight:period?.getBoundingClientRect().height,close:!!close,closeHeight:close?.parentElement.getBoundingClientRect().height,
     pad:getComputedStyle(document.querySelector('#statementPrintFilters .core-drawer-content')).paddingLeft};
   });
   assert(ui.close,'Close icon must not depend on unavailable CDN fonts');assert(ui.closeHeight>=40,'Close target must meet shared40px guideline');assert(ui.gap>=6&&ui.gap<=10,'Consistent label/control gap');
   assert(ui.height>=40&&ui.selectHeight>=40,'Shared control heights');assert.equal(ui.border,'1px');assert.equal(ui.outerBorder,'0px','Select2 wrapper must not draw a second control border');assert.equal(ui.pad,width<768?'16px':'24px');
   await searchPick('printPeriod','All time','All time','all');
   assert.equal(await page.locator('#printFrom').inputValue(),'','Preset change clears stale custom dates');
   assert.equal(await page.locator('#printTo').inputValue(),'');
   await page.locator('#printFrom').fill('2025-01-01');await page.locator('#printTo').fill('2025-12-31');
   assert.equal(await page.locator('#printPeriod').inputValue(),'custom');
   assert.equal(await page.locator('#select2-printPeriod-container').innerText(),'Custom dates','Date edits update rendered Select2 too');
   await searchPick('printCurrency','USD','USD','USD');
   await searchPick('printCurrency','INR','INR','INR');
   const ageing=page.locator('#printAgeing');await ageing.check();
   await page.waitForFunction(()=>getComputedStyle(document.querySelector('#printAgeing + .master-slider'),'::before').backgroundColor==='rgb(255, 255, 255)');
   await page.waitForFunction(()=>getComputedStyle(document.querySelector('#printAgeing + .master-slider')).backgroundColor==='rgb(79, 131, 241)');
   await ageing.uncheck();
   await page.waitForFunction(()=>getComputedStyle(document.querySelector('#printAgeing + .master-slider')).backgroundColor==='rgb(248, 251, 255)');
   await page.screenshot({path:path.join(directory,'filters-'+width+'.png'),fullPage:true});
   await page.getByRole('button',{name:'Apply filters'}).click();await page.waitForLoadState('networkidle');
   const query=new URL(page.url()).searchParams;assert.equal(query.get('period'),'custom');assert.equal(query.get('currency'),'INR');
   assert.equal(query.getAll('ageing').at(-1),'0');assert.equal(query.get('date_from'),'2025-01-01');
   console.log('PASS statement filters '+width+': shared design, searchable presets/currency, date sync and exact submitted scope');
  }
  assert.deepEqual(errors,[],'No browser exceptions');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

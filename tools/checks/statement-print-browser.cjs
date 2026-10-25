#!/usr/bin/env node
'use strict';
const {chromium}=require('playwright-core');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..'),dir=path.resolve(process.argv[2]||path.join(root,'.work/statement-print'));
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_EXECUTABLE_PATH,headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu']});
 try{
  const context=await browser.newContext();const errors=[];
  context.on('page',p=>p.on('pageerror',e=>errors.push(e.message)));
  await context.addInitScript(()=>{window.print=()=>{window.__printRequested=true;};});
  await context.route('**/*',route=>{
   const url=new URL(route.request().url());
   if(url.hostname==='print.test'&&!url.pathname.includes('.')){
    const name=url.pathname==='/show'?'show':url.pathname==='/large'?'client-large':url.pathname.includes('/vendor/')?'vendor-all':url.searchParams.get('period')==='custom'?'client-custom':'client-all';
    return route.fulfill({path:path.join(dir,name+'.html'),contentType:'text/html'});
   }
   const file=path.resolve(root,'public','.'+decodeURIComponent(url.pathname));
   if(file.startsWith(path.join(root,'public')+path.sep)&&fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
   return route.abort();
  });
  const page=await context.newPage();
  await page.goto('http://print.test/show',{waitUntil:'networkidle'});
  const popupPromise=context.waitForEvent('page');
  await page.locator('#printStatement').click();const preview=await popupPromise;
  await preview.waitForLoadState('networkidle');
  assert.equal(new URL(preview.url()).searchParams.get('period'),'all');
  assert.equal(new URL(preview.url()).searchParams.get('ageing'),'0');
  assert.equal(new URL(page.url()).pathname,'/show');
  assert.equal(await preview.locator('.pdf-toolbar a,.pdf-toolbar button').count(),2);
  await preview.locator('#statementDownload').click();
  assert(await preview.evaluate(()=>window.__printRequested));
  assert.equal(new URL(preview.url()).searchParams.get('period'),'all');
  await preview.locator('[data-drawer-open="statementPrintFilters"]').click();
  await preview.locator('#printFrom').fill('2025-01-01');
  await preview.locator('#printTo').fill('2025-12-31');
  assert.equal(await preview.locator('#printPeriod').inputValue(),'custom');
  await preview.getByRole('button',{name:'Apply filters'}).click();await preview.waitForLoadState('networkidle');
  assert.equal(new URL(preview.url()).searchParams.get('period'),'custom');
  assert.equal(new URL(await preview.locator('#statementDownload').getAttribute('href')).searchParams.get('date_from'),'2025-01-01');
  await preview.close();
  for(const [name,url]of [['client-all','http://print.test/cashflows/statements/client/1/print?period=all&currency=INR&ageing=0'],['client-custom','http://print.test/cashflows/statements/client/1/print?period=custom'],['vendor-all','http://print.test/cashflows/statements/vendor/1/print?period=all'],['client-large','http://print.test/large']]){
   await page.goto(url,{waitUntil:'networkidle'});
   for(const width of [1440,390]){
    await page.setViewportSize({width,height:1000});
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No page-wide overflow '+name+' '+width);
    await page.locator('[data-drawer-open="statementPrintFilters"]').click();
    assert(await page.locator('#printPeriod').isVisible());
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#statementPrintFilters-layer').getAttribute('aria-hidden'),'true');
   }
   await page.setViewportSize({width:1200,height:1000});
   await page.emulateMedia({media:'print'});
   const layout=await page.evaluate(()=>{
    const b=document.querySelector('.stmt-brand').getBoundingClientRect(),h=document.querySelector('.stmt-heading').getBoundingClientRect();
    return {aligned:Math.abs(b.top-h.top)<2,separate:b.right<=h.left+2,logo:document.querySelector('.stmt-logo').complete,
     cropped:[...document.querySelectorAll('.stmt-figure strong')].filter(n=>n.scrollWidth>n.clientWidth+1).map(n=>n.textContent),
     hidden:getComputedStyle(document.querySelector('.pdf-toolbar')).display==='none'};
   });
   assert(layout.aligned&&layout.separate,'Issuer and party must be side by side '+name);
   assert(layout.logo,'Logo loaded');assert.deepEqual(layout.cropped,[],'No cropped summary amounts');assert(layout.hidden,'Toolbar print-hidden');
   await page.pdf({path:path.join(dir,name+'.pdf'),format:'A4',preferCSSPageSize:true,printBackground:true});
   await page.emulateMedia({media:'screen'});
   await page.screenshot({path:path.join(dir,name+'.png'),fullPage:true});
   console.log('PASS '+name+': new-tab controls, A4 two-column header, loaded logo, unclipped full-width summary');
  }
  assert.deepEqual(errors,[],'No JavaScript exceptions');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});

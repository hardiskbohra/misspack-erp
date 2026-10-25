#!/usr/bin/env node
'use strict';
const {chromium}=require('playwright-core');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..'),dir=path.join(root,'.work/audit-register');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_EXECUTABLE_PATH,headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 try{
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',route=>{
   const url=new URL(route.request().url()),file=path.resolve(root,'public','.'+decodeURIComponent(url.pathname));
   if(file.startsWith(root+'/public/')&&fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
   if(url.hostname==='audit.test'){
    const name=url.pathname==='/hostile'?'hostile':url.pathname==='/empty'?'empty':url.searchParams.get('page')==='2'?'page2':'page1';
    return route.fulfill({body:fs.readFileSync(path.join(dir,name+'.html'),'utf8').replaceAll('http://localhost','http://audit.test'),contentType:'text/html'});
   }
   return route.abort();
  });
  const scope='search=Example+operator&event=fixture.changed&outcome=success&actor_type=office_user&from=2026-10-01&to=2026-10-31';
  for(const width of [1440,900,390])for(const theme of ['light','dark']){
   await page.setViewportSize({width,height:900});await page.goto('http://audit.test/audit?'+scope,{waitUntil:'networkidle'});
   await page.evaluate(t=>document.documentElement.dataset.theme=t,theme);
   assert(await page.locator('[data-audit-search]').isVisible());
   const form=await page.locator('.audit-search-form').evaluate(n=>Object.fromEntries(new FormData(n)));
   for(const [k,v]of new URLSearchParams(scope))assert.equal(form[k],v,'One GET form retains '+k);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No list overflow '+width);
   const buttons=page.locator('[data-audit-evidence]'),first=buttons.first();
   await first.click();
   const modal=page.locator('#auditEvidenceModal'),dialog=modal.getByRole('dialog');
   assert.equal(await modal.getAttribute('aria-hidden'),'false');
   assert.equal(await dialog.getAttribute('aria-modal'),'true');
   assert(await modal.locator('[data-audit-body]').innerText().then(t=>t.includes('Fixture browser 35')&&t.includes('reason, decision')));
   assert(await modal.locator('[aria-label="Close audit evidence"]').evaluate(n=>n===document.activeElement));
   const geom=await dialog.boundingBox();assert(geom.x>=0&&geom.y>=0&&geom.x+geom.width<=width+1&&geom.y+geom.height<=901,'Dialog stays in viewport');
   assert(await modal.locator('[data-audit-body]').evaluate(n=>n.scrollWidth<=n.clientWidth+1),'Long evidence wraps without horizontal overflow');
   await modal.getByRole('button',{name:'Close',exact:true}).focus();await page.keyboard.press('Tab');
   assert(await modal.locator('[aria-label="Close audit evidence"]').evaluate(n=>n===document.activeElement),'Tab wraps');
   await page.keyboard.press('Shift+Tab');assert(await modal.getByRole('button',{name:'Close',exact:true}).evaluate(n=>n===document.activeElement),'Shift+Tab wraps');
   await page.screenshot({path:path.join(dir,'modal-'+width+'-'+theme+'.png'),fullPage:false});
   await page.keyboard.press('Escape');assert.equal(await modal.getAttribute('aria-hidden'),'true');assert(await first.evaluate(n=>n===document.activeElement),'Escape restores row focus');
   await buttons.nth(1).click();assert((await modal.innerText()).includes('Fixture browser 34'));assert(!(await modal.innerText()).includes('Fixture browser 35'),'No stale row content');
   await modal.getByRole('button',{name:'Close',exact:true}).click();assert.equal(await modal.getAttribute('aria-hidden'),'true');
   await first.click();await modal.click({position:{x:2,y:2}});assert.equal(await modal.getAttribute('aria-hidden'),'true');assert(await first.evaluate(n=>n===document.activeElement),'Backdrop restores focus');
   await page.locator('[data-drawer-open="auditFilterDrawer"]').click();
   await page.locator('#auditOutcome + .select2 .select2-selection').click();
   const search=page.locator('.select2-container--open .select2-search__field:visible').last();assert(await search.isVisible());await search.pressSequentially('Denied');
   await page.locator('.select2-results').getByRole('option',{name:'Denied',exact:true}).click();await page.keyboard.press('Escape');
   assert.equal(await page.locator('#auditFilterDrawer-layer').getAttribute('aria-hidden'),'true');
   const link=page.locator('.custom-pagination a').filter({hasText:'2'}).first();await link.click();await page.waitForLoadState('networkidle');
   assert.equal(new URL(page.url()).searchParams.get('page'),'2');for(const [k,v]of new URLSearchParams(scope))assert.equal(new URL(page.url()).searchParams.get(k),v);
   assert.equal(await page.locator('[data-audit-evidence]').count(),5);await page.evaluate(t=>document.documentElement.dataset.theme=t,theme);
   await page.screenshot({path:path.join(dir,'page2-'+width+'-'+theme+'.png'),fullPage:true});
  }
  await page.goto('http://audit.test/hostile',{waitUntil:'networkidle'});await page.locator('[data-audit-evidence]').first().click();
  assert.equal(await page.locator('#auditEvidenceModal img').count(),0);assert.equal(await page.evaluate(()=>window.auditXss),undefined);
  await page.goto('http://audit.test/empty',{waitUntil:'networkidle'});assert.equal(await page.locator('[data-audit-evidence]').count(),0);assert(await page.getByText('No audit events match these filters.').isVisible());
  assert.deepEqual(errors,[]);console.log('PASS audit register: 1440/900/390 light/dark, shared pagination + retained filters, searchable drawer, read-only evidence, row switching, keyboard/backdrop/close/focus, escaped hostile content and empty state.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

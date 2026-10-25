#!/usr/bin/env node
'use strict';
// Render fixtures first using tools/testing/render-statement-list.php.
const {chromium}=require('playwright-core');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..');
const directory=path.resolve(process.argv[2]||path.join(root,'.work/statement-list'));
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_EXECUTABLE_PATH,headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu']});
 try {
  const page=await browser.newPage();const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  await page.route('**/*',route=>{
   const url=new URL(route.request().url());
   if(url.hostname==='statements.test'&&url.pathname==='/cashflows/statements'){
    const q=url.searchParams;
    const variant=q.get('q')?(q.get('currency')==='USD'?'search':'search-all'):q.get('currency')==='USD'?(q.get('party_type')==='client'?'client-usd':'usd'):q.get('party_type')==='client'?'client':q.get('party_type')==='vendor'?'vendor':q.get('period')==='all'?'all':'default';
    return route.fulfill({path:path.join(directory,variant+'.html'),contentType:'text/html'});
   }
   const file=path.resolve(root,'public','.'+decodeURIComponent(url.pathname));
   if(file.startsWith(path.join(root,'public')+path.sep)&&fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
   return route.abort();
  });
  for(const width of [1440,900,390]){
   await page.setViewportSize({width,height:1000});errors.length=0;
   await page.goto('http://statements.test/cashflows/statements',{waitUntil:'networkidle'});
   assert.equal(await page.locator('.cashflow-statements table').first().locator('tbody tr').count(),4);
   await page.screenshot({path:path.join(directory,'all-currencies-'+width+'.png'),fullPage:true});
   await page.locator('.master-list-chip').filter({hasText:'All time'}).click();
   await page.waitForLoadState('networkidle');
   assert.equal(new URL(page.url()).searchParams.get('period'),'all');
   assert.equal(await page.locator('nav[aria-label="Statement currencies"] a[aria-current="true"]').textContent(),'All currencies');
   const currencyNav=page.locator('nav[aria-label="Statement currencies"]');
   await currencyNav.getByText('USD',{exact:true}).click();
   await page.waitForLoadState('networkidle');
   assert.equal(new URL(page.url()).searchParams.get('currency'),'USD');
   assert.equal(new URL(page.url()).searchParams.get('period'),'all');
   assert.equal(await page.locator('.cashflow-statements table').first().locator('tbody tr').count(),1);
   await page.locator('.master-list-chip').getByText('Clients',{exact:true}).click();
   await page.waitForLoadState('networkidle');
   assert.equal(new URL(page.url()).searchParams.get('party_type'),'client');
   assert.equal(new URL(page.url()).searchParams.get('period'),'all');
   assert(await page.locator('.master-list-chip.is-active').getByText('Clients',{exact:true}).isVisible());
   await page.locator('[name="q"]').fill('Historical');
   await page.locator('[name="q"]').press('Enter');
   await page.waitForLoadState('networkidle');
   assert.equal(new URL(page.url()).searchParams.get('period'),'all');
   assert.equal(new URL(page.url()).searchParams.get('currency'),'USD');
   assert.equal(new URL(page.url()).searchParams.get('q'),'Historical');
   assert.equal(new URL(page.url()).searchParams.get('party_type'),'client');
   await currencyNav.getByText('All currencies',{exact:true}).click();
   await page.waitForLoadState('networkidle');
   assert.equal(new URL(page.url()).searchParams.get('period'),'all');
   assert.equal(new URL(page.url()).searchParams.get('q'),'Historical');
   assert.equal(new URL(page.url()).searchParams.get('party_type'),'client');
   assert.equal(await page.locator('.cashflow-statements table').first().locator('tbody tr').count(),3);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No document overflow at '+width);
   assert.deepEqual(errors,[]);
   await page.screenshot({path:path.join(directory,'statements-'+width+'.png'),fullPage:true});
   await page.locator('[data-drawer-open="statementListFiltersDrawer"]').click();
   assert.equal(await page.locator('[name="period"]').inputValue(),'all');
   await page.locator('#statementListDateFrom').fill('2024-01-01');
   assert.equal(await page.locator('[name="period"]').inputValue(),'custom');
   await page.locator('#statementListDateTo').fill('2026-10-31');
   await page.keyboard.press('Escape');
   assert.equal(await page.locator('#statementListFiltersDrawer-layer').getAttribute('aria-hidden'),'true');
   console.log('PASS '+width+'px: real All time/currency/party/search controls, preserved period, custom date edits, no overflow/JS errors');
  }
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});

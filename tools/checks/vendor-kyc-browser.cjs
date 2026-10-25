#!/usr/bin/env node
'use strict';
const {chromium}=require('playwright-core');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..'),dir=path.join(root,'.work/vendor-kyc');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_EXECUTABLE_PATH,headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 try {
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',route=>{
   const url=new URL(route.request().url());
   if(/^\/office-alerts\/\d+\/(popup|seen|ack)$/.test(url.pathname)) return route.fulfill({json:{unread:0,critical:0,items:[],toasts:[],popup:null}});
   const file=path.resolve(root,'public','.'+decodeURIComponent(url.pathname));
   if (file.startsWith(root+'/public/')&&fs.existsSync(file)&&fs.statSync(file).isFile()) return route.fulfill({path:file});
   const name=url.hostname==='kyc.test'?url.pathname.slice(1):null;
   if(name&&fs.existsSync(path.join(dir,name+'.html'))) return route.fulfill({body:fs.readFileSync(path.join(dir,name+'.html'),'utf8').replaceAll('http://localhost','http://kyc.test'),contentType:'text/html'});
   return route.abort();
  });
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});
   await page.goto('http://kyc.test/draft',{waitUntil:'networkidle'});
   assert.equal(await page.locator('[data-kyc-panel]:visible').count(),1);
   assert(!(await page.locator('[data-kyc-prev]').isVisible()),'Back is hidden on the first step');
   assert(!(await page.locator('[data-kyc-submit]').isVisible()),'Submit is hidden before the final step');
   await page.locator('#vendor_name').fill('Browser packaging partner');
   await page.locator('#vendor_type').selectOption('consultant');
   await page.locator('[data-kyc-next]').click();
   assert(await page.locator('[data-kyc-panel="2"]').isVisible());
   await page.locator('[data-kyc-next]').click();
   assert(await page.locator('[data-kyc-panel="2"]').isVisible(),'Cannot skip missing contacts');
   await page.locator('#contact_person_name').fill('Browser contact');
   await page.locator('#contact_person_email').fill('contact@example.test');
   await page.locator('#contact_person_mobile').fill('bad phone');await page.locator('[data-kyc-next]').click();
   assert(await page.locator('[data-kyc-panel="2"]').isVisible(),'Invalid phone stays on contact step');
   await page.locator('#contact_person_mobile').fill('+91 9876543210');
   await page.locator('[data-kyc-next]').click();
   await page.locator('#address').fill('Browser supplier address');await page.locator('#city').fill('Singapore');await page.locator('#country').fill('Singapore');
   await page.locator('[data-kyc-next]').click();
   assert(!(await page.locator('[data-kyc-next]').isVisible()),'Continue is hidden on the final step');
   assert(await page.locator('#bank_address').isVisible());assert(await page.locator('#beneficiary_address').isVisible());
   await page.locator('[data-kyc-prev]').click();await page.locator('#country').fill('India');await page.locator('[data-kyc-next]').click();
   for(const id of ['gstin','account_number','ifsc_code','gst_certificate','pan_document']) assert(await page.locator('#'+id).evaluate(n=>n.required),'Indian requirement '+id);
   await page.locator('[data-kyc-submit]').click();assert.equal(await page.getByRole('button',{name:'Yes, submit',exact:true}).count(),0,'Missing Indian requirements prevent confirmation');
   await page.locator('[data-kyc-prev]').click();await page.locator('#country').fill('Singapore');await page.locator('[data-kyc-next]').click();
   for(const id of ['gstin','account_number','ifsc_code','gst_certificate','pan_document']) assert(!(await page.locator('#'+id).evaluate(n=>n.required)),'Overseas exemption '+id);
   await page.locator('#bank_address').fill('Bank Road\nSingapore');await page.locator('#beneficiary_address').fill('Beneficiary Road\nSingapore');
   await page.locator('#account_number').fill('PRIVATE-BROWSER-ACCOUNT');await page.locator('#bank_name').focus();
   assert.equal(await page.evaluate(()=>Object.keys(localStorage).filter(k=>k.startsWith('misspack.kyc.')).length),0,'Public bank details must not persist in localStorage');
   await page.locator('#documents').setInputFiles({name:'bank.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.7\nfixture')});
   await page.evaluate(()=>{document.querySelector('form[data-kyc-form]').submit=function(){window.__submittedIntent=this.elements.intent.value;};});
   await page.locator('[data-kyc-draft]').click();assert.equal(await page.evaluate(()=>window.__submittedIntent),'draft');
   await page.goto('http://kyc.test/draft',{waitUntil:'networkidle'});
   await page.locator('#vendor_name').fill('Browser partner');await page.locator('#vendor_type').selectOption('forwarder');
   await page.locator('[data-kyc-next]').click();await page.locator('#contact_person_name').fill('Browser contact');await page.locator('#contact_person_email').fill('contact@example.test');await page.locator('#contact_person_mobile').fill('+91 9876543210');
   await page.locator('[data-kyc-next]').click();await page.locator('#address').fill('A road');await page.locator('#city').fill('Singapore');await page.locator('#country').fill('Singapore');await page.locator('[data-kyc-next]').click();
   await page.evaluate(()=>{document.querySelector('form[data-kyc-form]').submit=function(){window.__submittedIntent=this.elements.intent.value;};});
   await page.locator('[data-kyc-submit]').click();await page.getByRole('button',{name:'Keep editing',exact:true}).click();assert.equal(await page.evaluate(()=>window.__submittedIntent),undefined);
   await page.locator('[data-kyc-submit]').click();await page.getByRole('button',{name:'Yes, submit',exact:true}).click();await page.waitForFunction(()=>window.__submittedIntent==='submit');
   for(const state of ['under-review','approved','revision','errors','office-maker','office-checker','office-reader']){
    await page.goto('http://kyc.test/'+state,{waitUntil:'networkidle'});
    const markRead=page.getByRole('button',{name:'Mark read',exact:true});if(await markRead.isVisible()) await markRead.click();
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No page-wide overflow '+state+' '+width);
    if(state==='errors') assert(await page.locator('[data-kyc-panel="2"]').isVisible(),'First server error navigates to contact step');
    if(state==='under-review'||state==='approved'){
     assert.equal(await page.locator('[data-kyc-draft],[data-kyc-submit]').count(),0);
     for(let i=1;i<4;i++)await page.locator('[data-kyc-next]').click();
     assert(await page.locator('#account_number').isDisabled());
    }
    if(state==='office-checker'&&width===390){
     const labels=await page.locator('.vendor-kyc tbody tr').first().locator('td').evaluateAll(cells=>cells.map(n=>getComputedStyle(n,'::before').content));
     assert(labels[0].includes('Current vendor record')&&labels[1].includes('Submitted for review'),'Mobile comparison must label both current and submitted values');
    }
    if(state==='office-checker'||state==='office-maker')assert.equal(await page.locator('button[name="decision"]').count(),3);
    if(state==='office-reader')assert.equal(await page.locator('button[name="decision"]').count(),0);
    await page.screenshot({path:path.join(dir,state+'-'+width+'.png'),fullPage:true});
   }
  }
  await page.goto('http://kyc.test/client-readonly',{waitUntil:'networkidle'});
  assert(!(await page.locator('[data-kyc-prev]').isVisible()),'Client KYC retains the same first-step navigation');
  for(let i=1;i<4;i++)await page.locator('[data-kyc-next]').click();
  assert(!(await page.locator('[data-kyc-next]').isVisible()),'Client KYC final-step navigation is also correct');
  assert.equal(await page.locator('[data-kyc-submit],[data-kyc-draft]').count(),0,'Approved client KYC remains locked');
  assert.deepEqual(errors,[]);console.log('PASS vendor KYC: four steps, required/error navigation, draft, files, confirmation, no bank persistence, locked states, maker/checker/read-only and 1440/390px layouts.');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

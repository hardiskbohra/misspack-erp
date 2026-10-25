#!/usr/bin/env node
'use strict';
const {chromium}=require('playwright-core');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..'),dir=path.join(root,'.work/detail-photo');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_EXECUTABLE_PATH,headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 try{
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));let submitted=null;
  await page.route('**/*',route=>{
   const url=new URL(route.request().url()),file=path.resolve(root,'public','.'+decodeURIComponent(url.pathname));
   if(file.startsWith(root+'/public/')&&fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
   if(url.hostname==='photo.test'){
    if(url.pathname==='/storage/avatars/current.png')return route.fulfill({path:path.join(dir,'image.png'),contentType:'image/png'});
    if(url.pathname.startsWith('/storage/'))return route.fulfill({status:404,body:'Not found'});
    if(url.pathname==='/users/2/photo'){
     submitted={url:url.href,body:route.request().postDataBuffer().toString(),type:route.request().headers()['content-type']};
     // Persistence and redirect are exercised by the real HTTP tests; inspect native multipart here.
     return route.fulfill({body:fs.readFileSync(path.join(dir,'record.html'),'utf8').replaceAll('http://localhost','http://photo.test'),contentType:'text/html'});
    }
    const name=({'/readonly':'readonly','/no-photo':'no-photo','/validation':'validation'})[url.pathname]||'record';
    return route.fulfill({body:fs.readFileSync(path.join(dir,name+'.html'),'utf8').replaceAll('http://localhost','http://photo.test'),contentType:'text/html'});
   }
   return route.abort();
  });
  const png={name:'replacement.png',mimeType:'image/png',buffer:fs.readFileSync(path.join(dir,'image.png'))};
  for(const width of [1440,900,390])for(const theme of ['light','dark']){
   await page.setViewportSize({width,height:900});await page.goto('http://photo.test/users/2?tab=overview&year=2026',{waitUntil:'networkidle'});
   await page.evaluate(t=>document.documentElement.dataset.theme=t,theme);
   const opener=page.locator('[data-open-photo]'),modal=page.locator('#userPhotoModal'),dialog=modal.getByRole('dialog'),circle=page.locator('#recordPhotoCircle'),save=modal.getByRole('button',{name:'Save photo'});
   assert(await opener.isVisible(),'Detail photo action visible');await opener.click();
   await page.waitForFunction(()=>document.querySelector('#recordPhotoCircle').classList.contains('has-photo'));
   assert.equal(await modal.getAttribute('aria-hidden'),'false');assert(await save.isDisabled());
   assert(await modal.getByRole('button',{name:'Close photo editor'}).evaluate(n=>n===document.activeElement));
   const box=await dialog.boundingBox();assert(box.x>=0&&box.y>=0&&box.x+box.width<=width+1&&box.y+box.height<=901,'Modal contained in viewport');
   assert(await modal.locator('.master-modal-body').evaluate(n=>n.scrollWidth<=n.clientWidth+1),'Photo band does not overflow');
   const keys=await modal.locator('form').evaluate(n=>Array.from(new FormData(n).keys()));assert.deepEqual(keys,['_token','_method','remove_avatar','avatar'],'No full-user fields submitted');
   assert.equal(await modal.locator('[name=_method]').inputValue(),'PATCH');
   await page.locator('#recordPhotoFile').setInputFiles(png);
   await page.waitForFunction(()=>document.querySelector('#recordPhotoCircle img')?.src.startsWith('data:image/png;'));
   assert(await save.isEnabled());assert.equal(await modal.locator('[name=remove_avatar]').inputValue(),'0');
   await page.locator('#recordPhotoRemove').click();assert.equal(await circle.locator('img').count(),0);assert.equal(await circle.textContent(),'HB');
   assert.equal(await modal.locator('[name=remove_avatar]').inputValue(),'1');assert(await save.isEnabled());
   await page.locator('#recordPhotoFile').setInputFiles(png);
   await page.waitForFunction(()=>document.querySelector('#recordPhotoCircle').classList.contains('has-photo'));
   assert.equal(await modal.locator('[name=remove_avatar]').inputValue(),'0');
   await page.screenshot({path:path.join(dir,`editor-${width}-${theme}.png`)});
   await save.focus();await page.keyboard.press('Tab');assert(await modal.getByRole('button',{name:'Close photo editor'}).evaluate(n=>n===document.activeElement),'Trap forward');
   await page.keyboard.press('Shift+Tab');assert(await save.evaluate(n=>n===document.activeElement),'Trap backwards');
   await page.keyboard.press('Escape');assert.equal(await modal.getAttribute('aria-hidden'),'true');assert(await opener.evaluate(n=>n===document.activeElement),'Focus restored');
   await opener.click();assert(await save.isDisabled(),'Cancel/reopen resets pending mutation');assert.equal(await page.locator('#recordPhotoFile').inputValue(),'');
   await page.locator('#recordPhotoFile').setInputFiles({name:'script.html',mimeType:'text/html',buffer:Buffer.from('<script>bad</script>')});
   assert(await save.isDisabled());assert.match(await page.locator('#recordPhotoName').textContent(),/JPG, PNG, GIF or WEBP/);assert(await circle.locator('img').evaluate(n=>n.src.endsWith('/storage/avatars/current.png')),'Invalid choice retains current preview');
   await modal.getByRole('button',{name:'Cancel',exact:true}).click();assert.equal(await modal.getAttribute('aria-hidden'),'true');
   await opener.click();await modal.getByRole('button',{name:'Close photo editor'}).click();assert(await opener.evaluate(n=>n===document.activeElement));
   await opener.click();await modal.click({position:{x:2,y:2}});assert.equal(await modal.getAttribute('aria-hidden'),'true');
   await opener.click();await page.locator('#recordPhotoFile').setInputFiles(png);await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),save.click()]);assert.equal(page.url(),'http://photo.test/users/2/photo?tab=overview&year=2026');
   assert(submitted&&submitted.type.includes('multipart/form-data'));assert(submitted.url.includes('tab=overview&year=2026'));assert(submitted.body.includes('filename="replacement.png"'));assert(!submitted.body.includes('name="role"'));
   await page.goto('http://photo.test/validation',{waitUntil:'networkidle'});assert(await modal.isVisible());assert(await modal.getByRole('alert').isVisible(),'Validation automatically reopens editor with inline error');
   await page.goto('http://photo.test/no-photo',{waitUntil:'networkidle'});await page.locator('[data-open-photo]').click();assert.equal(await circle.locator('img').count(),0);assert.equal(await circle.textContent(),'HB');assert(await page.locator('#recordPhotoRemove').isHidden());
   await page.goto('http://photo.test/readonly',{waitUntil:'networkidle'});assert.equal(await page.locator('[data-open-photo]').count(),0);assert.equal(await page.locator('#userPhotoModal').count(),0);
   console.log(`PASS detail editor saved/replace/remove/cancel, payload, validation, permissions, focus and viewport ${width}/${theme}`);
  }
  assert.deepEqual(errors,[],'No browser JS exceptions');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

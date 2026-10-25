#!/usr/bin/env node
'use strict';
const {chromium}=require('playwright-core');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..'),dir=path.join(root,'.work/media-avatar');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_EXECUTABLE_PATH,headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 try{
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',route=>{
   const url=new URL(route.request().url()),file=path.resolve(root,'public','.'+decodeURIComponent(url.pathname));
   if(file.startsWith(root+'/public/')&&fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
   if(url.hostname==='media.test'){
    if(url.pathname==='/storage/avatars/example.png')return route.fulfill({path:path.join(dir,'image.png'),contentType:'image/png'});
    if(url.pathname.startsWith('/storage/'))return route.fulfill({status:404,body:'Not found'});
    if(/^\/users\/\d+\/data$/.test(url.pathname))return route.fulfill({path:path.join(dir,'edit.json'),contentType:'application/json'});
    if(url.pathname==='/search')return route.fulfill({body:'[]',contentType:'application/json'});
    const name=url.pathname.startsWith('/vendor-kyc')?'kyc':url.pathname==='/account'?'account':/^\/users\/\d+$/.test(url.pathname)?'record':'users';
    return route.fulfill({body:fs.readFileSync(path.join(dir,name+'.html'),'utf8').replaceAll('http://localhost','http://media.test'),contentType:'text/html'});
   }
   return route.abort();
  });
  for(const width of [1440,900,390])for(const theme of ['light','dark']){
   await page.setViewportSize({width,height:900});await page.goto('http://media.test/users',{waitUntil:'networkidle'});
   await page.evaluate(t=>document.documentElement.dataset.theme=t,theme);
   assert(await page.locator('[data-user-avatar-image]').count()>=5,'List and both shell menus use saved photo');
   assert(await page.locator('[data-user-avatar-image]').evaluateAll(nodes=>nodes.every(n=>n.naturalWidth>0&&!n.hidden)),'All saved photos decoded');
   const avatar=page.locator('.user-cell .user-avatar');
   assert(await avatar.evaluate(n=>getComputedStyle(n.querySelector('img')).objectFit==='cover'),'Cropped photo, not stretched');
   await page.getByRole('button',{name:'Actions for Hardik Bohra'}).click();await page.locator('[data-edit-user]').click();
   const modal=page.locator('#editModal'),circle=page.locator('#editAvatarCircle');
   await modal.waitFor({state:'visible'});await circle.scrollIntoViewIfNeeded();
   await page.waitForFunction(()=>document.querySelector('#editAvatarCircle').classList.contains('has-photo'));
   assert.equal(await circle.locator('[data-avatar-initials]').textContent(),'HB');
   const png={name:'replacement.png',mimeType:'image/png',buffer:fs.readFileSync(path.join(dir,'image.png'))};
   await page.locator('#editAvatarFile').setInputFiles(png);
   await page.waitForFunction(()=>document.querySelector('#editAvatarCircle img')?.src.startsWith('data:image/png;'));
   assert.equal(await page.locator('#editRemoveAvatar').inputValue(),'0');
   await page.locator('#editRemoveBtn').click();
   assert.equal(await circle.locator('img').count(),0);assert.equal(await circle.textContent(),'HB');
   assert.equal(await page.locator('#editRemoveAvatar').inputValue(),'1');
   await page.locator('#editAvatarFile').setInputFiles(png);
   await page.waitForFunction(()=>document.querySelector('#editAvatarCircle').classList.contains('has-photo'));
   assert.equal(await page.locator('#editRemoveAvatar').inputValue(),'0');
   await page.screenshot({path:path.join(dir,`edit-${width}-${theme}.png`)});
   await page.keyboard.press('Escape');
   await page.evaluate(()=>document.querySelectorAll('[data-user-avatar-image]').forEach(n=>n.src='/storage/missing.png'));
   await page.waitForFunction(()=>Array.from(document.querySelectorAll('[data-user-avatar-image]')).every(n=>n.hidden));
   assert(await page.locator('[data-user-avatar]').evaluateAll(nodes=>nodes.every(n=>!n.classList.contains('has-photo'))),'Failed images reveal initials everywhere');
   assert.equal(await avatar.locator('[data-avatar-initials]').textContent(),'HB');
   for(const [url,selector]of [['/users/1','.emp-avatar'],['/account','.account-head-avatar']]){
    await page.goto('http://media.test'+url,{waitUntil:'networkidle'});
    assert(await page.locator(selector+' img').evaluate(n=>n.naturalWidth>0&&!n.hidden),'Photo on '+url);
    await page.locator(selector+' img').evaluate(n=>n.src='/storage/missing.png');
    await page.waitForFunction(s=>document.querySelector(s+' img').hidden,selector);
    assert.equal(await page.locator(selector+' [data-avatar-initials]').textContent(),'HB');
   }
   await page.goto('http://media.test/users',{waitUntil:'networkidle'});
   await page.evaluate(()=>window.UserAvatars.render(document.querySelector('.user-cell .user-avatar'),'javascript:window.avatarXss=1','<img onerror=window.avatarXss=1>'));
   assert.equal(await avatar.locator('img').count(),0);assert.equal(await page.evaluate(()=>window.avatarXss),undefined,'No HTML or URL script injection');
   console.log(`PASS user photo, modal saved/replacement/remove, all-surface error fallback, injection: ${width}/${theme}`);
  }
  for(const width of [1440,900,390]){
   await page.setViewportSize({width,height:900});await page.goto('http://media.test/vendor-kyc/fixture',{waitUntil:'networkidle'});
   // Visit steps via the actual navigation, keeping fixture required inputs valid.
   await page.locator('#vendor_name').fill('Example vendor');await page.locator('#vendor_type').selectOption({index:1});
   await page.locator('[data-kyc-next]').click();
   await page.locator('#contact_person_name').fill('Example contact');await page.locator('#contact_person_email').fill('contact@example.test');await page.locator('#contact_person_mobile').fill('9999999999');
   await page.locator('[data-kyc-next]').click();
   const address=page.locator('#address');await address.fill('Line one\nLine two');await address.focus();
   const focus=await address.evaluate(n=>({shadow:getComputedStyle(n).boxShadow,border:getComputedStyle(n).borderColor}));assert.notEqual(focus.shadow,'none','Visible textarea focus');
   const base=await address.evaluate(n=>{const t=getComputedStyle(n),i=getComputedStyle(document.querySelector('#city'));return{font:t.fontFamily,fontInput:i.fontFamily,weight:t.fontWeight,radius:t.borderRadius,inputRadius:i.borderRadius,resize:t.resize,height:n.offsetHeight,width:n.offsetWidth,parent:n.parentElement.clientWidth}});
   assert.equal(base.font,base.fontInput);assert.equal(base.radius,base.inputRadius);assert.equal(base.weight,'500');assert.equal(base.resize,'vertical');assert(base.height>=96&&base.width<=base.parent,'Multiline size stays inside field');
   await address.fill('');await page.locator('[data-kyc-next]').click();assert.equal(await address.getAttribute('aria-invalid'),'true');
   assert(await address.evaluate(n=>n.closest('.kyc-field').classList.contains('has-error')));
   const errorBorder=await address.evaluate(n=>getComputedStyle(n).borderColor);assert.notEqual(errorBorder,focus.border,'Error border is not focus blue');
   await address.fill('Line one\nLine two');await page.locator('#city').fill('Ahmedabad');await page.locator('#country').fill('India');await page.locator('[data-kyc-next]').click();
   for(const id of ['bank_address','beneficiary_address']){
    const field=page.locator('#'+id);await field.fill('First line\nSecond line');
    assert.equal(await field.inputValue(),'First line\nSecond line');assert.equal(await field.evaluate(n=>getComputedStyle(n).resize),'vertical');
    assert.equal(await field.getAttribute('maxlength'),'3000');
   }
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No KYC overflow');
   await page.locator('#bank_address').focus();await page.screenshot({path:path.join(dir,`kyc-${width}.png`),fullPage:true});
   console.log(`PASS KYC textarea base, focus/error, multiline values, geometry: ${width}`);
  }
  assert.deepEqual(errors,[],'No browser JS errors');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

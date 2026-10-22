#!/usr/bin/env node
const assert = require('assert/strict');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const read = name => fs.readFileSync(path.resolve(__dirname, '../..', name), 'utf8');
const shell = read('resources/views/packaging_lists/label.blade.php');
const show = read('resources/views/packaging_lists/show.blade.php');
const controller = read('app/Http/Controllers/PackagingListController.php');
const service = read('app/Services/PackagingListLabels.php');
const script = read('public/assets/js/packaging-list-label-print.js');
let passed = 0, failed = 0;
async function check(name, callback) {
  try { await callback(); console.log(`PASS ${name}`); passed++; }
  catch (error) { console.error(`FAIL ${name}: ${error.message}`); failed++; }
}
const flush = () => new Promise(resolve => setImmediate(resolve));
function setup({ auto = true, ready = 'complete', qr = true, fonts, logos = [] } = {}) {
  const button = { disabled: false, addEventListener(event, callback) { this.click = callback; } };
  const error = { hidden: true, textContent: '' }, events = {};
  let prints = 0;
  const document = {
    readyState: ready, body: { dataset: { autoPrint: String(auto) } },
    fonts: { ready: fonts || Promise.resolve() },
    querySelector: selector => selector === '[data-print-labels]' ? button : error,
    querySelectorAll: selector => selector === '.label-brand-logo' ? logos : [{ querySelector: () => qr ? {} : null, classList: { contains: () => !qr } }],
    addEventListener: (event, callback) => { events[event] = callback; },
  };
  vm.runInNewContext(script, { document, window: { print() { prints++; } }, requestAnimationFrame: callback => callback() });
  return { button, error, events, get prints() { return prints; } };
}
(async () => {
  await check('one CSRF-protected batch button offers all three page types', () => {
    assert(show.includes("route('packaging-lists.labels', $list)"));
    assert(show.includes('Print / download all labels'));
    const form = show.slice(show.indexOf('class="pl-label-batch"'), show.indexOf('<div class="pl-label-list">'));
    assert(form.includes('@csrf'));
    for (const type of ['a4', 'a5', 'thermal']) assert(form.includes(`value="${type}"`));
    assert(read('routes/web.php').includes("Route::post('/packaging-lists/{packagingList}/labels'"));
  });
  await check('all groups and all physical cartons use the shared label partial in order', () => {
    assert(shell.includes('@foreach ($labelBatches as $batch)'));
    assert(shell.includes("$copy < $batch['copies']"));
    assert(shell.includes("$batch['startCartonNumber'] + $copy"));
    assert(shell.includes("@include('packaging_lists.partials.carton-label'"));
    assert(service.includes('$next += $copies;'));
    assert(service.includes("$carton->setRelation('packagingList', $list)"));
    assert(service.includes("loadMissing('cartons.items')"));
    assert(service.includes('MAX_BATCH_LABELS'));
    assert(service.includes("if ($batches === [])"));
  });
  await check('a validated batch is audited atomically, once per group with actual copies', () => {
    const method = controller.slice(controller.indexOf('public function labels('), controller.indexOf('public function label('));
    assert(method.includes("Rule::in(['a4','a5','thermal'])"));
    assert(method.indexOf('$labels->batches') < method.indexOf('DB::transaction'));
    assert(method.includes("'copies' => $batch['copies']"));
    assert(method.includes("'printed_by' => Auth::id()") && method.includes("'printed_at' => now()"));
    assert(method.includes("$packagingList->total_cartons = $copies"));
    assert(!method.includes('->save()') && !method.includes('syncCartons'));
  });
  await check('the final label does not force a trailing blank page', () => {
    assert(read('public/assets/css/packaging-list-label.css').includes('.label-page .carton-label:last-of-type{page-break-after:auto;break-after:auto}'));
    assert(shell.includes('Save as PDF') && shell.includes('One label per page'));
  });
  await check('batch auto-print waits for fonts and then opens one print dialog', async () => {
    let resolveFonts;
    const fonts = new Promise(resolve => { resolveFonts = resolve; });
    const run = setup({ fonts });
    assert.equal(run.prints, 0);
    resolveFonts(); await flush();
    assert.equal(run.prints, 1);
    assert.equal(run.button.disabled, false);
    assert.equal(run.error.hidden, true);
  });
  await check('auto-print waits for the sender logo to decode', async () => {
    let resolveLogo;
    const decoded = new Promise(resolve => { resolveLogo = resolve; });
    const run = setup({ logos: [{ naturalWidth: 240, decode: () => decoded }] });
    await flush(); assert.equal(run.prints, 0);
    resolveLogo(); await flush();
    assert.equal(run.prints, 1);
    assert.equal(run.error.hidden, true);
  });
  await check('a missing sender logo blocks printing instead of producing an unbranded label', async () => {
    const run = setup({ logos: [{ naturalWidth: 0, decode: () => Promise.reject(new Error('not found')) }] });
    await flush();
    assert.equal(run.prints, 0);
    assert.equal(run.error.hidden, false);
    assert(run.error.textContent.includes('sender logo'));
    assert.equal(run.button.disabled, false);
  });
  await check('deferred scripts wait for DOMContentLoaded and QR rendering', async () => {
    const run = setup({ ready: 'interactive' });
    assert.equal(run.prints, 0);
    assert.equal(typeof run.events.DOMContentLoaded, 'function');
    await run.events.DOMContentLoaded();
    assert.equal(run.prints, 1);
  });
  await check('failed QR preparation blocks printing with a visible error', async () => {
    const run = setup({ qr: false });
    await flush();
    assert.equal(run.prints, 0);
    assert.equal(run.error.hidden, false);
    assert(run.error.textContent.includes('QR codes'));
    assert.equal(run.button.disabled, false);
  });
  await check('single-group output stays manual and the print button supports retries', async () => {
    const run = setup({ auto: false });
    await flush(); assert.equal(run.prints, 0);
    await run.button.click(); assert.equal(run.prints, 1);
    await run.button.click(); assert.equal(run.prints, 2);
  });
  console.log(`\nPackaging label batch: ${passed} passed, ${failed} failed`);
  process.exitCode = failed ? 1 : 0;
})();

#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert/strict');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
let passed = 0, failed = 0;
const check = (label, run) => {
  try { run(); console.log(`PASS ${label}`); passed++; }
  catch (error) { console.error(`FAIL ${label}: ${error.message}`); failed++; }
};
const script = read('public/assets/js/packaging-list-print.js');
const view = read('resources/views/packaging_lists/print.blade.php');
const css = read('public/assets/css/packaging-list-print-settings.css');
const controller = read('app/Http/Controllers/PackagingListController.php');
const edit = read('resources/views/packaging_lists/form.blade.php');

check('orientation, running text and page-number controls exist only on the print page', () => {
  for (const field of ['orientation', 'header_text', 'footer_text', 'show_page_numbers']) {
    assert(view.includes(`name="${field}"`));
    assert(!edit.includes(`name="${field}"`));
  }
  assert(view.includes('@csrf') && view.includes("@method('PATCH')"));
  assert(view.includes('name="show_page_numbers" value="0"'));
});
check('print settings use a bounded presentation-only PATCH endpoint', () => {
  assert(read('routes/web.php').includes("Route::patch('/packaging-lists/{packagingList}/print-settings'"));
  const method = controller.slice(controller.indexOf('public function updatePrintSettings'), controller.indexOf('public function duplicate'));
  assert(method.includes("Rule::in(['portrait', 'landscape'])"));
  assert(method.includes("'max:160'") && method.includes("'max:240'"));
  assert(method.includes("update(['print_settings' => $settings])"));
  assert(!method.includes('syncCartons') && !method.includes('reconcile') && !method.includes('assertPackingEditable'));
  assert(read('app/Services/PermissionMatrix.php').includes("['shipments.*','packaging-lists.*']"));
});
check('the final page rule follows shared styles, with real page counters and safe server text', () => {
  assert(view.indexOf('id="plPageStyle"') > view.indexOf('packaging-list-print-settings.css'));
  assert(view.indexOf('id="plPageStyle"') < view.indexOf('</head>'));
  assert(view.includes("size: A4 {{ $printSettings['orientation'] }}"));
  assert(view.includes('@top-center') && view.includes('@bottom-left') && view.includes('@bottom-right'));
  assert(view.includes('counter(page)') && view.includes('counter(pages)'));
  assert(view.includes("PrintCss::text($printSettings['header_text'])") && view.includes("PrintCss::text($printSettings['footer_text'])"));
  assert(css.includes('--pdf-sheet-width: 297mm') && css.includes('--pdf-sheet-height: 210mm'));
  assert(css.includes('.pl-footer { display: none !important; }'));
  assert(view.includes('recent Chrome or Edge'));
});

const handlers = {}, windowHandlers = {};
const orientation = { value: 'portrait' }, header = { value: '' }, footer = { value: 'Original footer' }, numbers = { checked: false };
const pageStyle = { textContent: '' }, previewHeader = {}, previewFooter = {}, previewNumbers = {}, status = {};
let prints = 0, valid = true;
const form = {
  querySelector(selector) {
    return ({ '[name="orientation"]': orientation, '[name="header_text"]': header,
      '[name="footer_text"]': footer, 'input[type="checkbox"][name="show_page_numbers"]': numbers,
      '[data-print-settings-status]': status })[selector];
  },
  addEventListener(event, handler) { handlers[event] = handler; },
  reportValidity() { return valid; },
};
const printButton = { addEventListener(event, handler) { handlers.print = handler; } };
const document = {
  body: { dataset: {} },
  getElementById() { return pageStyle; },
  querySelector(selector) {
    return ({ '[data-print-settings]': form, '[data-preview-header]': previewHeader,
      '[data-preview-footer-text]': previewFooter, '[data-preview-page-number]': previewNumbers,
      '[data-print-document]': printButton })[selector];
  },
};
const window = { addEventListener(event, handler) { windowHandlers[event] = handler; }, print() { prints++; } };
check('initialization applies portrait and hides disabled page numbers', () => {
  vm.runInNewContext(script, { document, window });
  assert(pageStyle.textContent.includes('size:A4 portrait'));
  assert(pageStyle.textContent.includes('content:none'));
  assert.equal(previewHeader.hidden, true);
  assert.equal(previewNumbers.hidden, true);
});
check('orientation and number toggle update paper and preview without navigating', () => {
  orientation.value = 'landscape'; numbers.checked = true;
  handlers.change();
  assert.equal(document.body.dataset.orientation, 'landscape');
  assert(pageStyle.textContent.includes('size:A4 landscape'));
  assert(pageStyle.textContent.includes('counter(page) " of " counter(pages)'));
  assert.equal(previewNumbers.hidden, false);
  assert(status.textContent.includes('Save settings'));
});
check('arbitrary Unicode and hostile text stay text, never CSS rules or HTML', () => {
  header.value = '";} </style><script>alert(1)</script>\nગુજરાત ✓ 📦';
  footer.value = 'Footer\\new\nline';
  handlers.input();
  assert.equal(previewHeader.textContent, header.value);
  assert.equal(previewFooter.textContent, footer.value);
  assert(!pageStyle.textContent.includes('<script>'));
  assert(!pageStyle.textContent.includes('</style>'));
  const match = pageStyle.textContent.match(/@top-center \{ content:"([^"]*)"/);
  const decoded = match[1].replace(/\\([0-9a-f]+) /g, (_, code) => String.fromCodePoint(parseInt(code, 16)));
  assert.equal(decoded, header.value);
});
check('clearing header/footer and switching numbers off updates the printed output', () => {
  header.value = ''; footer.value = ''; numbers.checked = false;
  handlers.input();
  assert.equal(previewHeader.hidden, true);
  assert.equal(previewFooter.textContent, '');
  assert.equal(previewNumbers.hidden, true);
  assert(pageStyle.textContent.includes('@top-center { content:""'));
  assert(pageStyle.textContent.includes('@bottom-left { content:""'));
  assert(pageStyle.textContent.includes('@bottom-right { content:none'));
});
check('print button and Ctrl+P use the current orientation and text', () => {
  orientation.value = 'portrait'; header.value = 'Current header';
  windowHandlers.beforeprint();
  assert(pageStyle.textContent.includes('size:A4 portrait'));
  assert.equal(previewHeader.textContent, 'Current header');
  valid = false; handlers.print(); assert.equal(prints, 0);
  valid = true; handlers.print(); assert.equal(prints, 1);
});
check('unrecognized orientation cannot inject CSS', () => {
  orientation.value = 'landscape; } body { display:none';
  handlers.change();
  assert(pageStyle.textContent.includes('size:A4 portrait'));
  assert(!pageStyle.textContent.includes('display:none'));
});
console.log(`\nPackaging print settings: ${passed} passed, ${failed} failed`);
process.exitCode = failed ? 1 : 0;

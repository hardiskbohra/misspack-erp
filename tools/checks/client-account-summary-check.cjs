/* Read-only client selection summary: dependency-free async regression checks.
 * Run: node tools/checks/client-account-summary-check.cjs */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
class Element {
    constructor() { this.value = ''; this.hidden = false; this.attrs = {}; this.events = {}; this.textContent = ''; this.innerHTML = ''; }
    setAttribute(key, value) { this.attrs[key] = value; }
    removeAttribute(key) { delete this.attrs[key]; }
    addEventListener(key, handler) { this.events[key] = handler; }
    replaceChildren() { this.innerHTML = ''; }
}
async function run(enhanced) {
    const select = new Element(), panel = new Element(), content = new Element();
    const status = new Element(), message = new Element(), retry = new Element();
    panel.dataset = { summaryUrl: '/clients/__CLIENT__/payment-summary' };
    panel.querySelector = selector => ({ '[data-client-account-content]': content,
        '[data-client-account-status]': status, '[data-client-account-message]': message,
        '[data-client-account-retry]': retry })[selector];
    const pending = [];
    let change;
    const window = enhanced ? { jQuery: () => ({ on: (name, handler) => { change = handler; } }) } : {};
    vm.runInNewContext(fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/invoice-client-account.js'), 'utf8'), {
        window, document: { readyState: 'complete', getElementById: () => select, querySelector: () => panel },
        AbortController, fetch: (url, options) => new Promise(resolve => pending.push({ url, options, resolve })),
    });
    change ??= select.events.change;
    const tick = () => new Promise(resolve => setImmediate(resolve));
    const pick = id => { select.value = id; return change(); };
    const reply = (index, id, html, overrides = {}) => pending[index].resolve({ ok: true, redirected: false, json: async () => ({ client_id: id, html }), ...overrides });
    assert.equal(panel.hidden, true);
    assert.equal(pending.length, 0);
    pick('1');
    assert.equal(panel.hidden, false);
    assert.equal(panel.attrs['aria-busy'], 'true');
    assert.equal(pending[0].url, '/clients/1/payment-summary');
    assert.equal(pending[0].options.cache, 'no-store');
    reply(0, 1, 'First client'); await tick();
    assert.equal(content.innerHTML, 'First client');
    assert.equal(status.hidden, true);
    pick('2');
    assert.equal(content.innerHTML, '', 'Previous client amounts disappear immediately.');
    pick('3');
    assert.equal(pending[1].options.signal.aborted, true);
    reply(2, 3, 'Third client'); await tick();
    reply(1, 2, 'Late second client'); await tick();
    assert.equal(content.innerHTML, 'Third client', 'Even a server ignoring abort cannot overwrite the current client.');
    pick('4'); reply(3, 4, '', { ok: false }); await tick();
    assert.equal(content.innerHTML, '');
    assert.equal(retry.hidden, false);
    assert.match(message.textContent, /unavailable/);
    retry.events.click(); reply(4, 4, 'Recovered client'); await tick();
    assert.equal(content.innerHTML, 'Recovered client');
    assert.equal(status.hidden, true);
    pick('5'); pick(''); reply(5, 5, 'Late fifth client'); await tick();
    assert.equal(panel.hidden, true);
    assert.equal(content.innerHTML, '');
    pick('6'); reply(6, 7, 'Wrong client'); await tick();
    assert.equal(content.innerHTML, '', 'A mismatched client identity must not display any figures.');
    assert.equal(retry.hidden, false);
    assert.equal(panel.attrs['aria-busy'], undefined);
    console.log('PASS '+(enhanced ? 'Select2/jQuery' : 'native select')+': selection, clear, loading, identity, fresh requests, races, failure and retry');
}
(async () => { await run(true); await run(false); })().catch(error => { console.error(error); process.exitCode = 1; });

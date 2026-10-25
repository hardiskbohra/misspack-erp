/* Reporting-period field visibility, including Select2's jQuery-only events.
 * Run: node tools/checks/dashboard-period-controls-check.cjs */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
let checks = 0;
function test(module, enhanced, loading) {
    const prefix = module ? 'module-period-' : 'period-';
    const type = { value: 'year', events: {}, addEventListener(name, fn) { this.events[name] = fn; } };
    const definitions = module ? [['month'], ['quarter'], ['half']] :
        [['range'], ['year', 'month', 'quarter', 'half'], ['month'], ['quarter'], ['half'], ['multi_year'], ['multi_year']];
    const fields = definitions.map(types => ({ types, hidden: false, style: {}, value: 'saved choice',
        classList: { contains: name => types.some(t => prefix + t === name) } }));
    const ready = [];
    const document = {
        readyState: loading ? 'loading' : 'complete', documentElement: {},
        addEventListener(name, fn) { if (name === 'DOMContentLoaded') ready.push(fn); },
        getElementById(id) {
            if (id === (module ? 'modulePeriodType' : 'periodType')) return type;
            if (id === 'dashboardRoot') return { getAttribute: () => '{}' };
            return null;
        },
        querySelectorAll(selector) { return selector === (module ? '.module-period-control' : '.period-control') ? fields : []; },
    };
    const window = { addEventListener() {}, setTimeout() {}, location: { hash: '' } };
    if (enhanced) window.jQuery = () => ({ on: (name, fn) => { type.jqueryChange = fn; } });
    vm.runInNewContext(fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/' + (module ? 'module-dashboard.js' : 'dashboard.js')), 'utf8'), {
        document, window, MutationObserver: class { observe() {} },
    });
    ready.forEach(fn => fn());
    function expect() {
        fields.forEach(field => {
            const visible = field.types.includes(type.value);
            assert.equal(field.hidden, !visible);
            assert.equal(field.style.display, visible ? 'flex' : 'none');
            assert.equal(field.value, 'saved choice', 'Changing View must not reset saved input values.');
            checks += 3;
        });
    }
    expect();
    const change = enhanced ? type.jqueryChange : type.events.change;
    assert.equal(typeof change, 'function', 'Bind the event type emitted by the select control.'); checks++;
    for (const value of module ? ['month', 'quarter', 'half', 'year', 'month'] : ['month', 'quarter', 'half', 'year', 'multi_year', 'range', 'month']) {
        type.value = value;
        change();
        expect();
    }
    console.log('PASS ' + (module ? 'module dashboard' : 'overview dashboard') + ' / ' + (enhanced ? 'Select2' : 'native') + ' / ' + (loading ? 'DOMContentLoaded' : 'ready') + ': instant fields and preserved choices');
}
for (const module of [false, true]) for (const enhanced of [false, true]) for (const loading of [false, true]) test(module, enhanced, loading);
console.log(checks + ' assertions PASS');

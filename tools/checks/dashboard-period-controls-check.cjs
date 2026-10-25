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
    const definitions = module ? [['year', 'month', 'quarter', 'half'], ['month'], ['quarter'], ['half'], ['custom'], ['custom']] :
        [['range'], ['year', 'month', 'quarter', 'half'], ['month'], ['quarter'], ['half'], ['multi_year'], ['multi_year'], ['custom'], ['custom']];
    const fromDate = {value: '2026-10-01', events: {}, addEventListener(name, fn) { this.events[name] = fn; }};
    const toDate = {value: '2026-10-31'};
    let dateIndex = 0;
    const fields = definitions.map(types => ({ types, hidden: false, style: {}, value: 'saved choice',
        classList: { contains: name => types.some(t => prefix + t === name) },
        inputs: types.includes('custom') ? [dateIndex++ === 0 ? fromDate : toDate] : [],
        querySelectorAll() { return this.inputs; } }));
    const ready = [];
    const document = {
        readyState: loading ? 'loading' : 'complete', documentElement: {},
        addEventListener(name, fn) { if (name === 'DOMContentLoaded') ready.push(fn); },
        getElementById(id) {
            if (id === (module ? 'modulePeriodType' : 'periodType')) return type;
            if (id === (module ? 'modulePeriodStartDate' : 'periodStartDate')) return fromDate;
            if (id === (module ? 'modulePeriodEndDate' : 'periodEndDate')) return toDate;
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
            field.inputs.forEach(input => {
                assert.equal(input.disabled, !visible, 'Hidden dates must not be submitted or block Apply.');
                assert.equal(input.required, visible, 'Both dates are required in Custom mode.');
                checks += 2;
            });
        });
    }
    expect();
    const change = enhanced ? type.jqueryChange : type.events.change;
    assert.equal(typeof change, 'function', 'Bind the event type emitted by the select control.'); checks++;
    for (const value of module ? ['month', 'quarter', 'half', 'year', 'custom', 'month'] : ['month', 'quarter', 'half', 'year', 'multi_year', 'range', 'custom', 'month']) {
        type.value = value;
        change();
        expect();
    }
    assert.equal(toDate.min, '2026-10-01');
    fromDate.value = '2026-10-10';
    fromDate.events.input();
    assert.equal(toDate.min, '2026-10-10', 'To date cannot precede the chosen From date.');
    assert.equal(toDate.value, '2026-10-31', 'Updating a date bound must preserve the date choice.');
    checks += 3;
    console.log('PASS ' + (module ? 'module dashboard' : 'overview dashboard') + ' / ' + (enhanced ? 'Select2' : 'native') + ' / ' + (loading ? 'DOMContentLoaded' : 'ready') + ': instant fields and preserved choices');
}
for (const module of [false, true]) for (const enhanced of [false, true]) for (const loading of [false, true]) test(module, enhanced, loading);
console.log(checks + ' assertions PASS');

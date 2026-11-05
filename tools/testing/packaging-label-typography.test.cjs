'use strict';
// Portable source-level guard. The companion Chromium/PDF regression checks
// the actual CSS cascade and rendering; this runs without browser dependencies.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const css = fs.readFileSync(path.join(root, 'public/assets/css/packaging-list-label.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
const blade = fs.readFileSync(path.join(root, 'resources/views/packaging_lists/label.blade.php'), 'utf8');
const blocks = [...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)];

function declaration(selector, property) {
  let value;
  for (const [, selectors, body] of blocks) {
    if (!selectors.split(',').map(s => s.trim()).includes(selector)) continue;
    for (const raw of body.split(';')) {
      const colon = raw.indexOf(':');
      if (colon >= 0 && raw.slice(0, colon).trim() === property) value = raw.slice(colon + 1).trim();
    }
  }
  assert.notEqual(value, undefined, `${selector} must declare ${property}`);
  return value;
}

function points(expression, parentPt) {
  const source = expression.replace(/^calc\((.*)\)$/, '$1').replace(/\s+/g, '');
  const terms = source.match(/[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:em|pt)/g) || [];
  assert.equal(terms.join(''), source, `Unexpected font-size expression ${expression}`);
  return terms.reduce((sum, term) => sum + parseFloat(term) * (term.endsWith('em') ? parentPt : 1), 0);
}

function size(selector, parentPt) {
  return points(declaration(selector, 'font-size'), parentPt);
}

test('both thermal formats add exactly one point to the previous text sizes', () => {
  const previousBase = 5.6;
  const base = size('.format-thermal .carton-label', 0);
  assert.equal(base, previousBase + 1);
  const samples = [
    ['.format-thermal .carton-label header p', .72 * previousBase],
    ['.format-thermal .carton-label h1', 1.35 * previousBase],
    ['.format-thermal .label-qr small', .68 * previousBase],
    ['.format-thermal .carton-label span.label-key', .72 * previousBase],
    ['.format-thermal .label-identity strong', 1.15 * previousBase],
    ['.format-thermal .carton-label .label-parties span.label-key', .72 * previousBase + 2],
    ['.format-thermal .label-parties strong', 1.05 * previousBase + 2],
    ['.format-thermal .label-parties p', .8 * previousBase + 2],
    ['.format-thermal .carton-label th', .82 * previousBase],
    // The former UA small size in Chromium is 1/1.2 of the parent.
    ['.format-thermal .carton-label td small', previousBase / 1.2],
    ['.format-thermal .label-physical strong', 1.15 * previousBase],
    ['.format-thermal .label-handling-symbol strong', .74 * previousBase],
  ];
  for (const [selector, previous] of samples) {
    const actual = size(selector, base);
    assert(Math.abs(actual - (previous + 1)) < .0001, `${selector}: ${actual} != ${previous} + 1 pt`);
  }
  assert(blade.includes("$format === 'thermal150' ? ' format-thermal' : ''"), '150 mm labels must share the thermal typography');
});

test('thermal geometry and non-thermal typography remain unchanged', () => {
  const thermal = '.format-thermal .carton-label';
  assert.equal(declaration(thermal, 'width'), '100mm');
  assert.equal(declaration(thermal, 'height'), '140mm');
  assert.equal(declaration(thermal, 'padding'), '3.5mm');
  assert.equal(declaration('.format-thermal150 .carton-label', 'height'), '150mm');
  assert.equal(size('.format-a4 .carton-label', 0), 12);
  assert.equal(size('.format-a5 .carton-label', 0), 11);
  assert.equal(declaration('.label-parties span.label-key', 'font-size'), 'calc(.72em + 2pt)');
  assert.equal(declaration('.label-parties strong', 'font-size'), 'calc(1.05em + 2pt)');
  assert.equal(declaration('.label-parties p', 'font-size'), 'calc(.88em + 2pt)');
  assert.equal(declaration('.format-a5 .label-parties p', 'font-size'), 'calc(.84em + 2pt)');
});

'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const directory = __dirname;
const source = fs.readFileSync(path.join(directory,'catalogue.cjs'),'utf8');
const cataloguePath = path.resolve(directory,'../../docs/testing/catalogue.json');
const base = JSON.parse(fs.readFileSync(cataloguePath,'utf8'));
function run(change, staleFile) {
  const data = structuredClone(base); change(data);
  const fakeFs = {...fs, readFileSync(file,...args) {
    if (path.resolve(file) === cataloguePath) return JSON.stringify(data);
    if (staleFile && String(file).endsWith(staleFile)) return 'stale export';
    return fs.readFileSync(file,...args);
  }};
  vm.runInNewContext(source, {__dirname:directory, require(name) {return name==='node:fs'?fakeFs:require(name);},
    process:{argv:['node','catalogue.cjs','--check']}, console:{log(){}}});
}
run(()=>{});
const mutations = [
 ['duplicate case',d=>d.cases.push(structuredClone(d.cases[0]))],
 ['duplicate module',d=>d.modules.push(structuredClone(d.modules[0]))],
 ['unknown module',d=>d.cases[0].module='MISSING'],
 ['missing expectation',d=>d.cases[0].expected=''],
 ['empty steps',d=>d.cases[0].steps=[]],
 ['bad priority',d=>d.cases[0].priority='P9'],
 ['missing evidence method',d=>d.cases[0].automation=['tests/Unit/PermissionMatrixTest.php#test_nonexistent_method']],
 ['missing source',d=>d.modules[0].sources=['app/Services/Nonexistent.php']],
];
for (const [name,change] of mutations) {
 assert.throws(()=>run(change),undefined,name+' must be rejected');
}
assert.throws(()=>run(()=>{},'unit-test-cases.csv'),undefined,'stale CSV must be rejected');
console.log('PASS catalogue tooling: valid pack plus 9 invalid/stale variants');

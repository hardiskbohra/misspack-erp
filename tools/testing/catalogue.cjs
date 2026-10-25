/* Validate method-level traceability and deterministically render QA hand-off files. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const data = JSON.parse(read('docs/testing/catalogue.json'));
const check = process.argv.includes('--check');
const ids = new Set(), modules = new Set(data.modules.map(m => m.id));
assert.equal(modules.size, data.modules.length, 'Duplicate module IDs');
for (const module of data.modules) {
  assert(module.name && module.fixture && module.sources.length);
  module.sources.forEach(source => assert(fs.existsSync(path.join(root, source)), 'Missing source '+source));
  for (const level of ['unit','integration']) assert(data.cases.some(c => c.module === module.id && c.level === level), module.id+' lacks '+level+' cases');
}
for (const c of data.cases) {
  assert(!ids.has(c.id), 'Duplicate case ID '+c.id); ids.add(c.id);
  assert(modules.has(c.module), 'Unknown module '+c.module);
  assert(['unit','integration'].includes(c.level));
  assert(c.id.startsWith(c.level === 'unit' ? 'UT-' : 'IT-'));
  assert(['P0','P1','P2'].includes(c.priority));
  for (const key of ['title','preconditions','test_data','expected','requirement']) assert(typeof c[key] === 'string' && c[key].trim(), c.id+' missing '+key);
  assert(c.steps.length && c.steps.every(s => typeof s === 'string' && s.trim()));
  for (const ref of c.automation) {
    const [file,method] = ref.split('#');
    assert(file.startsWith('tests/') && file.endsWith('Test.php') && /^test_\w+$/.test(method), 'Bad evidence '+ref);
    assert(read(file).includes('function '+method+'('), c.id+' points to a nonexistent test: '+ref);
  }
}
const outputs = new Map();
const esc = s => String(s).replace(/\|/g,'\\|').replace(/\n/g,' ');
const csv = s => '"'+String(s).replace(/"/g,'""')+'"';
for (const level of ['unit','integration']) {
  const cases = data.cases.filter(c => c.level === level);
  let md = '# '+(level==='unit'?'Unit':'Integration')+' test cases\n\nGenerated from [catalogue.json](catalogue.json); edit the JSON and run `npm run test:catalogue:build`.\n\n';
  md += '**Implementation links are not a passing result.** Unlinked cases are planned. Partial evidence does not cover every step. Unit targets linked only to component evidence remain unclaimed as isolated unit coverage.\n\n';
  for (const module of data.modules) {
    md += '## '+module.name+' (`'+module.id+'`)\n\nSources: '+module.sources.map(s=>'`'+s+'`').join(', ')+'.\n\n';
    for (const c of cases.filter(c => c.module === module.id)) {
      md += '### '+c.id+' — '+c.title+'\n\n';
      md += '**Priority:** '+c.priority+' · **Requirement:** '+c.requirement+' · **Implementation:** '+(c.automation.length?(c.evidence_scope==='partial'?'partial evidence':'linked executable evidence'):'planned')+'\n\n';
      md += '- **Preconditions:** '+c.preconditions+'\n- **Data:** '+c.test_data+'\n';
      md += c.steps.map((s,i)=>(i+1)+'. '+s).join('\n')+'\n\n**Expected:** '+c.expected+'\n\n';
      if (c.notes) md += '**Note:** '+c.notes+'\n\n';
      if (c.automation.length) md += '**Evidence:** '+c.automation.map(a=>'`'+a+'`').join('; ')+'\n\n';
    }
  }
  outputs.set('docs/testing/'+level+'-test-cases.md', md.trimEnd()+'\n');
  const columns=['id','module','level','priority','requirement','title','preconditions','test_data','steps','expected','implementation','automation'];
  outputs.set('docs/testing/'+level+'-test-cases.csv', '\uFEFF'+[columns.map(csv).join(','),...cases.map(c=>columns.map(k=>csv(k==='steps'?c.steps.join('; '):k==='implementation'?(c.automation.length?(c.evidence_scope==='partial'?'partial evidence':'linked evidence'):'planned'):k==='automation'?c.automation.join('; '):c[k])).join(','))].join('\n')+'\n');
}
let trace = '# Requirements and implementation traceability\n\nOne row is a test design requirement, **not a measured coverage percentage**. A linked case may still be failing or not executed; consult [execution-baseline.md](execution-baseline.md).\n\n| Requirement | Domain | Unit cases | Integration cases | Cases with executable links |\n|---|---|---:|---:|---:|\n';
for (const module of data.modules) {
 const cases=data.cases.filter(c=>c.module===module.id);
 trace += '| REQ-'+module.id+' | '+esc(module.name)+' | '+cases.filter(c=>c.level==='unit').length+' | '+cases.filter(c=>c.level==='integration').length+' | '+cases.filter(c=>c.automation.length).length+' |\n';
}
outputs.set('docs/testing/traceability.md',trace);
const walk = dir => fs.readdirSync(path.join(root,dir),{withFileTypes:true}).flatMap(e=>e.isDirectory()?walk(dir+'/'+e.name):[dir+'/'+e.name]).sort();
const files = walk('tests').filter(p=>p.endsWith('Test.php'));
const inventory = {
  note:'Source inventory only. Method counts exclude data-provider expansion and are not runtime coverage or pass counts.',
  test_files:files.map(file=>({file,methods:[...read(file).matchAll(/public function (test_\w+)\(/g)].map(m=>m[1])})),
  controllers:walk('app/Http/Controllers').filter(p=>p.endsWith('.php')),
  services:walk('app/Services').filter(p=>p.endsWith('.php')),
  models:walk('app/Models').filter(p=>p.endsWith('.php')),
  source_checks:walk('tools/checks').filter(p=>p.endsWith('.cjs')),
};
outputs.set('docs/testing/source-inventory.json',JSON.stringify(inventory,null,2)+'\n');
for (const [file,content] of outputs) {
 if (check) assert.equal(read(file),content,'Stale generated file '+file+'; run npm run test:catalogue:build');
 else fs.writeFileSync(path.join(root,file),content);
}
console.log(`PASS ${data.modules.length} domains; ${data.cases.length} test-case designs; ${data.cases.filter(c=>c.automation.length).length} method-linked cases; ${files.length} executable test files inventoried. No runtime coverage claim.`);

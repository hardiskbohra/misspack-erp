'use strict';
const fs=require('fs'),path=require('path');
const root=path.resolve(__dirname,'../..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const shell=read('resources/views/layouts/app.blade.php');
const css=read('public/assets/css/app-layout.css');
const menu=shell.slice(shell.indexOf("/* Office navigation follows"),shell.indexOf("if (Auth::user() && Auth::user()->isAdmin()"));
let passed=0,failed=0;
function check(label,value){if(value){passed++;console.log(`PASS ${label}`)}else{failed++;console.error(`FAIL ${label}`)}}
const ordered=['Analytics','Project execution','Sales & catalogue','Procurement','Finance','Administration'];
check('sidebar groups follow the office work sequence',ordered.every((label,index)=>menu.indexOf(`'section' => '${label}'`)>-1&&(index===0||menu.indexOf(`'section' => '${ordered[index-1]}'`)<menu.indexOf(`'section' => '${label}'`))));
check('orientation starts with the canonical module directory, not retired executive reporting',
    !menu.includes("'route' => 'dashboard'") && menu.indexOf("'route' => 'dashboards.index'") >= 0
    && menu.indexOf("'route' => 'dashboards.index'") < menu.indexOf("'section' => 'Project execution'"));
check('daily execution keeps queue projects tasks and exceptions together',['projects.work-queue.index','projects.index','tasks.index','projects.exceptions.index'].every(route=>menu.includes(`'route' => '${route}'`))&&menu.indexOf("'route' => 'projects.work-queue.index'")<menu.indexOf("'route' => 'projects.index'"));
check('fulfilment follows projects in the execution group',menu.indexOf("'route' => 'packaging-lists.index'")>menu.indexOf("'route' => 'projects.index'")&&menu.indexOf("'route' => 'shipments.index'")>menu.indexOf("'route' => 'packaging-lists.index'"));
check('customer catalogue procurement finance and governance are separated',['clients.index','products.index','vendors.index','purchase-invoices.index','cashflows.index','audit.index','settings.index'].every(route=>menu.includes(`'route' => '${route}'`)));
check('recurring payments and document archive use clear sentence-case labels',menu.includes("'label' => 'Recurring payments'")&&menu.includes("'label' => 'Document archive'")&&!menu.includes("'label' => 'Document Archive'"));
check('office notes stays in Quick Menu rather than duplicating topbar/sidebar controls', !menu.includes("'route' => 'notes.index'") && read('resources/views/layouts/partials/quick-menu.blade.php').includes("route('notes.index')") && !shell.includes("class=\"topbar-btn\" href=\"{{ route('notes.index') }}\""));
check('permission filtering removes orphan section headings',shell.includes('$pendingSidebarSection')&&shell.includes('$visibleSidebarItems[] = $pendingSidebarSection'));
check('the current destination is exposed to assistive technology',shell.includes('aria-current="page"'));
check('sidebar density keeps destinations reachable without tiny controls',css.includes('min-height: 40px')&&css.includes('padding: 8px 14px')&&css.includes('.sidebar-section:first-child'));
console.log(`\nsidebar navigation: ${passed} passed, ${failed} failed`);if(failed)process.exit(1);

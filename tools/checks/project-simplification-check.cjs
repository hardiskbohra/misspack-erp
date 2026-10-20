'use strict';
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const exists = p => fs.existsSync(path.join(root, p));
const walk = dir => fs.readdirSync(path.join(root, dir), { withFileTypes: true }).flatMap(entry => {
  const file = path.join(dir, entry.name);
  return entry.isDirectory() ? walk(file) : [file];
});
const runtimeFiles = ['app', 'resources/views', 'routes', 'public/assets/js', 'public/assets/css']
  .flatMap(walk).filter(file => /\.(php|blade\.php|js|css)$/.test(file));
const runtime = runtimeFiles.map(read).join('\n');
const projectController = read('app/Http/Controllers/ProjectController.php');
const workflowController = read('app/Http/Controllers/ProjectWorkflowController.php');
const trackingController = read('app/Http/Controllers/ProjectTrackingController.php');
const execution = read('app/Services/ProjectWorkflowExecution.php');
const migration = read('database/migrations/2026_10_20_140000_remove_milestones_and_manual_progress.php');
const projectViews = walk('resources/views/projects').filter(file => file.endsWith('.blade.php')).map(read).join('\n');
let passed = 0;
function check(ok, label) { if (!ok) { console.error(`FAIL: ${label}`); process.exitCode = 1; } else { console.log(`PASS: ${label}`); passed++; } }
check(!exists('app/Models/ProjectMilestone.php') && !exists('app/Http/Controllers/ProjectMilestoneController.php'), 'milestone model and controller are removed');
check(!exists('resources/views/projects/partials/milestones-tab.blade.php') && !exists('resources/views/projects/partials/milestone-product-block.blade.php'), 'office milestone tab and partial are removed');
check(!exists('resources/views/client_portal/projects/partials/milestone-product-block.blade.php'), 'portal milestone partial is removed');
check(!/milestone|pmile/i.test(runtime), 'runtime code and assets contain no milestone connections');
check(migration.includes("Schema::dropIfExists('project_milestones')"), 'deployment migration drops the milestone table');
check(migration.includes("dropColumn('reported_progress')") && migration.includes("dropColumn('progress_percent')"), 'manual workflow and activity progress columns are removed');
check(!projectViews.includes('name="progress_percent"') && !projectViews.includes('name="reported_progress"'), 'project UI has no manual progress input');
check(!projectController.includes("'progress_percent' => ['") && !workflowController.includes("'reported_progress' =>") && !trackingController.includes("'progress_percent' =>"), 'controllers reject manual progress paths');
check(execution.includes("'progress_percent' => $calculatedProgress") && execution.includes("'stage' => $terminalDecision") && execution.includes("'status' => $terminalDecision"), 'workflow calculator publishes project progress, stage and status caches');
const projectFillable = read('app/Models/Project.php').match(/\$fillable\s*=\s*\[([\s\S]*?)\];/)?.[1] || '';
check(['progress_percent', 'status', 'stage', 'completed_at'].every(field => !projectFillable.includes(`'${field}'`)), 'workflow-controlled project state cannot be mass assigned');
if (!process.exitCode) console.log(`Project simplification checks passed (${passed}/10).`);

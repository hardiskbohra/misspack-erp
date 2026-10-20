const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const guidance = read('app/Services/ProjectWorkflowGuidance.php');
const queue = read('app/Services/ProjectCoordinatorQueue.php');
const workflow = read('resources/views/projects/partials/record-workflow.blade.php');
const returnService = read('app/Services/ProjectWorkflowReturn.php');
const css = read('public/assets/css/projects.css');
const controllers = [
  'app/Http/Controllers/SalesInvoiceController.php',
  'app/Http/Controllers/PurchaseInvoiceController.php',
  'app/Http/Controllers/CashflowController.php',
  'app/Http/Controllers/ShipmentController.php',
].map(read).join('\n');
let pass = 0;
function check(ok, label) { if (!ok) { console.error(`FAIL: ${label}`); process.exitCode = 1; } else { console.log(`PASS: ${label}`); pass++; } }
check(guidance.includes("'production_complete' =>") && guidance.includes("'production'"), 'production uses a focused guidance recipe and modal');
check(guidance.includes("'advance_received' =>") && guidance.includes("'Invoice receipt', false, 'commercial', 'receipt'") && workflow.includes('data-open-payment') && workflow.includes("sales_invoices.partials.payment-modal"), 'advance and dispatch actions open the invoice receipt modal in workflow');
check(guidance.includes("'procurement_ready' =>") && guidance.includes("'project_product_id' => $product?->id"), 'procurement action carries product context');
check(guidance.includes("route('shipments.create', $context)") && guidance.includes("'Project dispatch documents', false, 'products', 'dispatch-documents'") && workflow.includes('workflowDispatchDocumentsModal'), 'shipment preparation is followed by the in-context Project document checklist');
check(queue.includes("'guidance' => app(ProjectWorkflowGuidance::class)->forStep($step)"), 'coordinator queue receives centralized guidance');
check(workflow.includes('workflow-action-checklist') && workflow.includes('Why it is blocked'), 'action cards explain steps and blocking reason');
check(workflow.includes('workflowProductionModal') && ['status','expected_ready_date','actual_ready_date','production_completed_quantity','production_evidence','notes'].every(field => workflow.includes(`name=\"${field}\"`)), 'production modal exposes every operational field needed for the next step');
check(guidance.includes("'QC inspection', false, 'products', 'quality'") && workflow.includes('workflowQualityModal'), 'quality action opens its focused inspection modal');
check((workflow.match(/projects\.quality-inspections\.store/g) || []).length === 1, 'QC entry exists only in the action center while product view retains history');
check(workflow.includes('Sample-lot inspection is allowed') && workflow.includes('Inspection evidence <small>Optional</small>'), 'QC modal supports representative samples and optional evidence');
check(workflow.includes("route('projects.products.update',$action['product'])"), 'production modal uses controlled product endpoint');
check(workflow.includes('data-workflow-open="{{ $guide[\'view\'] }}"'), 'internal guidance opens the correct focused workflow view');
check(css.includes('.workflow-action-card') && css.includes('minmax(min(100%,430px),1fr)'), 'guided cards have responsive compact layout');
check(returnService.includes("str_starts_with($return, $projectBase)"), 'return target is restricted to project routes');
check((controllers.match(/ProjectWorkflowReturn::class/g) || []).length === 4, 'four source modules return to the launching workflow');
check(read('app/Http/Controllers/PurchaseInvoiceController.php').includes("'project_id' => $project?->id") && read('app/Http/Controllers/PurchaseInvoiceController.php').includes("'vendor_id' => $projectProduct?->vendor_id"), 'purchase order preselects project and vendor');
check(read('app/Http/Controllers/CashflowController.php').includes("'transaction_type' => $direction") && read('app/Http/Controllers/CashflowController.php').includes("'client_id' => $project?->client_id"), 'receipt preselects direction and client');
check(read('app/Http/Controllers/SalesInvoiceController.php').includes("ProjectWorkflowGates::class)->evaluateAll($workflow"), 'invoice receipt immediately recalculates workflow payment gates');
check(read('app/Http/Controllers/ShipmentController.php').includes('public function create(Request $request)') && read('app/Http/Controllers/ShipmentController.php').includes("'project_id' => $project?->id"), 'shipment create preselects project');
if (!process.exitCode) console.log(`Workflow guidance checks passed (${pass}/19).`);

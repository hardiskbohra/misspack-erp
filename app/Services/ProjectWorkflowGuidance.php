<?php

namespace App\Services;

use App\Models\ProjectWorkflowStep;
use Illuminate\Support\Facades\Route;

/**
 * Turns an internal gate key into a plain-language next action.
 *
 * The coordinator should not need to know which ERP module owns a source fact.
 * Each recipe explains the source record, links to a project-prefilled workspace
 * and says what the workflow will verify after that record is saved.
 */
class ProjectWorkflowGuidance
{
    public function forStep(ProjectWorkflowStep $step): array
    {
        $step->loadMissing('workflow.project', 'product');
        $project = $step->workflow->project;
        $product = $step->product;
        $projectWorkflow = route('projects.show', ['project' => $project, 'tab' => 'workflow']).'#workflow-actions';
        $projectProducts = route('projects.show', ['project' => $project, 'tab' => 'workflow']).'#workflow-products';
        $commercial = route('projects.show', ['project' => $project, 'tab' => 'workflow']).'#workflow-commercial';
        $context = ['project_id' => $project->id, 'return_to' => $projectWorkflow];

        return match ($step->step_key) {
            'project_setup' => $this->recipe(
                'Complete the project setup',
                'Confirm the coordinator, dates, client and execution scope, then mark setup complete in History & controls.',
                'Edit project', route('projects.edit', $project),
                ['Confirm the project coordinator.', 'Check target dates and scope.', 'Return and complete the setup control.'],
                'Project record', false, 'history'
            ),
            'commercial_baseline' => $this->recipe(
                'Create or select the Proforma Invoice',
                'The workflow needs one project Proforma as its commercial baseline before it can calculate advance and dispatch terms.',
                'Create Proforma', route('sales-invoices.create', ['type' => 'proforma'] + $context),
                ['Create the Proforma with this project selected.', 'Set advance and balance-before-dispatch terms.', 'Return here and choose it in Coordinator report.'],
                'Sales invoice', false, 'commercial'
            ),
            'advance_received' => $this->recipe(
                'Record and allocate the client advance',
                'Post the inward receipt and allocate it to this project or its Proforma. The gate updates from the ledger—not from a checkbox.',
                'Record receipt', $commercial,
                ['Record the client receipt against the workflow invoice.', 'The ledger entry is linked to this project automatically.', 'Workflow recalculates the required advance after saving.'],
                'Invoice receipt', false, 'commercial', 'receipt'
            ),
            'procurement_ready' => $this->recipe(
                'Create the vendor Purchase Order',
                'Assign the vendor through a project-linked Purchase Order and cover the required product quantity.',
                'Create Purchase Order', route('purchase-invoices.create', ['type' => 'order', 'project_product_id' => $product?->id] + $context),
                ['Choose the supplying vendor.', 'Keep this project selected.', 'Add the product quantity and save or approve the order.'],
                'Purchase order', false, 'products'
            ),
            'artwork_approved' => $this->recipe(
                'Send the artwork for client approval',
                'Upload the current artwork version. The step clears only after the client approves that exact version or a manager records an override.',
                'Manage artwork approval', $projectProducts,
                ['Create an Artwork approval request for this product.', 'Upload the exact client-facing file.', 'Wait for the portal decision or record a manager override.'],
                'Client approval', false, 'products'
            ),
            'pps_approved' => $this->recipe(
                'Send the PPS for client approval',
                'Upload the current pre-production sample evidence and collect the client decision for that version.',
                'Manage PPS approval', $projectProducts,
                ['Create a PPS approval request for this product.', 'Set a response due date.', 'Wait for the portal decision or record a manager override.'],
                'Client approval', false, 'products'
            ),
            'production_complete' => $this->recipe(
                'Record completed production quantity',
                'Enter the quantity actually completed and the production evidence. Partial completion remains visible and the remaining quantity stays open.',
                'Record production', $projectProducts,
                ['Enter completed quantity and production evidence.', 'Keep In Production for a partial quantity.', 'For full completion, change status to QC Pending to open inspection.'],
                'Production record', false, 'products', 'production'
            ),
            'quality_passed' => $this->recipe(
                'Record the quality inspection',
                'Inspect a representative sample and reconcile it into passed, rejected and rework quantities. An evidence attachment is optional.',
                'Record QC inspection', $projectProducts,
                ['Open the QC form here.', 'Reconcile the sampled quantities and checklist observations.', 'Optionally attach evidence, then save the immutable inspection.'],
                'QC inspection', false, 'products', 'quality'
            ),
            'packing_verified' => $this->recipe(
                'Create and confirm the Packaging List',
                'Pack the project quantities and mark the list Confirmed. Workflow checks the current carton and item details automatically.',
                'Open Packaging Lists', route('packaging-lists.index', ['search' => $project->project_number]),
                ['Generate or create a list for this project.', 'Allocate items into carton groups.', 'Resolve any automatic check issues and mark the list Confirmed.'],
                'Packaging List', true, 'products'
            ),
            'dispatch_balance' => $this->recipe(
                'Record the complete pre-dispatch balance',
                'Allocate inward receipts until the full required balance is covered, unless a manager accepts the current risk.',
                'Record receipt', $commercial,
                ['Record the receipt against the workflow invoice.', 'The ledger entry is linked to this project automatically.', 'Workflow recalculates the dispatch shortfall after saving.'],
                'Invoice receipt', false, 'commercial', 'receipt'
            ),
            'dispatch_approved', 'shipment_dispatched' => $this->recipe(
                'Create the shipment in Planning',
                'Keep the shipment in Planning, allocate confirmed Packaging List quantities, attach mandatory paperwork and then authorize dispatch.',
                'Create shipment', route('shipments.create', $context),
                ['Create the project shipment in Planning.', 'Allocate exact confirmed packed quantities.', 'Complete paperwork and authorize dispatch.'],
                'Shipment', false, 'products'
            ),
            'delivery_complete', 'delivery_reconciled' => $this->recipe(
                'Record the delivery receipt',
                'Open the project shipment and record accepted, short, damaged, rejected or returned quantities with POD evidence.',
                'Open project shipments', route('projects.show', ['project' => $project, 'tab' => 'shipments']),
                ['Open the delivered shipment.', 'Record the line-level delivery receipt.', 'Attach POD evidence and reconcile discrepancies.'],
                'Delivery receipt', false, 'products'
            ),
            ProjectFinancialClosure::GATE => $this->recipe(
                'Review commercial closure',
                'Reconcile receivables, payables, advances and unapplied cash, then choose ordinary or manager-exception closure.',
                'Review closure', $commercial,
                ['Review every financial category.', 'Resolve source balances or document accepted risk.', 'Record the closure decision.'],
                'Financial closure', false, 'commercial'
            ),
            ProjectFeedbackClosure::GATE => $this->recipe(
                'Close the client feedback loop',
                'Collect the close-out response and resolve every required feedback action before final closure.',
                'Open feedback', route('projects.show', ['project' => $project, 'tab' => 'feedback']),
                ['Check the close-out request state.', 'Send a reminder when due.', 'Resolve all feedback follow-up actions.'],
                'Feedback', false, 'commercial'
            ),
            default => $this->recipe(
                'Update the current workflow action',
                $step->blocked_reason ?: 'Open History & controls and record the current source action.',
                'Open control', $projectWorkflow,
                ['Review the source-controlled reason.', 'Complete the source record or manual action.', 'Refresh workflow sources.'],
                'Workflow control', false, 'history'
            ),
        };
    }

    private function recipe(string $title, string $instruction, string $label, string $url, array $checklist, string $source, bool $newTab, string $view, ?string $modal = null): array
    {
        return compact('title', 'instruction', 'label', 'url', 'checklist', 'source', 'newTab', 'view', 'modal');
    }
}

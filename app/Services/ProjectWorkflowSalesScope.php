<?php

namespace App\Services;

use App\Models\ProjectProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The product lanes belong to this project's live sales-document items, not purchases. */
class ProjectWorkflowSalesScope
{
    public function products(int $projectId, bool $current = false): Builder
    {
        $products = ProjectProduct::query()->where('project_id', $projectId)->where('status', '!=', 'cancelled');
        if (! $this->available()) return $products->whereRaw('1 = 0');

        return $products->whereExists(function (QueryBuilder $sales) use ($current) {
            $this->salesForProduct($sales, 'project_products', $current);
        });
    }

    public function contains(int $projectId, ?int $productId, bool $current = false): bool
    {
        return $productId !== null && $this->products($projectId, $current)->whereKey($productId)->when($current, fn ($query) => $query->lockForUpdate())->exists();
    }

    /** Apply the same predicate to project-wide and product-specific workflow rows. */
    public function steps(HasMany $steps, bool $current = false): HasMany
    {
        return $steps->where(function ($query) use ($current) {
            $query->whereNull('project_workflow_steps.project_product_id');
            if (! $this->available()) return;
            $query->orWhereExists(function (QueryBuilder $products) use ($current) {
                if ($current) $products->lockForUpdate();
                $products->selectRaw('1')->from('project_products as scope_product')
                    ->join('project_workflows as scope_workflow', function ($join) {
                        $join->on('scope_workflow.project_id', '=', 'scope_product.project_id');
                    })
                    ->whereColumn('scope_workflow.id', 'project_workflow_steps.project_workflow_id')
                    ->whereColumn('scope_product.id', 'project_workflow_steps.project_product_id')
                    ->where('scope_product.status', '!=', 'cancelled')
                    ->whereExists(function (QueryBuilder $sales) use ($current) {
                        $this->salesForProduct($sales, 'scope_product', $current);
                    });
            });
        });
    }

    /** Lines whose historical link cannot be matched safely must be reviewed. */
    public function unmatched(int $projectId, bool $current = false): Collection
    {
        if (! $this->available()) return collect();
        return DB::table('sales_invoice_items as scope_item')
            ->when($current, fn ($query) => $query->lockForUpdate())
            ->join('sales_invoices as scope_invoice', 'scope_invoice.id', '=', 'scope_item.sales_invoice_id')
            ->where('scope_invoice.project_id', $projectId)->where('scope_invoice.status', '!=', 'cancelled')
            // A converted cash invoice contains a monetary "amount against PI"
            // line, not a product. Its source PI supplies the product lanes.
            ->where(function (QueryBuilder $invoice) {
                $invoice->where('scope_invoice.invoice_type', '!=', 'cash')->orWhereNull('scope_invoice.source_proforma_id');
            })
            ->whereNotExists(function (QueryBuilder $products) use ($current) {
                if ($current) $products->lockForUpdate();
                $products->selectRaw('1')->from('project_products as scope_product')
                    ->whereColumn('scope_product.project_id', 'scope_invoice.project_id')
                    ->where('scope_product.status', '!=', 'cancelled')
                    ->where(function (QueryBuilder $match) use ($current) {
                        $this->matchItem($match, 'scope_product', 'scope_item', $current);
                    });
            })
            ->select('scope_invoice.id as invoice_id', 'scope_invoice.invoice_number', 'scope_item.id', 'scope_item.product_name')
            ->orderBy('scope_invoice.id')->orderBy('scope_item.id')->get();
    }

    private function salesForProduct(QueryBuilder $sales, string $product, bool $current = false): void
    {
        if ($current) $sales->lockForUpdate();
        $sales->selectRaw('1')->from('sales_invoice_items as scope_item')
            ->join('sales_invoices as scope_invoice', 'scope_invoice.id', '=', 'scope_item.sales_invoice_id')
            ->whereColumn('scope_invoice.project_id', $product.'.project_id')
            ->where('scope_invoice.status', '!=', 'cancelled')
            ->where(function (QueryBuilder $invoice) {
                $invoice->where('scope_invoice.invoice_type', '!=', 'cash')->orWhereNull('scope_invoice.source_proforma_id');
            })
            ->where(function (QueryBuilder $match) use ($product, $current) {
                $this->matchItem($match, $product, 'scope_item', $current);
            });
    }

    /**
     * Explicit links win. Older unlinked lines may match only one same-project
     * product: catalogue ID first, otherwise an unambiguous normalized name.
     * These read-only fallbacks never rewrite invoice lines or cross projects.
     */
    private function matchItem(QueryBuilder $match, string $product, string $item, bool $current = false): void
    {
        // MySQL repeatable-read subqueries otherwise reuse a stale snapshot even
        // when their enclosing SELECT locks rows. SQLite ignores locking clauses.
        $lock = $current && DB::connection()->getDriverName() === 'mysql' ? ' for update' : '';
        $match->whereColumn($item.'.project_product_id', $product.'.id')
            ->orWhere(function (QueryBuilder $legacy) use ($product, $item, $lock) {
                $legacy->whereNull($item.'.project_product_id')->whereNotNull($item.'.product_id')
                    ->whereColumn($item.'.product_id', $product.'.product_id')
                    ->whereRaw("(select count(*) from project_products as scope_same_id where scope_same_id.project_id = {$product}.project_id and scope_same_id.product_id = {$product}.product_id{$lock}) = 1");
            })
            ->orWhere(function (QueryBuilder $legacy) use ($product, $item, $current, $lock) {
                $legacy->whereNull($item.'.project_product_id')
                    ->whereRaw("trim({$item}.product_name) <> ''")
                    ->whereRaw("lower(trim({$item}.product_name)) = lower(trim({$product}.product_name))")
                    ->whereRaw("(select count(*) from project_products as scope_same_name where scope_same_name.project_id = {$product}.project_id and lower(trim(scope_same_name.product_name)) = lower(trim({$product}.product_name)){$lock}) = 1")
                    ->whereNotExists(function (QueryBuilder $byId) use ($product, $item, $current) {
                        if ($current) $byId->lockForUpdate();
                        $byId->selectRaw('1')->from('project_products as scope_id_candidate')
                            ->whereColumn('scope_id_candidate.project_id', $product.'.project_id')
                            ->whereColumn('scope_id_candidate.product_id', $item.'.product_id');
                    });
            });
    }

    private function available(): bool
    {
        return Schema::hasTable('sales_invoice_items') && Schema::hasTable('sales_invoices');
    }
}

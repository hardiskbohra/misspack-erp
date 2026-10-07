<?php

namespace App\Services;

use App\Models\ProjectProduct;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * The project's product list, written by the documents that mention the product.
 *
 * A project product used to be typed in by hand on the project, and the sales
 * invoice the office raised afterwards pre-filled its lines from that list — so
 * the same product was entered twice, and a project whose invoice had been
 * raised while nobody filled the products tab showed nothing at all. The lines
 * of an invoice are already the facts: what the client ordered, how many, at
 * what rate. Materialising them into `project_products` means the products tab
 * is filled by the documents the office already raises, and the invoice form's
 * pre-fill then reads what the last document wrote.
 *
 * Whichever document mentions the product first creates the row; after that the
 * two kinds own different facts and never write over each other:
 *
 * - a **sales invoice line** (the client's own document) owns what the client
 *   sees: the product, the quantity, the unit, the rate and the currency;
 * - a **purchase line** owns what we buy: the vendor, and the supplier's own
 *   document number. On a row it is the first to mention it, a purchase line
 *   seeds the quantity, unit and currency from its own line but not the rate —
 *   a vendor's cost is not the project's value, and the client's invoice is
 *   where that figure comes from.
 *
 * Two things this class deliberately does not do:
 *
 * - it never deletes. A project product carries milestones, comments and
 *   attachments; a line removed from a draft invoice must not take the job's
 *   work with it. The products tab's own remove button is the door for that;
 * - it never writes `total_amount`. The quantity × rate rule is
 *   `ProjectProduct::saving`'s, on the model, and a second arithmetic here is
 *   how the two start disagreeing.
 */
class ProjectProducts
{
    /** Materialise a sales invoice's lines into its project's product list. */
    public function fromSalesInvoice(SalesInvoice $invoice): int
    {
        $projectId = (int) $invoice->project_id;

        if (! $this->available() || $projectId <= 0) {
            return 0;
        }

        return $this->write($projectId, $this->lines($invoice), 'sales', $invoice);
    }

    /** Materialise a purchase order or bill's lines, so a project sees what it buys. */
    public function fromPurchaseInvoice(PurchaseInvoice $invoice): int
    {
        $projectId = (int) $invoice->project_id;

        if (! $this->available() || $projectId <= 0) {
            return 0;
        }

        return $this->write($projectId, $this->lines($invoice), 'purchase', $invoice);
    }

    /**
     * A document's lines, in the order the document draws them, with the
     * product's own name loaded for a line that was written before the product
     * was renamed.
     */
    private function lines(Model $invoice)
    {
        $invoice->loadMissing('items.product');

        return $invoice->items;
    }

    /**
     * Write the lines the source owns, and count the rows touched.
     *
     * @param  iterable<int, Model>  $lines
     */
    private function write(int $projectId, iterable $lines, string $source, Model $invoice): int
    {
        $touched = 0;

        foreach ($lines as $line) {
            /* `project_products.product_name` is what the products tab (and a
               milestone, and a document) names the row by, and the column is
               not nullable: a line with no name at all is not a product. */
            $name = trim((string) ($line->product_name ?: $line->product?->name ?: ''));

            if ($name === '') {
                continue;
            }

            $row = $this->resolve($projectId, $line, $name);

            $row->fill($source === 'sales'
                ? $this->salesFacts($row, $line, $invoice, $name)
                : $this->purchaseFacts($row, $line, $invoice, $name));

            /* A row this service creates gets no `status`/`stage` of its own:
               the model's defaults are the vocabulary for a project product,
               and the office moves it along from the products tab. */
            $row->save();
            $touched++;
        }

        return $touched;
    }

    /**
     * The row this line is about: the one it already points at, else the same
     * product on the same project, else the same name on the same project, else
     * a new row. Matching by product id before name keeps a rename from
     * creating a second row for one product.
     */
    private function resolve(int $projectId, Model $line, string $name): ProjectProduct
    {
        $query = ProjectProduct::query()->where('project_id', $projectId);

        if ($line->project_product_id && ($match = (clone $query)->whereKey($line->project_product_id)->first())) {
            return $match;
        }

        if ($line->product_id && ($match = (clone $query)->where('product_id', $line->product_id)->first())) {
            return $match;
        }

        if ($name !== '' && ($match = (clone $query)->whereRaw('LOWER(product_name) = ?', [mb_strtolower($name)])->first())) {
            return $match;
        }

        return new ProjectProduct(['project_id' => $projectId]);
    }

    /** The client's own facts: what was ordered, how many, at what rate. */
    private function salesFacts(ProjectProduct $row, Model $line, Model $invoice, string $name): array
    {
        /* The line's description is the specification at the time the product
           first appears. It is seeded once: the office edits specs on the
           product from there, and re-saving the invoice must not overwrite what
           it typed. Read as one value before the array — a `?:` inside the
           `:` of a `?` is a ternary PHP 8 refuses to parse, and it refuses at
           load time, so the file stops being a file. */
        $specification = trim((string) ($line->description ?: ''));

        return array_filter([
            'product_id' => $line->product_id ?: null,
            'product_name' => $name ?: null,
            'quantity' => $line->quantity ?: null,
            'unit' => $line->unit ?: null,
            'unit_price' => $line->unit_price,
            'currency' => $invoice->currency ?: null,
            'sort_order' => $line->sort_order ?: null,
            'notes' => $row->exists || $specification === '' ? null : $specification,
        ], fn ($value) => $value !== null);
    }

    /** What we buy: the vendor, and the supplier's own document number. */
    private function purchaseFacts(ProjectProduct $row, Model $line, Model $invoice, string $name): array
    {
        $facts = [
            'product_id' => $line->product_id ?: null,
            'product_name' => $name ?: null,
            'vendor_id' => $invoice->vendor_id ?: null,
            /* The supplier's own document number, and nothing else: our own
               number standing in for theirs is not a vendor invoice number,
               and the products tab says "No vendor invoice" until the office
               has the paperwork. */
            'vendor_invoice_number' => $invoice->vendor_bill_number ?: null,
        ];

        /* Only a row this document is the first to mention is seeded with the
           ordered quantity — the client's invoice owns that figure once it
           exists, and a purchase document must not rewrite it. */
        if (! $row->exists) {
            $facts['quantity'] = $line->quantity ?: null;
            $facts['unit'] = $line->unit ?: null;
            $facts['currency'] = $invoice->currency ?: null;
        }

        return array_filter($facts, fn ($value) => $value !== null);
    }

    private function available(): bool
    {
        return class_exists(ProjectProduct::class) && Schema::hasTable('project_products');
    }
}

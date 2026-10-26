<?php

namespace App\Services;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesInvoiceNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * One authority for "who may change money on a tax invoice".
 *
 * - A tax invoice that has left `draft` is locked: its monetary and identity
 *   fields cannot change, and it cannot be deleted. Corrections are documents:
 *   credit notes (lower what the client owes, optionally restocking goods),
 *   debit notes (raise it), and returns (a credited restock in one flow).
 * - Notes are immutable once issued; a mistake is cancelled with its own
 *   reason so the paper trail always reflects what actually happened.
 *
 * Proformas are untouched here — `assertEditable()` already guards any invoice
 * that money has been converted out of.
 */
class SalesInvoiceAdjustment
{
    /**
     * Fields whose change would silently rewrite a tax document after issue.
     * The list deliberately keeps the working fields editable — payment terms,
     * due dates for dunning, notes, attachments, the seller block (which is
     * re-merged from organisation settings on every save) — and locks what a
     * GST document may not change on: identity, taxes, and money.
     */
    protected const MONETARY_FIELDS = [
        'invoice_number' => 'invoice number',
        'invoice_type' => 'invoice type',
        'client_id' => 'client',
        'project_id' => 'project',
        'client_company_name' => 'client name',
        'billing_address' => 'billing address',
        'billing_city' => 'billing city',
        'billing_state' => 'billing state',
        'billing_country' => 'billing country',
        'billing_pincode' => 'billing pincode',
        'client_gstin' => 'client GSTIN',
        'place_of_supply' => 'place of supply',
        'gst_type' => 'GST split',
        'invoice_date' => 'invoice date',
        'currency' => 'currency',
        'exchange_rate' => 'exchange rate',
        'discount_type' => 'discount',
        'discount_value' => 'discount',
        'freight_amount' => 'freight',
        'packing_amount' => 'packing',
        'other_charges' => 'other charges',
        'round_off' => 'round off',
        'amount_paid' => 'the opening payment figure',
    ];

    /**
     * Throw when an update would rewrite money or identity on an issued tax
     * invoice. Unlocked rows (drafts / proformas / cancelled) skip the check.
     *
     * @throws ValidationException
     */
    public function assertMoneyFieldsUnchanged(SalesInvoice $invoice, array $data, array $items): void
    {
        if (! $this->isLocked($invoice)) {
            return;
        }

        $changed = [];

        foreach (self::MONETARY_FIELDS as $key => $label) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            if (! $this->sameValue($invoice->{$key}, $data[$key])) {
                $changed[] = $label;
            }
        }

        if ($this->itemsChanged($invoice, $items)) {
            $changed[] = 'line items';
        }

        if ($changed !== []) {
            $list = implode(', ', array_map('ucfirst', $changed));
            throw ValidationException::withMessages([
                'invoice' => ["This tax invoice has already been issued — {$list} cannot change. "
                    .'Issue a credit or debit note instead so the audit trail stays intact.'],
            ]);
        }
    }

    /** A locked tax invoice cannot be deleted — only cancelled notes and the trail remain. */
    public function assertDeletable(SalesInvoice $invoice): void
    {
        if ($this->isLocked($invoice)) {
            throw ValidationException::withMessages([
                'invoice' => ['This tax invoice has already been issued and cannot be deleted. '
                    .'Correct it with a credit note, or ask an administrator to cancel it with a reason.'],
            ]);
        }
    }

    /** Drafts may still be edited freely; issued tax documents may not. */
    public function isLocked(SalesInvoice $invoice): bool
    {
        return $invoice->invoice_type === 'tax'
            && $invoice->status !== 'draft'
            && $invoice->status !== 'cancelled';
    }

    /**
     * Issue a credit or debit note against an issued tax invoice.
     *
     * @param  array{note_date?:string, subtotal?:float, amount?:float, reason?:string, restock?:bool|mixed}  $payload
     *
     * @throws ValidationException
     */
    public function issue(SalesInvoice $invoice, string $kind, array $payload): SalesInvoiceNote
    {
        abort_unless(in_array($kind, [SalesInvoiceNote::KIND_CREDIT, SalesInvoiceNote::KIND_DEBIT], true), 404);

        $data = $this->validatedNotePayload($invoice, $payload);

        $subtotal = round((float) $data['subtotal'], 2);
        $taxes = $this->taxSplit($invoice, $subtotal);
        $total = round($subtotal + $taxes['cgst_amount'] + $taxes['sgst_amount'] + $taxes['igst_amount'], 2);

        if ($kind === SalesInvoiceNote::KIND_CREDIT) {
            $this->assertCreditFits($invoice, $total);
        }

        return DB::transaction(function () use ($invoice, $kind, $data, $subtotal, $taxes, $total) {
            $note = SalesInvoiceNote::create([
                'sales_invoice_id' => $invoice->id,
                'number' => $this->makeNoteNumber($invoice, $kind),
                'kind' => $kind,
                'is_return' => false,
                'note_date' => $data['note_date'],
                'reason' => $data['reason'],
                'subtotal' => $subtotal,
                'cgst_amount' => $taxes['cgst_amount'],
                'sgst_amount' => $taxes['sgst_amount'],
                'igst_amount' => $taxes['igst_amount'],
                'total' => $total,
                'created_by' => auth()->id(),
                'status' => SalesInvoiceNote::STATUS_ISSUED,
            ]);
            $this->syncStoredBalance($note->invoice);

            return $note;
        });
    }

    /**
     * Record a sales return: validate returned quantities against what has
     * already come back, then post the matching credit note in the same
     * transaction — the return document and its money effect must never drift.
     *
     * @param  array{note_date?:string, reason?:string, restock?:bool, lines?:array<int,array{item_id:int,quantity:mixed}>|null}  $payload
     *
     * @throws ValidationException
     */
    public function recordReturn(SalesInvoice $invoice, array $payload): SalesInvoiceNote
    {
        abort_if(
            $invoice->invoice_type !== 'tax'
            || $invoice->status === 'draft'
            || $invoice->status === 'cancelled'
            || $invoice->isSuperseded(),
            422
        );

        $data = Validator::make($payload, [
            'note_date' => 'required|date',
            'reason' => 'required|string|max:500',
            'restock' => 'sometimes|boolean',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
        ])->validate();

        $invoice->loadMissing('items', 'notes');

        $alreadyReturned = $invoice->notes->where('is_return', true)
            ->where('status', SalesInvoiceNote::STATUS_ISSUED)
            ->flatMap(fn ($note) => collect($note->lines ?? []))
            ->groupBy('item_id')
            ->map(fn ($rows) => (float) $rows->sum('quantity'));

        $returnLines = [];
        $subtotal = 0.0;
        $seen = [];

        foreach ($data['lines'] as $line) {
            if ((float) $line['quantity'] <= 0) {
                continue;
            }

            $item = $invoice->items->firstWhere('id', (int) $line['item_id']);
            if (! $item) {
                throw ValidationException::withMessages(['lines' => ['Line '.($line['item_id']).' does not belong to this invoice.']]);
            }
            if (isset($seen[$item->id])) {
                throw ValidationException::withMessages(['lines' => ['A line can only be returned once per document.']]);
            }
            $seen[$item->id] = true;

            $returned = (float) ($alreadyReturned[$item->id] ?? 0);
            $remaining = round((float) $item->quantity - $returned, 4);
            $qty = round((float) $line['quantity'], 4);

            if ($qty > $remaining + 0.0001) {
                throw ValidationException::withMessages([
                    'lines' => ["\"{$item->product_name}\": only {$remaining} can still come back ({$returned} already returned of {$item->quantity} invoiced)."],
                ]);
            }

            $amount = round($qty * (float) $item->unit_price, 2);
            $subtotal += $amount;
            $returnLines[] = [
                'item_id' => $item->id,
                'product_name' => $item->product_name,
                'hsn_sac' => $item->hsn_sac,
                'unit' => $item->unit,
                'quantity' => $qty,
                'unit_price' => (float) $item->unit_price,
                'amount' => $amount,
            ];
        }

        if ($returnLines === []) {
            throw ValidationException::withMessages(['lines' => ['Choose at least one line with a quantity above zero.']]);
        }

        $subtotal = round($subtotal, 2);
        $taxes = $this->taxSplit($invoice, $subtotal);
        $total = round($subtotal + $taxes['cgst_amount'] + $taxes['sgst_amount'] + $taxes['igst_amount'], 2);

        $this->assertCreditFits($invoice, $total);

        return DB::transaction(function () use ($invoice, $data, $returnLines, $subtotal, $taxes, $total) {
            $note = SalesInvoiceNote::create([
                'sales_invoice_id' => $invoice->id,
                'number' => $this->makeNoteNumber($invoice, SalesInvoiceNote::KIND_CREDIT),
                'kind' => SalesInvoiceNote::KIND_CREDIT,
                'is_return' => true,
                'restock' => ! empty($data['restock']),
                'lines' => $returnLines,
                'note_date' => $data['note_date'],
                'reason' => $data['reason'],
                'subtotal' => $subtotal,
                'cgst_amount' => $taxes['cgst_amount'],
                'sgst_amount' => $taxes['sgst_amount'],
                'igst_amount' => $taxes['igst_amount'],
                'total' => $total,
                'created_by' => auth()->id(),
                'status' => SalesInvoiceNote::STATUS_ISSUED,
            ]);
            $this->syncStoredBalance($note->invoice);

            return $note;
        });
    }

    /**
     * Cancelling a note is a correction of a correction: the note stays on
     * record with its cancel reason, and its money stops applying.
     *
     * @throws ValidationException
     */
    public function cancel(SalesInvoiceNote $note, ?string $reason): SalesInvoiceNote
    {
        Validator::make(
            ['cancel_reason' => $reason],
            ['cancel_reason' => 'required|string|max:500'],
            ['cancel_reason.required' => 'Cancelling a note without saying why just makes the trail lie twice.']
        )->validate();

        abort_unless($note->isLive(), 422);

        return DB::transaction(function () use ($note, $reason) {
            $note->forceFill([
                'status' => SalesInvoiceNote::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();
            $this->syncStoredBalance($note->invoice);

            return $note->fresh();
        });
    }

    /**
     * `balance_amount` is the stored copy other ledgers read; when a note
     * changes what is owed, write the copy from the same money rule — the
     * controller's refreshInvoiceMoney does exactly this on receipts.
     */
    public function syncStoredBalance(SalesInvoice $invoice): void
    {
        $invoice->unsetRelation('payments');
        $invoice->unsetRelation('notes')->refresh();

        $balance = $invoice->balanceDue();

        if (abs((float) $invoice->balance_amount - $balance) > 0.001) {
            $invoice->forceFill(['balance_amount' => $balance])->saveQuietly();
        }
    }

    /**
     * MP/CN/26-27/001 — financial year runs April–March, matching the invoice
     * numbering on the parent document.
     */
    protected function makeNoteNumber(SalesInvoice $invoice, string $kind): string
    {
        $date = now();
        $year = ((int) $date->format('Y')) % 100;
        $fy = $date->format('n') >= 4
            ? sprintf('%02d-%02d', $year, $year + 1)
            : sprintf('%02d-%02d', $year - 1, $year);

        $prefix = $kind === SalesInvoiceNote::KIND_CREDIT ? 'MP/CN' : 'MP/DN';
        $base = "{$prefix}/{$fy}/";
        $next = SalesInvoiceNote::query()
            ->where('number', 'like', $base.'%')
            ->count() + 1;

        do {
            $number = $base.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (SalesInvoiceNote::where('number', $number)->exists());

        return $number;
    }

    /** Taxes on a note follow the parent invoice's split, pro-rated to the amount. */
    protected function taxSplit(SalesInvoice $invoice, float $subtotal): array
    {
        $base = (float) $invoice->subtotal;
        if ($base <= 0 || $subtotal <= 0) {
            return ['cgst_amount' => 0.0, 'sgst_amount' => 0.0, 'igst_amount' => 0.0, 'total_tax' => 0.0];
        }

        $share = min($subtotal / $base, 1.0);

        return [
            'cgst_amount' => round((float) $invoice->cgst_amount * $share, 2),
            'sgst_amount' => round((float) $invoice->sgst_amount * $share, 2),
            'igst_amount' => round((float) $invoice->igst_amount * $share, 2),
            'total_tax' => round(((float) $invoice->cgst_amount + (float) $invoice->sgst_amount + (float) $invoice->igst_amount) * $share, 2),
        ];
    }

    protected function validatedNotePayload(SalesInvoice $invoice, array $payload): array
    {
        /* Corrections belong to issued tax documents only: nothing to correct
           on a draft (edit it), a proforma (convert or edit it), a superseded
           PI (its money moved), or a cancelled invoice (it no longer exists
           for tax). */
        abort_if(
            $invoice->invoice_type !== 'tax'
            || $invoice->status === 'draft'
            || $invoice->status === 'cancelled'
            || $invoice->isSuperseded(),
            422
        );

        return Validator::make($payload, [
            'note_date' => 'required|date',
            'subtotal' => 'required|numeric|min:0.01|max:100000000',
            'reason' => 'required|string|max:500',
        ], [
            'subtotal.min' => 'An adjustment needs a positive amount — a zero note records nothing.',
        ])->validate();
    }

    /** You cannot credit more than the document is currently worth. */
    protected function assertCreditFits(SalesInvoice $invoice, float $creditTotal): void
    {
        $invoice->loadMissing('notes');
        $payable = $invoice->payableTotal();

        if ($creditTotal > $payable + 0.01) {
            throw ValidationException::withMessages([
                'subtotal' => ['The credit exceeds what this invoice is still worth ('
                    .\App\Helpers\CommonHelper::amount($payable, $invoice->currency)
                    .'). Cancel a previous note first if the earlier credit was wrong.'],
            ]);
        }
    }

    /** Loose equality for money and dates: 2dp and the calendar day. */
    protected function sameValue($current, $incoming): bool
    {
        /* Money columns read null on older rows and 0 / "" from forms; to the
           office they are all "no money". Comparing literally would lock the
           user out of their own document by an artefact of storage. */
        if (($current === null || is_numeric($current)) && ($incoming === null || is_numeric($incoming))
            && ($current !== null || $incoming !== null)) {
            return round((float) ($current ?? 0), 2) === round((float) ($incoming ?? 0), 2);
        }

        /* A `date` cast arrives as Carbon; the form sends "Y-m-d". */
        if ($current instanceof \DateTimeInterface) {
            return $incoming !== null
                && \Illuminate\Support\Carbon::parse($current)->toDateString()
                === substr((string) $incoming, 0, 10);
        }

        if ($incoming instanceof \DateTimeInterface) {
            return $this->sameValue($incoming, $current);
        }

        return (string) ($current ?? '') === (string) ($incoming ?? '');
    }

    /** Money moved if any saved line changed what it sells, how much, or its tax. */
    protected function itemsChanged(SalesInvoice $invoice, array $incoming): bool
    {
        $shape = fn ($row, $source) => json_encode([
            'product_name' => (string) ($source === 'db' ? $row->product_name : ($row['product_name'] ?? '')),
            'quantity' => round((float) ($source === 'db' ? $row->quantity : ($row['quantity'] ?? 0)), 4),
            'unit' => (string) ($source === 'db' ? $row->unit : ($row['unit'] ?? '')),
            'unit_price' => round((float) ($source === 'db' ? $row->unit_price : ($row['unit_price'] ?? 0)), 4),
            'discount_percent' => round((float) ($source === 'db' ? $row->discount_percent : ($row['discount_percent'] ?? 0)), 2),
            'gst_percent' => round((float) ($source === 'db' ? $row->gst_percent : ($row['gst_percent'] ?? 0)), 2),
            'hsn_sac' => (string) ($source === 'db' ? $row->hsn_sac : ($row['hsn_sac'] ?? '')),
        ]);

        $current = $invoice->items->map(fn (SalesInvoiceItem $item) => $shape($item, 'db'))->sort()->values()->all();
        $next = collect($incoming)->filter(fn ($row) => ! empty($row['product_name']))
            ->map(fn ($row) => $shape($row, 'in'))->sort()->values()->all();

        return $current !== $next;
    }
}

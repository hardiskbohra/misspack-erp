<?php

namespace App\Services\EInvoice;

use App\Models\HsnSacCode;
use App\Models\SalesInvoice;
use App\Support\Gstin;

/**
 * Builds the NIC e-invoice request document (schema v1.03 shape) from a sales
 * invoice — the JSON the Invoice Registration Portal (or a GSP speaking the
 * same schema) turns into an IRN and a signed QR.
 *
 * The builder is also the bouncer: an invoice that cannot legally produce a
 * document (a proforma, a draft, a line with no HSN, a buyer whose GSTIN
 * fails its check digit) is rejected here with reasons, before any byte is
 * sent anywhere. `issues()` answers why; `build()` refuses until they are
 * resolved — a "best effort" payload is how a portal rejection becomes a
 * cancelled invoice three days later.
 */
final class EInvoicePayload
{
    public const SCHEMA_VERSION = '1.1';

    /** Supply types we can emit from this invoice model. */
    private const SUPPLY_B2B = 'B2B';
    private const SUPPLY_B2CL = 'B2CL';      // inter-state to unregistered, above the ₹2.5L line
    private const SUPPLY_EXP_WITH = 'EXPWP'; // export with tax payment
    private const SUPPLY_EXP_WITHOUT = 'EXPWOP';

    public function __construct(private readonly SalesInvoice $invoice)
    {
        $this->invoice->loadMissing('items');
    }

    /**
     * Everything that must be fixed before registration, in plain sentences.
     *
     * @return list<string>
     */
    public function issues(): array
    {
        $invoice = $this->invoice;
        $issues = [];

        if ($invoice->invoice_type !== 'tax') {
            $issues[] = 'Only a tax invoice can be registered for e-invoice (this one is "' . $invoice->invoice_type . '").';
        }

        if ($invoice->cancelled_at !== null) {
            $issues[] = 'A cancelled invoice cannot be registered for e-invoice.';
        }

        if (! $invoice->invoice_date) {
            $issues[] = 'The invoice has no invoice date.';
        }

        if (! Gstin::isValid($invoice->seller_gstin)) {
            $issues[] = 'The seller GSTIN on the invoice is missing or fails its checksum — fix the organisation profile and re-save the invoice.';
        }

        /* Buyer: a B2B document needs a valid buyer GSTIN; without one the
           supply can still be B2CL/EXP depending on value and direction. */
        if ($invoice->gst_type !== 'export' && ! Gstin::isValid($invoice->client_gstin)) {
            $supersedesB2c = $invoice->gst_type === 'inter_state' && (float) $invoice->total_amount > 250000;
            if (! $supersedesB2c) {
                $issues[] = 'The buyer has no valid GSTIN — for this supply type the portal expects one (B2C large or export would still fly; intra-state B2C small will not).';
            }
        }

        if (! $invoice->place_of_supply) {
            $issues[] = 'Place of supply is missing.';
        } elseif ($invoice->gst_type !== 'export' && ! array_key_exists($invoice->place_of_supply, Gstin::stateCodes())) {
            // Accept either the code ("24") or the exact state name.
            $nameMatches = in_array(mb_strtolower($invoice->place_of_supply), array_map('mb_strtolower', Gstin::stateCodes()), true);
            if (! $nameMatches) {
                $issues[] = 'Place of supply "' . $invoice->place_of_supply . '" is not a GST state code or name.';
            }
        }

        foreach ($invoice->items as $index => $item) {
            $line = $index + 1;
            $hsn = HsnSacCode::normaliseCode($item->hsn_sac);
            if ($hsn === null || ! in_array(strlen($hsn), [4, 5, 6, 7, 8], true)) {
                $issues[] = "Line {$line} ({$item->product_name}) has no usable HSN/SAC — the law expects at least 4 digits on an e-invoice.";
            }
            if ($invoice->invoice_type === 'tax') {
                // Do not round away a saved discrepancy when constructing the
                // legal JSON. This also catches legacy prorated tax lines whose
                // three-place rate cannot represent the allocated gross amount.
                if (abs(round((float) $item->quantity * (float) $item->unit_price, 2) - round((float) $item->gross_amount, 2)) > 0.005) {
                    $issues[] = "Line {$line} ({$item->product_name}) has a gross amount that does not match quantity × unit price. Ask Accounts to correct the document before e-registration.";
                }
                if (abs(round((float) $item->gross_amount - (float) $item->discount_amount, 2) - round((float) $item->taxable_amount, 2)) > 0.005) {
                    $issues[] = "Line {$line} ({$item->product_name}) has a taxable amount that does not match its gross less discount.";
                }
                $taxable = (float) $item->taxable_amount;
                $rate = (float) $item->gst_percent;
                $expected = match ($invoice->gst_type) {
                    'intra_state' => [round($taxable * $rate / 200, 2), round($taxable * $rate / 200, 2), 0],
                    'inter_state' => [0, 0, round($taxable * $rate / 100, 2)],
                    // Exports can be without tax or IGST-paid.
                    'export' => [0, 0, (float) $item->igst_amount > 0 ? round($taxable * $rate / 100, 2) : 0],
                    default => [0, 0, 0],
                };
                if (abs($expected[0] - (float) $item->cgst_amount) > 0.005
                    || abs($expected[1] - (float) $item->sgst_amount) > 0.005
                    || abs($expected[2] - (float) $item->igst_amount) > 0.005) {
                    $issues[] = "Line {$line} ({$item->product_name}) has tax values that do not match its taxable amount and tax rate.";
                }
                if (abs(round($taxable + (float) $item->cgst_amount + (float) $item->sgst_amount + (float) $item->igst_amount, 2)
                    - round((float) $item->line_total, 2)) > 0.005) {
                    $issues[] = "Line {$line} ({$item->product_name}) has a total that does not match its taxable amount and taxes.";
                }
                foreach (['gross_amount', 'discount_amount', 'taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'line_total'] as $field) {
                    if (abs((float) $item->$field - round((float) $item->$field, 2)) > 0.0005) {
                        $issues[] = "Line {$line} ({$item->product_name}) contains a monetary figure with more than two decimal places.";
                        break;
                    }
                }
            }
        }

        if ($invoice->items->isEmpty()) {
            $issues[] = 'The invoice has no line items.';
        } elseif ($invoice->invoice_type === 'tax') {
            // Stored item figures precede the invoice-wide discount, whereas
            // the header is after it. Reconcile that relationship first; then
            // allocate the header back to item rows as the printed invoice does.
            $beforeDiscount = round((float) $invoice->items->sum('taxable_amount'), 2);
            $afterDiscount = round(max($beforeDiscount - (float) $invoice->discount_amount, 0), 2);
            if (abs($afterDiscount - round((float) $invoice->taxable_amount, 2)) > 0.005) {
                $issues[] = 'The invoice taxable total does not match the sum of item values less the invoice discount. Ask Accounts to reconcile it before e-registration.';
            }
            $ratio = $beforeDiscount > 0 ? $afterDiscount / $beforeDiscount : 1;
            foreach (['cgst_amount', 'sgst_amount', 'igst_amount'] as $field) {
                if (abs(round((float) $invoice->items->sum($field) * $ratio, 2) - round((float) $invoice->$field, 2)) > 0.005) {
                    $issues[] = 'The invoice total for '.$field.' does not match the discounted item values. Ask Accounts to reconcile it before e-registration.';
                }
            }
            $allocated = $this->legalItemFigures();
            if ($allocated === null) {
                $issues[] = 'The invoice total cannot be allocated to its item values. Ask Accounts to reconcile it before e-registration.';
            } elseif ((float) $invoice->discount_amount > 0) {
                foreach ($invoice->items as $index => $item) {
                    $line = $index + 1;
                    $figures = $allocated[$index];
                    $taxable = $figures['taxable_amount'];
                    $rate = (float) $item->gst_percent;
                    $expected = match ($invoice->gst_type) {
                        'intra_state' => [round($taxable * $rate / 200, 2), round($taxable * $rate / 200, 2), 0],
                        'inter_state' => [0, 0, round($taxable * $rate / 100, 2)],
                        'export' => [0, 0, $figures['igst_amount'] > 0 ? round($taxable * $rate / 100, 2) : 0],
                        default => [0, 0, 0],
                    };
                    if ($taxable > (float) $item->gross_amount + 0.005
                        || abs($expected[0] - $figures['cgst_amount']) > 0.005
                        || abs($expected[1] - $figures['sgst_amount']) > 0.005
                        || abs($expected[2] - $figures['igst_amount']) > 0.005) {
                        $issues[] = "Line {$line} ({$item->product_name}) cannot reconcile its allocated discount and tax rate. Ask Accounts to review the invoice.";
                    }
                }
            }
            foreach (['taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'discount_amount', 'freight_amount', 'packing_amount', 'other_charges', 'round_off', 'total_amount'] as $field) {
                if (abs((float) $invoice->$field - round((float) $invoice->$field, 2)) > 0.0005) {
                    $issues[] = 'A tax invoice monetary total contains more than two decimal places. Ask Accounts to reconcile it before e-registration.';
                    break;
                }
            }
            $calculatedTotal = round((float) $invoice->taxable_amount + (float) $invoice->cgst_amount + (float) $invoice->sgst_amount
                + (float) $invoice->igst_amount + (float) $invoice->freight_amount + (float) $invoice->packing_amount
                + (float) $invoice->other_charges + (float) $invoice->round_off, 2);
            if (abs($calculatedTotal - round((float) $invoice->total_amount, 2)) > 0.005) {
                $issues[] = 'The invoice total does not match its taxable, tax, charge and round-off figures.';
            }
            if (abs((float) $invoice->round_off) > 99.99) {
                $issues[] = 'The tax invoice round-off exceeds the e-invoice limit of 99.99. Ask Accounts to reconcile it before e-registration.';
            }
        }

        return $issues;
    }

    /**
     * Allocate the saved, post-discount header values across their pre-discount
     * item weights, in paise, using the same largest-remainder rule as print.
     * This does not change any persisted invoice or its agreed total.
     *
     * @return array<int, array<string, float>>|null
     */
    private function legalItemFigures(): ?array
    {
        $rows = array_fill(0, $this->invoice->items->count(), []);
        foreach (['taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount'] as $field) {
            $weights = $this->invoice->items->map(fn ($item) => max((float) $item->$field, 0))->all();
            $amounts = $this->allocatePaise((float) $this->invoice->$field, $weights);
            if ($amounts === null) return null;
            foreach ($amounts as $index => $amount) $rows[$index][$field] = $amount;
        }
        return $rows;
    }

    /** @return list<float>|null */
    private function allocatePaise(float $target, array $weights): ?array
    {
        $count = count($weights);
        if ($target < -0.005 || $count === 0) return null;
        $paise = (int) round($target * 100);
        if ($paise === 0) return array_fill(0, $count, 0.0);
        $sum = array_sum($weights);
        if ($sum <= 0) return null;
        $raw = array_map(fn ($weight) => $paise * $weight / $sum, $weights);
        $amounts = array_map(fn ($value) => (int) floor($value), $raw);
        $remaining = $paise - array_sum($amounts);
        $order = array_keys($raw);
        usort($order, fn ($a, $b) => (($raw[$b] - floor($raw[$b])) <=> ($raw[$a] - floor($raw[$a]))) ?: ($a <=> $b));
        for ($i = 0; $i < $remaining; $i++) $amounts[$order[$i % $count]]++;
        return array_map(fn ($value) => $value / 100, $amounts);
    }

    /**
     * The payload in portal schema order. Throws when `issues()` is not empty.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $issues = $this->issues();
        if ($issues !== []) {
            throw new \DomainException('Invoice cannot be e-registered: ' . implode(' ', $issues));
        }

        $invoice = $this->invoice;
        $sellerState = Gstin::stateCode($invoice->seller_gstin);
        $buyerGstin = Gstin::normalise($invoice->client_gstin);
        $buyerRegistered = Gstin::isValid($buyerGstin);
        $supply = $this->supplyType($buyerRegistered);
        $buyerState = $buyerRegistered ? Gstin::stateCode($buyerGstin) : $this->placeOfSupplyCode();

        $items = [];
        $figures = $this->legalItemFigures(); // preflight already checked that allocation is possible
        foreach ($invoice->items as $index => $item) {
            $line = $figures[$index];
            $taxable = $line['taxable_amount'];
            $items[] = array_filter([
                'SlNo' => (string) ($index + 1),
                'PrdDesc' => mb_substr($item->product_name, 0, 300),
                'IsServc' => 'N', // packaging goods dominate; SAC lines still carry HsnCd either way
                'HsnCd' => HsnSacCode::normaliseCode($item->hsn_sac),
                'Qty' => round((float) $item->quantity, 3),
                'Unit' => strtoupper(mb_substr($item->unit ?: 'PCS', 0, 8)),
                'UnitPrice' => round((float) $item->unit_price, 3),
                'TotAmt' => round((float) $item->gross_amount, 2),
                'Discount' => round((float) $item->gross_amount - $taxable, 2),
                'AssAmt' => $taxable,
                'GstRt' => round((float) $item->gst_percent, 2),
                'IgstAmt' => $line['igst_amount'],
                'CgstAmt' => $line['cgst_amount'],
                'SgstAmt' => $line['sgst_amount'],
                'TotItemVal' => round($taxable + $line['igst_amount'] + $line['cgst_amount'] + $line['sgst_amount'], 2),
            ], fn ($value) => $value !== null);
        }

        return array_filter([
            'Version' => self::SCHEMA_VERSION,
            'TranDtls' => array_filter([
                'TaxSch' => 'GST',
                'SupTyp' => $supply,
                'RegRev' => 'N',
                'IgstOnIntra' => 'N',
            ]),
            'DocDtls' => [
                'Typ' => 'INV',
                'No' => mb_substr($invoice->invoice_number, 0, 16),
                'Dt' => $invoice->invoice_date->format('d/m/Y'),
            ],
            'SellerDtls' => array_filter([
                'Gstin' => Gstin::normalise($invoice->seller_gstin),
                'LglNm' => $invoice->seller_company_name,
                'Addr1' => mb_substr((string) $invoice->seller_address, 0, 100),
                'Loc' => $invoice->seller_city,
                'Pin' => (int) $invoice->seller_pincode ?: null,
                'Stcd' => $sellerState,
                'Ph' => $this->digits($invoice->seller_mobile, 12) ?: null,
                'Em' => $invoice->seller_email,
            ], fn ($value) => $value !== null && $value !== ''),
            'BuyerDtls' => array_filter([
                'Gstin' => $buyerRegistered ? $buyerGstin : 'URP',
                'LglNm' => $invoice->client_company_name,
                'TrdNm' => $invoice->client_brand_name,
                'Pos' => $buyerState,
                'Addr1' => mb_substr((string) $invoice->billing_address, 0, 100),
                'Loc' => $invoice->billing_city,
                'Pin' => (int) $invoice->billing_pincode ?: null,
                'Stcd' => $buyerState,
                'Ph' => $this->digits($invoice->client_mobile, 12) ?: null,
                'Em' => $invoice->client_email,
            ], fn ($value) => $value !== null && $value !== ''),
            'ItemList' => $items,
            'ValDtls' => array_filter([
                'AssVal' => round((float) $invoice->taxable_amount, 2),
                'CgstVal' => round((float) $invoice->cgst_amount, 2),
                'SgstVal' => round((float) $invoice->sgst_amount, 2),
                'IgstVal' => round((float) $invoice->igst_amount, 2),
                // The invoice-wide discount is already allocated into item
                // discounts above; repeating it here would deduct it twice.
                'Discount' => 0.0,
                'OthChrg' => round((float) $invoice->other_charges + (float) $invoice->freight_amount + (float) $invoice->packing_amount, 2),
                'RndOffAmt' => round((float) $invoice->round_off, 2),
                'TotInvVal' => round((float) $invoice->total_amount, 2),
                'TotInvValFc' => $invoice->currency !== 'INR' ? round((float) $invoice->total_amount, 2) : null,
            ], fn ($value) => $value !== null),
        ], fn ($value) => $value !== null && $value !== []);
    }

    /** Which supply type the portal should see. */
    private function supplyType(bool $buyerRegistered): string
    {
        if ($this->invoice->gst_type === 'export') {
            return (float) $this->invoice->igst_amount > 0 ? self::SUPPLY_EXP_WITH : self::SUPPLY_EXP_WITHOUT;
        }

        if (! $buyerRegistered) {
            return self::SUPPLY_B2CL;
        }

        return self::SUPPLY_B2B;
    }

    /** The two-digit code behind the invoice's place of supply. */
    private function placeOfSupplyCode(): ?string
    {
        $pos = (string) $this->invoice->place_of_supply;
        $states = Gstin::stateCodes();

        if (isset($states[$pos])) {
            return $pos;
        }

        $code = array_search(mb_strtolower($pos), array_map('mb_strtolower', $states), true);

        return $code !== false ? (string) $code : null;
    }

    private function digits(?string $value, int $max): string
    {
        return substr(preg_replace('/\D/', '', (string) $value), 0, $max);
    }
}

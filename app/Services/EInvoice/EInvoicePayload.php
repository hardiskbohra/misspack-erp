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
        }

        if ($invoice->items->isEmpty()) {
            $issues[] = 'The invoice has no line items.';
        }

        return $issues;
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
        foreach ($invoice->items as $index => $item) {
            $taxable = round((float) $item->taxable_amount, 2);
            $items[] = array_filter([
                'SlNo' => (string) ($index + 1),
                'PrdDesc' => mb_substr($item->product_name, 0, 300),
                'IsServc' => 'N', // packaging goods dominate; SAC lines still carry HsnCd either way
                'HsnCd' => HsnSacCode::normaliseCode($item->hsn_sac),
                'Qty' => round((float) $item->quantity, 3),
                'Unit' => strtoupper(mb_substr($item->unit ?: 'PCS', 0, 8)),
                'UnitPrice' => round((float) $item->unit_price, 2),
                'TotAmt' => round((float) $item->gross_amount, 2),
                'Discount' => round((float) $item->discount_amount, 2),
                'AssAmt' => $taxable,
                'GstRt' => round((float) $item->gst_percent, 2),
                'IgstAmt' => round((float) $item->igst_amount, 2),
                'CgstAmt' => round((float) $item->cgst_amount, 2),
                'SgstAmt' => round((float) $item->sgst_amount, 2),
                'TotItemVal' => round($taxable + (float) $item->igst_amount + (float) $item->cgst_amount + (float) $item->sgst_amount, 2),
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
                'Discount' => round((float) $invoice->discount_amount, 2),
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

<?php

namespace App\Services;

use App\Models\Organisation;
use App\Models\PackagingList;
use App\Models\PackagingListItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Shipment;

class PackagingListSource
{
    public function salesInvoice(SalesInvoice $invoice): array
    {
        $invoice->load('items.product');
        return [
            'attributes' => [
                'source_type'=>'sales_invoice','source_id'=>$invoice->id,'sales_invoice_id'=>$invoice->id,
                'client_id'=>$invoice->client_id,'project_id'=>$invoice->project_id,'invoice_number'=>$invoice->invoice_number,
                'transport_mode'=>$this->mode($invoice->transport_mode),'from_name'=>$invoice->seller_company_name,
                'from_address'=>$this->address([$invoice->seller_address,$invoice->seller_city,$invoice->seller_state,$invoice->seller_pincode,$invoice->seller_country]),
                'from_contact'=>$invoice->seller_mobile,'from_email'=>$invoice->seller_email,
                'bill_to_name'=>$invoice->client_company_name,'bill_to_address'=>$this->address([$invoice->billing_address,$invoice->billing_city,$invoice->billing_state,$invoice->billing_pincode,$invoice->billing_country]),
                'ship_to_name'=>$invoice->client_company_name,'ship_to_address'=>$this->address([$invoice->shipping_address,$invoice->shipping_city,$invoice->shipping_state,$invoice->shipping_pincode,$invoice->shipping_country]),
            ],
            'cartons'=>$this->lines('sales_invoice', $invoice->items),
        ];
    }

    public function purchaseInvoice(PurchaseInvoice $invoice): array
    {
        $invoice->load('items.product');
        return [
            'attributes'=>[
                'source_type'=>'purchase_invoice','source_id'=>$invoice->id,'purchase_invoice_id'=>$invoice->id,
                'vendor_id'=>$invoice->vendor_id,'project_id'=>$invoice->project_id,'invoice_number'=>$invoice->referenceNumber(),
                'transport_mode'=>$this->mode($invoice->transport_mode),'from_name'=>$invoice->vendor_company_name,
                'from_address'=>$this->address([$invoice->vendor_address,$invoice->vendor_city,$invoice->vendor_state,$invoice->vendor_pincode,$invoice->vendor_country]),
                'from_contact'=>$invoice->vendor_mobile,'from_email'=>$invoice->vendor_email,
                'bill_to_name'=>$invoice->buyer_company_name,'bill_to_address'=>$this->address([$invoice->buyer_address,$invoice->buyer_city,$invoice->buyer_state,$invoice->buyer_pincode,$invoice->buyer_country]),
                'ship_to_name'=>$invoice->buyer_company_name,'ship_to_address'=>$this->address([$invoice->buyer_address,$invoice->buyer_city,$invoice->buyer_state,$invoice->buyer_pincode,$invoice->buyer_country]),
            ],
            'cartons'=>$this->lines('purchase_invoice', $invoice->items),
        ];
    }

    public function shipment(Shipment $shipment): array
    {
        $shipment->load('items');
        $organisation=Organisation::current(); $address=$organisation->defaultAddress('billing') ?: $organisation->defaultAddress('shipping');
        return [
            'attributes'=>[
                'source_type'=>'shipment','source_id'=>$shipment->id,'shipment_id'=>$shipment->id,
                'sales_invoice_id'=>$shipment->sales_invoice_id,'vendor_id'=>$shipment->vendor_id,'client_id'=>$shipment->client_id,'project_id'=>$shipment->project_id,
                'transport_mode'=>$this->mode($shipment->shipment_mode),'from_name'=>$shipment->from_name ?: 'Not specified',
                'from_address'=>$this->address([$shipment->from_address,$shipment->from_city,$shipment->from_state,$shipment->from_pincode,$shipment->from_country]),
                'from_contact'=>$shipment->from_mobile,'from_email'=>$shipment->from_email,
                'bill_to_name'=>$organisation->legal_name ?: $organisation->trade_name ?: 'Not specified','bill_to_address'=>$address?->oneLine(),
                'ship_to_name'=>$shipment->to_name ?: 'Not specified','ship_to_address'=>$this->address([$shipment->to_address,$shipment->to_city,$shipment->to_state,$shipment->to_pincode,$shipment->to_country]),
            ],
            'cartons'=>$this->lines('shipment', $shipment->items),
        ];
    }

    private function lines(string $type, $lines): array
    {
        if ($lines->isEmpty()) return [];
        $items=[];
        foreach ($lines as $line) {
            $source=(float)($line->quantity ?? 0); $previous=$this->packed($type,$line->id); $remaining=max(0,$source-$previous);
            $items[]=[
                'product_id'=>$line->product_id ?? null,'source_type'=>$type,'source_item_id'=>$line->id,
                'source_quantity'=>$source,'previously_packed_quantity'=>$previous,
                'item_description'=>$line->product_name ?? $line->description ?? 'Item','sku'=>$line->sku ?? $line->product?->sku,
                'hs_code'=>$line->hsn_sac ?? $line->hs_code ?? null,'quantity_per_carton'=>$remaining,
                'total_quantity'=>$remaining,'unit'=>$line->unit ?: 'pcs','unit_net_weight'=>$line->net_weight ?? null,
                'unit_gross_weight'=>$line->gross_weight ?? null,'remarks'=>$line->remarks ?? null,
            ];
        }
        return [[
            'carton_label'=>'Source items — assign carton details','packing_mode'=>'grouped','carton_number'=>'1',
            'carton_type'=>'box','carton_count'=>1,'length_cm'=>'','width_cm'=>'','height_cm'=>'',
            'net_weight_per_carton'=>'','gross_weight_per_carton'=>'','tare_weight'=>0,'is_stackable'=>1,'items'=>$items,
        ]];
    }

    private function packed(string $type, int $lineId): float
    {
        return round((float) PackagingListItem::query()->where('source_type',$type)->where('source_item_id',$lineId)
            ->whereHas('carton.packagingList',fn($q)=>$q->whereNotIn('status',['cancelled']))->sum('total_quantity'),3);
    }

    private function mode(?string $mode): string
    {
        $mode=strtolower((string)$mode); return array_key_exists($mode,PackagingList::transportModeOptions()) ? $mode : 'road';
    }
    private function address(array $parts): string { return collect($parts)->filter()->implode(', '); }
}

<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentDeliveryReceipt;
use App\Models\ShipmentItem;
use App\Services\ProjectOperationalReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ShipmentDeliveryReceiptController extends Controller
{
    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        abort_unless(in_array($shipment->status, Shipment::dispatchedStatuses(), true), 422, 'Delivery receipts can only be recorded after dispatch starts.');
        $data = $request->validate([
            'receipt_reference'=>['nullable','string','max:255'],
            'recipient_name'=>['required','string','max:255'],
            'received_at'=>['required','date','before_or_equal:now'],
            'notes'=>['nullable','string','max:4000'],
            'evidence_file'=>['nullable','file','max:20480','mimes:jpg,jpeg,png,webp,pdf'],
            'lines'=>['required','array','min:1'],
            'lines.*.shipment_item_id'=>['required','integer','distinct','exists:shipment_items,id'],
            'lines.*.received_quantity'=>['required','numeric','min:0'],
            'lines.*.accepted_quantity'=>['required','numeric','min:0'],
            'lines.*.short_quantity'=>['required','numeric','min:0'],
            'lines.*.damaged_quantity'=>['required','numeric','min:0'],
            'lines.*.rejected_quantity'=>['required','numeric','min:0'],
            'lines.*.returned_quantity'=>['required','numeric','min:0'],
            'lines.*.replacement_quantity'=>['required','numeric','min:0'],
            'lines.*.discrepancy_reason'=>['nullable','string','max:2000'],
        ]);

        $rows = [];
        foreach ($data['lines'] as $index => $line) {
            $values = collect(['received_quantity','accepted_quantity','short_quantity','damaged_quantity','rejected_quantity','returned_quantity','replacement_quantity'])
                ->mapWithKeys(fn ($key) => [$key=>round((float)$line[$key], 3)]);
            if ($values->sum() <= 0) continue;
            if (abs($values['received_quantity'] - ($values['accepted_quantity'] + $values['damaged_quantity'] + $values['rejected_quantity'])) > 0.0001) {
                throw ValidationException::withMessages(["lines.$index.received_quantity"=>'Received quantity must equal accepted, damaged and rejected quantities.']);
            }
            if ($values['replacement_quantity'] > $values['accepted_quantity'] + 0.0001) {
                throw ValidationException::withMessages(["lines.$index.replacement_quantity"=>'Replacement quantity is part of accepted quantity and cannot be greater than it.']);
            }
            $hasDiscrepancy = $values['short_quantity'] > 0 || $values['damaged_quantity'] > 0 || $values['rejected_quantity'] > 0 || $values['returned_quantity'] > 0;
            if ($hasDiscrepancy && blank($line['discrepancy_reason'] ?? null)) {
                throw ValidationException::withMessages(["lines.$index.discrepancy_reason"=>'Explain the shortage, damage, rejection or return.']);
            }
            $rows[] = array_merge($values->all(), [
                'shipment_item_id'=>(int)$line['shipment_item_id'],
                'discrepancy_reason'=>$line['discrepancy_reason'] ?? null,
            ]);
        }
        if ($rows === []) throw ValidationException::withMessages(['lines'=>'Enter at least one received, short, damaged, rejected or returned quantity.']);

        $warnings = [];
        $receipt = DB::transaction(function () use ($request, $shipment, $data, $rows, &$warnings) {
            $lockedShipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $itemIds = collect($rows)->pluck('shipment_item_id');
            $items = ShipmentItem::query()->where('shipment_id', $lockedShipment->id)->whereIn('id', $itemIds)->lockForUpdate()->get()->keyBy('id');
            if ($items->count() !== $itemIds->unique()->count()) throw ValidationException::withMessages(['lines'=>'Every delivery line must belong to this shipment.']);
            foreach ($rows as &$row) {
                $item = $items->get($row['shipment_item_id']);
                $acceptedBefore = (float)$item->deliveryReceiptLines()->sum('accepted_quantity');
                $returnedBefore = (float)$item->deliveryReceiptLines()->sum('returned_quantity');
                if ($returnedBefore + $row['returned_quantity'] > $acceptedBefore + $row['accepted_quantity'] + 0.0001) {
                    throw ValidationException::withMessages(['lines'=>'Returned quantity cannot exceed the quantity accepted for '.$item->product_name.'.']);
                }
                $netAfter = $acceptedBefore + $row['accepted_quantity'] - $returnedBefore - $row['returned_quantity'];
                if ($netAfter > (float)$item->quantity + 0.0001) {
                    $warnings[] = $item->product_name.' exceeds its dispatched quantity by '.number_format($netAfter-(float)$item->quantity, 3).'.';
                }
                $row['project_product_id'] = $item->project_product_id;
            }
            unset($row);
            /* The signed POD may arrive later: the line reconciliation is the
               receipt, and the evidence file is supporting proof only. */
            $evidence = $request->file('evidence_file');
            $payload = [
                'shipment_id'=>$lockedShipment->id, 'receipt_reference'=>$data['receipt_reference'] ?? null,
                'recipient_name'=>$data['recipient_name'], 'received_at'=>$data['received_at'],
                'notes'=>$data['notes'] ?? null,
                'evidence_path'=>$evidence?->store('shipments/delivery-receipts', 'public'),
                'evidence_original_name'=>$evidence?->getClientOriginalName(), 'evidence_mime'=>$evidence?->getMimeType(),
                'evidence_size'=>$evidence?->getSize() ?: 0,
                'evidence_hash'=>$evidence ? hash_file('sha256', $evidence->getRealPath()) : null,
                'recorded_by'=>Auth::id(),
            ];
            $payload['content_hash'] = hash('sha256', json_encode(['receipt'=>$payload,'lines'=>$rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $receipt = ShipmentDeliveryReceipt::create($payload);
            $receipt->lines()->createMany($rows);
            return $receipt;
        });

        if ($workflow = $shipment->project?->workflow) app(ProjectOperationalReadiness::class)->evaluateAll($workflow, Auth::id());
        $message = 'Immutable delivery receipt '.$receipt->id.' recorded and delivery quantities reconciled.';
        return redirect()->route('shipments.show', $shipment)->with($warnings ? 'warning' : 'success', $message.($warnings ? ' '.implode(' ', $warnings) : ''));
    }

    /**
     * Printable delivery receipt — the signed proof of what actually arrived,
     * kept beside the immutable record it was read from.
     */
    public function print(Shipment $shipment, ShipmentDeliveryReceipt $receipt): Response
    {
        abort_unless((int) $receipt->shipment_id === (int) $shipment->id, 404);

        $receipt->load(['lines.shipmentItem', 'recorder']);
        $shipment->load(['client', 'project']);

        $viewData = [
            'shipment' => $shipment,
            'receipt' => $receipt,
            'printMode' => true,
        ];

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('shipments.print.delivery-receipt', $viewData)
                ->setPaper('a4')
                ->download('delivery-receipt-'.$receipt->id.'-'.$shipment->shipment_number.'.pdf');
        }

        return response()->view('shipments.print.delivery-receipt', $viewData + [
            'pdfFallbackMessage' => 'Use the print dialog and choose Save as PDF to download this delivery receipt.',
        ]);
    }
}

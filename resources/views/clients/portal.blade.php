<label</label><label</label><label</label><label</label><label</label><input class="master-input" name="password" placeholder="Leave blank to keep existing / auto generate"></div>
                <div class="cpa-checks">
                    <label class="master-check"><input type="checkbox" name="generate_password" value="1" {{ ! $portalUser ? 'checked' : '' }}></label>
                    <label class="master-check"><input type="checkbox" name="portal_enabled" value="1" {{ old('portal_enabled', $portalUser-></label>
                    <label class="master-check"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $portalUser-></label>
                    <label class="master-check"><input type="checkbox" name="must_change_password" value="1" {{ old('must_change_password', $portalUser-></label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><input class="master-input" type="file" name="file"></div>
                <label class="master-check"><input type="checkbox" name="is_public_to_client" value="1" checked></label><label</label><label</label><label</label><label</label><label</label><textarea class="master-textarea" name="message"></textarea></div>
                <div class="cpa-submit"><button class="master-btn master-btn-primary">Send Notification</button></div>
            </form>
        </div>
    </div>

    <div class="cpa-card" style="margin-top:18px;">
        <div class="cpa-section-head"><div><p class="cpa-eyebrow">Records</p><h2>Portal Invoices</h2></div></div>
        <div class="cpa-table-wrap"><table class="cpa-table"><thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Status</th><th>Public</th><th>Action</th></tr></thead><tbody>@forelse($invoices as $invoice)<tr><td><strong>{{ $invoice->invoice_number }}</strong><span>{{ $invoice->title }}</span></td><td>{{ optional($invoice->invoice_date)->format('d M Y') ?: '-' }}</td><td>{{ $invoice->currency }} {{ number_format((float)$invoice->total_amount, 2) }}</td><td>{{ $invoice->statusLabel() }}</td><td>{{ $invoice->is_public_to_client ? 'Yes' : 'No' }}</td><td><form method="POST" action="{{ route('clients.portal.invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete invoice?')">@csrf @method('DELETE')<button class="master-btn master-btn-soft master-btn-sm">Delete</button></form></td></tr>@empty<tr><td colspan="6"><div class="cpa-empty">No invoices added.</div></td></tr>@endforelse</tbody></table></div>
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/clients.css') }}">
@endpush
@endsection

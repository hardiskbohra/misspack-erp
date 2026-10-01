<label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><input class="master-input" name="place_of_supply"
                            value="{{ old('place_of_supply', $invoice->place_of_supply) }}"></div>
                    <label class="master-check"><input name="show_client_portal" value="1"
                            {{ old('show_client_portal', $invoice-></label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><input class="master-input" type="file" name="attachments[]"
                                multiple></div>
                    </div>
                    <div class="master-total-box">
                        <div><span>Subtotal</span><strong id="previewSubtotal">₹ 0.00</strong></div>
                        <div><span>Tax</span><strong id="previewTax">₹ 0.00</strong></div>
                        <div><span>Total</span><strong id="previewTotal">₹ 0.00</strong></div>
                        <div><span>Balance</span><strong id="previewBalance">₹ 0.00</strong></div>
                    </div>
                </div>
            </div>

            @foreach ($sellerDefaults as $field => $value)
                @if (!in_array($field, ['seller_company_name'], true))
                    <input type="hidden" name="{{ $field }}"
                        value="{{ old($field, $invoice->{$field} ?: $value) }}">
                @endif
            @endforeach
            <input type="hidden" name="seller_company_name"
                value="{{ old('seller_company_name', $invoice->seller_company_name ?: $sellerDefaults['seller_company_name']) }}">

            <div class="master-submit"><a
                    href="{{ $isEdit ? route('sales-invoices.show', $invoice) : route('sales-invoices.index') }}"
                    class="master-btn master-btn-light-dark">Cancel</a><button class="master-btn master-btn-primary"
                    type="submit">{{ $isEdit ? 'Update Invoice' : 'Create Invoice' }}</button></div>
        </form>
    </div>


@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/sales-invoices.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/sales-invoices.js') }}"></script>
@endpush
@endsection

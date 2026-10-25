{{-- Separate from the invoice form: saving a client never submits the invoice. --}}
<div class="master-modal" id="invoiceClientModal" aria-hidden="true">
    <div class="master-modal-card is-wide si-client-create-modal" role="dialog" aria-modal="true"
        aria-labelledby="invoiceClientTitle" aria-describedby="invoiceClientDescription" tabindex="-1">
        <form method="POST" action="{{ route('clients.quickStore') }}" data-invoice-client-form>
            @csrf
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon"><i class="fas fa-building" aria-hidden="true"></i></span>
                    <div>
                        <h2 class="master-modal-title" id="invoiceClientTitle">Add client</h2>
                        <p class="master-modal-subtitle" id="invoiceClientDescription">Save contact and address details, then continue this invoice.</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="invoiceClientModal" aria-label="Close add client">&times;</button>
            </div>
            <div class="master-modal-body">
                <p class="master-help">The client is saved to Clients immediately as a draft profile. Your invoice entries stay unchanged.</p>
                <div class="master-alert error" data-client-create-error role="alert" tabindex="-1" hidden></div>
                <div class="master-modal-grid">
                    @foreach ([
                        'company_name' => ['Company name', 'text', 255],
                        'brand_name' => ['Brand name', 'text', 255],
                        'ceo_name' => ['Primary contact', 'text', 255],
                        'ceo_contact' => ['Contact phone', 'tel', 40],
                        'ceo_email' => ['Contact email', 'email', 255],
                        'gstin' => ['GSTIN', 'text', 30],
                        'pan' => ['PAN', 'text', 20],
                    ] as $field => $input)
                        <div class="master-field">
                            <label class="master-label" for="invoice_new_{{ $field }}">{{ $input[0] }}@if ($field === 'company_name') <span class="master-required" aria-hidden="true">*</span>@endif</label>
                            <input class="master-input" id="invoice_new_{{ $field }}" name="{{ $field }}" type="{{ $input[1] }}" maxlength="{{ $input[2] }}"
                                @required($field === 'company_name') aria-describedby="invoice_new_{{ $field }}Error">
                            <p class="master-help" id="invoice_new_{{ $field }}Error" data-client-field-error hidden></p>
                        </div>
                    @endforeach
                </div>
                <div class="si-address-pair">
                    @foreach (['billing' => 'Billing address', 'shipping' => 'Shipping address'] as $prefix => $heading)
                        <div class="si-address-block" role="group" aria-labelledby="invoice_new_{{ $prefix }}_heading">
                            <div class="si-address-head"><p class="master-section-label" id="invoice_new_{{ $prefix }}_heading">{{ $heading }}</p></div>
                            @if ($prefix === 'shipping')
                                <label class="master-check si-client-create-toggle">
                                    <input type="hidden" name="shipping_same_as_billing" value="0">
                                    <input type="checkbox" name="shipping_same_as_billing" value="1" data-client-same-address checked> Same as billing address
                                </label>
                            @endif
                            <fieldset class="si-client-address-fields" @if ($prefix === 'shipping') data-client-shipping-fields hidden disabled @endif>
                                <div class="master-form-grid">
                                    <div class="master-field full">
                                        <label class="master-label" for="invoice_new_{{ $prefix }}_address">Address line</label>
                                        <textarea class="master-textarea" id="invoice_new_{{ $prefix }}_address" name="{{ $prefix }}_address" rows="2" maxlength="5000" aria-describedby="invoice_new_{{ $prefix }}_addressError"></textarea>
                                        <p class="master-help" id="invoice_new_{{ $prefix }}_addressError" data-client-field-error hidden></p>
                                    </div>
                                    @foreach (['city' => 'City', 'state' => 'State', 'country' => 'Country', 'pincode' => 'Pincode / postal code'] as $part => $label)
                                        <div class="master-field">
                                            <label class="master-label" for="invoice_new_{{ $prefix }}_{{ $part }}">{{ $label }}</label>
                                            <input class="master-input" id="invoice_new_{{ $prefix }}_{{ $part }}" name="{{ $prefix }}_{{ $part }}" maxlength="{{ $part === 'pincode' ? 30 : 255 }}" value="{{ $part === 'country' ? 'India' : '' }}" aria-describedby="invoice_new_{{ $prefix }}_{{ $part }}Error">
                                            <p class="master-help" id="invoice_new_{{ $prefix }}_{{ $part }}Error" data-client-field-error hidden></p>
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="invoiceClientModal">Cancel</button>
                <button type="submit" class="master-btn master-btn-primary" data-save-invoice-client>Save client &amp; use on invoice</button>
            </div>
        </form>
    </div>
</div>

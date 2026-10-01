<label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><input class="master-input"
                            name="billing_pincode" value="{{ old('billing_pincode', $client->billing_pincode) }}"></div>
                    <label class="master-check"><input name="shipping_same_as_billing" value="1"
                            @checked(old('shipping_same_as_billing', $client-></label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><input
                            class="master-input" name="notes" value="{{ old('notes', $client->notes) }}"></div>
                </div>
            </div>

            <div class="master-actions">
                <a href="{{ $isEdit ? route('clients.show', $client) : route('clients.index') }}"
                    class="master-btn master-btn-light">Cancel</a>
                <button type="submit"
                    class="master-btn master-btn-primary">{{ $isEdit ? 'Update Client' : 'Create Client' }}</button>
            </div>
        </form>
    </div>
@endsection
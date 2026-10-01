<label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><select class="master-select"
                                    name="printing_required">
                                    <option value="">Select</option>
                                    @foreach ($printingOptions as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select></div><label class="master-check"><input type="checkbox"
                                    name="ready_stock_required" value="1"></label><label</label>
                                <textarea class="master-textarea" name="sales_notes"
                                    placeholder="Requirement summary, quote quantities, colors, stock question..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light"
                            data-close-modal="quickLeadModal">Cancel</button><button class="master-btn master-btn-primary"
                            type="submit">Create Lead</button></div>
                </form>
            </div>
        </div>
    </div>

@push('scripts')
    <script src="{{ asset('assets/js/leads.js') }}"></script>
@endpush
@endsection

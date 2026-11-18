@extends('layouts.app')

@section('title', 'Settings · Taxes & HSN')
@section('page-title', 'Settings · Taxes & HSN')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/settings.css') }}">
@endpush
<div class="set master-list">
    @include('settings.partials.nav', ['current' => 'taxes'])
    <div class="set-area">
        <div class="master-card master-card--flat set-head">
            <span class="set-head-mark" aria-hidden="true"><i class="fa-solid fa-percent"></i></span>
            <div>
                <h1 class="set-head-title">Taxes &amp; HSN</h1>
                <p class="master-sub">The HSN/SAC codes this business sells and bills under, with the GST rate each
                    carries today. Invoice forms offer these codes and pre-fill the rate; e-invoice payloads are
                    checked against them.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="master-alert"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>{{ session('status') }}</span></div>
        @endif
        @if ($errors->any())
            <div class="master-alert master-alert-danger"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span></div>
        @endif

        <form method="POST" action="{{ route('settings.taxes.hsn.store') }}" class="master-card master-card--flat master-section">
            @csrf
            <div class="master-section-head"><div>
                <h2 class="master-section-title">Add a code</h2>
                <p class="master-sub">Goods codes are HSN (4–8 digits), service codes are SAC. The rate is the total
                    GST percent — the invoice splits it into CGST/SGST or IGST itself.</p>
            </div></div>
            <div class="master-grid-4">
                <div class="master-field">
                    <label class="master-label" for="hsnCode">Code</label>
                    <input class="master-input" id="hsnCode" name="code" inputmode="numeric" maxlength="8"
                        placeholder="3923" value="{{ old('code') }}" required>
                </div>
                <div class="master-field">
                    <label class="master-label" for="hsnKind">Kind</label>
                    <select class="master-select" id="hsnKind" name="kind">
                        <option value="goods" @selected(old('kind') === 'goods')>Goods (HSN)</option>
                        <option value="service" @selected(old('kind') === 'service')>Service (SAC)</option>
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label" for="hsnRate">GST rate %</label>
                    <input class="master-input" id="hsnRate" name="gst_rate" type="number" step="0.01" min="0" max="99.99"
                        placeholder="18" value="{{ old('gst_rate') }}" required>
                </div>
                <div class="master-field">
                    <label class="master-label" for="hsnSort">Sort order</label>
                    <input class="master-input" id="hsnSort" name="sort_order" type="number" min="0" step="10"
                        placeholder="10" value="{{ old('sort_order') }}">
                </div>
            </div>
            <div class="master-field">
                <label class="master-label" for="hsnDesc">Description</label>
                <input class="master-input" id="hsnDesc" name="description" maxlength="255"
                    placeholder="Articles for the conveyance or packing of goods, of plastics"
                    value="{{ old('description') }}" required>
            </div>
            <label class="master-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                <span>Offer this code on invoice and purchase forms</span></label>
            <div class="master-actions">
                <button type="submit" class="master-btn master-btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add code</button>
            </div>
        </form>

        <section class="master-card master-card--flat master-section">
            <div class="master-section-head"><div>
                <h2 class="master-section-title">The master</h2>
                <p class="master-sub">{{ $codes->count() }} {{ \Illuminate\Support\Str::plural('code', $codes->count()) }}
                    — editing a rate here never re-prices an invoice that already exists.</p>
            </div></div>
            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th>Code</th><th>Kind</th><th>Description</th>
                            <th class="is-num">GST %</th><th>Offered</th><th class="is-act">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($codes as $code)
                            <tr>
                                <td data-label="Code"><code class="set-hsn-code">{{ $code->code }}</code></td>
                                <td data-label="Kind">{{ $code->kind === 'service' ? 'Service (SAC)' : 'Goods (HSN)' }}</td>
                                <td data-label="Description">{{ $code->description }}</td>
                                <td data-label="GST %" class="is-num">
                                    <form method="POST" action="{{ route('settings.taxes.hsn.update', $code) }}" class="set-hsn-inline">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="code" value="{{ $code->code }}">
                                        <input type="hidden" name="kind" value="{{ $code->kind }}">
                                        <input type="hidden" name="description" value="{{ $code->description }}">
                                        <input type="hidden" name="sort_order" value="{{ $code->sort_order }}">
                                        <input class="master-input" type="number" name="gst_rate" step="0.01" min="0" max="99.99"
                                            value="{{ number_format($code->gst_rate, 2, '.', '') }}" aria-label="GST rate for {{ $code->code }}">
                                        <label class="master-check"><input type="checkbox" name="is_active" value="1"
                                            @checked($code->is_active) aria-label="Offer {{ $code->code }} on forms"></label>
                                        <button type="submit" class="master-btn master-btn-light master-btn-sm">
                                            <i class="fa-solid fa-check" aria-hidden="true"></i> Save</button>
                                    </form>
                                </td>
                                <td data-label="Offered">{{ $code->is_active ? 'Yes' : 'No' }}</td>
                                <td data-label="Actions" class="is-act">
                                    <form method="POST" action="{{ route('settings.taxes.hsn.destroy', $code) }}"
                                        onsubmit="return confirm('Remove HSN/SAC {{ $code->code }} from the master? Saved invoice lines are untouched.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="master-btn master-btn-light master-btn-sm">
                                            <i class="fa-solid fa-trash" aria-hidden="true"></i> Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="is-empty">No codes yet — add the first one above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="master-alert"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>
                The seeded rates follow the current GST notifications for packaging goods and job-work services.
                When a notification changes a rate, edit the row — invoice forms stop offering the old rate at once,
                and saved invoices are untouched.</span></div>
        </section>
    </div>
</div>
@endsection

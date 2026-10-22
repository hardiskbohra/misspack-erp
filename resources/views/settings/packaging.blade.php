@extends('layouts.app')

@section('title', 'Settings · Packaging lists')
@section('page-title', 'Settings · Packaging lists')

@section('page-actions')
    <button type="submit" form="packagingSettingsForm" class="master-btn master-btn-primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Save settings</button>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/settings.css') }}">
@endpush
<div class="set master-list">
    @include('settings.partials.nav', ['current' => 'packaging'])
    <div class="set-area">
        <div class="master-card master-card--flat set-head">
            <span class="set-head-mark" aria-hidden="true"><i class="fa-solid fa-boxes-stacked"></i></span>
            <div><h1 class="set-head-title">Packaging lists</h1><p class="master-sub">Global defaults for every packaging list’s carton labels.</p></div>
        </div>
        <form id="packagingSettingsForm" method="POST" action="{{ route('settings.packaging.update') }}" class="master-form">
            @csrf
            @method('PUT')
            <section class="master-card master-card--flat master-section">
                <div class="master-section-head"><div><h2 class="master-section-title">QR code link</h2><p class="master-sub">Choose where a scan of a carton label should open.</p></div></div>
                <div class="master-field">
                    <label class="master-label" for="packagingLabelQrUrl">Destination URL</label>
                    <input class="master-input" type="url" id="packagingLabelQrUrl" name="label_qr_url" maxlength="200"
                        value="{{ old('label_qr_url', $settings['label_qr_url']) }}" placeholder="https://your-website.example/packaging" aria-describedby="packagingQrHelp packagingQrError" @error('label_qr_url') aria-invalid="true" @enderror>
                    <p class="master-help" id="packagingQrHelp">Leave blank to open each carton’s own packaging-list record in the ERP (login required). A custom URL is used exactly as entered for all cartons, including vendor-sender labels, on A4, A5, 100 × 140 mm and 100 × 150 mm labels, in single and batch prints.</p>
                    <p class="master-help">Use a short HTTP or HTTPS link, up to 200 bytes (normally 200 characters), so it fits the printed QR code. No carton number or tracking data is appended.</p>
                    <p class="master-field-error" id="packagingQrError">@error('label_qr_url'){{ $message }}@enderror</p>
                </div>
                <div class="master-alert"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Changes apply when labels are generated again. Already printed labels and downloaded PDFs keep their original QR link. Paper sizes, barcodes and print counts are unchanged.</span></div>
                <div class="master-actions"><button type="submit" class="master-btn master-btn-primary">Save settings</button></div>
            </section>
        </form>
    </div>
</div>
@endsection

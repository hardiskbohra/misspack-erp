@extends('layouts.app')

@section('title', 'Settings · Fixed assets')
@section('page-title', 'Settings · Fixed assets')

@section('content')
    @push('styles')
        <link rel="stylesheet" href="{{ $assetVer('assets/css/assets.css') }}">
        <link rel="stylesheet" href="{{ $assetVer('assets/css/settings.css') }}">
    @endpush
    <div class="set master-list">

        @include('settings.partials.nav', ['current' => 'assets'])

        <div class="set-area">

            @if ($errors->any())
                <div class="master-info-box is-danger" role="alert">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="fixed-assets ast-settings">
                <div class="master-card master-card--flat set-head">
                    <span class="set-head-mark" aria-hidden="true"><i class="fa-solid fa-calculator"></i></span>
                    <div>
                        <h1 class="set-head-title">Fixed assets</h1>
                        <p class="master-sub">
                            The classes the company's assets are grouped by — and the depreciation recipe each class
                            hands to the assets in it: the useful life, the method and the value left at the end.
                        </p>
                    </div>
                    <div class="set-head-actions">
                        <a href="{{ route('assets.index') }}" class="master-btn master-btn-light">Back to the register</a>
                    </div>
                </div>

                {{-- The boundary, said where it is easiest to get wrong: the classes
                     are a setting because they change how every asset in them is
                     depreciated; the assets are not, because they are records. --}}
                <div class="master-info-box">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>
                        A class is the recipe, and an asset follows its class until somebody overrides it on the asset
                        itself. Changing a life or a method here changes how <strong>every asset that follows the
                        class</strong> is written down from now on — assets with their own recipe are untouched.
                        The chairs themselves live in
                        <a href="{{ route('assets.index') }}">the register</a>, not here.
                    </span>
                </div>

                <div class="master-card master-card--flat ast-cat-card">
                    <div class="ast-cat-head">
                        <div>
                            <p class="master-eyebrow">The classes</p>
                            <h2 class="master-section-title">{{ number_format($categories->count()) }} on file</h2>
                            <p class="master-sub">
                                Ordered the way the register groups them. A class that is holding assets cannot be
                                removed — the assets would be left without a recipe.
                            </p>
                        </div>
                        <button type="button" class="master-btn master-btn-primary" data-open-asset-modal="category">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i> Add a class
                        </button>
                    </div>

                    @if ($categories->isEmpty())
                        <div class="master-list-empty">
                            <span class="master-list-empty-icon" aria-hidden="true">📐</span>
                            <h3 class="master-list-empty-title">No classes yet</h3>
                            <p class="master-list-empty-text">
                                Add the first one — "Furniture &amp; fixtures, 10 years, straight line, 5% residual" is
                                the usual place to start. Assets registered against a class inherit its life, method and
                                residual value, and can still override any of them.
                            </p>
                        </div>
                    @else
                        <div class="ast-cat-list">
                            @foreach ($categories as $category)
                                <form method="POST" action="{{ route('settings.assets.update', $category) }}" class="ast-cat-row">
                                    @csrf
                                    @method('PUT')

                                    <div class="ast-cat-name">
                                        <span class="ast-code">{{ $category->code }}</span>
                                        <input class="master-input" name="name" value="{{ $category->name }}" required
                                            maxlength="120" aria-label="Class name">
                                        @if ($category->notes)
                                            <span class="ast-cell-sub">{{ $category->notes }}</span>
                                        @endif
                                    </div>

                                    <label class="ast-cat-field">
                                        <span class="master-label">Useful life</span>
                                        <input class="master-input" type="number" name="useful_life_years" min="1" max="100"
                                            value="{{ $category->useful_life_years }}" required>
                                        <span class="ast-cell-sub">years</span>
                                    </label>

                                    <label class="ast-cat-field">
                                        <span class="master-label">Method</span>
                                        <select class="master-select" name="depreciation_method">
                                            @foreach ($methodOptions as $key => $label)
                                                <option value="{{ $key }}" @selected($category->depreciation_method === $key)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="ast-cell-sub">{{ $category->recipeLabel() }}</span>
                                    </label>

                                    <label class="ast-cat-field">
                                        <span class="master-label">Residual</span>
                                        <input class="master-input" type="number" step="0.01" min="0" max="100"
                                            name="residual_percent" value="{{ $category->residual_percent }}">
                                        <span class="ast-cell-sub">% of cost</span>
                                    </label>

                                    <label class="ast-cat-field">
                                        <span class="master-label">Sort</span>
                                        <input class="master-input" type="number" name="sort_order" min="0" max="9999"
                                            value="{{ $category->sort_order }}">
                                    </label>

                                    <label class="ast-cat-field">
                                        <span class="master-label">Notes</span>
                                        <input class="master-input" name="notes" maxlength="255" value="{{ $category->notes }}"
                                            placeholder="Schedule II reference, why this life">
                                    </label>

                                    <div class="ast-cat-state">
                                        <label class="master-check">
                                            <input type="checkbox" name="is_active" value="1" @checked($category->is_active)>
                                            Active
                                        </label>
                                        <span class="ast-cell-sub">
                                            {{ number_format($category->assets_count) }}
                                            {{ \Illuminate\Support\Str::plural('asset', $category->assets_count) }}
                                            in it
                                        </span>
                                    </div>

                                    <div class="ast-cat-actions">
                                        <button class="master-btn master-btn-primary" type="submit">Save</button>
                                        <button class="master-btn master-btn-danger" type="submit"
                                            formaction="{{ route('settings.assets.destroy', $category) }}"
                                            name="_method" value="DELETE"
                                            data-confirm="{{ $category->assets_count > 0
                                                ? 'This class is holding '.$category->assets_count.' asset(s). Move them to another class first — the register refuses to remove a class that is in use.'
                                                : 'Delete the '.$category->code.' class? Nothing is using it.' }}"
                                            data-confirm-title="Remove the class"
                                            data-confirm-text="Remove it">
                                            Delete
                                        </button>
                                    </div>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- A new class, in a dialog: five fields and a recipe, and the page behind it
         keeps its list. `data-open-dialog` reopens it when a save fails. --}}
    <span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>

    <div class="master-modal" id="assetCategoryModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="assetCategoryTitle">
            <form method="POST" action="{{ route('settings.assets.store') }}" data-asset-form="category">
                @csrf
                <input type="hidden" name="_dialog" value="assetCategoryModal">

                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-calculator"></i></span>
                        <div>
                            <h3 class="master-modal-title" id="assetCategoryTitle">A class of assets</h3>
                            <p class="master-modal-subtitle">
                                Furniture &amp; fixtures, computers, plant &amp; machinery — and the recipe they
                                depreciate by.
                            </p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="assetCategoryModal"
                        aria-label="Close">&times;</button>
                </div>

                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field">
                            <label class="master-label" for="categoryCode">Code
                                <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="categoryCode" name="code" required maxlength="40"
                                value="{{ old('code') }}" placeholder="e.g. FURN, COMP, PLANT">
                        </div>

                        <div class="master-field">
                            <label class="master-label" for="categoryName">Name
                                <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="categoryName" name="name" required maxlength="120"
                                value="{{ old('name') }}" placeholder="e.g. Furniture &amp; fixtures">
                        </div>

                        <div class="master-field">
                            <label class="master-label" for="categoryLife">Useful life
                                <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="categoryLife" type="number" name="useful_life_years" min="1"
                                max="100" required value="{{ old('useful_life_years', 10) }}">
                            <span class="master-help">Years — Schedule II's own lives are a good default.</span>
                        </div>

                        <div class="master-field">
                            <label class="master-label" for="categoryMethod">Depreciation method</label>
                            <select class="master-select" id="categoryMethod" name="depreciation_method">
                                @foreach ($methodOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('depreciation_method', 'straight_line') === $key)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="master-field">
                            <label class="master-label" for="categoryResidual">Residual value</label>
                            <input class="master-input" id="categoryResidual" type="number" step="0.01" min="0" max="100"
                                name="residual_percent" value="{{ old('residual_percent', $defaultResidual) }}">
                            <span class="master-help">Percentage of cost left at the end of the life.</span>
                        </div>

                        <div class="master-field">
                            <label class="master-label" for="categorySort">Sort order</label>
                            <input class="master-input" id="categorySort" type="number" min="0" max="9999" name="sort_order"
                                value="{{ old('sort_order', 10) }}">
                        </div>

                        <div class="master-field full">
                            <label class="master-label" for="categoryNotes">Notes</label>
                            <input class="master-input" id="categoryNotes" name="notes" maxlength="255"
                                value="{{ old('notes') }}" placeholder="Why this life, which statute it comes from">
                        </div>
                    </div>
                </div>

                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="assetCategoryModal">Cancel</button>
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add the class
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/assets.js') }}" defer></script>
@endpush

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

                {{-- The list is a table, like every other list in this application,
                     and the card carrying it carries no padding of its own: the
                     toolbar has its inset, the table is flush to the card's edges. --}}
                <section class="master-card master-table-card master-card--flat" aria-label="Asset classes">
                    <div class="master-list-toolbar">
                        <p class="master-list-hint"
                            title="A class hands its useful life, method and residual value to every asset in it. A class that is holding assets cannot be removed — the register would be left without a recipe.">
                            {{ number_format($categories->count()) }}
                            {{ \Illuminate\Support\Str::plural('class', $categories->count()) }} on file, ordered the
                            way the register groups them
                        </p>

                        <div class="master-list-toolbar-actions">
                            {{-- One dialog, opened as "add" here and as "change" from a
                                 row: the door names where it posts and which verb, and
                                 the dialog's own words come from the door too — so the
                                 same dialog says the right thing in both places. --}}
                            <button type="button" class="master-btn master-btn-light master-btn-sm"
                                data-open-asset-modal="category"
                                data-action="{{ route('settings.assets.store') }}"
                                data-method="POST"
                                data-subject="A class of assets"
                                data-current="Furniture &amp; fixtures, computers, plant &amp; machinery — and the recipe they depreciate by."
                                data-submit="Add the class">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add a class
                            </button>
                        </div>
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
                            <div class="master-list-empty-actions">
                                <button type="button" class="master-btn master-btn-primary"
                                    data-open-asset-modal="category"
                                    data-action="{{ route('settings.assets.store') }}"
                                    data-method="POST"
                                    data-subject="A class of assets"
                                    data-current="Furniture &amp; fixtures, computers, plant &amp; machinery — and the recipe they depreciate by."
                                    data-submit="Add the class">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add a class
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="master-table-wrap ui-mobile-cards">
                            <table class="master-table ast-cat-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Class</th>
                                        <th scope="col">Useful life</th>
                                        <th scope="col">Method</th>
                                        <th scope="col" class="ast-col-num">Residual</th>
                                        <th scope="col" class="ast-col-num">Sort</th>
                                        <th scope="col">Assets</th>
                                        <th scope="col" class="ast-col-state">State</th>
                                        <th scope="col" class="ast-col-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($categories as $category)
                                        {{-- The row's own values, handed to the dialog that
                                             edits it. Everything the class's validator
                                             writes is here: a form that carries only part
                                             of a record cannot save that record. --}}
                                        @php
                                            $payload = [
                                                'code' => $category->code,
                                                'name' => $category->name,
                                                'useful_life_years' => $category->useful_life_years,
                                                'depreciation_method' => $category->depreciation_method,
                                                'residual_percent' => $category->residual_percent,
                                                'sort_order' => $category->sort_order,
                                                'notes' => $category->notes,
                                                'is_active' => $category->is_active,
                                            ];
                                        @endphp
                                        <tr>
                                            <td data-label="Class">
                                                <span class="ast-code ast-mono">{{ $category->code }}</span>
                                                <strong class="ast-cat-name">{{ $category->name }}</strong>
                                                @if ($category->notes)
                                                    <span class="ast-cell-sub">{{ $category->notes }}</span>
                                                @endif
                                            </td>

                                            <td data-label="Useful life">{{ $category->lifeLabel() }}</td>

                                            <td data-label="Method">{{ $category->methodLabel() }}</td>

                                            <td data-label="Residual" class="ast-col-num ast-money">
                                                {{ $category->residualLabel() }}%
                                            </td>

                                            <td data-label="Sort" class="ast-col-num ui-mobile-secondary">
                                                {{ $category->sort_order }}
                                            </td>

                                            {{-- The count is a door, not a number: it opens the
                                                 register filtered to this class, which is the
                                                 question the number raises. --}}
                                            <td data-label="Assets">
                                                @if ($category->assets_count > 0)
                                                    <a class="ast-asset-link"
                                                        href="{{ route('assets.index', ['category' => $category->id]) }}">
                                                        {{ number_format($category->assets_count) }}
                                                        {{ \Illuminate\Support\Str::plural('asset', $category->assets_count) }}
                                                    </a>
                                                @else
                                                    <span class="ast-cell-sub">none yet</span>
                                                @endif
                                            </td>

                                            <td data-label="State" class="ast-col-state">
                                                <span class="core-badge core-badge-{{ $category->stateTone() }}">
                                                    {{ $category->stateLabel() }}
                                                </span>
                                            </td>

                                            <td class="ast-col-actions" data-label="Actions">
                                                <button type="button" class="master-btn master-btn-soft master-btn-sm"
                                                    data-open-asset-modal="category"
                                                    data-action="{{ route('settings.assets.update', $category) }}"
                                                    data-method="PUT"
                                                    data-record="{{ $category->id }}"
                                                    data-payload='@json($payload)'
                                                    data-subject="{{ $category->code }} · {{ $category->name }}"
                                                    data-current="{{ $category->recipeLabel() }}"
                                                    data-submit="Save the class">
                                                    Change
                                                </button>

                                                <form method="POST"
                                                    action="{{ route('settings.assets.destroy', $category) }}"
                                                    class="ast-inline-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="master-btn master-btn-ghost master-btn-sm" type="submit"
                                                        data-confirm="{{ $category->assets_count > 0
                                                            ? 'This class is holding '.$category->assets_count.' asset(s). Move them to another class first — the register refuses to remove a class that is in use.'
                                                            : 'Delete the '.$category->code.' class? Nothing is using it.' }}"
                                                        data-confirm-title="Remove the class"
                                                        data-confirm-text="Remove it">
                                                        Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>

    {{-- One dialog for both doors. It carries **every field the class validator
         writes** — including the code, which is what a form missing it would fail
         on — and the address it posts to comes from the door, not from here. --}}
    <span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>

    <div class="master-modal" id="assetCategoryModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="assetCategoryTitle">
            <form method="POST" action="{{ route('settings.assets.store') }}" data-asset-form="category">
                @csrf

                {{-- Which row this save was for. The office never sees it; a save
                     that fails comes back with it in `old()`, so the page can
                     reopen *that row's* dialog rather than a nameless one. --}}
                <input type="hidden" name="_record" value="{{ old('_record') }}">
                <input type="hidden" name="_dialog" value="category">

                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-calculator"></i></span>
                        <div>
                            <h3 class="master-modal-title" id="assetCategoryTitle" data-asset-subject>A class of assets</h3>
                            <p class="master-modal-subtitle" data-asset-current>
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
                            <span class="master-help">The register's grouping key. Letters, digits and dashes.</span>
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
                            <span class="master-help">Where this class sits in the register's grouping.</span>
                        </div>

                        <div class="master-field">
                            <span class="master-label">State</span>
                            {{-- The unchecked answer has to travel too: a checkbox on its
                                 own says nothing when it is cleared, and the class would
                                 quietly come back as active. --}}
                            <label class="master-check">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1"
                                    @checked(old('is_active', true))>
                                In use — offered to new assets
                            </label>
                            <span class="master-help">Retire a class to keep it out of the pickers without losing its history.</span>
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
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        <span data-asset-submit>Add the class</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/assets.js') }}" defer></script>
@endpush

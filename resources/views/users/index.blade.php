@extends('layouts.app')

@section('title', 'Team')
@section('page-title', 'Team')

@section('page-actions')
    {{-- The page's primary action lives in the header, so it stays reachable
         however far the list scrolls — the same slot every other list uses. --}}
    <button type="button" class="master-btn master-btn-primary" data-open-add-modal>
        <i class="fas fa-plus" aria-hidden="true"></i> Add user
    </button>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/users.css') }}">
@endpush

@php
    /* A failed save on the edit form comes back here with the person's id in the
       query (`UserController::update`), because its dialog opens over the list
       and the redirect has to land somewhere the dialog can reopen on. */
    $reopenUserId = $errors->any() ? max(0, (int) request()->query('edit')) : 0;

    /* One URL per removable filter: everything else stays, the page restarts,
       and a filter that has no visible control above (it arrived from a URL or
       a bookmark) can still be taken off. */
    $chipUrl = function (string $key) {
        $keep = collect(request()->except([$key, 'page']))
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

        return route('users.index', $keep->all());
    };

    $baseFilters = request()->except(['page']);
    $roleLabels = ['all' => 'Everyone'] + $roles;
@endphp

<div class="users user-index master-list">

    {{-- The five figures the office actually has about its people. They count
         the module, not the page: a tile that changed with the filter would be
         a tile you cannot trust. --}}
    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true">👥</span>
            <div>
                <p class="master-stat-title">People</p>
                <p class="master-stat-value">{{ $stats['total'] }}</p>
                <p class="master-sub">{{ $stats['employees'] }} employee{{ $stats['employees'] === 1 ? '' : 's' }} · {{ $stats['admins'] }} office</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat purple">
            <span class="icon" aria-hidden="true">🏷</span>
            <div>
                <p class="master-stat-title">Employee codes</p>
                <p class="master-stat-value">{{ $stats['with_code'] }}</p>
                <p class="master-sub">of {{ $stats['employees'] }} employee{{ $stats['employees'] === 1 ? '' : 's' }} on the payroll</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat green">
            <span class="icon" aria-hidden="true">₹</span>
            <div>
                <p class="master-stat-title">Paid in {{ $stats['month_label'] }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($stats['paid_this_month']) }}</p>
                <p class="master-sub">salary credited this month</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat teal">
            <span class="icon" aria-hidden="true">📄</span>
            <div>
                <p class="master-stat-title">Documents</p>
                <p class="master-stat-value">{{ $stats['documents'] }}</p>
                <p class="master-sub">papers filed for the team</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $stats['missing_id_proof'] ? 'orange' : 'green' }}">
            <span class="icon" aria-hidden="true">{{ $stats['missing_id_proof'] ? '!' : '✓' }}</span>
            <div>
                <p class="master-stat-title">No ID proof</p>
                <p class="master-stat-value">{{ $stats['missing_id_proof'] }}</p>
                <p class="master-sub">employee{{ $stats['missing_id_proof'] === 1 ? '' : 's' }} still to file one</p>
            </div>
        </div>
    </div>

    <div class="master-card master-card--flat">
        <div class="master-list-bar">
            <div class="master-list-chips">
                @foreach ($roleLabels as $key => $label)
                    <a class="master-list-chip {{ ($role ?: 'all') === $key ? 'is-active' : '' }}"
                        href="{{ route('users.index', collect(request()->except(['role', 'page']))->reject(fn ($v) => $v === null || $v === '' || $v === 'all')->all() + (array_key_exists($key, $roles) ? ['role' => $key] : [])) }}">
                        {{ $key === 'admin' ? 'Office / admins' : ($key === 'employee' ? 'Employees' : $label) }}
                        <span class="master-list-chip-count">{{ $roleCounts[$key] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route('users.index') }}">
            {{-- The role is a chip, and the chips are links: without this the
                 chip would be silently dropped every time the filters below
                 are applied. --}}
            @if ($availableFilters['role'] ?? true)
                <input type="hidden" name="role" value="{{ $role }}">
            @endif

            <div class="master-filter-row core-filter-toolbar">
                <div class="master-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="master-input" type="text" name="search" value="{{ $search }}"
                        placeholder="Name, email, mobile, code…" aria-label="Search the team">
                </div>
                <x-filter-trigger drawer="userFiltersDrawer" label="Filters" :count="count($filterChips)" />
            </div>

            <x-drawer id="userFiltersDrawer" title="Filter the team" eyebrow="Team filters"
                subtitle="Narrow the list by department, employment status, code, or joining period." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Employment details</h3>
                    <div class="core-drawer-fields">
                        @if ($availableFilters['department'] ?? true)
                            <div class="master-field">
                                <label class="master-label" for="userFilterDepartment">Department</label>
                                <select class="master-select" id="userFilterDepartment" name="department" aria-label="Filter by department">
                                    <option value="all">All departments</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department }}" @selected($department === ($filters['department'] ?? null))>{{ $department }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if ($availableFilters['status'] ?? true)
                            <div class="master-field">
                                <label class="master-label" for="userFilterStatus">Employment status</label>
                                <select class="master-select" id="userFilterStatus" name="status" aria-label="Filter by employment status">
                                    <option value="all">Any status</option>
                                    @foreach ($employmentStatuses as $key => $label)
                                        <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if ($availableFilters['code'] ?? true)
                            <div class="master-field">
                                <label class="master-label" for="userFilterCode">Employee code</label>
                                <select class="master-select" id="userFilterCode" name="code" aria-label="Filter by employee code">
                                    <option value="all">Code: any</option>
                                    <option value="present" @selected(($filters['code'] ?? null) === 'present')>Has a code</option>
                                    <option value="missing" @selected(($filters['code'] ?? null) === 'missing')>No code yet</option>
                                </select>
                            </div>
                        @endif

                        @if ($availableFilters['joined'] ?? true)
                            <div class="master-field">
                                <label class="master-label" for="userFilterJoined">Joining period</label>
                                <select class="master-select" id="userFilterJoined" name="joined" aria-label="Filter by joining period">
                                    <option value="all">Joined: any time</option>
                                    @foreach (\App\Helpers\DateRanges::LABELS as $key => $label)
                                        <option value="{{ $key }}" @selected(($filters['joined'] ?? null) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                </section>
                <x-slot:footer>
                    @if ($filtersActive)
                        <a class="master-btn master-btn-soft" href="{{ route('users.index') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </x-slot:footer>
            </x-drawer>

            {{-- What is actually filtering, one removable chip each. Built from
                 the controller's own list, so a filter that can be applied can
                 always be taken off. --}}
            @if ($filtersActive)
                <div class="master-list-applied">
                    <span class="master-list-applied-title">Filtered by</span>

                    @foreach ($filterChips as $chip)
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">{{ $chip['label'] }}</span>
                            <span class="master-list-applied-value">{{ $chip['value'] }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl($chip['key']) }}"
                                aria-label="Remove the {{ strtolower($chip['label']) }} filter"
                                title="Remove the {{ strtolower($chip['label']) }} filter">&times;</a>
                        </span>
                    @endforeach

                    <a class="master-list-applied-clear" href="{{ route('users.index') }}">Clear all filters</a>
                </div>
            @endif
        </form>
    </div>

    <div class="master-card master-table-card master-card--flat">
        <div class="master-list-toolbar">
            <p class="master-list-hint"
                title="Employees first, then the office accounts, each block by name. An employee sees only their own workspace; an administrator sees the whole ERP.">
                Employees first &middot; office accounts below
            </p>

            <div class="master-list-toolbar-actions">
                {{-- The list as a spreadsheet, under the filters currently on
                     screen — the same rows, one click. --}}
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('users.export', $baseFilters) }}">
                    <i class="fas fa-file-csv" aria-hidden="true"></i> Export CSV
                </a>
                <div class="master-list-density desktop-only" role="group" aria-label="Table density">
                    <button type="button" class="master-list-density-btn" data-density="standard" aria-pressed="true">Standard</button>
                    <button type="button" class="master-list-density-btn" data-density="comfortable" aria-pressed="false">Comfortable</button>
                    <button type="button" class="master-list-density-btn" data-density="compact" aria-pressed="false">Compact</button>
                </div>
            </div>
        </div>

        <div class="master-table-wrap">
            <table class="master-table" data-table-settings data-table-key="users">
                <thead>
                    <tr>
                        <th scope="col">Person</th>
                        <th scope="col">Role</th>
                        <th scope="col" class="ui-mobile-secondary">Contact</th>
                        <th scope="col" class="ui-mobile-secondary">Department</th>
                        <th scope="col" class="ui-mobile-secondary">Designation</th>
                        <th scope="col" class="ui-mobile-secondary">Joined</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php($officeDividerShown = false)
                    @forelse ($users as $user)
                        @if (! $officeDividerShown && $user->isAdmin())
                            @php($officeDividerShown = true)
                            <tr class="master-list-group">
                                {{-- the cell stays a table cell: display:flex on a
                                     <td> takes it out of the table layout and the
                                     colspan stops spanning, so the row lives in a
                                     flex wrapper inside it --}}
                                <td colspan="8">
                                    <div class="master-list-group-inner">
                                        <span>Office accounts — full access to the ERP</span>
                                        <span class="master-list-group-count">
                                            {{ $users->where('role', '!=', \App\Models\User::ROLE_EMPLOYEE)->count() }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endif

                        {{-- The whole row opens the record (assets/js/users.js →
                             MasterList.rowNavigation); anything interactive
                             inside it keeps its own click. --}}
                        <tr class="user-row is-clickable" data-href="{{ route('users.show', $user) }}">
                            <td data-label="Person">
                                <a class="user-cell" href="{{ route('users.show', $user) }}">
                                    <span class="user-avatar-mini" aria-hidden="true">{{ $user->initials ?: strtoupper(substr($user->name, 0, 2)) }}</span>
                                    <span class="user-cell-text">
                                        <span class="user-cell-name">{{ $user->name }}</span>
                                        <span class="master-sub">
                                            {{ $user->employee_code ?: 'No employee code' }}
                                            @if ($user->email_verified_at) · verified @endif
                                        </span>
                                    </span>
                                </a>
                            </td>
                            <td data-label="Role">
                                <span class="emp-pill {{ $user->isAdmin() ? 'is-info' : 'is-ok' }}">{{ $user->roleLabel() }}</span>
                            </td>
                            <td data-label="Contact" class="ui-mobile-secondary">
                                {{ $user->mobile ?: '—' }}
                                <span class="master-sub">{{ $user->email }}</span>
                            </td>
                            <td data-label="Department" class="ui-mobile-secondary">{{ $user->department ?: '—' }}</td>
                            <td data-label="Designation" class="ui-mobile-secondary">{{ $user->designation ?: '—' }}</td>
                            <td data-label="Joined" class="ui-mobile-secondary">{{ $user->date_of_joining?->format('d M Y') ?: '—' }}</td>
                            <td data-label="Status">
                                @if ($user->isEmployee())
                                    <span class="emp-pill is-{{ $user->employmentStatusTone() === 'ok' ? 'ok' : ($user->employmentStatusTone() === 'warn' ? 'warn' : 'off') }}">
                                        {{ $user->employmentStatusLabel() }}
                                    </span>
                                    <span class="master-sub ui-mobile-secondary">
                                        {{ (int) $user->payslips_count }} {{ \Illuminate\Support\Str::plural('payslip', (int) $user->payslips_count) }}
                                        · {{ (int) $user->employee_documents_count }} {{ \Illuminate\Support\Str::plural('document', (int) $user->employee_documents_count) }}
                                    </span>
                                @else
                                    <span class="emp-pill is-off">Office access</span>
                                @endif
                            </td>
                            <td data-label="Action">
                                <div class="master-row-actions">
                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $user->name }}"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="master-dropdown-menu">
                                            <a href="{{ route('users.show', $user) }}">
                                                <i class="fas fa-folder-open"></i>
                                                Open record
                                            </a>

                                            <button type="button" data-edit-user="{{ $user->id }}">
                                                <i class="fas fa-pen"></i>
                                                Edit user
                                            </button>

                                            <a href="{{ route('users.show', [$user, 'tab' => 'salary']) }}">
                                                <i class="fas fa-file-invoice-dollar"></i>
                                                Payslips &amp; pay
                                            </a>

                                            <a href="{{ route('users.show', [$user, 'tab' => 'documents']) }}">
                                                <i class="fas fa-folder-open"></i>
                                                Documents
                                            </a>

                                            {{-- Nobody deletes themselves, and the last
                                                 administrator cannot be deleted — the
                                                 controller refuses both, so the menu
                                                 does not offer what it cannot do. --}}
                                            @if ((int) $user->id !== (int) auth()->id() && ! ($user->isAdmin() && ($roleCounts['admin'] ?? 0) <= 1))
                                                <button type="button" class="danger" data-delete-user="{{ $user->id }}"
                                                    data-delete-name="{{ $user->name }}">
                                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                                    Delete user
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">👥</span>
                                    <p class="master-list-empty-title">
                                        {{ $filtersActive ? 'Nobody matches these filters' : 'No users yet' }}
                                    </p>
                                    <p class="master-list-empty-text">
                                        {{ $filtersActive
                                            ? 'Adjust the search or the filters above — the counts on the role chips show what each side of the team holds.'
                                            : 'Add the first person. An employee account gets their own workspace — profile, salary, payslips and documents.' }}
                                    </p>
                                    <div class="master-list-empty-actions">
                                        @if ($filtersActive)
                                            <a class="master-btn master-btn-soft" href="{{ route('users.index') }}">Clear filters</a>
                                        @endif
                                        <button type="button" class="master-btn master-btn-primary" data-open-add-modal>+ Add user</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="master-list-total">
                        <td colspan="7">
                            <strong>Total — {{ $users->count() }} {{ \Illuminate\Support\Str::plural('person', $users->count()) }} shown</strong>
                            <span class="master-sub">{{ $filtersActive ? 'of ' . $stats['total'] . ' in the team, with these filters' : $stats['employees'] . ' on the payroll' }}</span>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <x-pagination :items="$users" />
    </div>

    {{-- ================= Add user =================
         The shared modal sheet (.master-modal → .master-modal-card →
         .master-modal-body): header and footer pinned, the body carries the
         scroll, so the fields are reachable and the action row is always in
         view. The form is the card's own child, which is what the sheet's
         column layout hangs on — a modal without that pair is a sheet that
         does not scroll. --}}
    <div class="master-modal" id="addModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="addUserTitle">
            <form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data" id="addForm">
                @csrf

                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon" aria-hidden="true">＋</span>
                        <div>
                            <h3 class="master-modal-title" id="addUserTitle">Add a user</h3>
                            <p class="master-modal-subtitle">The role decides which application they see</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="addModal" aria-label="Close">&times;</button>
                </div>

                <div class="master-modal-body">
                    @if ($errors->any())
                        <div class="master-info-box is-danger">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="master-section-label">Who is this?</div>
                    <div class="master-form-grid">
                        <div class="master-field full">
                            <label class="master-label" for="addRole">Role <span class="master-required">*</span></label>
                            <select name="role" id="addRole" class="master-select" data-role-select required>
                                @foreach ($roles as $key => $label)
                                    <option value="{{ $key }}" @selected(old('role', 'employee') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="master-info-box">
                                <i class="fas fa-info-circle" aria-hidden="true"></i>
                                <span data-role-hint-text>
                                    An employee sees only their own workspace: profile, salary, payslips and documents.
                                </span>
                            </p>
                            @error('role')<span class="master-error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="master-section-label">Basic information</div>
                    <div class="master-form-grid">
                        <div class="master-field">
                            <label class="master-label" for="addName">Full name <span class="master-required">*</span></label>
                            <input type="text" name="name" id="addName" class="master-input {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                placeholder="e.g. Ramesh Patel" value="{{ old('name') }}" required>
                            @error('name')<span class="master-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addEmail">Email address <span class="master-required">*</span></label>
                            <input type="email" name="email" id="addEmail" class="master-input {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                placeholder="name@misspack.com" value="{{ old('email') }}" required>
                            @error('email')<span class="master-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addMobile">Mobile <span class="master-required" data-role-required>*</span></label>
                            <input type="text" name="mobile" id="addMobile" class="master-input" placeholder="+91 98765 43210" value="{{ old('mobile') }}">
                            @error('mobile')<span class="master-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addCode">Employee code</label>
                            <input type="text" name="employee_code" id="addCode" class="master-input"
                                placeholder="Left blank: EMP-0001" value="{{ old('employee_code') }}">
                            @error('employee_code')<span class="master-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addDepartment">Department</label>
                            <input type="text" name="department" id="addDepartment" class="master-input" list="departmentList"
                                placeholder="Technology" value="{{ old('department') }}">
                            <datalist id="departmentList">
                                @foreach ($departments as $department)
                                    <option value="{{ $department }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addDesignation">Designation <span class="master-required" data-role-required>*</span></label>
                            <input type="text" name="designation" id="addDesignation" class="master-input" placeholder="Senior Developer" value="{{ old('designation') }}">
                            @error('designation')<span class="master-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addJoining">Date of joining <span class="master-required" data-role-required>*</span></label>
                            <input type="date" name="date_of_joining" id="addJoining" class="master-input" value="{{ old('date_of_joining') }}">
                            @error('date_of_joining')<span class="master-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addType">Employment type</label>
                            <select name="employment_type" id="addType" class="master-select">
                                <option value="">—</option>
                                @foreach ($employmentTypes as $key => $label)
                                    <option value="{{ $key }}" @selected(old('employment_type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addStatus">Status</label>
                            <select name="employment_status" id="addStatus" class="master-select">
                                @foreach ($employmentStatuses as $key => $label)
                                    <option value="{{ $key }}" @selected(old('employment_status', 'active') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addPan">PAN</label>
                            <input type="text" name="pan_number" id="addPan" class="master-input" value="{{ old('pan_number') }}">
                        </div>
                    </div>

                    <div class="master-section-label">Photo</div>
                    <div class="master-avatar-row" data-avatar-drop data-avatar-circle="addAvatarCircle"
                        data-avatar-name="addAvName" data-avatar-remove="clear">
                        <span class="master-avatar-picker">
                            <label class="master-avatar-preview" id="addAvatarCircle" for="addAvatarFile"
                                aria-label="Choose a photo" title="Choose a photo">
                                <i class="fas fa-user" aria-hidden="true"></i>
                            </label>
                            <span class="master-avatar-camera" aria-hidden="true"><i class="fas fa-camera"></i></span>
                        </span>
                        <div class="master-avatar-info">
                            <p>JPG, PNG, GIF or WEBP, up to 2 MB. Click the circle or drag a photo onto it.</p>
                            <div class="master-avatar-actions">
                                <label class="master-upload-btn" for="addAvatarFile">
                                    <i class="fas fa-upload" aria-hidden="true"></i> Choose file
                                    <input class="master-input" type="file" id="addAvatarFile" name="avatar"
                                        accept="image/jpeg,image/png,image/gif,image/webp">
                                </label>
                                <button type="button" class="master-remove-avatar-btn" hidden>
                                    <i class="fas fa-times" aria-hidden="true"></i> Clear
                                </button>
                                <span class="master-file-name" id="addAvName">No file chosen</span>
                            </div>
                        </div>
                    </div>

                    <div class="master-section-label">Account security</div>
                    <p class="master-sub">They sign in with this password, and can change it themselves later.</p>
                    <div class="master-form-grid">
                        <div class="master-field">
                            <label class="master-label" for="addPw1">Password <span class="master-required">*</span></label>
                            <div class="master-password-wrap">
                                <input type="password" name="password" id="addPw1" class="master-input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                                    placeholder="Min. 6 characters" required autocomplete="new-password">
                                <button type="button" class="master-password-toggle" data-toggle-password="addPw1" aria-label="Toggle password visibility"><i class="fas fa-eye" aria-hidden="true"></i></button>
                            </div>
                            @error('password')<span class="master-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addPw2">Confirm password <span class="master-required">*</span></label>
                            <div class="master-password-wrap">
                                <input type="password" name="password_confirmation" id="addPw2" class="master-input" placeholder="Repeat password" required autocomplete="new-password">
                                <button type="button" class="master-password-toggle" data-toggle-password="addPw2" aria-label="Toggle password visibility"><i class="fas fa-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="addModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary"><i class="fas fa-user-plus" aria-hidden="true"></i> Add user</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ================= Edit user ================= --}}
    <div class="master-modal" id="editModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="editUserTitle">
            <form method="POST" id="editForm" enctype="multipart/form-data"
                action="{{ $reopenUserId ? route('users.update', $reopenUserId) : '' }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="remove_avatar" id="editRemoveAvatar" value="0">

                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon" aria-hidden="true">✎</span>
                        <div>
                            <h3 class="master-modal-title" id="editUserTitle">Edit user</h3>
                            <p class="master-modal-subtitle" id="editModalSub">{{ $reopenUserId && old('name') ? 'Update the record for '.old('name') : 'Update the record' }}</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="editModal" aria-label="Close">&times;</button>
                </div>

                <div class="master-modal-body">
                    <div class="master-section-label">Who is this?</div>
                    <div class="master-form-grid">
                        <div class="master-field full">
                            <label class="master-label" for="editRole">Role <span class="master-required">*</span></label>
                            <select name="role" id="editRole" class="master-select" data-role-select required @disabled($isSelf ?? false)>
                                @foreach ($roles as $key => $label)
                                    <option value="{{ $key }}" @selected(old('role') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="master-sub" id="editRoleNote"></p>
                        </div>
                    </div>

                    <div class="master-section-label">Basic information</div>
                    <div class="master-form-grid">
                        <div class="master-field">
                            <label class="master-label" for="editName">Full name <span class="master-required">*</span></label>
                            <input type="text" name="name" id="editName" class="master-input" value="{{ old('name') }}" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editEmail">Email address <span class="master-required">*</span></label>
                            <input type="email" name="email" id="editEmail" class="master-input" value="{{ old('email') }}" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editMobile">Mobile <span class="master-required" data-role-required>*</span></label>
                            <input type="text" name="mobile" id="editMobile" class="master-input" value="{{ old('mobile') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editCode">Employee code</label>
                            <input type="text" name="employee_code" id="editCode" class="master-input" value="{{ old('employee_code') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editDepartment">Department</label>
                            <input type="text" name="department" id="editDepartment" class="master-input" value="{{ old('department') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editDesignation">Designation <span class="master-required" data-role-required>*</span></label>
                            <input type="text" name="designation" id="editDesignation" class="master-input" value="{{ old('designation') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editJoining">Date of joining <span class="master-required" data-role-required>*</span></label>
                            <input type="date" name="date_of_joining" id="editJoining" class="master-input" value="{{ old('date_of_joining') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editType">Employment type</label>
                            <select name="employment_type" id="editType" class="master-select">
                                <option value="">—</option>
                                @foreach ($employmentTypes as $key => $label)
                                    <option value="{{ $key }}" @selected(old('employment_type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editStatus">Status</label>
                            <select name="employment_status" id="editStatus" class="master-select">
                                @foreach ($employmentStatuses as $key => $label)
                                    <option value="{{ $key }}" @selected(old('employment_status') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editPan">PAN</label>
                            <input type="text" name="pan_number" id="editPan" class="master-input" value="{{ old('pan_number') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editBank">Bank</label>
                            <input type="text" name="bank_name" id="editBank" class="master-input" value="{{ old('bank_name') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editAccount">Account number</label>
                            <input type="text" name="bank_account_number" id="editAccount" class="master-input" value="{{ old('bank_account_number') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editIfsc">IFSC</label>
                            <input type="text" name="bank_ifsc" id="editIfsc" class="master-input" value="{{ old('bank_ifsc') }}">
                        </div>
                        <div class="master-field full">
                            <label class="master-label" for="editAddress">Address</label>
                            <textarea name="address" id="editAddress" class="master-input" rows="2">{{ old('address') }}</textarea>
                        </div>
                    </div>

                    <div class="master-section-label">Photo</div>
                    <div class="master-avatar-row" data-avatar-drop data-avatar-circle="editAvatarCircle"
                        data-avatar-name="editAvName" data-avatar-remove="delete">
                        <span class="master-avatar-picker">
                            <label class="master-avatar-preview" id="editAvatarCircle" for="editAvatarFile"
                                aria-label="Change the photo" title="Change the photo">
                                <i class="fas fa-user" aria-hidden="true"></i>
                            </label>
                            <span class="master-avatar-camera" aria-hidden="true"><i class="fas fa-camera"></i></span>
                        </span>
                        <div class="master-avatar-info">
                            <p>JPG, PNG, GIF or WEBP, up to 2 MB. Click the circle or drag a photo onto it.</p>
                            <div class="master-avatar-actions">
                                <label class="master-upload-btn" for="editAvatarFile">
                                    <i class="fas fa-upload" aria-hidden="true"></i> Change photo
                                    <input class="master-input" type="file" id="editAvatarFile" name="avatar"
                                        accept="image/jpeg,image/png,image/gif,image/webp">
                                </label>
                                <button type="button" class="master-remove-avatar-btn" id="editRemoveBtn" hidden>
                                    <i class="fas fa-trash-alt" aria-hidden="true"></i> Remove
                                </button>
                                <span class="master-file-name" id="editAvName">No file chosen</span>
                            </div>
                        </div>
                    </div>

                    <div class="master-section-label">Account security</div>
                    <p class="master-sub">Leave both fields blank to keep the current password.</p>
                    <div class="master-form-grid">
                        <div class="master-field">
                            <label class="master-label" for="editPw1">New password</label>
                            <div class="master-password-wrap">
                                <input type="password" name="password" id="editPw1" class="master-input {{ $errors->has('password') ? 'is-invalid' : '' }}" placeholder="Min. 6 characters" autocomplete="new-password">
                                <button type="button" class="master-password-toggle" data-toggle-password="editPw1" aria-label="Toggle password visibility"><i class="fas fa-eye" aria-hidden="true"></i></button>
                            </div>
                            @error('password')<span class="master-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="editPw2">Repeat new password</label>
                            <div class="master-password-wrap">
                                <input type="password" name="password_confirmation" id="editPw2" class="master-input" placeholder="Repeat new password" autocomplete="new-password">
                                <button type="button" class="master-password-toggle" data-toggle-password="editPw2" aria-label="Toggle password visibility"><i class="fas fa-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="master-modal-footer">
                    <p class="master-sub master-modal-lead">
                        <a id="editRecordLink" href="#">Open the full record — pay, payslips, documents</a>
                    </p>
                    <button type="button" class="master-btn master-btn-light" data-close-modal="editModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Save changes</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ================= Delete ================= --}}
    <div class="master-modal" id="deleteModal" aria-hidden="true">
        <div class="master-modal-card is-narrow" role="dialog" aria-modal="true" aria-labelledby="deleteUserTitle">
            <form method="POST" id="deleteForm">
                @csrf
                @method('DELETE')

                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon is-danger" aria-hidden="true">🗑</span>
                        <div>
                            <h3 class="master-modal-title" id="deleteUserTitle">Delete this user</h3>
                            <p class="master-modal-subtitle">This cannot be undone</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="deleteModal" aria-label="Close">&times;</button>
                </div>

                <div class="master-modal-body">
                    <p class="master-modal-text" id="deleteDesc">Are you sure you want to delete this user?</p>
                    <p class="master-sub user-modal-foot">
                        Their payslips, documents and uploaded files go with them. The salary entries
                        already filed in the ledger stay, with the name kept on the row.
                    </p>
                </div>

                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="deleteModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-danger">Delete user</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Validation failure on create/update re-opens the modal, so the reader
         does not have to find their way back to the form. --}}
    <span hidden data-open-dialog="{{ $errors->any() ? ($reopenUserId ? 'edit' : 'add') : '' }}"></span>
</div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/users.js') }}"></script>
    <script src="{{ $assetVer('assets/js/employees.js') }}"></script>
@endpush

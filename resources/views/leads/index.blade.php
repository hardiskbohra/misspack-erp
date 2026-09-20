@extends('layouts.app')

@section('page-title', 'Lead Management')

@section('content')
    <style>
        :root {
            --master-primary: #4f83f1;
            --master-primary-2: #6366f1;
            --master-info: #159ff7;
            --master-teal: #12cbb7;
            --master-purple: #8b5cf6;
            --master-orange: #f59e0b;
            --master-red: #ef4770;
            --master-green: #10b981;
            --master-dark: #17233b;
            --master-muted: #687386;
            --master-border: #dfe7f3;
            --master-bg: #eef3ff;
            --master-soft: #edf5ff;
            --master-white: #fff;
            --master-shadow: 0 14px 35px rgba(25, 42, 70, .08);
        }

        .priority-low {
            background: #e8fff7;
            color: #0e9f6e
        }

        .priority-medium {
            background: #eaf1ff;
            color: #3f7cf4
        }

        .priority-high {
            background: #fff4e5;
            color: #d97706
        }

        .priority-urgent {
            background: #ffeaf0;
            color: #e11d48
        }

        .status-new {
            background: #eaf1ff;
            color: #3f7cf4
        }

        .status-requirement-received {
            background: #f3f6fb;
            color: #536079
        }

        .status-sourcing {
            background: #ece7ff;
            color: #7c3aed
        }

        .status-quoted {
            background: #fff4e5;
            color: #d97706
        }

        .status-negotiation {
            background: #fef3c7;
            color: #92400e
        }

        .status-won {
            background: #e8fff7;
            color: #0e9f6e
        }

        .status-lost {
            background: #ffeaf0;
            color: #e11d48
        }

        .status-on-hold {
            background: #f3f4f6;
            color: #4b5563
        }

        .master-status-form {
            margin-top: 8px;
        }

        .master-status-select {
            height: 34px;
            min-width: 100px;
            border: 1px solid var(--master-border);
            border-radius: 10px;
            color: var(--master-dark);
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            outline: none;
            cursor: pointer;
        }

        .master-status-select:focus {
            border-color: var(--lm-primary);
            box-shadow: 0 0 0 3px rgba(79, 131, 241, .12);
        }

        .users-person {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .users-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            color: #fff;
            flex: 0 0 42px;
            overflow: hidden;
        }

        .users-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
    </style>

    <div class="master">

        <div class="master-stats">
            <div class="master-stat blue"><span class="icon">🎯</span>
                <div>
                    <p class="master-stat-title">Total Leads</p>
                    <p class="master-stat-value">{{ $stats['total'] }}</p>
                </div>
            </div>
            <div class="master-stat purple"><span class="icon">🔍</span>
                <div>
                    <p class="master-stat-title">In Sourcing</p>
                    <p class="master-stat-value">{{ $stats['sourcing'] }}</p>
                </div>
            </div>
            <div class="master-stat teal"><span class="icon">₹</span>
                <div>
                    <p class="master-stat-title">Quoted</p>
                    <p class="master-stat-value">{{ $stats['quoted'] }}</p>
                </div>
            </div>
            <div class="master-stat orange"><span class="icon">✓</span>
                <div>
                    <p class="master-stat-title">Won</p>
                    <p class="master-stat-value">{{ $stats['won'] }}</p>
                </div>
            </div>
        </div>

        <div class="master-card">
            <form method="GET" action="{{ route('leads.index') }}">
                <div class="master-toolbar">
                    <div class="master-search"><span>⌕</span><input class="master-input" type="text" name="search"
                            value="{{ $search }}" placeholder="Search lead, client, product, requirement..."></div>
                    <div class="master-actions-top"><button type="button" class="master-btn master-btn-primary"
                            id="openQuickLeadModal">+ Quick Lead</button><a href="{{ route('leads.create') }}"
                            class="master-btn master-btn-soft">Detailed Form</a><a href="{{ route('leads.public.create') }}"
                            target="_blank" class="master-btn master-btn-light">Public Link</a><a
                            href="{{ route('lead-quotes.index') }}" class="master-btn master-btn-light">Lead
                            Quotes</a><a href="{{ route('leads.settings.index') }}"
                            class="master-btn master-btn-light">Settings</a><a href="{{ route('vendor-quotes.index') }}"
                            class="master-btn master-btn-light">Vendor Quotes</a></div>
                </div>
                <div class="master-filter-row"><select class="master-select" name="status">
                        <option value="all">All Status</option>
                        @foreach ($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select class="master-select" name="priority">
                        <option value="all">All Priority</option>
                        @foreach ($priorityOptions as $key => $label)
                            <option value="{{ $key }}" @selected($priority === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select class="master-select" name="source">
                        <option value="all">All Sources</option>
                        @foreach ($sourceOptions as $key => $label)
                            <option value="{{ $key }}" @selected($source === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select class="master-select" name="assigned_to">
                        <option value="all">All Assignees</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) $assignedTo === (string) $user->id)>
                                {{ $user->name ?? $user->email }}</option>
                        @endforeach
                    </select>
                    <button class="master-btn master-btn-primary" type="submit">Filter</button><a
                        class="master-btn master-btn-light" href="{{ route('leads.index') }}">Reset</a>
                </div>
            </form>
        </div>

        <div class="master-card master-table-card">
            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th>Lead / Product</th>
                            <th>Client</th>
                            <th>Qty / Capacity</th>
                            <th>Finish / Print</th>
                            <th>Assigned</th>
                            <th>Quotes</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leads as $lead)
                            <tr>
                                <td>
                                    <div class="master-product">
                                        @if ($lead->product_image_path)
                                            <a href="{{ route('leads.image', $lead) }}" title="View product image"><img
                                                    class="master-product-img"
                                                    src="{{ asset('storage/' . $lead->product_image_path) }}"
                                                alt="{{ $lead->product_name }}"></a>@else<span
                                                class="master-product-img">📦</span>
                                        @endif
                                        <div>
                                            <span class="master-id">{{ $lead->lead_number }}</span><span
                                                class="master-sub">{{ $lead->title }}</span><strong>{{ $lead->product_name ?: '-' }}</strong>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $lead->client?->company_name ?? ($lead->client_company_name ?? '-') }}<span
                                        class="master-sub">{{ $lead->client_contact_name ?: '-' }}
                                        {{ $lead->client_mobile ?: '' }}</span></td>
                                <td>{{ $lead->required_quantity ? number_format($lead->required_quantity) . ' pcs' : '-' }}<span
                                        class="master-sub">{{ $lead->capacity_value ? $lead->capacity_value . ' ' . $lead->capacity_unit : '-' }}</span>
                                </td>
                                <td>{{ $finishOptions[$lead->finish_required] ?? '-' }}<span
                                        class="master-sub">{{ $printingOptions[$lead->printing_required] ?? '-' }}</span>
                                </td>
                                <td>{{ $lead->assignee?->name ?? ($lead->assignee?->email ?? '-') }}</td>
                                <td>{{ $lead->vendor_quotes_count }}</td>
                                <td><span
                                        class="master-badge priority-{{ $lead->priority }}">{{ $lead->priorityLabel() }}</span>
                                    <form method="POST" action="{{ route('leads.status.update', $lead) }}"
                                        class="master-status-form">@csrf @method('PATCH')<select name="status"
                                            class="master-status-select status-{{ str_replace('_', '-', $lead->status) }}"
                                            onchange="this.form.submit()" title="Change lead status">
                                            @foreach ($statusOptions as $statusKey => $statusLabel)
                                                <option value="{{ $statusKey }}"
                                                    {{ $lead->status === $statusKey ? 'selected' : '' }}>
                                                    {{ $statusLabel }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <div class="master-row-actions">

                                        <div class="master-dropdown">
                                            <button type="button" class="master-dropdown-toggle">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                    
                                            <div class="master-dropdown-menu">
                                    
                                                <a href="{{ route('leads.show', $lead) }}">
                                                    <i class="fas fa-eye"></i>
                                                    View Lead
                                                </a>
                                    
                                                <a href="{{ route('leads.edit', $lead) }}">
                                                    <i class="fas fa-pen"></i>
                                                    Edit Lead
                                                </a>
                                                
                                                <a href="{{ route('lead-quotes.create', ['lead_id' => $lead->id]) }}">
                                                    <i class="fas fa-pen"></i>
                                                    Quick Lead Quote
                                                </a>
                                    
                                                <button
                                                    type="button"
                                                    class="openQuickQuoteModal"
                                                    data-lead-id="{{ $lead->id }}">
                                                    <i class="fas fa-indian-rupee-sign"></i>
                                                    Quick Vendor Quote
                                                </button>
                                    
                                                <button
                                                    type="button"
                                                    class="lead-quotes-btn"
                                                    data-lead-id="{{ $lead->id }}">
                                                    <i class="fas fa-file-invoice-dollar"></i>
                                                    View Quotes
                                                </button>
                                    
                                                <form method="POST"
                                                      action="{{ route('leads.destroy', $lead) }}"
                                                      onsubmit="return confirm('Delete this lead?')">
                                    
                                                    @csrf
                                                    @method('DELETE')
                                    
                                                    <button type="submit" class="danger">
                                                        <i class="fas fa-trash"></i>
                                                        Delete Lead
                                                    </button>
                                    
                                                </form>
                                    
                                            </div>
                                        </div>
                                    
                                    </div>
                                </td>
                        </tr>@empty<tr>
                                <td colspan="8">
                                    <div class="master-empty">No leads found. Create your first sales lead.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-pagination :items="$leads" />
        </div>

        <div class="master-modal" id="quickLeadModal">
            <div class="master-modal-card">
                <form method="POST" action="{{ route('leads.quickStore') }}" enctype="multipart/form-data">@csrf<div
                        class="master-modal-header">
                        <div>
                            <h3 class="master-modal-title">Quick Lead</h3>
                            <p class="master-modal-subtitle">Capture sales requirement quickly.</p>
                        </div><button type="button" class="master-modal-close"
                            data-close-modal="quickLeadModal">×</button>
                    </div>
                    <div class="master-modal-body">
                        <div class="master-modal-grid">
                            <div class="master-field full"><label class="master-label">Lead Title *</label><input
                                    class="master-input" name="title" required
                                    placeholder="Client needs 50ml matte bottle with one color printing"></div>
                            <div><label class="master-label">Client Company</label><input class="master-input"
                                    name="client_company_name"></div>
                            <div><label class="master-label">Contact Person</label><input class="master-input"
                                    name="client_contact_name"></div>
                            <div><label class="master-label">Email</label><input class="master-input" type="email"
                                    name="client_email"></div>
                            <div><label class="master-label">Mobile</label><input class="master-input"
                                    name="client_mobile"></div>
                            <div><label class="master-label">Source</label><select class="master-select"
                                    name="lead_source">
                                    @foreach ($sourceOptions as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="master-label">Priority</label><select class="master-select"
                                    name="priority">
                                    @foreach ($priorityOptions as $key => $label)
                                        <option value="{{ $key }}" @selected($key === 'medium')>
                                            {{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="master-label">Status</label><select class="master-select" name="status">
                                    @foreach ($statusOptions as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="master-label">Assign To</label><select class="master-select"
                                    name="assigned_to">
                                    <option value="">Unassigned</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name ?? $user->email }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="master-label">Product Name</label><input class="master-input"
                                    name="product_name"></div>
                            <div><label class="master-label">Product Image</label><input class="master-input"
                                    type="file" name="product_image" accept="image/*"></div>
                            <div><label class="master-label">Capacity</label><input class="master-input" type="number"
                                    step="0.001" name="capacity_value" placeholder="50"></div>
                            <div><label class="master-label">Required Qty</label><input class="master-input"
                                    type="number" name="required_quantity" placeholder="5000"></div>
                            <div><label class="master-label">Finish</label><select class="master-select"
                                    name="finish_required">
                                    <option value="">Select</option>
                                    @foreach ($finishOptions as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="master-label">Printing</label><select class="master-select"
                                    name="printing_required">
                                    <option value="">Select</option>
                                    @foreach ($printingOptions as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select></div><label class="master-check"><input type="checkbox"
                                    name="ready_stock_required" value="1"> Ready stock required?</label>
                            <div class="master-field full"><label class="master-label">Sales Notes</label>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function openModal(id) {
                document.getElementById(id)?.classList.add('open');
                document.body.classList.add('master-modal-open')
            }

            function closeModal(id) {
                document.getElementById(id)?.classList.remove('open');
                document.body.classList.remove('master-modal-open')
            }
            document.getElementById('openQuickLeadModal')?.addEventListener('click', () => openModal(
                'quickLeadModal'));
            document.querySelectorAll('[data-close-modal]').forEach(btn => btn.addEventListener('click', () =>
                closeModal(btn.dataset.closeModal)));
            document.querySelectorAll('.master-modal').forEach(m => m.addEventListener('click', e => {
                if (e.target === m) closeModal(m.id)
            }));
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') document.querySelectorAll('.master-modal.open').forEach(m =>
                    closeModal(m.id))
            });
        });
    </script>
@endsection

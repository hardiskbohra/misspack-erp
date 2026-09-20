@extends('layouts.app')

@section('page-title', 'Shipment Tracking')

@section('content')
<style>
    .master-route { max-width: 260px; }
    .master-route strong { display: block; }

    .type-domestic { background: #46d488; color: #fff; }
    .type-import { background: #fff4e5; color: #d97706; }
    .type-export { background: #ecfdf5; color: #059669; }
    .status-planning { background: #aab1bd; color: #FFF; }
    .status-picked-up { background: #dbcb3b; color: #FFF; }
    .status-in-transit { background: #7b99db; color: #FFF; }
    .status-custom-hold { background: #fff4e5; color: #d97706; }
    .status-delayed { background: #ffeaf0; color: #e11d48; }
    .status-out-for-delivery { background: #46d488; color: #FFF; }
    .status-delivered { background: #e8fff7; color: #0e9f6e; }
    .status-cancelled { background: #f3f4f6; color: #4b5563; }
    
    /* Default */
    .label-0 { background:blue; color:#FFF; }
    .label-1 { background:red; color:#FFF; }
    .label-2 { background:green; color:#FFF; }
    .label-3 { background:orange; color:black; }
    .label-4 { background:yellow; color:red; }
    .label-5 { background:pink; color:blue; }
    
    /* Mobile Responsive Shipment Table */
    @media (max-width:768px){

        .master-table,
        .master-table tbody,
        .master-table tr,
        .master-table td {
            display:block;
            min-width: 350px;
            width:100%;
        }
    
        .master-table thead{
            display:none;
        }
    
        .master-table tr{
            background:#fff;
            border:0px solid #e8edf7;
            border-radius:16px;
            padding:18px;
            margin-bottom:18px;
            box-shadow:0 4px 18px rgba(0,0,0,.06);
        }
    
        .master-table td{
            padding:0;
            border:none;
            margin-bottom:16px;
        }
    
        .master-table td:last-child{
            margin-bottom:0;
        }
    
        .master-table td::before{
            display:block;
            font-size:13px;
            font-weight:600;
            color:#64748b;
            margin-bottom:6px;
        }
    
        .master-table td:nth-child(1)::before{content:"";}
        .master-table td:nth-child(2)::before{content:"";}
        .master-table td:nth-child(3)::before{content:"";}
        .master-table td:nth-child(4)::before{content:"";}
        .master-table td:nth-child(5)::before{content:"";}
        .master-table td:nth-child(6)::before{content:"";}
    
        /* Shipment */
    
        .master-id{
            display:block;
            font-size:20px;
            font-weight:600;
        }
    
        .master-sub{
            display:block;
            color:#64748b;
            margin-top:4px;
        }
    
        .master-badge{
            display:inline-flex;
            font-size:11px;
            border-radius: 999px;
            padding: 6px 11px;
        }
    
        /* Route */
    
        .master-route strong{
            display:block;
            white-space:normal;
            line-height:1.5;
            font-size:16px;
        }
    
        .master-route .master-sub{
            margin-top:8px;
        }
    
        /* Pickup */
    
        td:nth-child(3){
            font-weight:600;
        }
    
        td:nth-child(3) .master-sub{
            margin-top:6px;
        }
    
        /* Logistic */
    
        td:nth-child(4){
            font-weight:600;
        }
    
        td:nth-child(4) .master-sub{
            margin-top:6px;
        }
    
        /* Actions */
    
        .master-row-actions{
            display:flex;
            justify-content:flex-start;
            gap:10px;
            flex-wrap:wrap;
        }
    
        .master-icon-btn{
            width:42px;
            height:42px;
        }
    }
</style>

<div class="ship">

    <div class="master-stats desktop-only">
        <div class="master-stat blue"><span class="icon">⇄</span><div><p class="master-stat-title">Total Shipments</p><p class="master-stat-value">{{ $stats['total'] }}</p></div></div>
        <div class="master-stat purple"><span class="icon">✈</span><div><p class="master-stat-title">In Transit</p><p class="master-stat-value">{{ $stats['in_transit'] + $stats['out_for_delivery'] }}</p></div></div>
        <div class="master-stat teal"><span class="icon">✓</span><div><p class="master-stat-title">Delivered</p><p class="master-stat-value">{{ $stats['delivered'] }}</p></div></div>
        <div class="master-stat orange"><span class="icon">!</span><div><p class="master-stat-title">Hold / Delayed</p><p class="master-stat-value">{{ $stats['custom_hold'] + $stats['delayed'] }}</p></div></div>
    </div>

    <div class="master-card">
        <form method="GET" action="{{ route('shipments.index') }}">

            <div class="master-filter-row" style="padding-top:22px;">
                <div class="master-search">
                    <span>⌕</span>
                    <input class="master-input" type="text" name="search" value="{{ $search }}" placeholder="Search shipment, tracking, BOE, partner...">
                </div>
                <select class="master-select" name="status">
                    <option value="all">All Status</option>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="master-select desktop-only" name="currency" hidden>
                    <option value="all">All Currency</option>
                    @foreach($currencyOptions as $key => $label)
                        <option value="{{ $key }}" @selected($currency === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input class="master-input desktop-only" type="date" name="from_date" value="{{ $fromDate }}" title="Pickup date">
                <button class="master-btn master-btn-primary" type="submit">Filter</button>
                <a class="master-btn master-btn-soft" href="{{ route('shipments.index') }}">Reset</a>
                <button type="button" class="master-btn master-btn-primary" id="openQuickShipmentModal">+ Quick Shipment</button>
            </div>
        </form>
    </div>

    <div class="master-card master-table-card">
        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th>Pickup / Drop</th>
                        <th>Shipment</th>
                        <th>Route</th>
                        <th>Logistic</th>
                        <th>Charges</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shipments as $shipment)
                        @php($statusClass = str_replace('_', '-', $shipment->status))
                        <tr style="line-height:1.5;">
                            <td style="font-weight:500">
                                {{ $shipment->pickup_date ? $shipment->pickup_date->format('d M') : '-' }}
                                <span class="master-sub">
                                    @if($shipment->project)
                                        <span class="master-badge type-export tooltip-container" style="font-size:9px;border:1px solid grey">P
                                            <span class="tooltip-text">{{ $shipment->project->project_number }} - {{ $shipment->project->name }}</span>
                                        </span>
                                    @endif
                                    @if($shipment->client)
                                        <span class="master-badge type-import tooltip-container" style="font-size:9px;border:1px solid grey">C
                                            <span class="tooltip-text">{{ $shipment->client->company_name }}</span>
                                        </span>
                                    @endif
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('shipments.show', $shipment) }}" style="text-decoration:none;">
                                    <span class="master-sub">{{ $shipment->shipment_number }} &nbsp;
                                    @if($shipment->shipment_label)
                                        <span class="master-badge {{ $shipment->labelColorClass() }}" style="margin-top:5px;font-size:9px;">{{ $shipment->shipment_label }}</span>
                                    @endif
                                    </span>
                                    <span class="master-id">{{ $shipment->identity_name }}</span>
                                </a>
                            </td>
                            <td class="master-route">
                                <strong style="font-weight:500">{{ $shipment->from_name ?: 'Origin' }} → {{ $shipment->to_name ?: 'Destination' }}</strong>
                                <span class="master-sub desktop-only">{{ $shipment->from_city ?: '-' }} to {{ $shipment->to_city ?: '-' }}</span>
                            </td>
                            <td style="font-weight:500">
                                {{ $shipment->logistic_partner ?: '-' }}
                                <span class="master-sub">{{ $shipment->tracking_number ?: 'No tracking' }}</span>
                            </td>
                            <td style="font-weight:500">
                                {{ $shipment->currency ?: '' }} {{ $shipment->shipment_cost ?: '0' }}
                                <span class="master-sub">{{ $shipment->cost_borne_by ?: '' }}</span>
                            </td>
                            <td style="font-weight:500"><span class="master-badge status-{{ $statusClass }}">{{ $shipment->statusLabel() }}</span></td>
                            <td style="font-weight:500">
                                <div class="master-row-actions">

                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                
                                        <div class="master-dropdown-menu">
                                
                                            <a href="{{ route('shipments.show', $shipment) }}">
                                                <i class="fas fa-eye"></i>
                                                View Shipment
                                            </a>
                                
                                            <a href="{{ route('shipments.edit', $shipment) }}">
                                                <i class="fas fa-pen"></i>
                                                Edit Shipment
                                            </a>
                                            
                                            <button type="button" onclick="copyShipmentLink('{{ route('shipments.publicTrack', $shipment->public_token) }}')">Public Link</button>
                                
                                            <form method="POST"
                                                  action="{{ route('shipments.destroy', $shipment) }}"
                                                  onsubmit="return confirm('Delete this shipment?')">
                                
                                                @csrf
                                                @method('DELETE')
                                
                                                <button type="submit" class="danger">
                                                    <i class="fas fa-trash"></i>
                                                    Delete Shipment
                                                </button>
                                
                                            </form>
                                
                                        </div>
                                    </div>
                                
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="master-empty">No shipments found. Create your first shipment.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :items="$shipments" />

    </div>

    <div class="master-modal" id="quickShipmentModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="quickShipmentTitle">
            <form method="POST" action="{{ route('shipments.quickStore') }}">
                @csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon">⇄</span>
                        <div>
                            <h3 class="master-modal-title" id="quickShipmentTitle">Quick Shipment</h3>
                            <p class="master-modal-subtitle desktop-only">Create shipment with essential details only</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" id="closeQuickShipmentModal">×</button>
                </div>
                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field full">
                            <label class="master-label">Shipment Identity Name <span class="master-required">*</span></label>
                            <input class="master-input" name="identity_name" placeholder="e.g. Green pigment samples from China" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label">Shipment Type <span class="master-required">*</span></label>
                            <select class="master-select" name="shipment_type" required>
                                @foreach($typeOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field"><label class="master-label">Pickup Date</label><input class="master-input" type="date" name="pickup_date" value="{{ now()->toDateString() }}"></div>
                        <div class="master-field"><label class="master-label">From Name</label><input class="master-input" name="from_name" placeholder="Shipper name"></div>
                        <div class="master-field"><label class="master-label">To Name</label><input class="master-input" name="to_name" placeholder="Receiver name"></div>
                        <div class="master-field"><label class="master-label">Logistic Partner</label><input class="master-input" name="logistic_partner" placeholder="DHL / FedEx / BlueDart"></div>
                        <div class="master-field"><label class="master-label">Tracking Number</label><input class="master-input" name="tracking_number" placeholder="Tracking number"></div>
                    </div>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" id="cancelQuickShipmentModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary">Create Shipment</button>
                </div>
            </form>
        </div>
    </div>

    <div class="master-modal" id="deleteShipmentModal" aria-hidden="true">
        <div class="master-modal-card" style="max-width: 440px;" role="dialog" aria-modal="true" aria-labelledby="deleteShipmentTitle">
            <div class="master-modal-header">
                <div class="master-modal-heading"><span class="master-modal-icon" style="background:#fff0f4;color:var(--master-red);">🗑</span><div><h3 class="master-modal-title" id="deleteShipmentTitle">Delete Shipment</h3><p class="master-modal-subtitle">This action cannot be undone</p></div></div>
                <button type="button" class="master-modal-close" id="closeDeleteShipmentModal">×</button>
            </div>
            <div class="master-modal-body"><p id="deleteShipmentDesc" style="margin:0;font-weight:700;color:#536079;">Are you sure you want to delete this shipment?</p></div>
            <form method="POST" action="" id="deleteShipmentForm">
                @csrf
                @method('DELETE')
                <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light" id="cancelDeleteShipmentModal">Cancel</button><button type="submit" class="master-btn master-btn-danger">Delete Shipment</button></div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const quickModal = document.getElementById('quickShipmentModal');
        const deleteModal = document.getElementById('deleteShipmentModal');
        const deleteForm = document.getElementById('deleteShipmentForm');
        const deleteDesc = document.getElementById('deleteShipmentDesc');

        function openModal(modal) { modal?.classList.add('open'); modal?.setAttribute('aria-hidden', 'false'); document.body.classList.add('master-modal-open'); }
        function closeModal(modal) { modal?.classList.remove('open'); modal?.setAttribute('aria-hidden', 'true'); document.body.classList.remove('master-modal-open'); }

        document.getElementById('openQuickShipmentModal')?.addEventListener('click', () => openModal(quickModal));
        document.getElementById('closeQuickShipmentModal')?.addEventListener('click', () => closeModal(quickModal));
        document.getElementById('cancelQuickShipmentModal')?.addEventListener('click', () => closeModal(quickModal));

        document.querySelectorAll('.master-delete-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                deleteDesc.textContent = 'Are you sure you want to delete "' + button.dataset.name + '"? This action cannot be undone.';
                deleteForm.action = button.dataset.deleteUrl;
                openModal(deleteModal);
            });
        });
        document.getElementById('closeDeleteShipmentModal')?.addEventListener('click', () => closeModal(deleteModal));
        document.getElementById('cancelDeleteShipmentModal')?.addEventListener('click', () => closeModal(deleteModal));

        [quickModal, deleteModal].forEach(function (modal) {
            modal?.addEventListener('click', function (event) {
                if (event.target === modal) closeModal(modal);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            closeModal(quickModal);
            closeModal(deleteModal);
        });
    });

    function copyShipmentLink(url) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(() => alert('Public tracking link copied.'));
        } else {
            prompt('Copy public tracking link:', url);
        }
    }
</script>
@endsection

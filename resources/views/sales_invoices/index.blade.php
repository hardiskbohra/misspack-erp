@extends('layouts.app')

@section('page-title', 'PI / Tax Invoices')

@section('content')
<div class="si-page" style="padding:0;">
    <div class="si-hero">
        <div>
            <p class="si-eyebrow">Sales Billing</p>
            <h1>Invoice Management</h1>
            <p>Create invoices with multiple linked products, clients, projects and client portal visibility.</p>
        </div>
        <div class="si-actions">
            <a href="{{ route('sales-invoices.create', ['type' => 'proforma']) }}" class="si-btn si-btn-primary">New PI</a>
            <a href="{{ route('sales-invoices.create', ['type' => 'tax']) }}" class="si-btn si-btn-light">New Tax Invoice</a>
        </div>
    </div>

    <div class="si-stats">
        <div class="si-stat"><span>Proforma</span><strong>{{ $stats['proforma'] }}</strong></div>
        <div class="si-stat blue"><span>Tax</span><strong>{{ $stats['tax'] }}</strong></div>
        <div class="si-stat purple"><span>Total</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($stats['sent']) }}</strong></div>
        <div class="si-stat green"><span>Paid</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($stats['paid']) }}</strong></div>
        <div class="si-stat orange"><span>Outstanding</span><strong>{{ \App\Helpers\CommonHelper::indianCurrency($stats['outstanding']) }}</strong></div>
    </div>

    <div class="si-card si-filter-card">
        <form method="GET" action="{{ route('sales-invoices.index') }}" class="si-filter-form">
            <div class="si-field search"><label>Search</label><input name="search" value="{{ $search }}" placeholder="Search invoice, client, GSTIN, PO..."></div>
            <div class="si-field"><label>Type</label><select name="type"><option value="all">All Types</option>@foreach($typeOptions as $key => $label)<option value="{{ $key }}" {{ $type === $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
            <div class="si-field"><label>Status</label><select name="status"><option value="all">All Status</option>@foreach($statusOptions as $key => $label)<option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
            <div class="si-field"><label>Client</label><select name="client_id"><option value="all">All Clients</option>@foreach($clients as $client)<option value="{{ $client->id }}" {{ (string)$clientId === (string)$client->id ? 'selected' : '' }}>{{ $client->company_name }}</option>@endforeach</select></div>
            <div class="si-field"><label>Project</label><select name="project_id"><option value="all">All Projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" {{ (string)$projectId === (string)$project->id ? 'selected' : '' }}>{{ $project->project_number }} - {{ $project->name }}</option>@endforeach</select></div>
            <div class="si-filter-actions"><button class="si-btn si-btn-primary" type="submit">Filter</button><a class="si-btn si-btn-soft" href="{{ route('sales-invoices.index') }}">Reset</a></div>
        </form>
    </div>

    <div class="si-card">
        <div class="master-table-wrap">
            <table class="master-table">
                <thead><tr><th>Invoice</th><th>Client</th><th>Project</th><th>Items</th><th>Total</th><th>Balance</th><th>Portal</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        @php
                            $paidAmount = $invoice->payments->sum('credit_amount') - $invoice->payments->sum('debit_amount');
                            $balanceAmount = (float) $invoice->total_amount - $paidAmount;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('sales-invoices.show', $invoice) }}" title="Open" style="text-decoration:none;">
                                    <strong class="master-id">{{ $invoice->invoice_number }}</strong><br><span style="color:grey;font-size:11px;">{{ $invoice->typeLabel() }}</span><br>{{ optional($invoice->invoice_date)->format('d M Y') ?: '-' }}
                                </a>
                            </td>
                            <td><strong>{{ Str::limit($invoice->client_company_name, 22, '...') ?: '-' }}</strong><br><span>{{ $invoice->client_gstin ?: 'GSTIN -' }}</span></td>
                            <td>{{ $invoice->project ? $invoice->project->project_number : '-' }}<br><span>{{ Str::limit($invoice->project?->name ?? '', 22, '...') }}</span></td>
                            <td>{{ $invoice->items->count() }}</td>
                            <td>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->total_amount) }}</td>
                            <td>{{ \App\Helpers\CommonHelper::indianCurrency($balanceAmount) }}</td>
                            <td><span class="si-badge {{ $invoice->show_client_portal ? 'public' : 'private' }}">{{ $invoice->show_client_portal ? 'Visible' : 'Hidden' }}</span></td>
                            <td><span class="si-badge status-{{ $invoice->status }}">{{ $invoice->statusLabel() }}</span></td>
                            <td><div class="si-row-actions"><a class="si-icon" href="{{ route('sales-invoices.show', $invoice) }}" title="Open">👁</a><a class="si-icon" href="{{ route('sales-invoices.print', $invoice) }}" target="_blank" title="Print">🖨</a><a class="si-icon" href="{{ route('sales-invoices.edit', $invoice) }}" title="Edit">✎</a><form method="POST" action="{{ route('sales-invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete invoice?')">@csrf @method('DELETE')<button class="si-icon danger" type="submit">🗑</button></form></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="si-empty">No invoices found.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="si-pagination">{{ $invoices->links() }}</div>
    </div>
</div>

<style>
.si-page{display:flex;flex-direction:column;gap:18px;background:#eef3ff;min-height:calc(100vh - 70px);padding:28px;color:#17233b}.si-page *{box-sizing:border-box}.si-hero{background:linear-gradient(135deg,#4f83f1,#7b61ff);border-radius:26px;padding:24px;color:#fff;display:flex;justify-content:space-between;gap:18px;box-shadow:0 18px 45px rgba(79,131,241,.22)}.si-eyebrow{margin:0 0 6px;text-transform:uppercase;letter-spacing:.13em;font-size:11px;font-weight:600;opacity:.78}.si-hero h1{margin:0;font-size:30px;font-weight:600}.si-hero p{margin:8px 0 0;max-width:780px;opacity:.9}.si-actions,.si-row-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.si-btn{border:0;border-radius:14px;padding:10px 15px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;white-space:nowrap}.si-btn-primary{background:#ef4770;color:#fff;box-shadow:0 10px 24px rgba(239,71,112,.24)}.si-btn-light{background:rgba(255,255,255,.16);color:#fff;border:1px solid rgba(255,255,255,.3)}.si-btn-soft{background:#eef3ff;color:#4f83f1}.si-alert{border-radius:16px;padding:13px 15px;font-weight:600}.si-alert.success{background:#e8fff7;color:#047857;border:1px solid #a7f3d0}.si-alert.error{background:#fff0f4;color:#be123c;border:1px solid #fecdd3}.si-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}.si-stat,.si-card{background:#fff;border:1px solid #dfe7f3;border-radius:22px;box-shadow:0 14px 35px rgba(25,42,70,.08)}.si-stat{padding:16px}.si-stat span{display:block;color:#687386;font-size:12px;font-weight:600;text-transform:uppercase}.si-stat strong{display:block;margin-top:7px;font-size:24px}.si-stat.blue strong{color:#4f83f1}.si-stat.purple strong{color:#7c3aed}.si-stat.green strong{color:#0e9f6e}.si-stat.orange strong{color:#d97706}.si-filter-card{padding:16px}.si-filter-form{display:grid;grid-template-columns:minmax(220px,1.3fr) repeat(4,minmax(140px,.8fr)) auto;gap:12px;align-items:end}.si-field{display:flex;flex-direction:column;gap:7px}.si-field label{font-size:12px;color:#536079;font-weight:600}.si-field input,.si-field select{height:44px;border:1px solid #d8e2ef;border-radius:13px;padding:10px 12px;background:#fff;outline:none}.si-filter-actions{display:flex;gap:8px}.si-table-wrap{overflow:auto}.si-table{width:100%;min-width:1180px;border-collapse:collapse}.si-table th,.si-table td{padding:15px 17px;border-bottom:1px solid #dfe7f3;text-align:left;vertical-align:top}.si-table th{font-size:12px;color:#7d8aa0;text-transform:uppercase;letter-spacing:.07em;background:#fbfdff}.si-table span{display:block;color:#687386;font-size:12px;margin-top:3px;font-weight:700}.si-id{color:#4f83f1}.si-badge{display:inline-flex!important;border-radius:999px;padding:6px 10px;font-size:11px!important;font-weight:600!important;text-transform:uppercase;margin:0!important}.si-badge.public,.status-paid,.status-accepted{background:#e8fff7;color:#0e9f6e!important}.si-badge.private,.status-draft{background:#f3f6fb;color:#536079!important}.status-sent,.status-partial{background:#eaf1ff;color:#3f7cf4!important}.status-overdue,.status-cancelled{background:#ffeaf0;color:#e11d48!important}.si-icon{width:36px;height:36px;border:0;border-radius:10px;background:#eef5ff;color:#4f83f1;text-decoration:none;display:grid;place-items:center;cursor:pointer}.si-icon.danger{background:#fff0f4;color:#ef4770}.si-empty{padding:50px;text-align:center;color:#687386;font-weight:600}.si-pagination{padding:16px}@media(max-width:1400px){.si-filter-form{grid-template-columns:1fr 1fr}.si-field.search{grid-column:1/-1}.si-filter-actions{grid-column:1/-1}.si-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:767px){.si-page{padding:14px}.si-hero{flex-direction:column}.si-actions,.si-actions .si-btn{width:100%}.si-filter-form,.si-stats{grid-template-columns:1fr}.si-filter-actions .si-btn{flex:1}.si-card{border-radius:18px}}
</style>
@endsection

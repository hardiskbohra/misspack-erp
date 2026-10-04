@extends('layouts.app')

@section('page-title', 'Lead Detail')

@section('content')
@push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/leads.css') }}">
@endpush
    <div class="master">
        <div class="master-card master-header">
            <div class="master-head-left">
                @if ($lead->product_image_path)
                    <a href="{{ route('leads.image', $lead) }}" title="View product image"><img class="master-img"
                        src="{{ asset('storage/' . $lead->product_image_path) }}"></a>@else<span class="master-img">📦</span>
                @endif
                <div>
                    <h1>{{ $lead->title }}</h1>
                    <p>{{ $lead->lead_number }} · {{ $lead->product_name ?: 'Product not specified' }}</p>
                </div>
            </div>
            <div class="master-actions"><a href="{{ route('leads.index') }}" class="master-btn master-btn-light">Back</a><a
                    href="{{ route('leads.edit', $lead) }}" class="master-btn master-btn-soft">Edit Lead</a>
                @if ($lead->public_token)
                    <a href="{{ route('leads.public.show', $lead->public_token) }}" target="_blank"
                        class="master-btn master-btn-soft">Public Product Link</a>
                @endif
                <a href="{{ route('lead-quotes.create', ['lead_id' => $lead->id]) }}" class="master-btn master-btn-primary">
                    Create Lead Quote</a><a href="{{ route('vendor-quotes.create', ['lead_id' => $lead->id]) }}"
                    class="master-btn master-btn-light">Create Vendor Quote</a>
            </div>
        </div>
        <div class="master-grid">
            <div>
                <div class="master-card master-section">
                    <h3>Lead Overview</h3>
                    <div class="master-info-grid">
                        <div class="master-info"><span>Status</span><strong><span
                                    class="master-badge status-{{ str_replace('_', '-', $lead->status) }}">{{ $lead->statusLabel() }}</span></strong>
                        </div>
                        <div class="master-info"><span>Priority</span><strong><span
                                    class="master-badge priority-{{ $lead->priority }}">{{ $lead->priorityLabel() }}</span></strong>
                        </div>
                        <div class="master-info">
                            <span>Client</span><strong>{{ $lead->client?->company_name ?? ($lead->client_company_name ?? '-') }}</strong><span
                                class="master-sub">{{ $lead->client_contact_name }} {{ $lead->client_mobile }}</span></div>
                        <div class="master-info"><span>Assigned
                                To</span><strong>{{ $lead->assignee?->name ?? ($lead->assignee?->email ?? '-') }}</strong>
                        </div>
                        <div class="master-info">
                            <span>Source</span><strong>{{ $sourceOptions[$lead->lead_source] ?? $lead->lead_source }}</strong>
                        </div>
                        <div class="master-info"><span>Target
                                Price</span><strong>{{ $lead->target_price ? \App\Helpers\CommonHelper::amount($lead->target_price, $lead->target_currency) : '-' }}</strong>
                        </div>
                    </div>
                </div>
                <div class="master-card master-section">
                    <h3>Product Requirement</h3>
                    <div class="master-info-grid">
                        <div class="master-info"><span>Product</span><strong>{{ $lead->product_name ?: '-' }}</strong></div>
                        <div class="master-info">
                            <span>Capacity</span><strong>{{ $lead->capacity_value ? $lead->capacity_value . ' ' . $lead->capacity_unit : '-' }}</strong>
                        </div>
                        <div class="master-info"><span>Required
                                Quantity</span><strong>{{ $lead->required_quantity ? number_format($lead->required_quantity) . ' pcs' : '-' }}</strong><span
                                class="master-sub">Quote for: {{ implode(', ', $lead->quote_quantities ?? []) ?: '-' }}</span>
                        </div>
                        <div class="master-info"><span>Finish /
                                Printing</span><strong>{{ $finishOptions[$lead->finish_required] ?? '-' }}</strong><span
                                class="master-sub">{{ $printingOptions[$lead->printing_required] ?? '-' }}</span></div>
                        <div class="master-info"><span>Ready
                                Stock</span><strong>{{ $lead->ready_stock_required ? 'Required' : 'No' }}</strong><span
                                class="master-sub">{{ $lead->ready_stock_color_requirement ?: '-' }}</span></div>
                        <div class="master-info"><span>Custom
                                Color</span><strong>{{ $lead->custom_color_required ? 'Required' : 'No' }}</strong><span
                                class="master-sub">{{ $lead->custom_color_specification ?: '-' }}</span></div>
                    </div>
                    <p style="font-weight:700;color:#536079;line-height:1.6;">{{ $lead->product_description }}</p>
                </div>
                <div class="master-card master-section">
                    <div class="master-section-head">
                        <h3>Lead Quotes Given</h3><a href="{{ route('lead-quotes.create', ['lead_id' => $lead->id]) }}"
                            class="master-btn master-btn-primary">+ New Lead Quote</a>
                    </div>
                    <div class="master-table-wrap">
                        <table class="master-table">
                            <thead>
                                <tr>
                                    <th>Quote</th>
                                    <th>Status</th>
                                    <th>Expiry Date</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lead->customerQuotes as $quote)
                                    <tr>
                                        <td>{{ $quote->quote_number }}<span class="master-sub">{{ $quote->title }}</span></td>
                                        <td>{{ $quote->statusLabel() }}</td>
                                        <td>{{ $quote->expiry_date ? $quote->expiry_date->format('d M Y') : '-' }}</td>
                                        <td>{{ \App\Helpers\CommonHelper::amount($quote->total_amount, $quote->currency) }}</td>
                                        <td><a href="{{ route('lead-quotes.show', $quote) }}">View</a></td>
                                </tr>@empty<tr>
                                        <td colspan="5">No lead quotes given yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="master-card master-section">
                    <h3>Vendor Quotes</h3>
                    <div class="master-table-wrap">
                        <table class="master-table">
                            <thead>
                                <tr>
                                    <th>Quote</th>
                                    <th>Vendor</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lead->vendorQuotes as $quote)
                                    <tr style="line-height:1.5">
                                        <td>{{ $quote->quote_number }}</td>
                                        <td>
                                            {{ \Illuminate\Support\Str::limit($quote->vendor_contact_name ?: '-', 25, '...') }}<br>
                                            <span style="color:grey">{{ \Illuminate\Support\Str::limit($quote->vendor_name ?: '-', 25, '...') }}</span>
                                        </td>
                                        <td>{!! $quote->items->isNotEmpty()
                                            ? $quote->items->map(fn($item) => number_format($item->quantity) . ' ' . $item->unit)->implode('<br>')
                                            : ($quote->quantity ? number_format($quote->quantity) . ' ' . $quote->unit : '-')
                                        !!}</td>
                                        <td>{!! $quote->items->isNotEmpty()
                                            ? $quote->items->map(fn($item) => \App\Helpers\CommonHelper::amount($item->vendor_unit_price, $quote->currency) . ($quote->incoterm ? ' ' . $quote->incoterm : ''))->implode('<br>')
                                            : ($quote->vendor_unit_price
                                                ? \App\Helpers\CommonHelper::amount($quote->vendor_unit_price, $quote->currency) . ($quote->incoterm ? ' ' . $quote->incoterm : '')
                                                : '-')
                                        !!}</td>
                                        <td>{{ $quote->statusLabel() }}</td>
                                        <td><a href="{{ route('vendor-quotes.show', $quote) }}">View</a></td>
                                </tr>@empty<tr>
                                        <td colspan="6">No vendor quotes added yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div>
                <div class="master-card master-section">
                    <h3>Add Comment</h3>
                    <form method="POST" action="{{ route('leads.comments.store', $lead) }}">@csrf<div
                            class="comment-form-grid">
                            <div class="full">
                                <textarea class="master-textarea" name="comment" placeholder="Add lead comment or follow-up note" required></textarea>
                            </div>
                            <div><select class="master-select" name="comment_type">
                                    @foreach ($commentTypeOptions as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><input class="master-input" type="datetime-local" name="next_follow_up_at"></div><label class="master-check"><input type="checkbox" name="is_pinned" value="1"> Pin comment</label>
                            <div><button class="master-btn master-btn-primary" type="submit">Add Comment</button></div>
                        </div>
                    </form>
                </div>
                <div class="master-card master-section">
                    <h3>Comments</h3>
                    <div class="comment-list">
                        @forelse($lead->comments as $comment)
                            <div class="comment-item">
                                <div class="comment-meta">
                                    <span>{{ $comment->typeLabel() }}</span><span>•</span><span>{{ $comment->creator?->name ?? ($comment->creator?->email ?? 'System') }}</span><span>•</span><span>{{ $comment->created_at->format('d M Y, h:i A') }}</span>
                                    @if ($comment->is_pinned)
                                        <span>📌 Pinned</span>
                                    @endif
                                </div>
                                <div class="comment-text">{{ $comment->comment }}</div>
                                @if ($comment->next_follow_up_at)
                                    <div class="master-sub">Follow-up:
                                        {{ $comment->next_follow_up_at->format('d M Y, h:i A') }}</div>
                                @endif
                                <form method="POST" action="{{ route('leads.comments.destroy', $comment) }}"
                                    data-confirm="Delete this comment?" style="margin-top:10px;">
                                    @csrf @method('DELETE')<button class="master-btn master-btn-danger"
                                        type="submit">Delete</button></form>
                        </div>@empty<p style="color:#687386;font-weight:700;">No comments yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="master-card master-section">
                    <h3>Sales Notes</h3>
                    <p style="color:#536079;font-weight:700;line-height:1.6;">{{ $lead->sales_notes ?: 'No sales notes.' }}
                    </p>
                </div>
                <div class="master-card master-section">
                    <h3>Purchase Notes</h3>
                    <p style="color:#536079;font-weight:700;line-height:1.6;">
                        {{ $lead->purchase_notes ?: 'No purchase notes.' }}</p>
                </div>
                <div class="master-card master-section">
                    <h3>Attachments</h3>
                    @forelse($lead->attachments as $attachment)
                        @if ($attachment->external_url)
                            <a class="master-link" href="{{ $attachment->external_url }}" target="_blank">🔗
                            {{ $attachment->title }}</a>@else<a class="master-link"
                                href="{{ asset('storage/' . $attachment->file_path) }}" target="_blank">📎
                                {{ $attachment->original_name }}</a>
                        @endif @empty<p style="color:#687386;font-weight:700;">No attachments.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

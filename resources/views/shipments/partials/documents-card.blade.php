@php
    $documentSummary = $documentSummary ?? app(\App\Services\ShipmentDocuments::class)->summary($shipment);
@endphp

<div class="master-card master-section">
    <div class="master-section-head">
        <div>
            <h3 class="master-section-title">Paperwork Checklist</h3>
            <p class="master-sub">
                {{ $documentSummary['done'] }} of {{ $documentSummary['required'] }} required documents on file
                @if (! $documentSummary['complete'])
                    · missing {{ implode(', ', $documentSummary['missing']) }}
                @else
                    · complete
                @endif
            </p>
        </div>
        @if ($documentSummary['complete'])
            <span class="master-badge status-delivered">Docs complete</span>
        @else
            <span class="master-badge status-delayed">Docs pending</span>
        @endif
    </div>

    <div class="ship-doc-list">
        @foreach ($documentChecklist as $row)
            @continue(! $row['required'] && ! $row['done'])
            <div class="ship-doc {{ $row['done'] ? 'is-done' : ($row['required'] ? 'is-missing' : 'is-optional') }}">
                <span class="ship-doc-mark">{{ $row['done'] ? '✓' : ($row['required'] ? '!' : '·') }}</span>
                <div class="ship-doc-body">
                    <strong>{{ $row['label'] }}</strong>
                    <span class="master-sub">
                        {{ $row['required'] ? 'Required' : 'Optional' }}
                        @if ($row['attachments']->isNotEmpty())
                            · {{ $row['attachments']->count() }} file{{ $row['attachments']->count() === 1 ? '' : 's' }}
                        @endif
                    </span>
                </div>
                @if ($row['attachments']->first())
                    <a class="master-btn master-btn-soft master-btn-sm"
                        href="{{ asset('storage/'.$row['attachments']->first()->file_path) }}" target="_blank">Open</a>
                @endif
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('shipments.attachments.store', $shipment) }}" enctype="multipart/form-data" class="ship-doc-upload">
        @csrf
        <select class="master-select" name="document_type" required>
            @foreach ($documentTypes as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <input class="master-input" type="file" name="attachment_photos[]" multiple required>
        <label class="master-check"><input type="checkbox" name="is_public" value="1"> Client can see</label>
        <button class="master-btn master-btn-primary">Upload document</button>
    </form>
</div>

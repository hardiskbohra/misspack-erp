@php
    /* The checklist an HR file is built from — identity, address, resume,
       experience — drawn the same way on the office's page for a person and on
       their own page. The two differ only in what can be *done* from here: the
       office files and verifies, the employee uploads and removes their own.
       Read-only rows, because the list is the record. */
    $mode = $mode ?? 'self';
    $office = $mode === 'office';
    $actionBase = $office
        ? fn (string $route, $document = null) => $document
            ? route($route, ['user' => $user, 'document' => $document])
            : route($route, ['user' => $user])
        : fn (string $route, $document = null) => $document
            ? route($route, $document)
            : route($route);
@endphp

<div class="emp-checklist">
    <div class="emp-checklist-head">
        <div>
            <p class="emp-checklist-title">Document file</p>
            <p class="master-sub">
                {{ $checklist['present'] }} of {{ $checklist['required'] }} required papers on file
                @if ($checklist['verified'] > 0) · {{ $checklist['verified'] }} checked @endif
            </p>
        </div>
        <span class="emp-pill {{ $checklist['complete'] ? 'is-ok' : 'is-warn' }}">
            {{ $checklist['complete'] ? 'Complete' : 'Incomplete' }}
        </span>
    </div>

    <div class="master-table-wrap">
        <table class="master-table emp-doc-table">
            <thead>
                <tr>
                    <th scope="col">Document</th>
                    <th scope="col">Status</th>
                    <th scope="col">File</th>
                    <th scope="col" class="is-num">Size</th>
                    <th scope="col">Uploaded</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($checklist['rows'] as $row)
                    @php($documents = $row['documents'])
                    <tr class="{{ $row['required'] && ! $row['present'] ? 'is-missing' : '' }}">
                        <td data-label="Document">
                            <strong>{{ $row['label'] }}</strong>
                            <span class="master-sub">{{ $row['hint'] }}</span>
                        </td>
                        <td data-label="Status">
                            @if (! $row['present'])
                                <span class="emp-pill {{ $row['required'] ? 'is-warn' : 'is-off' }}">
                                    {{ $row['required'] ? 'Required' : 'Not on file' }}</span>
                            @elseif ($row['verified'])
                                <span class="emp-pill is-ok">Checked</span>
                            @else
                                <span class="emp-pill is-info">Awaiting check</span>
                            @endif
                        </td>
                        <td data-label="File">
                            @forelse ($documents as $document)
                                <span class="emp-file">
                                    @if ($office)
                                        <a href="{{ route('users.documents.file', ['user' => $user, 'document' => $document]) }}">
                                            {{ $document->displayName() }}</a>
                                    @else
                                        <a href="{{ route('my.documents.file', $document) }}">
                                            {{ $document->displayName() }}</a>
                                    @endif
                                    @if ($document->isVerified())
                                        <span class="emp-tick" title="Checked by the office on {{ $document->verified_at->format('d M Y') }}">✓</span>
                                    @endif
                                </span>
                                @if (! $loop->last)<br>@endif
                            @empty
                                <span class="master-sub">—</span>
                            @endforelse
                        </td>
                        <td data-label="Size" class="is-num">
                            @foreach ($documents as $document)
                                {{ $document->sizeLabel() }}@if (! $loop->last)<br>@endif
                            @endforeach
                        </td>
                        <td data-label="Uploaded">
                            @foreach ($documents as $document)
                                {{ $document->created_at?->format('d M Y') }}@if (! $loop->last)<br>@endif
                            @endforeach
                        </td>
                        <td data-label="Action">
                            <div class="emp-row-actions">
                                @foreach ($documents as $document)
                                    <form method="POST"
                                        action="{{ $actionBase($office ? 'users.documents.verify' : 'my.documents.destroy', $document) }}">
                                        @csrf
                                        @method($office ? 'PATCH' : 'DELETE')
                                        @if ($office)
                                            <input type="hidden" name="verified" value="{{ $document->isVerified() ? '0' : '1' }}">
                                            <button class="master-btn master-btn-ghost master-btn-sm" type="submit"
                                                title="{{ $document->isVerified() ? 'Remove the verification mark' : 'Mark as seen' }}">
                                                {{ $document->isVerified() ? 'Uncheck' : 'Mark seen' }}
                                            </button>
                                        @elseif ($document->uploadedByEmployee() && ! $document->isVerified())
                                            <button class="master-btn master-btn-ghost master-btn-sm" type="submit"
                                                title="Remove this document">Remove</button>
                                        @endif
                                    </form>
                                    @if ($office && $document->isVerified())
                                        <span class="master-sub">by {{ $document->verifier?->name ?? 'the office' }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

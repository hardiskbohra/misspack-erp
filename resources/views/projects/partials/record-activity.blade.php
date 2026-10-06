<section class="master-tab-panel" id="project-panel-tracking" role="tabpanel" aria-labelledby="project-tab-tracking">
    <div class="project-blocks">
        <section class="master-card master-card--flat">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Activity updates</h2>
                    <p class="master-sub">{{ $project->trackingUpdates->count() }}
                        {{ \Illuminate\Support\Str::plural('update', $project->trackingUpdates->count()) }} —
                    the public ones are the client portal's timeline</p>
                </div>
                <div class="master-section-meta">
                    <button type="button" class="master-btn master-btn-primary addTrackingBtn" id="openAddTrackingModal">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add activity</button>
                </div>
            </div>
            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Title</th>
                            <th scope="col">Product</th>
                            <th scope="col">Status</th>
                            <th scope="col">Location</th>
                            <th scope="col">Visibility</th>
                            <th scope="col" class="project-col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($project->trackingUpdates as $tracking)
                            <tr>
                                <td data-label="Date">
                                    {{ optional($tracking->occurred_at)->format('d M Y') ?: '—' }}
                                    <span class="project-fact-note">{{ optional($tracking->occurred_at)->format('h:i A') }}</span>
                                </td>
                                <td data-label="Title"><strong>{{ $tracking->title }}</strong></td>
                                <td data-label="Product">{{ optional($tracking->product)->product_name ?: '—' }}</td>
                                <td data-label="Status">
                                    <span class="master-badge status-{{ str_replace('_', '-', (string) $tracking->status) }}">
                                    {{ $tracking->statusLabel() }}</span>
                                </td>
                                <td data-label="Location">{{ $tracking->location ?: '—' }}</td>
                                <td data-label="Visibility">
                                    <span class="master-badge {{ $tracking->is_public ? 'status-completed' : 'status-draft' }}">
                                    {{ $tracking->is_public ? 'Public' : 'Internal' }}</span>
                                </td>
                                <td data-label="Action" class="project-col-actions">
                                    <div class="master-row-actions">
                                        <button type="button" class="master-icon-btn editTrackingBtn"
                                            aria-label="Edit {{ $tracking->title }}"
                                            data-tracking='@json($tracking)'><i class="fas fa-pen" aria-hidden="true"></i></button>
                                        <form method="POST" action="{{ route('projects.tracking.destroy', $tracking) }}"
                                            data-confirm="Delete this activity update?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="master-icon-btn danger" aria-label="Delete {{ $tracking->title }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="master-empty-state">
                                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                        <p>No activity update yet. One public update per milestone keeps the client portal honest
                                        without a phone call.</p>
                                        <button type="button" class="master-btn master-btn-soft master-btn-sm"
                                            data-modal-open="addTrackingModal"><i class="fas fa-plus" aria-hidden="true"></i> Add activity</button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>


<!--Add Tracking-->
<div class="master-modal" id="addTrackingModal" aria-hidden="true">
    <div class="master-modal-card">
        <form method="POST" action="{{ route('projects.tracking.store', $project) }}">
            @csrf
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <div>
                        <h3 class="master-modal-title">Add Activity</h3>
                    </div>
                </div>
                <button type="button" class="master-modal-close" id="closeAddTrackingModal" data-close-modal>×</button>
            </div>
            <div class="master-modal-body">
                <div class="master-modal-grid">
                    <div class="master-field"><label class="master-label">Product (optional)</label><select class="master-select" name="project_product_id">
                            <option value="">Project level</option>
                            @foreach ($project->products as $projectProduct)
                                <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field"><label class="master-label">Title</label><input class="master-input" type="text" name="title"
                        placeholder="e.g. Artwork approved" required></div>
                    <div class="master-field"><label class="master-label">Status</label><select class="master-select" name="status">
                            @foreach ($trackingStatusOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field small"><label class="master-label">Progress %</label><input class="master-input" type="number"
                        name="progress_percent" min="0" max="100"
                        value="{{ $project->progress_percent }}"></div>
                    <div class="master-field"><label class="master-label">Activity By</label><input class="master-input" type="text" name="location"
                        placeholder="MP / Client / Vendor etc."></div>
                    <div class="master-field"><label class="master-label">Date & Time</label><input class="master-input" type="datetime-local"
                        name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}"></div>
                    <label class="master-check"><input type="checkbox" name="is_public" value="1" checked>
                    Public for client</label>
                    <div class="master-field full"><label class="master-label">Notes</label>
                        <textarea class="master-textarea" name="notes" rows="2" placeholder="Detailed tracking message"></textarea>
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelAddTrackingModal" data-close-modal>Cancel</button>
                <button class="master-btn master-btn-primary" type="submit"> Add Product</button>
            </div>
        </form>
    </div>
</div>

<!--Edit Tracking-->
<div class="master-modal" id="editTrackingModal">
    <div class="master-modal-card">
        <form id="editTrackingForm" method="POST"
            data-update-url="{{ route('projects.tracking.update', ['trackingUpdate' => '__ID__']) }}">
            @csrf
            @method('PUT')
            <div class="master-modal-header">
                <div>
                    <h3 class="master-modal-title">Edit Tracking</h3>
                </div>

                <button type="button" class="master-modal-close" id="closeEditTrackingModal" data-close-modal>×</button>
            </div>
            <div class="master-modal-body">
                <div class="master-modal-grid">

                    <div class="master-field">
                        <label class="master-label">Product (optional)</label>
                        <select class="master-select" name="project_product_id">
                            <option value="">Project level</option>
                            @foreach ($project->products as $projectProduct)
                                <option value="{{ $projectProduct->id }}">{{ $projectProduct->product_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Title</label>
                        <input class="master-input" type="text" name="title" placeholder="e.g. Artwork approved" required>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Status</label>
                        <select class="master-select" name="status">
                            @foreach ($trackingStatusOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field small">
                        <label class="master-label">Progress %</label>
                        <input class="master-input" type="number" name="progress_percent" min="0" max="100" value="{{ $project->progress_percent }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Location</label>
                        <input class="master-input" type="text" name="location" placeholder="Factory / Ahmedabad / etc.">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Date & Time</label>
                        <input class="master-input" type="datetime-local"
                            name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <label class="master-check">
                        <input class="master-check" type="checkbox" name="is_public" value="1" checked>
                        Public for client
                    </label>
                    <div class="master-field full">
                        <label class="master-label">Notes</label>
                        <textarea class="master-textarea" name="notes" rows="2" placeholder="Detailed tracking message"></textarea>
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelEditTrackingModal" data-close-modal>Cancel</button>
                <button class="master-btn master-btn-primary" type="submit"> Save Tracking</button>
            </div>
        </form>
    </div>
</div>

@if($notificationsAvailable)
    <div class="client-detail-tools">
        <div class="cpa-grid">
            <section class="cpa-card" aria-labelledby="client-send-notification-heading">
                <div class="cpa-section-head"><div><p class="cpa-eyebrow">Client portal</p><h2 id="client-send-notification-heading">Send notification</h2></div></div>
                <p class="client-detail-help">Send an update to active client portal users. Keep the title and message client-ready.</p>
                <form method="POST" action="{{ route('clients.portal.notifications.store', $client) }}" class="cpa-form-grid">
                    @csrf
                    <div class="master-field cpa-field-full">
                        <label class="master-label" for="clientNotificationTitle">Title *</label>
                        <input class="master-input" id="clientNotificationTitle" name="title" required maxlength="255" value="{{ old('title') }}" placeholder="e.g. Shipment documents are ready">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="clientNotificationType">Type</label>
                        <select class="master-select" id="clientNotificationType" name="type">
                            @foreach(['info' => 'Information', 'project' => 'Project', 'shipment' => 'Shipment', 'invoice' => 'Invoice', 'payment' => 'Payment', 'document' => 'Document', 'account' => 'Account'] as $key => $label)
                                <option value="{{ $key }}" @selected(old('type', 'info') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="clientNotificationActionUrl">Action URL</label>
                        <input class="master-input" id="clientNotificationActionUrl" name="action_url" maxlength="255" value="{{ old('action_url') }}" placeholder="/client-portal/projects">
                    </div>
                    <div class="master-field cpa-field-full">
                        <label class="master-label" for="clientNotificationMessage">Message</label>
                        <textarea class="master-textarea" id="clientNotificationMessage" name="message" maxlength="4000" rows="5" placeholder="Add the details the client needs to know.">{{ old('message') }}</textarea>
                    </div>
                    <div class="cpa-submit"><button class="master-btn master-btn-primary" type="submit"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send notification</button></div>
                </form>
            </section>

            <section class="cpa-card" aria-labelledby="client-notification-history-heading">
                <div class="cpa-section-head">
                    <div><p class="cpa-eyebrow">Recent activity</p><h2 id="client-notification-history-heading">Notification history</h2></div>
                    <span class="cpa-badge">{{ $notifications->count() }}</span>
                </div>
                <div class="client-notification-list">
                    @forelse($notifications as $notification)
                        <article class="client-notification-row {{ $notification->is_read ? '' : 'is-unread' }}">
                            <div class="client-notification-row-head">
                                <strong>{{ $notification->title }}</strong>
                                <span class="cpa-badge {{ $notification->is_read ? '' : 'active' }}">{{ $notification->is_read ? 'Read' : 'Unread' }}</span>
                            </div>
                            @if($notification->message)
                                <p>{{ $notification->message }}</p>
                            @endif
                            <div class="client-notification-meta">
                                <span>{{ $notification->portalUser?->displayName() ?: 'Client portal' }}</span>
                                <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->format('d M Y, h:i A') ?: '—' }}</time>
                                @if($notification->action_url)
                                    <a href="{{ $notification->action_url }}" target="_blank" rel="noopener">Open destination <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="cpa-empty">No portal notifications have been sent to this client yet.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@else
    <div class="master-card master-card--flat client-detail-card client-detail-card--wide">
        <div class="master-empty-state"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>Client portal notifications are not available in this installation.</p></div>
    </div>
@endif

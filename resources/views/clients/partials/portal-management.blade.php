@if($portalInstalled)
    <div class="client-detail-tools">
        <div class="cpa-grid">
            <section class="cpa-card" aria-labelledby="client-primary-portal-user-heading">
                <div class="cpa-section-head">
                    <div><p class="cpa-eyebrow">Credentials</p><h2 id="client-primary-portal-user-heading">Primary portal user</h2></div>
                    <span class="cpa-badge {{ $portalUser && $portalUser->is_active && $portalUser->portal_enabled ? 'active' : '' }}">
                        {{ $portalUser && $portalUser->is_active && $portalUser->portal_enabled ? 'Enabled' : 'Not enabled' }}
                    </span>
                </div>
                @if($portalUser)
                    <p class="client-portal-last-login">Last login: {{ $portalUser->last_login_at?->format('d M Y, h:i A') ?: 'No login yet' }}</p>
                @endif

                <form method="POST" action="{{ route('clients.portal.store', $client) }}" class="cpa-form-grid">
                    @csrf
                    @if($portalUser)<input type="hidden" name="portal_user_id" value="{{ $portalUser->id }}">@endif
                    <div class="master-field">
                        <label class="master-label" for="primaryPortalName">Name</label>
                        <input class="master-input" id="primaryPortalName" name="name" value="{{ old('name', $portalUser?->name ?? ($client->account_person_name ?: $client->company_name)) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="primaryPortalUsername">Username *</label>
                        <input class="master-input" id="primaryPortalUsername" name="username" required autocomplete="off" value="{{ old('username', $portalUser?->username ?? \Illuminate\Support\Str::slug($client->brand_name ?: $client->company_name, '')) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="primaryPortalEmail">Email</label>
                        <input class="master-input" id="primaryPortalEmail" type="email" name="email" value="{{ old('email', $portalUser?->email ?? ($client->account_person_email ?: $client->ceo_email)) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="primaryPortalMobile">Mobile</label>
                        <input class="master-input" id="primaryPortalMobile" name="mobile" value="{{ old('mobile', $portalUser?->mobile ?? ($client->account_person_contact ?: $client->ceo_contact)) }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="primaryPortalPassword">Temporary password</label>
                        <input class="master-input" id="primaryPortalPassword" type="password" name="password" minlength="12" autocomplete="new-password" placeholder="Leave blank to generate or keep current">
                    </div>
                    <div class="cpa-checks">
                        <label class="master-check"><input type="checkbox" name="generate_password" value="1" @checked(old('generate_password', ! $portalUser))> Generate temporary password</label>
                        <input type="hidden" name="portal_enabled" value="0"><label class="master-check"><input type="checkbox" name="portal_enabled" value="1" @checked(old('portal_enabled', $portalUser?->portal_enabled ?? true))> Portal enabled</label>
                        <input type="hidden" name="is_active" value="0"><label class="master-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $portalUser?->is_active ?? true))> Active user</label>
                        <input type="hidden" name="must_change_password" value="0"><label class="master-check"><input type="checkbox" name="must_change_password" value="1" @checked(old('must_change_password', $portalUser?->must_change_password ?? true))> Require password change</label>
                    </div>
                    <div class="cpa-submit"><button class="master-btn master-btn-primary" type="submit">Save portal credentials</button></div>
                </form>

                @if($oneTimePassword && $credentialUser)
                    <div class="cpa-password-box">
                        <span>One-time password for {{ $credentialUser->displayName() }}</span>
                        <strong id="portal-one-time-password">{{ $oneTimePassword }}</strong>
                        <button class="master-btn master-btn-soft master-btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('portal-one-time-password').innerText)">Copy password</button>
                    </div>
                @endif

                @if($portalUser)
                    <form method="POST" action="{{ route('clients.portal.users.resetPassword', [$client, $portalUser]) }}" class="client-portal-reset-form">
                        @csrf
                        <button class="master-btn master-btn-soft" type="submit">Reset primary password</button>
                    </form>
                @endif
            </section>

            <section class="cpa-card" aria-labelledby="client-share-portal-heading">
                <div class="cpa-section-head"><div><p class="cpa-eyebrow">Share</p><h2 id="client-share-portal-heading">Share login details</h2></div></div>
                @if($portalUsers->isNotEmpty() && $portalLoginUrl)
                    <form method="GET" action="{{ route('clients.show', $client) }}" class="master-field client-portal-user-picker">
                        <input type="hidden" name="tab" value="portal">
                        <label class="master-label" for="share-portal-user">Choose portal user</label>
                        <select class="master-select" id="share-portal-user" name="portal_user_id" onchange="this.form.submit()">
                            @foreach($portalUsers as $user)
                                <option value="{{ $user->id }}" @selected((int) $credentialUser?->id === (int) $user->id)>{{ $user->displayName() }} · {{ $user->username }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif

                @if($credentialUser && $portalLoginUrl)
                    @php
                        $sharePhone = preg_replace('/[^0-9]/', '', (string) ($credentialUser->mobile ?: $client->account_person_contact ?: $client->ceo_contact));
                    @endphp
                    <div class="master-field"><label class="master-label">Sharing credentials for</label><input class="master-input" readonly value="{{ $credentialUser->displayName() }} · {{ $credentialUser->username }}"></div>
                    <div class="master-field"><label class="master-label">Login URL</label><input class="master-input" readonly id="portalLoginUrl" value="{{ $portalLoginUrl }}"></div>
                    <div class="master-field"><label class="master-label" for="portalShareMessage">Share message</label><textarea class="master-textarea" readonly id="portalShareMessage" rows="7">{{ $shareMessage }}</textarea></div>
                    <div class="cpa-actions-wrap">
                        <button class="master-btn master-btn-soft" type="button" onclick="navigator.clipboard.writeText(document.getElementById('portalShareMessage').value)">Copy message</button>
                        @if($sharePhone)
                            <a class="master-btn master-btn-green" target="_blank" rel="noopener" href="https://wa.me/{{ $sharePhone }}?text={{ rawurlencode($shareMessage) }}">Share on WhatsApp</a>
                        @endif
                        @if($credentialUser->email)
                            <a class="master-btn master-btn-light-dark" href="mailto:{{ $credentialUser->email }}?subject={{ rawurlencode('MissPack Client Portal Login') }}&amp;body={{ rawurlencode($shareMessage) }}">Share by email</a>
                        @endif
                        <form method="POST" action="{{ route('clients.portal.users.markShared', [$client, $credentialUser]) }}">
                            @csrf
                            <button class="master-btn master-btn-primary" type="submit">Mark as shared</button>
                        </form>
                    </div>
                    <p class="cpa-muted">Last shared with {{ $credentialUser->displayName() }}: {{ $credentialUser->invitation_sent_at?->format('d M Y, h:i A') ?: 'Not marked' }}</p>
                @else
                    <div class="cpa-empty">Create portal credentials first to share login details.</div>
                @endif

                @if(\Illuminate\Support\Facades\Route::has('client-portal.login'))
                    <div class="client-portal-open-link"><a class="master-btn master-btn-soft" href="{{ route('client-portal.login') }}" target="_blank" rel="noopener">Open client portal</a></div>
                @endif
            </section>
        </div>

        <div class="cpa-grid cpa-team-grid">
            <section class="cpa-card" aria-labelledby="client-team-portal-heading">
                <div class="cpa-section-head">
                    <div><p class="cpa-eyebrow">Team access</p><h2 id="client-team-portal-heading">Additional portal users</h2></div>
                    <span class="cpa-badge">{{ max($portalUsers->count() - 1, 0) }}</span>
                </div>
                @forelse($portalUsers->skip(1) as $teamUser)
                    <div class="cpa-secondary-user">
                        <div class="cpa-secondary-user-head">
                            <div><strong>{{ $teamUser->displayName() }}</strong><span>{{ $teamUser->username }} · {{ $teamUser->email ?: 'No email' }}</span></div>
                            <span class="cpa-badge {{ $teamUser->is_active && $teamUser->portal_enabled ? 'active' : '' }}">{{ $teamUser->is_active && $teamUser->portal_enabled ? 'Enabled' : 'Disabled' }}</span>
                        </div>
                        <form method="POST" action="{{ route('clients.portal.store', $client) }}" class="cpa-form-grid cpa-secondary-form">
                            @csrf
                            <input type="hidden" name="portal_user_id" value="{{ $teamUser->id }}">
                            <div class="master-field"><label class="master-label">Name</label><input class="master-input" name="name" value="{{ $teamUser->name }}" required></div>
                            <div class="master-field"><label class="master-label">Username</label><input class="master-input" name="username" value="{{ $teamUser->username }}" required></div>
                            <div class="master-field"><label class="master-label">Email</label><input class="master-input" type="email" name="email" value="{{ $teamUser->email }}"></div>
                            <div class="master-field"><label class="master-label">Mobile</label><input class="master-input" name="mobile" value="{{ $teamUser->mobile }}"></div>
                            <div class="master-field"><label class="master-label">Set temporary password</label><input class="master-input" type="password" name="password" minlength="12" autocomplete="new-password" placeholder="Leave blank to keep current"></div>
                            <div class="cpa-checks">
                                <input type="hidden" name="portal_enabled" value="0"><label class="master-check"><input type="checkbox" name="portal_enabled" value="1" @checked($teamUser->portal_enabled)> Portal enabled</label>
                                <input type="hidden" name="is_active" value="0"><label class="master-check"><input type="checkbox" name="is_active" value="1" @checked($teamUser->is_active)> Active user</label>
                                <input type="hidden" name="must_change_password" value="0"><label class="master-check"><input type="checkbox" name="must_change_password" value="1" @checked($teamUser->must_change_password)> Require password change</label>
                            </div>
                            <div class="cpa-submit"><button class="master-btn master-btn-soft" type="submit">Save user</button></div>
                        </form>
                        <div class="cpa-secondary-actions">
                            <form method="POST" action="{{ route('clients.portal.users.resetPassword', [$client, $teamUser]) }}">@csrf<button class="master-btn master-btn-light-dark master-btn-sm" type="submit">Reset password</button></form>
                            <a class="master-btn master-btn-light-dark master-btn-sm" href="{{ route('clients.show', ['client' => $client, 'tab' => 'portal', 'portal_user_id' => $teamUser->id]) }}">Prepare share message</a>
                        </div>
                    </div>
                @empty
                    <div class="cpa-empty">Only the primary portal account is configured. Add additional users for finance, operations or management.</div>
                @endforelse
            </section>

            <section class="cpa-card" aria-labelledby="client-add-portal-heading">
                <div class="cpa-section-head"><div><p class="cpa-eyebrow">Invite a colleague</p><h2 id="client-add-portal-heading">Add portal user</h2></div></div>
                <form method="POST" action="{{ route('clients.portal.store', $client) }}" class="cpa-form-grid">
                    @csrf
                    <input type="hidden" name="create_user" value="1">
                    <div class="master-field"><label class="master-label">Name *</label><input class="master-input" name="name" required></div>
                    <div class="master-field"><label class="master-label">Username *</label><input class="master-input" name="username" required autocomplete="off"></div>
                    <div class="master-field"><label class="master-label">Email</label><input class="master-input" type="email" name="email"></div>
                    <div class="master-field"><label class="master-label">Mobile</label><input class="master-input" name="mobile"></div>
                    <div class="master-field"><label class="master-label">Temporary password <span class="cpa-muted">(leave blank to generate)</span></label><input class="master-input" type="password" name="password" minlength="12" autocomplete="new-password"></div>
                    <div class="cpa-checks">
                        <label class="master-check"><input type="checkbox" name="generate_password" value="1" checked> Generate temporary password</label>
                        <input type="hidden" name="portal_enabled" value="0"><label class="master-check"><input type="checkbox" name="portal_enabled" value="1" checked> Enable portal access</label>
                        <input type="hidden" name="is_active" value="0"><label class="master-check"><input type="checkbox" name="is_active" value="1" checked> Active user</label>
                        <input type="hidden" name="must_change_password" value="0"><label class="master-check"><input type="checkbox" name="must_change_password" value="1" checked> Require password change</label>
                    </div>
                    <div class="cpa-submit"><button class="master-btn master-btn-primary" type="submit">Create portal user</button></div>
                </form>
                <div class="cpa-support-shortcut">
                    <strong>{{ $supportCount }} support {{ \Illuminate\Support\Str::plural('request', $supportCount) }}</strong>
                    <span>Review and respond to client questions from the portal inbox.</span>
                    @if(\Illuminate\Support\Facades\Route::has('clients.portal.support.index'))
                        <a class="master-btn master-btn-soft" href="{{ route('clients.portal.support.index', $client) }}">Open support inbox</a>
                    @endif
                </div>
            </section>
        </div>

    </div>
@else
    <div class="master-card master-card--flat client-detail-card client-detail-card--wide">
        <div class="master-empty-state"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>Client portal access is not available in this installation.</p></div>
    </div>
@endif

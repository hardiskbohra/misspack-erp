@php($empty = fn ($value) => blank($value))
<section class="master-tab-panel" id="vendor-panel-contacts" role="tabpanel" aria-labelledby="vendor-tab-contacts">
    <div class="vendor-detail-grid">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-contacts-person-heading">
            <h2 class="vendor-detail-title" id="vendor-contacts-person-heading">Contact person</h2>
            <div class="master-facts">
                <div class="master-info"><span>Name</span><strong @class(['master-empty-value' => $empty($vendor->contact_person_name)])>{{ $vendor->contact_person_name ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Email</span>
                    @if ($vendor->contact_person_email)
                        <a class="vendor-detail-link" href="mailto:{{ $vendor->contact_person_email }}">{{ $vendor->contact_person_email }}</a>
                    @else
                        <strong class="master-empty-value">Not on file</strong>
                    @endif
                </div>
                <div class="master-info"><span>Mobile</span>
                    @if ($vendor->contact_person_mobile)
                        <a class="vendor-detail-link" href="tel:{{ preg_replace('/[^0-9+]/', '', $vendor->contact_person_mobile) }}">{{ $vendor->contact_person_mobile }}</a>
                    @else
                        <strong class="master-empty-value">Not on file</strong>
                    @endif
                </div>
                <div class="master-info"><span>WhatsApp</span><strong @class(['master-empty-value' => $empty($vendor->whatsapp_number)])>{{ $vendor->whatsapp_number ?: 'Not on file' }}</strong></div>
                <div class="master-info"><span>Alternate contact</span><strong @class(['master-empty-value' => $empty($vendor->alternate_contact)])>{{ $vendor->alternate_contact ?: 'Not on file' }}</strong></div>
            </div>
            <div class="vendor-detail-actions">
                @if ($vendor->contact_person_email)
                    <a class="master-btn master-btn-soft master-btn-sm" href="mailto:{{ $vendor->contact_person_email }}">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i> Email contact
                    </a>
                @endif
                @if ($vendor->contact_person_mobile)
                    <a class="master-btn master-btn-soft master-btn-sm" href="tel:{{ preg_replace('/[^0-9+]/', '', $vendor->contact_person_mobile) }}">
                        <i class="fa-solid fa-phone" aria-hidden="true"></i> Call contact
                    </a>
                @endif
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-contacts-links-heading">
            <h2 class="vendor-detail-title" id="vendor-contacts-links-heading">Online presence</h2>
            <div class="master-facts">
                <div class="master-info is-wide"><span>Website</span>
                    @if ($vendor->website)
                        <a class="vendor-detail-link" href="{{ $vendor->website }}" target="_blank" rel="noopener noreferrer">{{ $vendor->website }}</a>
                    @else
                        <strong class="master-empty-value">Not on file</strong>
                    @endif
                </div>
                <div class="master-info is-wide"><span>Alibaba profile</span>
                    @if ($vendor->alibaba_link)
                        <a class="vendor-detail-link" href="{{ $vendor->alibaba_link }}" target="_blank" rel="noopener noreferrer">Open Alibaba profile</a>
                    @else
                        <strong class="master-empty-value">Not on file</strong>
                    @endif
                </div>
            </div>
            <p class="vendor-detail-help">Links open in a new window and are stored exactly as entered.</p>
        </section>
    </div>
</section>

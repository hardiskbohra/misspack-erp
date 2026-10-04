<div class="vendor-detail-grid">
    <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-primary-contact-heading">
        <h2 class="master-section-title" id="vendor-primary-contact-heading">Primary contact</h2>
        <div class="master-facts">
            <div class="master-info"><span>Contact person</span><strong class="{{ blank($vendor->contact_person_name) ? 'master-empty-value' : '' }}">{{ $vendor->contact_person_name ?: 'Not on file' }}</strong></div>
            <div class="master-info"><span>Mobile</span>
                @if ($vendor->contact_person_mobile)
                    <a class="vendor-detail-link" href="tel:{{ preg_replace('/[^0-9+]/', '', $vendor->contact_person_mobile) }}">{{ $vendor->contact_person_mobile }}</a>
                @else
                    <strong class="master-empty-value">Not on file</strong>
                @endif
            </div>
            <div class="master-info is-wide"><span>Email</span>
                @if ($vendor->contact_person_email)
                    <a class="vendor-detail-link" href="mailto:{{ $vendor->contact_person_email }}">{{ $vendor->contact_person_email }}</a>
                @else
                    <strong class="master-empty-value">Not on file</strong>
                @endif
            </div>
        </div>
        <div class="vendor-contact-actions">
            @if ($vendor->contact_person_email)
                <a class="master-btn master-btn-soft" href="mailto:{{ $vendor->contact_person_email }}"><i class="fa-regular fa-envelope" aria-hidden="true"></i> Email contact</a>
            @endif
            @if ($vendor->contact_person_mobile)
                <a class="master-btn master-btn-soft" href="tel:{{ preg_replace('/[^0-9+]/', '', $vendor->contact_person_mobile) }}"><i class="fa-solid fa-phone" aria-hidden="true"></i> Call contact</a>
            @endif
        </div>
    </section>

    <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-alternate-contact-heading">
        <h2 class="master-section-title" id="vendor-alternate-contact-heading">Other contact channels</h2>
        <div class="master-facts">
            <div class="master-info"><span>WhatsApp number</span>
                @if ($vendor->whatsapp_number)
                    <a class="vendor-detail-link" href="https://wa.me/{{ preg_replace('/\D+/', '', $vendor->whatsapp_number) }}" target="_blank" rel="noopener">{{ $vendor->whatsapp_number }} <span class="visually-hidden">(opens WhatsApp in a new tab)</span></a>
                @else
                    <strong class="master-empty-value">Not on file</strong>
                @endif
            </div>
            <div class="master-info"><span>Alternate contact</span>
                @if ($vendor->alternate_contact)
                    <a class="vendor-detail-link" href="tel:{{ preg_replace('/[^0-9+]/', '', $vendor->alternate_contact) }}">{{ $vendor->alternate_contact }}</a>
                @else
                    <strong class="master-empty-value">Not on file</strong>
                @endif
            </div>
            <div class="master-info"><span>Website</span><strong class="vendor-profile-url {{ blank($vendor->website) ? 'master-empty-value' : '' }}">{{ $vendor->website ?: 'Not on file' }}</strong></div>
            <div class="master-info"><span>Alibaba profile</span><strong class="vendor-profile-url {{ blank($vendor->alibaba_link) ? 'master-empty-value' : '' }}">{{ $vendor->alibaba_link ?: 'Not on file' }}</strong></div>
        </div>
        <a href="{{ route('vendors.edit', $vendor) }}#vendor-contacts" class="vendor-inline-link vendor-contact-edit">Edit contact details <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    </section>
</div>

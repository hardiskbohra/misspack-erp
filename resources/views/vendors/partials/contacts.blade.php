@php
    /**
     * Contacts: the primary person on the vendor row, then the rest of the
     * supplier's desk. The primary contact is edited on the vendor form
     * because it lives on the vendor row; the others are their own records.
     */
    $empty = fn ($value) => blank($value);
    $contacts = $vendor->relationLoaded('contacts') ? $vendor->contacts : collect();
@endphp
<section class="master-tab-panel" id="vendor-panel-contacts" role="tabpanel" aria-labelledby="vendor-tab-contacts">
    <div class="vendor-panel-head">
        <div>
            <h2 class="vendor-detail-title">Contacts</h2>
            <p class="vendor-detail-help">Who to call at this supplier — orders, accounts and dispatch.</p>
        </div>
        <div class="vendor-panel-meta">
            <span class="vendor-pill">{{ $contacts->count() + ($vendor->contact_person_name ? 1 : 0) }} on file</span>
            @if ($contactsAvailable)
                <button type="button" class="master-btn master-btn-primary master-btn-sm" id="openAddContactModal">
                    <i class="fas fa-plus" aria-hidden="true"></i> Add contact
                </button>
            @endif
        </div>
    </div>

    <div class="vendor-detail-grid">
        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-primary-contact-heading">
            <div class="vendor-card-head">
                <h3 class="vendor-detail-title" id="vendor-primary-contact-heading">Primary contact</h3>
                <a class="vendor-card-link" href="{{ route('vendors.edit', $vendor) }}">Edit on the vendor form</a>
            </div>
            <div class="master-facts">
                <div class="master-info"><span>Contact person</span><strong @class(['master-empty-value' => $empty($vendor->contact_person_name)])>{{ $vendor->contact_person_name ?: 'Not on file' }}</strong></div>
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
                <div class="master-info"><span>Alternate number</span><strong @class(['master-empty-value' => $empty($vendor->alternate_contact)])>{{ $vendor->alternate_contact ?: 'Not on file' }}</strong></div>
            </div>
        </section>

        <section class="master-card master-card--flat vendor-detail-card" aria-labelledby="vendor-contact-desk-heading">
            <div class="vendor-card-head">
                <h3 class="vendor-detail-title" id="vendor-contact-desk-heading">Other people</h3>
                <span class="vendor-card-link">{{ $contacts->count() }} added</span>
            </div>

            @if (! $contactsAvailable)
                <p class="vendor-empty-text">Contacts are not enabled on this database yet.</p>
            @elseif ($contacts->isEmpty())
                <p class="vendor-empty-text">No other contacts yet. Add the accounts person or the dispatch clerk so nobody has to ask around.</p>
            @else
                <div class="vendor-contact-list">
                    @foreach ($contacts as $contact)
                        @php($contactPayload = [
                            'id' => $contact->id,
                            'name' => $contact->name,
                            'designation' => $contact->designation,
                            'email' => $contact->email,
                            'mobile' => $contact->mobile,
                            'whatsapp' => $contact->whatsapp,
                            'notes' => $contact->notes,
                            'url' => route('vendors.contacts.update', ['vendor' => $vendor, 'contact' => $contact]),
                        ])
                        <article class="vendor-contact-card">
                            <div class="vendor-contact-head">
                                <span class="vendor-comment-avatar" aria-hidden="true">{{ strtoupper(mb_substr($contact->name ?: 'C', 0, 1)) }}</span>
                                <div class="vendor-contact-copy">
                                    <strong>{{ $contact->name }}</strong>
                                    <span class="master-sub">{{ $contact->designation ?: 'Designation not set' }}</span>
                                </div>
                                <div class="master-row-actions">
                                    <button type="button" class="master-icon-btn editContactBtn"
                                        aria-label="Edit {{ $contact->name }}"
                                        data-contact='@json($contactPayload)'>
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </button>
                                    <form method="POST" action="{{ route('vendors.contacts.destroy', ['vendor' => $vendor, 'contact' => $contact]) }}"
                                        data-confirm="Remove {{ $contact->name }} from this vendor?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="master-icon-btn danger" aria-label="Remove {{ $contact->name }}">
                                            <i class="far fa-trash-alt" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="vendor-contact-lines">
                                @if ($contact->mobile)
                                    <a class="vendor-contact-line" href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->mobile) }}">
                                        <i class="fa-solid fa-phone" aria-hidden="true"></i> {{ $contact->mobile }}
                                    </a>
                                @endif
                                @if ($contact->whatsapp)
                                    <a class="vendor-contact-line" href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $contact->whatsapp) }}" target="_blank" rel="noopener noreferrer">
                                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> {{ $contact->whatsapp }}
                                    </a>
                                @endif
                                @if ($contact->email)
                                    <a class="vendor-contact-line" href="mailto:{{ $contact->email }}">
                                        <i class="fa-regular fa-envelope" aria-hidden="true"></i> {{ $contact->email }}
                                    </a>
                                @endif
                            </div>
                            @if ($contact->notes)
                                <p class="vendor-attachment-note">{{ $contact->notes }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</section>

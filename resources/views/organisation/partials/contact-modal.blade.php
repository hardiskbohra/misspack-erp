<div class="master-modal" id="orgContactModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="orgContactTitle">
        <form method="POST" action="{{ route('settings.organisation.contacts.store') }}" id="orgContactForm"
            data-store="{{ route('settings.organisation.contacts.store') }}"
            data-add-title="Add a contact"
            data-edit-title="Edit contact"
            data-add-sub="One person on a desk — sales, accounts, operations."
            data-edit-sub="This is who the office names for that department.">
            @csrf
            <input type="hidden" name="_method" value="PUT" data-org-method disabled>
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="orgContactTitle" data-org-title>Add a contact</h3>
                        <p class="master-modal-subtitle" data-org-subtitle>One person on a desk.</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="orgContactModal" aria-label="Close">&times;</button>
            </div>
            <div class="master-modal-body">
                @include('organisation.partials.contact-fields')
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-ghost" data-close-modal="orgContactModal">Cancel</button>
                <button type="submit" class="master-btn master-btn-primary">Save contact</button>
            </div>
        </form>
    </div>
</div>

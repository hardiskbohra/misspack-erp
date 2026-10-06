<div class="master-modal" id="orgSocialModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="orgSocialTitle">
        <form method="POST" action="{{ route('organisation.socials.store') }}" id="orgSocialForm"
            data-store="{{ route('organisation.socials.store') }}"
            data-add-title="Add a social link"
            data-edit-title="Edit social link"
            data-add-sub="Instagram, Facebook, LinkedIn, Pinterest and the rest."
            data-edit-sub="Stickers and public pages read Instagram from this list.">
            @csrf
            <input type="hidden" name="_method" value="PUT" data-org-method disabled>
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-share-nodes"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="orgSocialTitle" data-org-title>Add a social link</h3>
                        <p class="master-modal-subtitle" data-org-subtitle>Where the company is online.</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="orgSocialModal" aria-label="Close">&times;</button>
            </div>
            <div class="master-modal-body">
                @include('organisation.partials.social-fields')
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-ghost" data-close-modal="orgSocialModal">Cancel</button>
                <button type="submit" class="master-btn master-btn-primary">Save link</button>
            </div>
        </form>
    </div>
</div>

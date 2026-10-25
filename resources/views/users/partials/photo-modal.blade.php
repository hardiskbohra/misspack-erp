<div class="master-modal" id="userPhotoModal" aria-hidden="true"
    data-photo-url="{{ $user->avatar_url }}" data-photo-name="{{ $user->name }}"
    data-reopen-photo="{{ (int) session('edit_user_photo') === (int) $user->id ? '1' : '0' }}">
    <div class="master-modal-card is-narrow" role="dialog" aria-modal="true" aria-labelledby="userPhotoTitle">
        <form method="POST" enctype="multipart/form-data"
            action="{{ route('users.photo.update', ['user' => $user, 'tab' => $tab, 'year' => $salaryYearQuery]) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="remove_avatar" value="0">
            <header class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fas fa-camera"></i></span>
                    <div><h2 class="master-modal-title" id="userPhotoTitle">Edit photo</h2><p class="master-modal-subtitle">{{ $user->name }} · Only their photo will change</p></div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="userPhotoModal" aria-label="Close photo editor">&times;</button>
            </header>
            <div class="master-modal-body">
                @if ($errors->userPhoto->any())
                    <div class="master-error" role="alert" id="recordPhotoError">
                        @foreach ($errors->userPhoto->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif
                <div class="master-avatar-row" data-avatar-drop data-avatar-circle="recordPhotoCircle"
                    data-avatar-name="recordPhotoName" data-avatar-remove="delete">
                    <span class="master-avatar-picker">
                        <label class="master-avatar-preview" id="recordPhotoCircle" for="recordPhotoFile" aria-label="Choose a photo for {{ $user->name }}">
                            <span>{{ $user->initials }}</span>
                        </label>
                        <span class="master-avatar-camera" aria-hidden="true"><i class="fas fa-camera"></i></span>
                    </span>
                    <div class="master-avatar-info">
                        <p id="recordPhotoHelp">JPG, PNG, GIF or WEBP, up to 2 MB. Choose or drag a photo here.</p>
                        <div class="master-avatar-actions">
                            <label class="master-upload-btn" for="recordPhotoFile">
                                <i class="fas fa-upload" aria-hidden="true"></i> Change photo
                                <input type="file" id="recordPhotoFile" name="avatar" class="master-input" accept="image/jpeg,image/png,image/gif,image/webp"
                                    aria-describedby="recordPhotoHelp recordPhotoName{{ $errors->userPhoto->any() ? ' recordPhotoError' : '' }}">
                            </label>
                            <button type="button" class="master-remove-avatar-btn" id="recordPhotoRemove" hidden>Remove</button>
                            <span class="master-file-name" id="recordPhotoName" aria-live="polite">Current photo</span>
                        </div>
                    </div>
                </div>
                <p class="master-sub">Changes are applied only when you save. Cancel keeps the current photo.</p>
            </div>
            <footer class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="userPhotoModal">Cancel</button>
                <button type="submit" class="master-btn master-btn-primary" disabled>Save photo</button>
            </footer>
        </form>
    </div>
</div>

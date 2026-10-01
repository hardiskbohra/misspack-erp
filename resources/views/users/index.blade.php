@extends('layouts.app')

@section('title', 'Users')
@section('page-title', 'User Management')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/users.css') }}">
@endpush

@section('content')
<div class="users">
    {{-- Toolbar --}}
    <div class="master-toolbar" style="padding:0;padding-bottom:18px;">
        <form method="GET" action="{{ route('users.index') }}" class="master-search-form">
            <div class="master-search">
                <span><i class="fas fa-search"></i></span>
                <input type="text"
                       name="search"
                       class="master-input"
                       placeholder="Search by name, email, department…"
                       value="{{ $search }}">
            </div>
        </form>

        <button type="button" class="master-btn master-btn-primary" data-open-add-modal
                data-open-if-errors="{{ $errors->any() ? '1' : '0' }}">
            <i class="fas fa-plus"></i> Add User
        </button>
    </div>

    {{-- Users Table --}}
    <div class="master-card">
        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th class="master-col-number">#</th>
                        <th>User</th>
                        <th>Mobile</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Verified</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $i => $user)
                        @php
                            $colors = ['#4f8ef7','#6c63ff','#38d9a9','#f6ad55','#e879c0','#e53e6a','#22d3ee'];
                            $color  = $colors[$user->id % count($colors)];
                            $initials = $user->initials ?? strtoupper(substr($user->name ?? 'U', 0, 2));
                        @endphp
                        <tr>
                            <td class="master-col-number" data-label="#">{{ $users->firstItem() + $i }}</td>
                            <td class="master-user-td" data-label="User">
                                <div class="master-person">
                                    <div class="master-avatar" style="background:{{ $color }};">
                                        @if($user->avatar)
                                            <img src="{{ asset('storage/'.$user->avatar) }}" alt="{{ $user->name }}">
                                        @else
                                            {{ $initials }}
                                        @endif
                                    </div>
                                    <div>
                                        <div class="master-name">{{ $user->name }}</div>
                                        <div class="master-email">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Mobile">{{ $user->mobile ?? '—' }}</td>
                            <td data-label="Department">
                                @if($user->department)
                                    <span class="master-badge department">{{ $user->department }}</span>
                                @else
                                    <span class="master-muted">—</span>
                                @endif
                            </td>
                            <td data-label="Designation">
                                    {{ $user->designation ?? '-' }}
                            </td>
                            <td data-label="Verified">
                                @if($user->email_verified_at)
                                    <span class="master-badge verified"><i class="fas fa-check-circle"></i> Verified</span>
                                @else
                                    <span class="master-badge pending"><i class="fas fa-clock"></i> Pending</span>
                                @endif
                            </td>
                            <td data-label="Action">
                                <div class="master-actions">
                                    <button type="button"
                                            class="master-icon-btn edit"
                                            title="Edit"
                                            data-edit-user="{{ $user->id }}">
                                        <i class="fas fa-pen"></i>
                                    </button>

                                    @if($user->id !== auth()->id())
                                        <button type="button"
                                                class="master-icon-btn delete"
                                                title="Delete"
                                                data-delete-user="{{ $user->id }}"
                                                data-delete-name="{{ $user->name }}">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="master-empty">
                                    <i class="fas fa-master-cog"></i>
                                    <p>No users found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        {{-- Mobile Cards --}}
        <div class="master-mobile-list">
            @forelse($users as $user)
                @php
                    $colors = ['#4f8ef7','#6c63ff','#38d9a9','#f6ad55','#e879c0','#e53e6a','#22d3ee'];
                    $color  = $colors[$user->id % count($colors)];
                    $initials = $user->initials ?? strtoupper(substr($user->name ?? 'U', 0, 2));
                @endphp
        
                <div class="user-card">
        
                    <div class="user-card-header">
                        <div class="master-avatar" style="background:{{ $color }}">
                            @if($user->avatar)
                                <img src="{{ asset('storage/'.$user->avatar) }}" alt="">
                            @else
                                {{ $initials }}
                            @endif
                        </div>
                    
                        <div class="user-card-info">
                            <h4>{{ $user->name }}</h4>
                            <p>{{ $user->designation ?? 'No Designation' }}</p>
                        </div>
                    </div>
        
                    <div class="user-card-body">
        
                        <div class="user-row">
                            <span>Email</span>
                            <strong>{{ $user->email }}</strong>
                        </div>
        
                        <div class="user-row">
                            <span>Mobile</span>
                            <strong>{{ $user->mobile ?? '—' }}</strong>
                        </div>
        
                        <div class="user-row">
                            <span>Department</span>
                            <strong>{{ $user->department ?? '—' }}</strong>
                        </div>
        
                        <div class="user-row">
                            <span>Status</span>
        
                            @if($user->email_verified_at)
                                <span class="master-badge verified">
                                    <i class="fas fa-check-circle"></i> Verified
                                </span>
                            @else
                                <span class="master-badge pending">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                            @endif
                        </div>
        
                    </div>
        
                    <div class="user-card-footer" style="margin-bottom:10px;">
        
                        <button type="button"
                                class="master-icon-btn edit"
                                data-edit-user="{{ $user->id }}">
                            <i class="fas fa-pen"></i>
                        </button>
        
                        @if($user->id !== auth()->id())
                            <button type="button"
                                    class="master-icon-btn delete"
                                    data-delete-user="{{ $user->id }}"
                                    data-delete-name="{{ $user->name }}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        @endif
        
                    </div>
        
                </div>
        
            @empty
        
                <div class="master-empty">
                    No users found.
                </div>
        
            @endforelse
        </div>

        <div class="master-pagination">
            <x-pagination :items="$users" />
        </div>
    </div>
</div>

{{-- Add User Modal --}}
<div class="master-modal-overlay" id="addModal" aria-hidden="true">
    <div class="master-modal-box" role="dialog" aria-modal="true" aria-labelledby="addUserTitle">
        <div class="master-modal-head">
            <div class="master-modal-icon"><i class="fas fa-user-plus"></i></div>
            <div>
                <div class="master-modal-title" id="addUserTitle">Add User</div>
                <div class="master-modal-subtitle">Create a new user account</div>
            </div>
            <button type="button" class="master-modal-close" data-close-modal="addModal" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data" id="addForm">
            @csrf
            <div class="master-modal-body">
                <div class="master-avatar-row">
                    <div class="master-avatar-preview" id="addAvatarCircle"><i class="fas fa-user"></i></div>
                    <div class="master-avatar-info">
                        <p>Upload a profile photo. JPG, PNG or GIF, max 2MB.</p>
                        <div class="master-avatar-actions">
                            <label class="master-upload-btn" for="addAvatarFile">
                                <i class="fas fa-upload"></i> Choose file
                                <input class="master-input" type="file" id="addAvatarFile" name="avatar" accept="image/*" hidden>
                            </label>
                            <span class="master-file-name" id="addAvName">No file chosen</span>
                        </div>
                    </div>
                </div>

                <div class="master-section-label">Basic Information</div>
                <div class="master-form-grid">
                    <div class="master-field">
                        <label class="master-label">Full Name <span class="master-required">*</span></label>
                        <input type="text" name="name" class="master-input {{ $errors->has('name') ? 'is-invalid' : '' }}" placeholder="e.g. Jonathan Deo" value="{{ old('name') }}" required>
                        @error('name')<span class="master-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label">Email Address <span class="master-required">*</span></label>
                        <input type="email" name="email" class="master-input {{ $errors->has('email') ? 'is-invalid' : '' }}" placeholder="user@misspack.com" value="{{ old('email') }}" required>
                        @error('email')<span class="master-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label">Mobile Number</label>
                        <input type="text" name="mobile" class="master-input" placeholder="+91 9876543210" value="{{ old('mobile') }}">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Department</label>
                        <input type="text" name="department" class="master-input" placeholder="Technology" value="{{ old('department') }}">
                    </div>
                    <div class="master-field full">
                        <label class="master-label">Designation</label>
                        <input type="text" name="designation" class="master-input" placeholder="Senior Developer" value="{{ old('designation') }}">
                    </div>
                </div>

                <div class="master-section-label">Account Security</div>
                <div class="master-form-grid">
                    <div class="master-field">
                        <label class="master-label">Password <span class="master-required">*</span></label>
                        <div class="master-password-wrap">
                            <input type="password" name="password" id="addPw1" class="master-input {{ $errors->has('password') ? 'is-invalid' : '' }}" placeholder="Min. 6 characters" required>
                            <button type="button" class="master-password-toggle" data-toggle-password="addPw1" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                        </div>
                        @error('password')<span class="master-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label">Confirm Password <span class="master-required">*</span></label>
                        <div class="master-password-wrap">
                            <input type="password" name="password_confirmation" id="addPw2" class="master-input" placeholder="Repeat password" required>
                            <button type="button" class="master-password-toggle" data-toggle-password="addPw2" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="master-modal-footer">
                <button type="submit" class="master-btn master-btn-primary"><i class="fas fa-user-plus"></i> Add User</button>
                <button type="button" class="master-btn master-btn-light" data-close-modal="addModal">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit User Modal --}}
<div class="master-modal-overlay" id="editModal" aria-hidden="true">
    <div class="master-modal-box" role="dialog" aria-modal="true" aria-labelledby="editUserTitle">
        <div class="master-modal-head">
            <div class="master-modal-icon"><i class="fas fa-user-edit"></i></div>
            <div>
                <div class="master-modal-title" id="editUserTitle">Update User</div>
                <div class="master-modal-subtitle" id="editModalSub">Update user details</div>
            </div>
            <button type="button" class="master-modal-close" data-close-modal="editModal" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" id="editForm" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="remove_avatar" id="editRemoveAvatar" value="0">

            <div class="master-modal-body">
                <div class="master-avatar-row">
                    <div class="master-avatar-preview" id="editAvatarCircle"><i class="fas fa-user"></i></div>
                    <div class="master-avatar-info">
                        <p>Upload a new photo or remove the existing one.</p>
                        <div class="master-avatar-actions">
                            <label class="master-upload-btn" for="editAvatarFile">
                                <i class="fas fa-upload"></i> Change Photo
                                <input class="master-input" type="file" id="editAvatarFile" name="avatar" accept="image/*" hidden>
                            </label>
                            <button type="button" class="master-remove-avatar-btn" id="editRemoveBtn" style="display:none;">
                                <i class="fas fa-trash-alt"></i> Remove
                            </button>
                            <span class="master-file-name" id="editAvName">No file chosen</span>
                        </div>
                    </div>
                </div>

                <div class="master-section-label">Basic Information</div>
                <div class="master-form-grid">
                    <div class="master-field">
                        <label class="master-label">Full Name <span class="master-required">*</span></label>
                        <input type="text" name="name" id="editName" class="master-input" required>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Email Address <span class="master-required">*</span></label>
                        <input type="email" name="email" id="editEmail" class="master-input" required>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Mobile Number</label>
                        <input type="text" name="mobile" id="editMobile" class="master-input">
                    </div>
                    <div class="master-field">
                        <label class="master-label">Department</label>
                        <input type="text" name="department" id="editDepartment" class="master-input">
                    </div>
                    <div class="master-field full">
                        <label class="master-label">Designation</label>
                        <input type="text" name="designation" id="editDesignation" class="master-input">
                    </div>
                </div>

                <div class="master-section-label">Change Password</div>
                <div class="master-info-box">
                    <i class="fas fa-info-circle"></i>
                    Leave both fields blank to keep the current password unchanged.
                </div>
                <div class="master-form-grid">
                    <div class="master-field">
                        <label class="master-label">New Password</label>
                        <div class="master-password-wrap">
                            <input type="password" name="password" id="editPw1" class="master-input" placeholder="Min. 6 characters">
                            <button type="button" class="master-password-toggle" data-toggle-password="editPw1" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Confirm New Password</label>
                        <div class="master-password-wrap">
                            <input type="password" name="password_confirmation" id="editPw2" class="master-input" placeholder="Repeat new password">
                            <button type="button" class="master-password-toggle" data-toggle-password="editPw2" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="master-modal-footer">
                <button type="submit" class="master-btn master-btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                <button type="button" class="master-btn master-btn-light" data-close-modal="editModal">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Delete Confirmation --}}
<div class="master-modal-overlay" id="deleteModal" aria-hidden="true">
    <div class="master-confirm-box" role="dialog" aria-modal="true" aria-labelledby="deleteUserTitle">
        <div class="master-delete-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="master-delete-title" id="deleteUserTitle">Delete User</div>
        <div class="master-delete-desc" id="deleteDesc">Are you sure you want to delete this user? This action cannot be undone.</div>
        <div class="master-delete-actions">
            <form method="POST" id="deleteForm">
                @csrf
                @method('DELETE')
                <button type="submit" class="master-btn-danger">Yes, Delete</button>
            </form>
            <button type="button" class="master-btn-light" data-close-modal="deleteModal">Cancel</button>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('assets/js/users.js') }}"></script>
@endpush
@endsection

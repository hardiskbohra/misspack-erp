<div class="master-form-grid">
    <div class="master-field">
        <label class="master-label">Department</label>
        <select class="master-select" name="department">
            @foreach ($departments as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="master-field">
        <label class="master-label">Name</label>
        <input class="master-input" name="name" required>
    </div>
    <div class="master-field">
        <label class="master-label">Designation</label>
        <input class="master-input" name="designation" placeholder="Head of sales">
    </div>
    <div class="master-field">
        <label class="master-label">Email</label>
        <input class="master-input" type="email" name="email">
    </div>
    <div class="master-field">
        <label class="master-label">Mobile</label>
        <input class="master-input" name="mobile">
    </div>
    <div class="master-field">
        <label class="master-label">&nbsp;</label>
        <label class="master-check"><input type="checkbox" name="is_primary" value="1"> Primary for this desk</label>
    </div>
</div>

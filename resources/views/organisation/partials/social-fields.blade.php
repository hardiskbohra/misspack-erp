<div class="master-form-grid">
    <div class="master-field">
        <label class="master-label">Network</label>
        <select class="master-select" name="network">
            @foreach ($networks as $key => $meta)
                <option value="{{ $key }}">{{ $meta[0] }}</option>
            @endforeach
        </select>
    </div>
    <div class="master-field">
        <label class="master-label">Handle</label>
        <input class="master-input" name="handle" placeholder="@themisspack">
    </div>
    <div class="master-field full">
        <label class="master-label">URL</label>
        <input class="master-input" name="url" required placeholder="https://">
    </div>
</div>

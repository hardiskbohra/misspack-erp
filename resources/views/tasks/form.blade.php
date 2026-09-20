@extends('layouts.app')

@section('page-title', $task->exists ? 'Edit Task' : 'Add Task')

@section('content')
<style>
:root{--task-primary:#4f83f1;--task-primary-2:#6366f1;--task-soft:#edf5ff;--task-dark:#17233b;--task-muted:#7b8798;--task-border:#dfe7f3;--task-bg:#eef3ff;--task-red:#ef4770;--task-shadow:0 14px 35px rgba(25,42,70,.08)}.task-form-page,.task-form-page *{box-sizing:border-box}.task-form-page{background:var(--task-bg);min-height:calc(100vh - 70px);padding:28px;color:var(--task-dark)}.task-hero{background:linear-gradient(135deg,var(--task-primary),var(--task-primary-2));border-radius:24px;padding:24px;color:#fff;display:flex;justify-content:space-between;gap:18px;margin-bottom:18px;box-shadow:0 18px 45px rgba(79,131,241,.22)}.task-hero h1{margin:0;font-size:28px}.task-hero p{margin:7px 0 0;opacity:.9}.task-btn{border:0;border-radius:14px;padding:11px 16px;font-weight:900;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer}.task-btn-primary{background:#ef4770;color:#fff;box-shadow:0 10px 24px rgba(239,71,112,.24)}.task-btn-light{background:#f3f6fb;color:#17233b}.task-btn-white{background:rgba(255,255,255,.16);color:#fff;border:1px solid rgba(255,255,255,.3)}.task-card{background:#fff;border:1px solid var(--task-border);border-radius:22px;box-shadow:var(--task-shadow);padding:22px;max-width:1050px}.task-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.task-field.full{grid-column:1/-1}.task-label{display:block;margin-bottom:8px;color:#536079;font-size:12px;font-weight:900}.task-label span{color:#ef4770}.task-input,.task-select,.task-textarea{width:100%;border:1px solid #d8e2ef;border-radius:13px;padding:11px 13px;font-size:14px;font-weight:600;outline:none;background:#fff;color:#17233b}.task-input,.task-select{height:44px}.task-textarea{min-height:120px;resize:vertical}.task-input:focus,.task-select:focus,.task-textarea:focus{border-color:#4f83f1;box-shadow:0 0 0 3px rgba(79,131,241,.12)}.task-error{color:#be123c;font-size:12px;font-weight:800;margin-top:6px}.task-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px}.task-preview{grid-column:1/-1;border:1px solid var(--task-border);border-radius:18px;overflow:hidden;background:#f8fbff;display:none}.task-preview img{width:100%;max-height:260px;object-fit:cover;display:block}@media(max-width:767px){.task-form-page{padding:14px}.task-hero,.task-actions{flex-direction:column}.task-grid{grid-template-columns:1fr}.task-actions .task-btn,.task-hero .task-btn{width:100%}.task-card{padding:16px}}
</style>

@php
    $isEdit = $task->exists;
    $statusOptions = $statusOptions ?? \App\Models\Task::statusOptions();
    $priorityOptions = $priorityOptions ?? \App\Models\Task::priorityOptions();
    $categoryOptions = $categoryOptions ?? \App\Models\Task::categoryOptions();
@endphp

<div class="task-form-page">
    <div class="task-hero">
        <div>
            <h1>{{ $isEdit ? 'Edit Task' : 'Add Task' }}</h1>
            <p>Create rich kanban tasks with category, image, priority, assignee and board status.</p>
        </div>
        <a href="{{ route('tasks.index') }}" class="task-btn task-btn-white">Back to Kanban</a>
    </div>

    <div class="task-card">
        @if($errors->any())
            <div style="background:#fff0f4;color:#be123c;border:1px solid #fecdd3;border-radius:14px;padding:12px 14px;font-weight:800;margin-bottom:16px;">Please fix the errors and try again.</div>
        @endif

        <form method="POST" action="{{ $isEdit ? route('tasks.update', $task) : route('tasks.store') }}">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="task-grid">
                <div class="task-field full">
                    <label class="task-label">Task Title <span>*</span></label>
                    <input class="task-input" type="text" name="title" value="{{ old('title', $task->title) }}" required placeholder="Enter task title">
                    @error('title')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="task-field">
                    <label class="task-label">Property / Category</label>
                    <select class="task-select" name="category">
                        @foreach($categoryOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('category', $task->category ?: 'General') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('category')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="task-field">
                    <label class="task-label">Board Status</label>
                    <select class="task-select" name="status">
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('status', $task->status ?: 'new_request') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="task-field">
                    <label class="task-label">Priority <span>*</span></label>
                    <select class="task-select" name="priority" required>
                        @foreach($priorityOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('priority', $task->priority ?: 'medium') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('priority')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="task-field">
                    <label class="task-label">Assignee</label>
                    <select class="task-select" name="assignee_id">
                        <option value="">Unassigned</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ (string) old('assignee_id', $task->assignee_id) === (string) $user->id ? 'selected' : '' }}>{{ $user->name ?? $user->email }}</option>
                        @endforeach
                    </select>
                    @error('assignee_id')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="task-field">
                    <label class="task-label">Due Date</label>
                    <input class="task-input" type="date" name="due_date" value="{{ old('due_date', optional($task->due_date)->format('Y-m-d') ?? $task->due_date) }}">
                    @error('due_date')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="task-field">
                    <label class="task-label">Image URL</label>
                    <input class="task-input" type="text" name="image_url" id="taskImageUrl" value="{{ old('image_url', $task->image_url) }}" placeholder="https://... or /storage/...">
                    @error('image_url')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="task-preview" id="taskImagePreview"><img src="" alt="Task image preview"></div>

                <div class="task-field full">
                    <label class="task-label">Description</label>
                    <textarea class="task-textarea" name="description" placeholder="Write task details">{{ old('description', $task->description) }}</textarea>
                    @error('description')<div class="task-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="task-actions">
                <a href="{{ route('tasks.index') }}" class="task-btn task-btn-light">Cancel</a>
                <button type="submit" class="task-btn task-btn-primary">{{ $isEdit ? 'Update Task' : 'Add Task' }}</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('taskImageUrl');
    const preview = document.getElementById('taskImagePreview');
    const image = preview ? preview.querySelector('img') : null;
    function refreshPreview() {
        if (!input || !preview || !image) return;
        if (input.value.trim()) {
            preview.style.display = 'block';
            image.src = input.value.trim();
        } else {
            preview.style.display = 'none';
            image.src = '';
        }
    }
    input?.addEventListener('input', refreshPreview);
    refreshPreview();
});
</script>
@endsection

@extends('layouts.app')

@section('page-title', $task->exists ? 'Edit Task' : 'Add Task')

@section('content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tasks.css') }}">
@endpush
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
        <a href="{{ route('tasks.index') }}" class="master-btn master-btn-ghost">Back to Kanban</a>
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
                <div class="master-field full">
                    <label class="master-label">Task Title <span>*</span></label>
                    <input class="master-input" type="text" name="title" value="{{ old('title', $task->title) }}" required placeholder="Enter task title">
                    @error('title')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="master-field">
                    <label class="master-label">Property / Category</label>
                    <select class="master-select" name="category">
                        @foreach($categoryOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('category', $task->category ?: 'General') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('category')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="master-field">
                    <label class="master-label">Board Status</label>
                    <select class="master-select" name="status">
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('status', $task->status ?: 'new_request') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="master-field">
                    <label class="master-label">Priority <span>*</span></label>
                    <select class="master-select" name="priority" required>
                        @foreach($priorityOptions as $key => $label)
                            <option value="{{ $key }}" {{ old('priority', $task->priority ?: 'medium') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('priority')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="master-field">
                    <label class="master-label">Assignee</label>
                    <select class="master-select" name="assignee_id">
                        <option value="">Unassigned</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ (string) old('assignee_id', $task->assignee_id) === (string) $user->id ? 'selected' : '' }}>{{ $user->name ?? $user->email }}</option>
                        @endforeach
                    </select>
                    @error('assignee_id')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="master-field">
                    <label class="master-label">Due Date</label>
                    <input class="master-input" type="date" name="due_date" value="{{ old('due_date', optional($task->due_date)->format('Y-m-d') ?? $task->due_date) }}">
                    @error('due_date')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="master-field">
                    <label class="master-label">Image URL</label>
                    <input class="master-input" type="text" name="image_url" id="taskImageUrl" value="{{ old('image_url', $task->image_url) }}" placeholder="https://... or /storage/...">
                    @error('image_url')<div class="task-error">{{ $message }}</div>@enderror
                </div>

                <div class="task-preview" id="taskImagePreview"><img src="" alt="Task image preview"></div>

                <div class="master-field full">
                    <label class="master-label">Description</label>
                    <textarea class="master-textarea" name="description" placeholder="Write task details">{{ old('description', $task->description) }}</textarea>
                    @error('description')<div class="task-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="task-actions">
                <a href="{{ route('tasks.index') }}" class="master-btn master-btn-light">Cancel</a>
                <button type="submit" class="master-btn master-btn-primary">{{ $isEdit ? 'Update Task' : 'Add Task' }}</button>
            </div>
        </form>
    </div>
</div>


@push('scripts')
    <script src="{{ asset('assets/js/tasks.js') }}"></script>
@endpush
@endsection

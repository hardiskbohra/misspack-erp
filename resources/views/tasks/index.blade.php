@extends('layouts.app')

@section('page-title', 'Task Management')

@section('content')
    <div class="master">

        <!--<div class="master-stats master-top-grid desktop-only">-->
        <!--    <div class="master-stat orange"><span class="icon">!</span><div><p class="master-stat-title">Backlog</p><p class="master-stat-value">{{ $counts['backlog'] }}</p></div></div>-->
        <!--    <div class="master-stat blue"><span class="icon">+</span><div><p class="master-stat-title">New Request</p><p class="master-stat-value">{{ $counts['new_request'] }}</p></div></div>-->
        <!--    <div class="master-stat purple"><span class="icon">⇄</span><div><p class="master-stat-title">In Progress</p><p class="master-stat-value">{{ $counts['in_progress'] }}</p></div></div>-->
        <!--    <div class="master-stat teal"><span class="icon">✓</span><div><p class="master-stat-title">Complete</p><p class="master-stat-value">{{ $counts['completed'] }}</p></div></div>-->
        <!--</div>-->

        <div class="master-card" style="margin-bottom:15px;">
            <div class="master-toolbar" style="padding-bottom:0;">
                <div class="master-toolbar-left desktop-only">
                    <span class="master-help"><i class="fa-solid fa-hand-pointer"></i> Drag cards between columns to update
                        status</span>
                </div>
                <div class="master-toolbar-right">
                    <form method="POST" action="{{ route('tasks.markAll') }}" class="desktop-only"
                        onsubmit="return confirm('Mark all open tasks as completed?');">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="scope" value="{{ $scope }}">
                        <button class="master-btn master-btn-green" type="submit"><i class="fa-solid fa-check-double"></i> Mark
                            All Complete</button>
                    </form>
                    <a class="master-toggle {{ $showCompleted ? 'is-on' : '' }} desktop-only"
                        href="{{ route('tasks.index', array_merge(request()->except('page'), ['show_completed' => $showCompleted ? 0 : 1])) }}">
                        <span><i></i></span> {{ $showCompleted ? 'Hide Completed' : 'Show Completed' }}
                    </a>
                    
                    <button type="button" class="master-btn master-btn-primary" data-open-task-modal="create"><i
                            class="fa-solid fa-plus"></i> Add Task</button>
                </div>
            </div>
            <div class="master-filter-card">
                <form method="GET" action="{{ route('tasks.index') }}" class="master-filter-form">
                    <input type="hidden" name="show_completed" value="{{ $showCompleted ? 1 : 0 }}">
                    <div class="master-field search">
                        <label class="master-label">Search</label>
                        <input class="master-input" type="text" name="search" value="{{ $search }}"
                            placeholder="Search task, category, description...">
                    </div>
                    <div class="master-field desktop-only">
                        <label class="master-label">Scope</label>
                        <select class="master-select" name="category">
                            <option value="all" {{ $category === 'all' ? 'selected' : '' }}>All Category</option>
                            @foreach ($categoryOptions as $key => $label)
                                <option value="{{ $key }}" {{ $category === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field desktop-only">
                        <label class="master-label">Status</label>
                        <select class="master-select" name="status">
                            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Status</option>
                            @foreach ($statusOptions as $key => $label)
                                <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field desktop-only">
                        <label class="master-label">Priority</label>
                        <select class="master-select" name="priority">
                            <option value="all" {{ $priority === 'all' ? 'selected' : '' }}>All Priority</option>
                            @foreach ($priorityOptions as $key => $label)
                                <option value="{{ $key }}" {{ $priority === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field desktop-only">
                        <label class="master-label">Assignee</label>
                        <select class="master-select" name="assignee_id">
                            <option value="">All Assignees</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}"
                                    {{ (string) $assigneeId === (string) $user->id ? 'selected' : '' }}>
                                    {{ $user->name ?? $user->email }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-filter-actions">
                        <button type="submit" class="master-btn master-btn-primary"><i class="fa-solid fa-filter"></i>
                            Filter</button>
                        <a href="{{ route('tasks.index') }}" class="master-btn master-btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="master-card" style="padding:15px;">
            <div class="master-board" id="kanbanBoard" data-status-options='@json($statusOptions)'>
                @foreach ($columns as $columnKey => $column)
                    <section class="master-column column-{{ str_replace('_', '-', $columnKey) }}"
                        data-status="{{ $columnKey }}">
                        <div class="master-column-head">
                            <div>
                                <span class="master-dot"></span>
                                <h2 style="font-size:16px;font-weight:500;">{{ $column['label'] }}</h2>
                            </div>
                            <span class="master-count"
                                data-count-for="{{ $columnKey }}">{{ $column['tasks']->count() }}</span>
                        </div>
    
                        <div class="master-dropzone" data-dropzone="{{ $columnKey }}">
                            @forelse($column['tasks'] as $task)
                                <article class="master-task-card" draggable="true" data-task-id="{{ $task->id }}"
                                    data-status="{{ $task->status }}"
                                    data-update-status-url="{{ route('tasks.status.update', $task) }}">
                                    @if ($task->image_url)
                                        <div class="master-task-image">
                                            <img src="{{ $task->image_url }}" alt="{{ $task->title }}" loading="lazy">
                                        </div>
                                    @endif
    
                                    <div class="master-task-top">
                                        <div>
                                            <span class="master-category category-{{ \Illuminate\Support\Str::slug($task->category ?: 'General') }}">
                                                {{ $task->category ?: 'General' }}
                                            </span>
                                            <span
                                                class="master-priority priority-{{ $task->priorityColorClass() }}">{{ $task->priorityLabel() }}</span>
                                        </div>
                                        <div class="master-menu">
                                            <button type="button" class="master-menu-btn"><i
                                                    class="fa-solid fa-ellipsis-vertical"></i></button>
                                            <div class="master-menu-list">
                                                <button type="button" data-open-task-modal="edit"
                                                    data-id="{{ $task->id }}" data-title="{{ $task->title }}"
                                                    data-description="{{ $task->description }}"
                                                    data-due-date="{{ $task->due_date ? $task->due_date->format('Y-m-d') : '' }}"
                                                    data-priority="{{ $task->priority }}" data-status="{{ $task->status }}"
                                                    data-category="{{ $task->category }}"
                                                    data-image-url="{{ $task->image_url }}"
                                                    data-assignee-id="{{ $task->assignee_id }}"
                                                    data-update-url="{{ route('tasks.update', $task) }}">
                                                    <i class="fa-solid fa-pen"></i> Edit
                                                </button>
                                                <form method="POST" action="{{ route('tasks.toggle', $task) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"><i class="fa-solid fa-check"></i>
                                                        {{ $task->isCompleted() ? 'Move to New' : 'Complete' }}</button>
                                                </form>
                                                <button type="button" class="danger" data-delete-task
                                                    data-delete-url="{{ route('tasks.destroy', $task) }}"
                                                    data-title="{{ $task->title }}"><i class="fa-solid fa-trash"></i>
                                                    Delete</button>
                                            </div>
                                        </div>
                                    </div>
    
                                    <h3 style="font-size:14px;font-weight:550;">{{ $task->title }}</h3>
                                    @if ($task->description)
                                        <p style="font-size:13px;">{{ \Illuminate\Support\Str::limit($task->description, 120) }}</p>
                                    @endif
    
                                    <div class="master-task-meta">
                                        <span><i class="fa-regular fa-calendar"></i>
                                            {{ $task->due_date ? $task->due_date->format('d M Y') : 'No due date' }} &nbsp;&nbsp;
                                            <i class="fa-regular fa-user"></i>
                                            {{ $task->assignee ? $task->assignee->name ?? $task->assignee->email : 'Unassigned' }}</span>
                                    </div>
                                </article>
                            @empty
                                <div class="master-empty-column">Drop tasks here</div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>

    <div class="master-modal" id="taskFormModal" aria-hidden="true">
        <div class="master-modal-backdrop" data-close-modal></div>
        <div class="master-modal-card">
            <form method="POST" action="{{ route('tasks.store') }}" id="taskForm" data-store-url="{{ route('tasks.store') }}" data-default-due-date="{{ now()->toDateString() }}">
                @csrf
                <input type="hidden" name="_method" id="taskFormMethod" value="" disabled>
                <div class="master-modal-head">
                    <div>
                        <p class="master-eyebrow">Task Board</p>
                        <h3 id="taskFormTitle">Add Task</h3>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal><i
                            class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field full">
                            <label class="master-label">Title <span>*</span></label>
                            <input class="master-input" type="text" name="title" id="taskTitle" required placeholder="Task title">
                        </div>
                        <div class="master-field">
                            <label class="master-label">Property / Category</label>
                            <select class="master-select" name="category" id="taskCategory">
                                @foreach ($categoryOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label">Status</label>
                            <select class="master-select" name="status" id="taskStatus">
                                @foreach ($statusOptions as $key => $label)
                                    <option value="{{ $key }}" {{ $key === 'new_request' ? 'selected' : '' }}>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label">Priority</label>
                            <select class="master-select" name="priority" id="taskPriority">
                                @foreach ($priorityOptions as $key => $label)
                                    <option value="{{ $key }}" {{ $key === 'medium' ? 'selected' : '' }}>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label">Assignee</label>
                            <select class="master-select" name="assignee_id" id="taskAssignee">
                                <option value="">Unassigned</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name ?? $user->email }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label">Due Date</label>
                            <input class="master-input" type="date" name="due_date" id="taskDueDate" value="{{ now()->toDateString() }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label">Image URL</label>
                            <input class="master-input" type="text" name="image_url" id="taskImageUrl"
                                placeholder="https://... or /storage/...">
                        </div>
                        <div class="master-image-preview" id="taskImagePreview" style="display:none;">
                            <img src="" alt="Task image preview">
                        </div>
                        <div class="master-field full">
                            <label class="master-label">Description</label>
                            <textarea class="master-textarea" name="description" id="taskDescription" rows="4" placeholder="Task details"></textarea>
                        </div>
                    </div>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal>Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary" id="taskSubmitBtn">Add Task</button>
                </div>
            </form>
        </div>
    </div>

    <div class="master-modal" id="deleteTaskModal" aria-hidden="true">
        <div class="master-modal-backdrop" data-close-modal></div>
        <div class="master-modal-card small">
            <form method="POST" action="" id="deleteTaskForm">
                @csrf
                @method('DELETE')
                <div class="master-modal-head">
                    <div>
                        <p class="master-eyebrow">Confirm Delete</p>
                        <h3>Delete Task</h3>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal><i
                            class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="master-modal-body">
                    <p id="deleteTaskText" class="master-delete-text">Are you sure you want to delete this task?</p>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light-dark" data-close-modal>Cancel</button>
                    <button type="submit" class="master-btn master-btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>


@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tasks.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/tasks.js') }}"></script>
@endpush
@endsection

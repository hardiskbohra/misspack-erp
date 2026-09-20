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
            <div class="master-board" id="kanbanBoard">
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
            <form method="POST" action="{{ route('tasks.store') }}" id="taskForm">
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

    <style>
        .master-page {
            background: #eef3ff;
            min-height: calc(100vh - 70px);
            padding: 28px;
            color: #17233b
        }

        .master-page * {
            box-sizing: border-box
        }

        .master-hero {
            background: linear-gradient(135deg, #4f83f1, #7b61ff);
            border-radius: 26px;
            padding: 24px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            gap: 18px;
            box-shadow: 0 18px 45px rgba(79, 131, 241, .22);
            margin-bottom: 18px;
        }

        .master-eyebrow {
            margin: 0 0 6px;
            text-transform: uppercase;
            letter-spacing: .13em;
            font-size: 11px;
            font-weight: 600;
            opacity: .78
        }

        .master-hero h1 {
            margin: 0;
            font-size: 30px;
            font-weight: 600
        }

        .master-hero p {
            margin: 8px 0 0;
            max-width: 780px;
            opacity: .9
        }

        .master-hero-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap
        }

        .master-pill {
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .28);
            border-radius: 999px;
            padding: 10px 13px;
            font-weight: 600
        }

        .master-pill.danger {
            background: rgba(239, 71, 112, .2)
        }

        .master-top-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .master-stat {
            background: #fff;
            border: 1px solid #dfe7f3;
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 14px 35px rgba(25, 42, 70, .08)
        }

        .master-stat span {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            color: #687386;
            font-weight: 600
        }

        .master-stat strong {
            display: block;
            margin-top: 7px;
            font-size: 28px
        }

        .master-stat.new strong {
            color: #ef4770
        }

        .master-stat.progress strong {
            color: #4f83f1
        }

        .master-stat.complete strong {
            color: #10b981
        }

        .master-stat.backlog strong {
            color: #17233b
        }

        .master-card {
            background: #fff;
            border: 1px solid #dfe7f3;
            border-radius: 22px;
            box-shadow: 0 14px 35px rgba(25, 42, 70, .08)
        }

        .master-filter-card {
            padding: 16px
        }

        .master-filter-form {
            display: grid;
            grid-template-columns: minmax(240px, 1.4fr) repeat(4, minmax(140px, .75fr)) auto;
            gap: 12px;
            align-items: end
        }

        .master-filter-actions {
            display: flex;
            gap: 8px
        }

        .master-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: -15px;
        }

        .master-toolbar-left,
        .master-toolbar-right {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap
        }

        .master-toggle {
            min-height: 42px;
            border-radius: 14px;
            padding: 9px 13px;
            background: #fff;
            border: 1px solid #dfe7f3;
            color: #17233b;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
        }

        .master-toggle span {
            width: 38px;
            height: 22px;
            border-radius: 999px;
            padding: 3px;
            background: #d8e2ef;
            display: flex
        }

        .master-toggle span i {
            display: block;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            transition: .2s;
        }

        .master-toggle.is-on span {
            background: #4f83f1;
        }

        .master-toggle.is-on span i {
            transform: translateX(16px)
        }

        .master-help {
            color: #687386;
            font-weight: 500
        }

        .master-board {
            display: grid;
            grid-template-columns: repeat(4, minmax(370px, 1fr));
            gap: 18px;
            align-items: start;
            overflow-x: auto;
            padding-bottom: 8px
        }

        .master-column {
            background: #f4f7fc;
            border: 1px solid #dfe7f3;
            border-radius: 24px;
            min-height: 620px;
            overflow: hidden
        }

        .master-column-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 18px 14px
        }

        .master-column-head>div {
            display: flex;
            align-items: center;
            gap: 9px
        }

        .master-column-head h2 {
            margin: 0;
            font-size: 18px
        }

        .master-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ef4770;
            box-shadow: 0 0 0 5px #fff0f4
        }

        .column-in-progress .master-dot {
            background: #4f83f1;
            box-shadow: 0 0 0 5px #eaf1ff
        }

        .column-completed .master-dot {
            background: #10b981;
            box-shadow: 0 0 0 5px #e8fff7
        }

        .column-backlog .master-dot {
            background: #17233b;
            box-shadow: 0 0 0 5px #edf1f5
        }

        .master-count {
            background: #fff;
            border: 1px solid #dfe7f3;
            color: #4f83f1;
            border-radius: 999px;
            min-width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            font-weight: 600;
            font-size: 13px;
        }

        .master-dropzone {
            padding: 0 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-height:700px;
            max-height: 700px;
            overflow: scroll;
        }

        .master-dropzone.drag-over {
            background: rgba(79, 131, 241, .08);
            border-radius: 18px
        }

        .master-task-card {
            background: #fff;
            border: 1px solid #e8edf7;
            border-radius: 20px;
            box-shadow: 0 8px 22px rgba(25, 42, 70, .06);
            padding: 14px;
            padding-bottom: 20px;
            cursor: grab;
            transition: .2s
        }

        .master-task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(25, 42, 70, .11)
        }

        .master-task-card.dragging {
            opacity: .55;
            transform: rotate(2deg)
        }

        .master-task-image {
            height: 170px;
            border-radius: 18px;
            overflow: hidden;
            background: #edf5ff;
            margin-bottom: 13px
        }

        .master-task-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block
        }

        .master-task-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px
        }

        .master-category {
            display: inline-flex;
            border-radius: 999px;
            padding: 6px 10px;
            background: #eef3ff;
            color: #4f83f1;
            font-size: 12px;
            font-weight: 600
        }

        .category-design {
            background: #e8fffb;
            color: #0f9f8f
        }

        .category-development {
            background: #fff4e5;
            color: #d97706
        }

        .category-production {
            background: #eaf1ff;
            color: #3f7cf4
        }

        .category-shipment {
            background: #ece7ff;
            color: #7c3aed
        }

        .category-finance {
            background: #e8fff7;
            color: #0e9f6e
        }

        .category-purchase {
            background: #fff8ec;
            color: #b54708
        }

        .master-menu {
            position: relative
        }

        .master-menu-btn {
            border: 0;
            background: #f6f8fc;
            color: #17233b;
            width: 34px;
            height: 34px;
            border-radius: 12px;
            cursor: pointer
        }

        .master-menu-list {
            position: absolute;
            right: 0;
            top: 40px;
            width: 170px;
            background: #fff;
            border: 1px solid #e8edf7;
            border-radius: 14px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .14);
            display: none;
            overflow: hidden;
            z-index: 20
        }

        .master-menu.open .master-menu-list {
            display: block
        }

        .master-menu-list button {
            width: 100%;
            border: 0;
            background: none;
            text-align: left;
            padding: 12px 14px;
            display: flex;
            gap: 9px;
            align-items: center;
            cursor: pointer;
            color: #17233b;
            font-weight: 500
        }

        .master-menu-list button:hover {
            background: #f7f9fc
        }

        .master-menu-list .danger {
            color: #e11d48
        }

        .master-menu-list form {
            margin: 0
        }

        .master-task-card h3 {
            margin: 12px 0 8px;
            font-size: 16px;
            line-height: 1.35
        }

        .master-task-card p {
            margin: 0 0 12px;
            color: #687386;
            line-height: 1.5
        }

        .master-task-meta {
            display: flex;
            flex-direction: column;
            gap: 7px;
            color: #687386;
            font-size: 12px;
            font-weight: 500
        }

        .master-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-top: 13px;
            padding-top: 12px;
            border-top: 1px solid #edf0f7
        }

        .master-priority,
        .master-status {
            border-radius: 999px;
            padding: 6px 9px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase
        }

        .priority-low {
            background: #e8fff7;
            color: #0e9f6e
        }

        .priority-medium {
            background: #eaf1ff;
            color: #3f7cf4
        }

        .priority-high {
            background: #fff4e5;
            color: #d97706
        }

        .priority-urgent {
            background: #ffeaf0;
            color: #e11d48
        }

        .status-backlog {
            background: #edf1f5;
            color: #374151
        }

        .status-new-request {
            background: #fff0f4;
            color: #ef4770
        }

        .status-in-progress {
            background: #eaf1ff;
            color: #3f7cf4
        }

        .status-completed {
            background: #e8fff7;
            color: #0e9f6e
        }

        .master-empty-column {
            border: 1px dashed #ccd7ea;
            border-radius: 18px;
            padding: 28px;
            text-align: center;
            color: #8a95a8;
            font-size: 13px;
            font-weight: 400;
            background: rgba(255, 255, 255, .55)
        }

        .master-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none
        }

        .master-modal.open {
            display: block
        }

        .master-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, .58);
            backdrop-filter: blur(6px)
        }

        .master-modal-card {
            position: relative;
            background: #fff;
            width: min(640px, calc(100% - 24px));
            max-height: 92vh;
            overflow: auto;
            margin: 4vh auto;
            border-radius: 24px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, .28)
        }

        .master-modal-card.small {
            width: min(460px, calc(100% - 24px))
        }

        .master-modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f7
        }

        .master-modal-head h3 {
            margin: 0;
            font-size: 20px
        }

        .master-modal-close {
            border: 0;
            background: #f3f6fb;
            color: #687386;
            width: 38px;
            height: 38px;
            border-radius: 13px;
            cursor: pointer
        }

        .master-modal-body {
            padding: 18px 20px
        }

        .master-modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 13px
        }

        .master-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 16px 20px;
            border-top: 1px solid #edf0f7
        }

        .master-image-preview {
            grid-column: 1/-1;
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid #dfe7f3;
            background: #edf5ff
        }

        .master-image-preview img {
            width: 100%;
            max-height: 220px;
            object-fit: cover;
            display: block
        }

        .master-delete-text {
            font-weight: 500;
            color: #536079;
            line-height: 1.5
        }

        @media(max-width:1399px) {
            .master-filter-form {
                grid-template-columns: 1fr 1fr
            }

            .master-field.search {
                grid-column: 1/-1
            }

            .master-filter-actions {
                grid-column: 1/-1
            }

            .master-top-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }
        }

        @media(max-width:991px) {
            .master-board {
                grid-template-columns: repeat(4, 310px)
            }
        }

        @media(max-width:767px) {
            .master-page {
                padding: 14px
            }

            .master-hero {
                flex-direction: column;
                padding: 20px
            }

            .master-hero h1 {
                font-size: 24px
            }

            .master-hero-actions,
            .master-hero-actions .master-btn,
            .master-filter-actions .master-btn {
                width: 100%
            }

            .master-top-grid,
            .master-filter-form,
            .master-modal-grid {
                grid-template-columns: 1fr
            }

            .master-toolbar {
                align-items: stretch
            }

            .master-toolbar-left,
            .master-toolbar-right,
            .master-toolbar-left>* {
                width: 100%
            }

            .master-board {
                grid-template-columns: repeat(4, 285px);
                gap: 12px
            }

            .master-column {
                min-height: 560px
            }

            .master-task-image {
                height: 150px
            }

            .master-modal-card {
                margin: 10px auto;
                max-height: calc(100vh - 20px);
                border-radius: 20px
            }

            .master-modal-footer {
                flex-direction: column-reverse
            }

            .master-modal-footer .master-btn {
                width: 100%
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = @json(csrf_token());
            const taskFormModal = document.getElementById('taskFormModal');
            const deleteTaskModal = document.getElementById('deleteTaskModal');
            const taskForm = document.getElementById('taskForm');
            const methodInput = document.getElementById('taskFormMethod');
            const taskFormTitle = document.getElementById('taskFormTitle');
            const taskSubmitBtn = document.getElementById('taskSubmitBtn');
            const imageInput = document.getElementById('taskImageUrl');
            const imagePreview = document.getElementById('taskImagePreview');
            const imagePreviewImg = imagePreview ? imagePreview.querySelector('img') : null;

            function openModal(modal) {
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeModal(modal) {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
                if (!document.querySelector('.master-modal.open')) {
                    document.body.style.overflow = '';
                }
            }

            function setValue(id, value) {
                const el = document.getElementById(id);
                if (el) el.value = value || '';
            }

            function updateImagePreview() {
                if (!imagePreview || !imagePreviewImg || !imageInput) return;
                if (imageInput.value.trim()) {
                    imagePreview.style.display = 'block';
                    imagePreviewImg.src = imageInput.value.trim();
                } else {
                    imagePreview.style.display = 'none';
                    imagePreviewImg.src = '';
                }
            }

            imageInput?.addEventListener('input', updateImagePreview);

            document.querySelectorAll('[data-open-task-modal]').forEach(function(button) {
                button.addEventListener('click', function() {
                    const mode = button.getAttribute('data-open-task-modal');
                    taskForm.reset();
                    imagePreview.style.display = 'none';
                    imagePreviewImg.src = '';

                    if (mode === 'edit') {
                        taskForm.action = button.dataset.updateUrl;
                        methodInput.disabled = false;
                        methodInput.value = 'PUT';
                        taskFormTitle.textContent = 'Edit Task';
                        taskSubmitBtn.textContent = 'Update Task';
                        setValue('taskTitle', button.dataset.title);
                        setValue('taskDescription', button.dataset.description);
                        setValue('taskDueDate', button.dataset.dueDate);
                        setValue('taskPriority', button.dataset.priority || 'medium');
                        setValue('taskStatus', button.dataset.status || 'new_request');
                        setValue('taskCategory', button.dataset.category || 'General');
                        setValue('taskImageUrl', button.dataset.imageUrl);
                        setValue('taskAssignee', button.dataset.assigneeId);
                        updateImagePreview();
                    } else {
                        taskForm.action = @json(route('tasks.store'));
                        methodInput.disabled = true;
                        methodInput.value = '';
                        taskFormTitle.textContent = 'Add Task';
                        taskSubmitBtn.textContent = 'Add Task';
                        setValue('taskDueDate', @json(now()->toDateString()));
                        setValue('taskPriority', 'medium');
                        setValue('taskStatus', 'new_request');
                        setValue('taskCategory', 'General');
                    }

                    openModal(taskFormModal);
                });
            });

            document.querySelectorAll('[data-close-modal]').forEach(function(button) {
                button.addEventListener('click', function() {
                    const modal = button.closest('.master-modal');
                    if (modal) closeModal(modal);
                });
            });

            document.querySelectorAll('.master-modal').forEach(function(modal) {
                modal.addEventListener('click', function(event) {
                    if (event.target.classList.contains('master-modal-backdrop')) {
                        closeModal(modal);
                    }
                });
            });

            document.querySelectorAll('.master-menu-btn').forEach(function(button) {
                button.addEventListener('click', function(event) {
                    event.stopPropagation();
                    document.querySelectorAll('.master-menu.open').forEach(function(menu) {
                        if (menu !== button.closest('.master-menu')) menu.classList.remove(
                            'open');
                    });
                    button.closest('.master-menu').classList.toggle('open');
                });
            });

            document.addEventListener('click', function() {
                document.querySelectorAll('.master-menu.open').forEach(function(menu) {
                    menu.classList.remove('open');
                });
            });

            document.querySelectorAll('[data-delete-task]').forEach(function(button) {
                button.addEventListener('click', function() {
                    document.getElementById('deleteTaskForm').action = button.dataset.deleteUrl;
                    document.getElementById('deleteTaskText').textContent =
                        'Are you sure you want to delete "' + button.dataset.title +
                        '"? This action cannot be undone.';
                    openModal(deleteTaskModal);
                });
            });

            let draggedCard = null;

            document.querySelectorAll('.master-task-card').forEach(function(card) {
                card.addEventListener('dragstart', function() {
                    draggedCard = card;
                    card.classList.add('dragging');
                });
                card.addEventListener('dragend', function() {
                    card.classList.remove('dragging');
                    draggedCard = null;
                    document.querySelectorAll('.master-dropzone').forEach(function(zone) {
                        zone.classList.remove('drag-over');
                    });
                });
            });

            document.querySelectorAll('.master-dropzone').forEach(function(zone) {
                zone.addEventListener('dragover', function(event) {
                    event.preventDefault();
                    zone.classList.add('drag-over');
                    const afterElement = getDragAfterElement(zone, event.clientY);
                    if (!draggedCard) return;
                    zone.querySelectorAll('.master-empty-column').forEach(function(empty) {
                        empty.remove();
                    });
                    if (afterElement == null) {
                        zone.appendChild(draggedCard);
                    } else {
                        zone.insertBefore(draggedCard, afterElement);
                    }
                });

                zone.addEventListener('dragleave', function() {
                    zone.classList.remove('drag-over');
                });

                zone.addEventListener('drop', function(event) {
                    event.preventDefault();
                    zone.classList.remove('drag-over');
                    if (!draggedCard) return;

                    const newStatus = zone.dataset.dropzone;
                    const newOrder = Array.from(zone.querySelectorAll('.master-task-card')).indexOf(
                        draggedCard) + 1;
                    updateTaskStatus(draggedCard, newStatus, newOrder);
                });
            });

            function getDragAfterElement(container, y) {
                const draggableElements = [...container.querySelectorAll('.master-task-card:not(.dragging)')];
                return draggableElements.reduce(function(closest, child) {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height / 2;
                    if (offset < 0 && offset > closest.offset) {
                        return {
                            offset: offset,
                            element: child
                        };
                    }
                    return closest;
                }, {
                    offset: Number.NEGATIVE_INFINITY
                }).element;
            }

            function updateTaskStatus(card, status, sortOrder) {
                const oldStatus = card.dataset.status;
                const url = card.dataset.updateStatusUrl;
                card.dataset.status = status;
                refreshColumnCounts();

                fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        status: status,
                        sort_order: sortOrder
                    })
                }).then(function(response) {
                    if (!response.ok) throw new Error('Request failed');
                    return response.json();
                }).then(function() {
                    const statusChip = card.querySelector('.master-status');
                    if (statusChip) {
                        statusChip.className = 'master-status status-' + status.replaceAll('_', '-');
                        statusChip.textContent = statusLabel(status);
                    }
                }).catch(function() {
                    alert('Task could not be moved. Please refresh and try again.');
                    window.location.reload();
                });
            }

            function statusLabel(status) {
                const labels = @json($statusOptions);
                return labels[status] || status;
            }

            function refreshColumnCounts() {
                document.querySelectorAll('.master-column').forEach(function(column) {
                    const status = column.dataset.status;
                    const count = column.querySelectorAll('.master-task-card').length;
                    const badge = document.querySelector('[data-count-for="' + status + '"]');
                    if (badge) badge.textContent = count;
                });
            }

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    document.querySelectorAll('.master-modal.open').forEach(function(modal) {
                        closeModal(modal);
                    });
                }
            });
        });
    </script>
@endsection

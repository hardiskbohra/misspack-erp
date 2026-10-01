/* ==========================================================================
   TASKS.JS — Tasks module (form + kanban index views)
   --------------------------------------------------------------------------
   Form page: live image-URL preview.
   Kanban index:
     - task create/edit dialog prefill (standard .master-modal dialogs —
       the shared master-* modal layer owns open/close/Escape/backdrop;
       the backdrop already carries data-close-modal)
     - delete dialog prefill
     - row action menus (.master-menu)
     - drag & drop between columns with PATCH status update, column count
       refresh and status chip repaint

   Server values are passed via data attributes (Blade cannot render
   inside an external script):
     #taskForm         data-store-url, data-default-due-date
     #kanbanBoard      data-status-options='@json($statusOptions)'
   The CSRF token is read from the layout's <meta name="csrf-token">.
   ========================================================================== */
(function () {
    'use strict';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function metaCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /* ------------------------------------------------------------------
       Form page — image URL preview
       ------------------------------------------------------------------ */
    function initFormPreview() {
        var input = document.getElementById('taskImageUrl');
        var preview = document.getElementById('taskImagePreview');
        var image = preview ? preview.querySelector('img') : null;
        if (!input || !preview || !image) return;

        function refreshPreview() {
            if (input.value.trim()) {
                preview.style.display = 'block';
                image.src = input.value.trim();
            } else {
                preview.style.display = 'none';
                image.src = '';
            }
        }

        input.addEventListener('input', refreshPreview);
        refreshPreview();
    }

    /* ------------------------------------------------------------------
       Kanban index — dialog prefill
       ------------------------------------------------------------------ */
    function initTaskDialogs() {
        var taskFormModal = document.getElementById('taskFormModal');
        if (!taskFormModal || typeof window.MasterModal === 'undefined') return;

        var deleteTaskModal = document.getElementById('deleteTaskModal');
        var taskForm = document.getElementById('taskForm');
        var methodInput = document.getElementById('taskFormMethod');
        var taskFormTitle = document.getElementById('taskFormTitle');
        var taskSubmitBtn = document.getElementById('taskSubmitBtn');
        var imageInput = document.getElementById('taskImageUrl');
        var imagePreview = document.getElementById('taskImagePreview');
        var imagePreviewImg = imagePreview ? imagePreview.querySelector('img') : null;

        function setValue(id, value) {
            var el = document.getElementById(id);
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

        if (imageInput) imageInput.addEventListener('input', updateImagePreview);

        var storeUrl = taskForm ? taskForm.getAttribute('data-store-url') : '';
        var defaultDueDate = taskForm ? taskForm.getAttribute('data-default-due-date') : '';

        document.querySelectorAll('[data-open-task-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                var mode = button.getAttribute('data-open-task-modal');
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
                    taskForm.action = storeUrl;
                    methodInput.disabled = true;
                    methodInput.value = '';
                    taskFormTitle.textContent = 'Add Task';
                    taskSubmitBtn.textContent = 'Add Task';
                    setValue('taskDueDate', defaultDueDate);
                    setValue('taskPriority', 'medium');
                    setValue('taskStatus', 'new_request');
                    setValue('taskCategory', 'General');
                }

                window.MasterModal.open(taskFormModal);
            });
        });

        document.querySelectorAll('[data-delete-task]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('deleteTaskForm').action = button.dataset.deleteUrl;
                document.getElementById('deleteTaskText').textContent =
                    'Are you sure you want to delete "' + button.dataset.title +
                    '"? This action cannot be undone.';
                window.MasterModal.open(deleteTaskModal);
            });
        });
    }

    /* ------------------------------------------------------------------
       Kanban index — row action menus
       ------------------------------------------------------------------ */
    function initMenus() {
        var menuButtons = document.querySelectorAll('.master-menu-btn');
        if (!menuButtons.length) return;

        menuButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                document.querySelectorAll('.master-menu.open').forEach(function (menu) {
                    if (menu !== button.closest('.master-menu')) menu.classList.remove('open');
                });
                button.closest('.master-menu').classList.toggle('open');
            });
        });

        document.addEventListener('click', function () {
            document.querySelectorAll('.master-menu.open').forEach(function (menu) {
                menu.classList.remove('open');
            });
        });
    }

    /* ------------------------------------------------------------------
       Kanban index — drag & drop between columns
       ------------------------------------------------------------------ */
    function initBoardDragDrop() {
        var board = document.getElementById('kanbanBoard');
        if (!board) return;

        var statusOptions = {};
        try {
            statusOptions = JSON.parse(board.getAttribute('data-status-options') || '{}');
        } catch (e) { /* ignore malformed payload */ }

        var draggedCard = null;

        function statusLabel(status) {
            return statusOptions[status] || status;
        }

        function refreshColumnCounts() {
            document.querySelectorAll('.master-column').forEach(function (column) {
                var status = column.dataset.status;
                var count = column.querySelectorAll('.master-task-card').length;
                var badge = document.querySelector('[data-count-for="' + status + '"]');
                if (badge) badge.textContent = count;
            });
        }

        function updateTaskStatus(card, status, sortOrder) {
            var url = card.dataset.updateStatusUrl;
            card.dataset.status = status;
            refreshColumnCounts();

            fetch(url, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': metaCsrfToken(),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    status: status,
                    sort_order: sortOrder
                })
            }).then(function (response) {
                if (!response.ok) throw new Error('Request failed');
                return response.json();
            }).then(function () {
                var statusChip = card.querySelector('.master-status');
                if (statusChip) {
                    statusChip.className = 'master-status status-' + status.replace(/_/g, '-');
                    statusChip.textContent = statusLabel(status);
                }
            }).catch(function () {
                alert('Task could not be moved. Please refresh and try again.');
                window.location.reload();
            });
        }

        function getDragAfterElement(container, y) {
            var draggableElements = Array.from(container.querySelectorAll('.master-task-card:not(.dragging)'));
            return draggableElements.reduce(function (closest, child) {
                var box = child.getBoundingClientRect();
                var offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                }
                return closest;
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        document.querySelectorAll('.master-task-card').forEach(function (card) {
            card.addEventListener('dragstart', function () {
                draggedCard = card;
                card.classList.add('dragging');
            });
            card.addEventListener('dragend', function () {
                card.classList.remove('dragging');
                draggedCard = null;
                document.querySelectorAll('.master-dropzone').forEach(function (zone) {
                    zone.classList.remove('drag-over');
                });
            });
        });

        document.querySelectorAll('.master-dropzone').forEach(function (zone) {
            zone.addEventListener('dragover', function (event) {
                event.preventDefault();
                zone.classList.add('drag-over');
                var afterElement = getDragAfterElement(zone, event.clientY);
                if (!draggedCard) return;
                zone.querySelectorAll('.master-empty-column').forEach(function (empty) {
                    empty.remove();
                });
                if (afterElement == null) {
                    zone.appendChild(draggedCard);
                } else {
                    zone.insertBefore(draggedCard, afterElement);
                }
            });

            zone.addEventListener('dragleave', function () {
                zone.classList.remove('drag-over');
            });

            zone.addEventListener('drop', function (event) {
                event.preventDefault();
                zone.classList.remove('drag-over');
                if (!draggedCard) return;

                var newStatus = zone.dataset.dropzone;
                var newOrder = Array.from(zone.querySelectorAll('.master-task-card')).indexOf(draggedCard) + 1;
                updateTaskStatus(draggedCard, newStatus, newOrder);
            });
        });
    }

    onReady(function () {
        initFormPreview();
        initTaskDialogs();
        initMenus();
        initBoardDragDrop();
    });
})();

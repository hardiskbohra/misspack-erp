/* ==========================================================================
   PROJECTS.JS — Projects module (projects/* views, admin side)
   --------------------------------------------------------------------------
   Page behaviour for the Projects section:
     - project list: the shared quick-create dialog (.master-modal), the
       shared list toolkit (row navigation and the pinned-header shadow) and
       delete confirmation
     - project detail: tab navigation (hash + localStorage restore) and the
       add/edit modals for products, attachments and tracking
   The standard .master-modal dialogs on the detail page use the shared
   master-* modal lifecycle from app-layout.js (window.MasterModal): this
   file only opens modals and fills their forms — close/Escape/backdrop
   handling lives in the shared layer.
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

    /* ------------------------------------------------------------------
       Project list — the shared dialog and the shared list toolkit

       The quick-create dialog is a .master-modal, so close, Escape, the
       backdrop and the scroll lock are the shared layer's job; this only
       opens it. Row navigation and the pinned-header shadow come from
       MasterList, the same toolkit every other list uses.
       ------------------------------------------------------------------ */
    function initProjectList() {
        var dialog = document.getElementById('quickProjectModal');
        var opener = document.getElementById('openQuickProjectModal');

        if (dialog && opener && window.MasterModal) {
            opener.addEventListener('click', function () {
                window.MasterModal.open(dialog);
            });
        }

        if (!window.MasterList) return;

        window.MasterList.rowNavigation({ root: '.project-index' });
        window.MasterList.gridShadow({ root: '.project-index' });
    }

    /* ------------------------------------------------------------------
       Project list — delete confirmation
       ------------------------------------------------------------------ */
    function initDeleteConfirm() {
        var forms = document.querySelectorAll('.delete-project-form');
        if (!forms.length) return;

        forms.forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (typeof window.MasterAlert === 'undefined') {
                    form.submit();
                    return;
                }
                MasterAlert.confirm(
                    'This action cannot be undone.',
                    { title: 'Delete Project?', confirmText: 'Yes, Delete', cancelText: 'Cancel', danger: true }
                ).then(function (ok) {
                    if (ok) form.submit();
                });
            });
        });
    }

    /* ------------------------------------------------------------------
       Project detail — add/edit modals (standard .master-modal dialogs)
       Open triggers + form prefill live here; the shared master-* modal
       layer (app-layout.js) owns open/close state, backdrop, Escape and
       [data-close-modal] buttons.
       ------------------------------------------------------------------ */
    function initDetailModals() {
        if (typeof window.MasterModal === 'undefined') return;

        /* The update URL is emitted by the form as a template: a hand-built
           '/project-products/12' misses an install served from a sub-path. */
        function bindUpdateUrl(form, id) {
            var template = form.getAttribute('data-update-url') || '';
            form.action = template.replace('__ID__', id);
        }

        /* Any element can open a dialog by naming it. The add buttons keep
           their own ids for the pages that already bind them; this is the
           second door into the same dialog — an empty state, a summary tile. */
        document.querySelectorAll('[data-modal-open]').forEach(function (trigger) {
            var target = document.getElementById(trigger.getAttribute('data-modal-open'));
            if (!target) return;
            trigger.addEventListener('click', function () {
                window.MasterModal.open(target);
            });
        });
        document.querySelectorAll('.master-modal[data-open-on-errors="true"]').forEach(function (modal) {
            window.MasterModal.open(modal);
        });

        /* `type="date"` wants `YYYY-MM-DD`; a model cast hands over a full ISO
           timestamp, which the field quietly refuses and shows as empty. */
        function readyDate(value) {
            return value ? String(value).substring(0, 10) : '';
        }

        function setValue(form, name, value) {
            var field = form.elements[name];
            if (!field) return;
            if (field.type === 'checkbox') {
                field.checked = !!value;
            } else {
                field.value = value == null ? '' : value;
            }
        }

        /* Edit-product — the tab's only dialog: the product, the quantity, the
           rate and the vendor are the documents', so the form carries the
           project's own facts. */
        var editProductModal = document.getElementById('editProductModal');
        var editProductForm = document.getElementById('editProductForm');
        if (editProductModal && editProductForm) {
            document.querySelectorAll('.editProductBtn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var product = JSON.parse(this.getAttribute('data-product') || '{}');
                    bindUpdateUrl(editProductForm, product.id);
                    setValue(editProductForm, 'status', product.status);
                    setValue(editProductForm, 'expected_ready_date', readyDate(product.expected_ready_date));
                    setValue(editProductForm, 'actual_ready_date', readyDate(product.actual_ready_date));
                    setValue(editProductForm, 'notes', product.notes);
                    window.MasterModal.open(editProductModal);
                });
            });
        }

        /* Add-activity */
        var addTrackingModal = document.getElementById('addTrackingModal');
        var openAddTracking = document.getElementById('openAddTrackingModal');
        if (addTrackingModal && openAddTracking) {
            openAddTracking.addEventListener('click', function () {
                window.MasterModal.open(addTrackingModal);
            });
        }

        /* Edit-activity */
        var editTrackingModal = document.getElementById('editTrackingModal');
        var editTrackingForm = document.getElementById('editTrackingForm');
        if (editTrackingModal && editTrackingForm) {
            document.querySelectorAll('.editTrackingBtn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var tracking = JSON.parse(this.getAttribute('data-tracking') || '{}');
                    bindUpdateUrl(editTrackingForm, tracking.id);
                    setValue(editTrackingForm, 'project_product_id', tracking.project_product_id);
                    setValue(editTrackingForm, 'title', tracking.title);
                    setValue(editTrackingForm, 'status', tracking.status);
                    setValue(editTrackingForm, 'location', tracking.location);
                    setValue(editTrackingForm, 'notes', tracking.notes);
                    setValue(editTrackingForm, 'is_public', tracking.is_public);
                    if (tracking.occurred_at) {
                        setValue(editTrackingForm, 'occurred_at', tracking.occurred_at.substring(0, 16));
                    }
                    window.MasterModal.open(editTrackingModal);
                });
            });
        }

        /* Add-comment */
        var addCommentModal = document.getElementById('addCommentModal');
        var openAddComment = document.getElementById('openAddCommentModal');
        if (addCommentModal && openAddComment) {
            openAddComment.addEventListener('click', function () {
                window.MasterModal.open(addCommentModal);
            });
        }

        /* Edit-comment */
        var editCommentModal = document.getElementById('editCommentModal');
        var editCommentForm = document.getElementById('editCommentForm');
        if (editCommentModal && editCommentForm) {
            document.querySelectorAll('.editCommentBtn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var comment = JSON.parse(this.getAttribute('data-comment') || '{}');
                    bindUpdateUrl(editCommentForm, comment.id);
                    setValue(editCommentForm, 'project_product_id', comment.project_product_id);
                    setValue(editCommentForm, 'body', comment.body);
                    setValue(editCommentForm, 'is_pinned', comment.is_pinned);
                    setValue(editCommentForm, 'is_public', comment.is_public);
                    window.MasterModal.open(editCommentModal);
                });
            });
        }

        /* Add-attachment */
        var addAttachmentModal = document.getElementById('addAttachmentModal');
        var openAddAttachment = document.getElementById('openAddAttachmentModal');
        if (addAttachmentModal && openAddAttachment) {
            openAddAttachment.addEventListener('click', function () {
                window.MasterModal.open(addAttachmentModal);
            });
        }
    }

    /* ------------------------------------------------------------------
       Workflow command centre — task-first views
       Only one operational surface is visible at a time. The selected view is
       kept per project so a form round-trip returns the coordinator to the
       context they were working in.
       ------------------------------------------------------------------ */
    function initWorkflowViews() {
        var root = document.getElementById('project-panel-workflow');
        if (!root) return;

        var views = Array.prototype.slice.call(root.querySelectorAll('[data-workflow-view]'));
        var triggers = Array.prototype.slice.call(root.querySelectorAll('[data-workflow-open]'));
        if (!views.length || !triggers.length) return;

        var project = document.querySelector('.project-show');
        var storageKey = 'project-workflow-view:' + (project ? project.getAttribute('data-project-id') : 'default');
        var allowed = ['actions', 'matrix', 'products', 'commercial', 'history'];

        function select(name, shouldFocus) {
            if (allowed.indexOf(name) === -1) name = 'actions';
            views.forEach(function (view) {
                view.hidden = view.getAttribute('data-workflow-view') !== name;
            });
            triggers.forEach(function (trigger) {
                var active = trigger.getAttribute('data-workflow-open') === name;
                if (trigger.getAttribute('role') === 'tab') {
                    trigger.classList.toggle('is-active', active);
                    trigger.setAttribute('aria-selected', active ? 'true' : 'false');
                    trigger.setAttribute('tabindex', active ? '0' : '-1');
                }
            });
            try { window.localStorage.setItem(storageKey, name); } catch (error) {}
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', window.location.pathname + window.location.search + '#workflow-' + name);
            }
            if (shouldFocus) {
                var heading = root.querySelector('[data-workflow-view="' + name + '"] h2');
                if (heading) {
                    heading.setAttribute('tabindex', '-1');
                    heading.focus({ preventScroll: true });
                }
                root.querySelector('.workflow-view-tabs').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                if (trigger.matches('a')) event.preventDefault();
                select(trigger.getAttribute('data-workflow-open'), true);
            });
            trigger.addEventListener('keydown', function (event) {
                if (trigger.getAttribute('role') !== 'tab' || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
                event.preventDefault();
                var tabs = triggers.filter(function (item) { return item.getAttribute('role') === 'tab'; });
                var index = tabs.indexOf(trigger) + (event.key === 'ArrowRight' ? 1 : -1);
                var next = tabs[(index + tabs.length) % tabs.length];
                next.focus();
                select(next.getAttribute('data-workflow-open'), false);
            });
        });

        var fromHash = window.location.hash.indexOf('#workflow-') === 0 ? window.location.hash.replace('#workflow-', '') : null;
        var saved = null;
        try { saved = window.localStorage.getItem(storageKey); } catch (error) {}
        select(allowed.indexOf(fromHash) !== -1 ? fromHash : (allowed.indexOf(saved) !== -1 ? saved : 'actions'), false);
    }

    onReady(function () {
        initProjectList();
        initDeleteConfirm();
        initDetailModals();
        initWorkflowViews();
    });
})();

/* ==========================================================================
   PROJECTS.JS — Projects module (projects/* views, admin side)
   --------------------------------------------------------------------------
   Page behaviour for the Projects section:
     - project list: quick-create modal (.projects-modal, module-specific
       design with .is-open state) + SweetAlert delete confirmation
     - project detail: portal link copy, tab navigation (hash +
       localStorage restore) and the add/edit modals
     - milestones tab: timeline editor modal (pmile-*, module-specific
       design)
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
       Project list — quick-create modal (.projects-modal / .is-open)
       Module-specific modal design: keeps its own open/close wiring
       (the shared layer only manages .master-modal dialogs).
       ------------------------------------------------------------------ */
    function initQuickModal() {
        var modals = document.querySelectorAll('.projects-modal');
        if (!modals.length) return;

        document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modal = document.getElementById(btn.getAttribute('data-open-modal'));
                if (!modal || !modal.classList.contains('projects-modal')) return;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            });
        });

        document.querySelectorAll('.projects-modal [data-close-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modal = btn.closest('.projects-modal');
                if (!modal) return;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('.projects-modal.is-open').forEach(function (modal) {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            });
        });
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
       Project detail — copy portal link
       ------------------------------------------------------------------ */
    function initCopyPortalLink() {
        var copyBtn = document.getElementById('copyPortalLink');
        if (!copyBtn) return;
        var input = document.getElementById('portalLinkInput');
        if (!input) return;

        copyBtn.addEventListener('click', function () {
            input.select();
            input.setSelectionRange(0, 99999);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).catch(function () {
                    document.execCommand('copy');
                });
            } else {
                document.execCommand('copy');
            }
            var original = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
            setTimeout(function () {
                copyBtn.innerHTML = original;
            }, 1800);
        });
    }

    /* ------------------------------------------------------------------
       Project detail — tab navigation
       Active tab persists per project (localStorage) and in the URL hash.
       ------------------------------------------------------------------ */
    function initTabs() {
        var tabButtons = document.querySelectorAll('.pd-tab-btn');
        if (!tabButtons.length) return;

        var tabPanels = document.querySelectorAll('.pd-tab-panel');
        var shell = document.querySelector('.pd-tabs-shell');
        var projectId = shell ? shell.getAttribute('data-project-id') : '';
        var storageKey = 'project_show_active_tab_' + (projectId || 'default');

        function openProjectTab(tabName, updateHash) {
            var found = false;
            tabButtons.forEach(function (btn) {
                var isActive = btn.getAttribute('data-tab') === tabName;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                if (isActive) found = true;
            });
            if (!found) {
                openProjectTab('overview', updateHash);
                return;
            }
            tabPanels.forEach(function (panel) {
                panel.classList.toggle('active', panel.getAttribute('data-tab-panel') === tabName);
            });
            try {
                localStorage.setItem(storageKey, tabName);
            } catch (e) { /* private mode */ }
            if (updateHash) {
                history.replaceState(null, '', '#' + tabName);
            }
        }

        tabButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                openProjectTab(btn.getAttribute('data-tab'), true);
            });
        });

        document.querySelectorAll('[data-tab-jump]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openProjectTab(btn.getAttribute('data-tab-jump'), true);
                if (shell) {
                    window.scrollTo({
                        top: shell.offsetTop - 90,
                        behavior: 'smooth'
                    });
                }
            });
        });

        var initialTab = window.location.hash ? window.location.hash.replace('#', '') : '';
        if (!initialTab) {
            try {
                initialTab = localStorage.getItem(storageKey) || 'overview';
            } catch (e) {
                initialTab = 'overview';
            }
        }
        openProjectTab(initialTab, false);
    }

    /* ------------------------------------------------------------------
       Project detail — add/edit modals (standard .master-modal dialogs)
       Open triggers + form prefill live here; the shared master-* modal
       layer (app-layout.js) owns open/close state, backdrop, Escape and
       [data-close-modal] buttons.
       ------------------------------------------------------------------ */
    function initDetailModals() {
        if (typeof window.MasterModal === 'undefined') return;

        function setValue(form, name, value) {
            var field = form.elements[name];
            if (!field) return;
            if (field.type === 'checkbox') {
                field.checked = !!value;
            } else {
                field.value = value == null ? '' : value;
            }
        }

        /* Add-product */
        var addProductModal = document.getElementById('addProductModal');
        var openAddProduct = document.getElementById('openAddProductModal');
        if (addProductModal && openAddProduct) {
            openAddProduct.addEventListener('click', function () {
                window.MasterModal.open(addProductModal);
            });
        }

        /* Edit-product */
        var editProductModal = document.getElementById('editProductModal');
        var editProductForm = document.getElementById('editProductForm');
        if (editProductModal && editProductForm) {
            document.querySelectorAll('.editProductBtn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var product = JSON.parse(this.getAttribute('data-product') || '{}');
                    editProductForm.action = '/project-products/' + product.id;
                    setValue(editProductForm, 'product_id', product.product_id);
                    setValue(editProductForm, 'quantity', product.quantity);
                    setValue(editProductForm, 'unit_price', product.unit_price);
                    setValue(editProductForm, 'status', product.status);
                    setValue(editProductForm, 'stage', product.stage);
                    setValue(editProductForm, 'assigned_to', product.assigned_to);
                    setValue(editProductForm, 'vendor_id', product.vendor_id);
                    setValue(editProductForm, 'vendor_invoice_number', product.vendor_invoice_number);
                    setValue(editProductForm, 'expected_ready_date', product.expected_ready_date);
                    setValue(editProductForm, 'actual_ready_date', product.actual_ready_date);
                    setValue(editProductForm, 'notes', product.notes);
                    setValue(editProductForm, 'currency', product.currency);
                    setValue(editProductForm, 'sort_order', product.sort_order);
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
                    editTrackingForm.action = '/project-tracking/' + tracking.id;
                    setValue(editTrackingForm, 'project_product_id', tracking.project_product_id);
                    setValue(editTrackingForm, 'title', tracking.title);
                    setValue(editTrackingForm, 'status', tracking.status);
                    setValue(editTrackingForm, 'progress_percent', tracking.progress_percent);
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
                    editCommentForm.action = '/project-comments/' + comment.id;
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

        /* Edit-payment */
        var editPaymentModal = document.getElementById('editPaymentModal');
        var editPaymentForm = document.getElementById('editPaymentForm');
        if (editPaymentModal && editPaymentForm) {
            document.querySelectorAll('.editPaymentBtn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var payment = JSON.parse(this.getAttribute('data-payment') || '{}');
                    editPaymentForm.action = '/project-payments/' + payment.id;
                    setValue(editPaymentForm, 'transaction_type', payment.transaction_type);
                    var paymentDate = payment.payment_date;
                    if (paymentDate) {
                        paymentDate = paymentDate.substring(0, 10);
                    }
                    setValue(editPaymentForm, 'payment_date', paymentDate);
                    setValue(editPaymentForm, 'amount', payment.amount);
                    setValue(editPaymentForm, 'currency', payment.currency);
                    setValue(editPaymentForm, 'payment_mode', payment.payment_mode);
                    setValue(editPaymentForm, 'reference_number', payment.reference_number);
                    setValue(editPaymentForm, 'category', payment.category);
                    setValue(editPaymentForm, 'notes', payment.notes);
                    setValue(editPaymentForm, 'is_public', payment.is_public);
                    window.MasterModal.open(editPaymentModal);
                });
            });
        }

        /* Add-payment */
        var addPaymentModal = document.getElementById('addPaymentModal');
        var openAddPayment = document.getElementById('openAddPaymentModal');
        if (addPaymentModal && openAddPayment) {
            openAddPayment.addEventListener('click', function () {
                window.MasterModal.open(addPaymentModal);
            });
        }
    }

    /* ------------------------------------------------------------------
       Milestones tab — timeline editor modal
       Uses the shared master-modal layer (app-layout.js) so it matches
       every other module: same card, header, close button and footer.
       Destructive actions open a separate themed confirmation modal.
       ------------------------------------------------------------------ */
    function initMilestoneModal() {
        var editModal = document.getElementById('editMilestoneModal');
        if (!editModal) return;

        var deleteModal = document.getElementById('deleteMilestoneModal');
        var editForm = document.getElementById('editMilestoneForm');
        var deleteForm = document.getElementById('deleteMilestoneForm');

        function openModal(modal) {
            if (!modal) return;
            if (typeof window.MasterModal !== 'undefined') {
                window.MasterModal.open(modal);
                return;
            }
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('master-modal-open');
        }

        function closeModal(modal) {
            if (!modal) return;
            if (typeof window.MasterModal !== 'undefined') {
                window.MasterModal.close(modal);
                return;
            }
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            if (!document.querySelector('.master-modal.open')) {
                document.body.classList.remove('master-modal-open');
            }
        }

        function setField(id, value) {
            var field = document.getElementById(id);
            if (field) field.value = value || '';
        }

        function setChecked(id, value) {
            var field = document.getElementById(id);
            if (field) field.checked = !!value;
        }

        document.querySelectorAll('.editMilestoneBtn').forEach(function (button) {
            button.addEventListener('click', function () {
                var milestone = {};
                try {
                    milestone = JSON.parse(button.getAttribute('data-milestone') || '{}');
                } catch (e) { /* ignore malformed payload */ }

                if (editForm) editForm.action = button.getAttribute('data-update-url') || '#';
                if (deleteForm) deleteForm.action = button.getAttribute('data-delete-url') || '#';

                var modalTitle = document.getElementById('editMilestoneModalTitle');
                if (modalTitle) {
                    modalTitle.textContent = milestone.title ? 'Edit ' + milestone.title : 'Edit Milestone';
                }

                var deleteDesc = document.getElementById('deleteMilestoneDesc');
                if (deleteDesc) {
                    deleteDesc.textContent = milestone.title
                        ? 'Are you sure you want to delete "' + milestone.title + '"? This action cannot be undone.'
                        : 'Are you sure you want to delete this milestone? This action cannot be undone.';
                }

                setField('edit_milestone_key', milestone.milestone_key);
                setField('edit_project_product_id', milestone.project_product_id);
                setField('edit_title', milestone.title);
                setField('edit_status', milestone.status || 'not_started');
                setField('edit_progress_percent', milestone.progress_percent || 0);
                setField('edit_planned_start_date', milestone.planned_start_date);
                setField('edit_planned_end_date', milestone.planned_end_date);
                setField('edit_actual_start_date', milestone.actual_start_date);
                setField('edit_actual_end_date', milestone.actual_end_date);
                setField('edit_owner_id', milestone.owner_id);
                setField('edit_sort_order', milestone.sort_order || 0);
                setField('edit_client_note', milestone.client_note);
                setField('edit_internal_notes', milestone.internal_notes);
                setField('edit_notes', milestone.notes);
                setField('edit_blocked_reason', milestone.blocked_reason);
                setChecked('edit_is_public', milestone.is_public);
                setChecked('edit_is_required', milestone.is_required);

                openModal(editModal);
            });
        });

        /* Footer "Delete" hands over to the themed confirmation modal. */
        var openDelete = document.getElementById('openDeleteMilestoneModal');
        if (openDelete) {
            openDelete.addEventListener('click', function () {
                closeModal(editModal);
                openModal(deleteModal);
            });
        }

        /* Close/backdrop/Escape handling is owned by the shared modal layer
           (app-layout.js) through [data-close-modal]. */
    }

    onReady(function () {
        initQuickModal();
        initDeleteConfirm();
        initCopyPortalLink();
        initTabs();
        initDetailModals();
        initMilestoneModal();
    });
})();

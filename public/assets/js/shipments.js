/**
 * =====================================================================
 * MissPack ERP - Shipment Module Scripts
 * ---------------------------------------------------------------------
 * Shared by the shipment views that need behaviour:
 *   - shipments/index.blade.php  (Quick Shipment / Delete modals,
 *                                 copy public tracking link)
 *   - shipments/form.blade.php   (products rows, attachment remove /
 *                                 toggle-public AJAX)
 *
 * Load after page markup via @push('scripts'). All element bindings are
 * guarded, so the file is safe on any shipment page.
 * =====================================================================
 */
(function () {
    'use strict';

    /* ---------------- Modal helpers ---------------- */

    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('master-modal-open');
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('master-modal-open');
    }

    /* ---------------- Page initialisation ---------------- */

    document.addEventListener('DOMContentLoaded', function () {
        var quickModal = document.getElementById('quickShipmentModal');
        var deleteModal = document.getElementById('deleteShipmentModal');
        var deleteForm = document.getElementById('deleteShipmentForm');
        var deleteDesc = document.getElementById('deleteShipmentDesc');

        document.getElementById('openQuickShipmentModal')?.addEventListener('click', function () {
            openModal(quickModal);
        });
        document.getElementById('closeQuickShipmentModal')?.addEventListener('click', function () {
            closeModal(quickModal);
        });
        document.getElementById('cancelQuickShipmentModal')?.addEventListener('click', function () {
            closeModal(quickModal);
        });

        document.querySelectorAll('.master-delete-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                deleteDesc.textContent = 'Are you sure you want to delete "' + button.dataset.name + '"? This action cannot be undone.';
                deleteForm.action = button.dataset.deleteUrl;
                openModal(deleteModal);
            });
        });

        document.getElementById('closeDeleteShipmentModal')?.addEventListener('click', function () {
            closeModal(deleteModal);
        });
        document.getElementById('cancelDeleteShipmentModal')?.addEventListener('click', function () {
            closeModal(deleteModal);
        });

        [quickModal, deleteModal].forEach(function (modal) {
            modal?.addEventListener('click', function (event) {
                if (event.target === modal) closeModal(modal);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            closeModal(quickModal);
            closeModal(deleteModal);
        });
    });

    /* ---------------- Global helpers (used by inline onclick) ---------------- */

    /** Copy the public tracking link to the clipboard (list page). */
    window.copyShipmentLink = function (url) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                alert('Public tracking link copied.');
            });
        } else {
            prompt('Copy public tracking link:', url);
        }
    };

    /** Remove a product row from the shipment items table (form page). */
    window.removeShipmentItemRow = function (button) {
        var tbody = document.querySelector('#shipmentItemsTable tbody');
        if (!tbody || tbody.children.length <= 1) return;
        button.closest('tr').remove();
    };

    /** Remove an attachment via AJAX (form page). */
    window.removeAttachment = function (id, btn) {
        if (!confirm('Remove this attachment?')) {
            return;
        }

        fetch('/shipments/attachments/' + id, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        })
        .then(async function (response) {
            console.log("Status:", response.status);

            const text = await response.text();
            console.log(text);

            const card = btn.closest('.master-attachment-card');
            console.log(card);

            if (card) {
                card.remove();
            }
        })
        .catch(function (err) {
            console.error(err);
        });
    };

    /** Toggle an attachment's public visibility via AJAX (form page). */
    window.toggleAttachmentPublic = function (id, checkbox) {
        fetch('/shipments/attachments/' + id + '/toggle-public', {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                is_public: checkbox.checked
            })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) {
                checkbox.checked = !checkbox.checked;
                alert('Unable to update attachment.');
            }
        })
        .catch(function () {
            checkbox.checked = !checkbox.checked;
            alert('Something went wrong.');
        });
    };
})();

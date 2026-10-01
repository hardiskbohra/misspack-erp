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
    /* ------------------------------------------------------------------
       From / To memory
       ------------------------------------------------------------------
       Parties repeat between shipments (a shipper is usually the same
       company with the same address). When a name is picked or typed that
       we have shipped before, fill only the fields that are still empty and
       say where the values came from — with a one-click undo.
       ------------------------------------------------------------------ */

    var PARTY_DETAILS = ['email', 'mobile', 'address', 'city', 'state', 'country', 'pincode'];

    function initPartyMemory() {
        var form = document.querySelector('[data-party-lookup-url]');
        if (!form || form.dataset.partyMemoryReady) return;

        /* idempotent: a second DOMContentLoaded must not double-bind */
        form.dataset.partyMemoryReady = '1';

        var lookupUrl = form.getAttribute('data-party-lookup-url');
        var namesLoaded = {};

        function loadNames(field, input) {
            var listId = input.getAttribute('list');
            var datalist = listId ? document.getElementById(listId) : null;
            if (!datalist || namesLoaded[field]) return;

            namesLoaded[field] = true;
            fetch(lookupUrl + '?field=' + encodeURIComponent(field), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (payload) {
                    if (!payload || !payload.names) return;
                    payload.names.forEach(function (name) {
                        var option = document.createElement('option');
                        option.value = name;
                        datalist.appendChild(option);
                    });
                })
                .catch(function () { /* suggestions are optional */ });
        }

        function partyInputs(block, field) {
            return PARTY_DETAILS
                .map(function (suffix) { return block.querySelector('[name="' + field + '_' + suffix + '"]'); })
                .filter(Boolean);
        }

        function restore(values) {
            values.forEach(function (entry) {
                entry.field.value = entry.value;
            });
        }

        function applyDetails(field, name, block, note) {
            if (!name) return;

            fetch(lookupUrl + '?field=' + encodeURIComponent(field) + '&name=' + encodeURIComponent(name), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (payload) {
                    var details = (payload && payload.details) ? payload.details : {};

                    /* a name we have never shipped for is not an error — stay quiet */
                    if (!Object.keys(details).length) {
                        note.hidden = true;
                        return;
                    }

                    var filled = [];
                    var skipped = 0;

                    Object.keys(details).forEach(function (suffix) {
                        var input = block.querySelector('[name="' + field + '_' + suffix + '"]');
                        if (!input) return;

                        if (String(input.value || '').trim() !== '') {
                            skipped++;
                            return;
                        }

                        filled.push({ field: input, value: input.value });
                        input.value = details[suffix];
                        input.classList.add('party-prefilled');
                    });

                    if (!filled.length) {
                        note.hidden = false;
                        note.textContent = 'All ' + field + ' details for “' + name + '” are already filled.';
                        return;
                    }

                    var source = payload.source && payload.source.shipment_number
                        ? ' from ' + payload.source.shipment_number
                        : ' from a previous shipment';
                    note.hidden = false;
                    note.innerHTML = 'Prefilled ' + filled.length + ' field' + (filled.length === 1 ? '' : 's') + source
                        + (skipped ? ' (' + skipped + ' already filled)' : '')
                        + ' — <button type="button" class="party-prefill-undo">undo</button>';

                    note.querySelector('.party-prefill-undo').addEventListener('click', function () {
                        restore(filled);
                        filled.forEach(function (entry) { entry.field.classList.remove('party-prefilled'); });
                        note.hidden = true;
                    });
                })
                .catch(function () { /* prefill is a convenience, never an error */ });
        }

        ['from', 'to'].forEach(function (field) {
            var input = form.querySelector('[data-party-name="' + field + '"]');
            if (!input) return;

            var block = form.querySelector('[data-party-block="' + field + '"]') || form;
            var note = block.querySelector('[data-party-note="' + field + '"]');

            loadNames(field, input);
            input.addEventListener('focus', function () { loadNames(field, input); });

            /* 'change' fires when a datalist suggestion is chosen or the field
               is left; typing alone never overwrites anything. */
            input.addEventListener('change', function () {
                if (note) applyDetails(field, input.value.trim(), block, note);
            });
        });
    }

    window.copyShipmentLink = function (url) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                MasterAlert.toast('Public tracking link copied.', 'success');
            });
        } else {
            MasterAlert.alert(url, { title: 'Copy public tracking link', type: 'info' });
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
        MasterAlert.confirm('Remove this attachment?', { title: 'Remove attachment', confirmText: 'Remove', danger: true }).then(function (ok) {
            if (!ok) return;

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
                MasterAlert.toast('Unable to update attachment.', 'error');
            }
        })
        .catch(function () {
            checkbox.checked = !checkbox.checked;
            MasterAlert.toast('Something went wrong.', 'error');
        });

    };

    document.addEventListener('DOMContentLoaded', initPartyMemory);
})();

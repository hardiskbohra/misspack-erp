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

    /* ---------------- Listing behaviour ----------------
       Row navigation, the sticky-header shadow and the saved-view form are the
       same on every list screen, so they live in assets/js/master-list.js;
       this file only names the shipment list. */
    function initList(root) {
        window.MasterList.rowNavigation({ root: root });
        window.MasterList.gridShadow({ root: root });
        window.MasterList.saveViewToggle();
    }

    /* ---------------- Page initialisation ---------------- */

    document.addEventListener('DOMContentLoaded', function () {
        var quickModal = document.getElementById('quickShipmentModal');
        var deleteModal = document.getElementById('deleteShipmentModal');
        var deleteForm = document.getElementById('deleteShipmentForm');
        var deleteDesc = document.getElementById('deleteShipmentDesc');

        /* The primary action is yielded into the page header, and the empty
           state carries a copy of it, so it is bound by attribute. The old id
           is kept for any page still using it. */
        document.querySelectorAll('[data-quick-shipment], #openQuickShipmentModal').forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(quickModal);
            });
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

        initList('.ship-index');

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

    /* ------------------------------------------------------------------
       Saved views + cost heads
       ------------------------------------------------------------------ */

    /* the saved-view form and the scroll shadow are wired by initList(); this
       hook stays for any page-level extra a future shipment view needs */
    function initShipmentIndexExtras() {}

    /* ------------------------------------------------------------------
       Delivery date (form page + tracking history)
       ------------------------------------------------------------------
       A shipment that is marked delivered has a delivery date: the server
       fills in today when none is given, so this only makes that visible —
       the field appears with today in it, the operator can change it, and a
       date the record already has is never overwritten.
       ------------------------------------------------------------------ */

    function bindDeliveryDateDefault() {
        document.querySelectorAll('form').forEach(function (form) {
            var status = form.querySelector('select[name="status"]');
            var date = form.querySelector('input[name="drop_date"]');
            if (!status || !date) return;

            var optional = date.closest('[data-delivery-optional]');
            var today = date.getAttribute('data-today') || new Date().toISOString().slice(0, 10);

            function sync() {
                var delivered = status.value === 'delivered';

                if (optional) optional.hidden = !delivered;

                if (!delivered) {
                    /* only undo what we filled in ourselves */
                    if (date.dataset.autoFilled === '1') {
                        date.value = '';
                        delete date.dataset.autoFilled;
                    }
                    return;
                }

                if (!date.value) {
                    date.value = today;
                    date.dataset.autoFilled = '1';
                }
            }

            status.addEventListener('change', sync);
            sync();
        });
    }

    /* the shared list bindings live in master-list.js */

    function initCostModal() {
        var modal = document.getElementById('costModal');
        if (!modal || typeof window.MasterModal === 'undefined') return;

        var form = document.getElementById('costForm');
        var method = document.getElementById('costMethod');
        var title = document.getElementById('costModalTitle');
        var submit = document.getElementById('costSubmit');
        var head = document.getElementById('costHead');
        var labelWrap = document.getElementById('costLabelField');
        var currency = document.getElementById('costCurrency');
        var amount = document.getElementById('costAmount');
        var rateWrap = document.getElementById('costRateField');
        var rate = document.getElementById('costRate');
        var note = document.getElementById('costRateNote');
        var preview = document.getElementById('costRatePreview');
        if (!form) return;

        var lastRates = {};
        try {
            lastRates = JSON.parse((rateWrap && rateWrap.getAttribute('data-last-rates')) || '{}');
        } catch (error) {
            lastRates = {};
        }

        /* the server already filled the rate from the last one used for this
           currency, when it knew one — that is what the preview reports */
        var rateFromLastUsed = !!(rate && rate.dataset.autoFilled === '1');

        var storeAction = form.getAttribute('action');
        var updateUrl = modal.getAttribute('data-update-url') || '';

        function syncHead() {
            if (head && labelWrap) {
                labelWrap.hidden = head.value !== 'other';
            }
        }

        function currencyCode() {
            return currency ? String(currency.value).trim().toUpperCase() : 'INR';
        }

        function isBaseCurrency() {
            return currencyCode() === 'INR';
        }

        /* What the ledger will freeze, shown while it is typed: the operator
           never has to work out what the rate does to the amount. The rate is
           editable in every case, so the rupee value follows it in every case. */
        function paintRate() {
            var foreign = ! isBaseCurrency();
            var rateValue = parseFloat((rate && rate.value) || 0) || 0;

            /* the base currency has no conversion to make — the ledger stores
               it at 1 whatever is typed, so the preview says so too */
            if (!foreign) {
                rateValue = 1;
            } else if (!(rateValue > 0)) {
                rateValue = 0;
            }

            var value = (parseFloat((amount && amount.value) || 0) || 0) * rateValue;

            if (note) {
                note.textContent = foreign
                    ? 'The rupee value is frozen at this rate: amount × rate.'
                    : '₹ bill — the amount is already in rupees, so the ledger keeps the rate at 1.';
            }

            if (!preview) return;

            var parts = [];
            var known = lastRates[currencyCode()];

            if (foreign && rateFromLastUsed && known) {
                parts.push('last rate used for ' + currencyCode() + (known.on ? ' on ' + known.on : ''));
            }

            if (value > 0) {
                parts.push('≈ ' + window.misspackFormat.inr(value) + ' posted to the ledger');
            }

            preview.textContent = parts.length ? ' ' + parts.join(' · ') : '';
        }

        /* The rate box is always on screen and always editable: a field that
           appears and disappears is a field that is missing when it is needed,
           and a field that cannot be typed into is a field the operator has to
           work around. It starts from the rate the currency was last billed at
           (1 on the base currency) and the operator can type the rate this bill
           was actually raised at. */
        function syncCurrency(suggest) {
            if (!currency || !rateWrap || !rate) return;

            var foreign = ! isBaseCurrency();
            var code = currencyCode();

            rateWrap.classList.toggle('is-base', !foreign);
            rate.required = foreign;
            rate.placeholder = foreign ? '₹ per 1 ' + code : '1 — the bill is in ₹';

            if (!foreign) {
                /* the rupee amount is already in rupees: the ledger stores the
                   rate at 1, so the field shows 1 rather than a rate left over
                   from another currency */
                rate.value = '1';
                rateFromLastUsed = false;
            } else if (suggest) {
                /* a rate entered for another currency never carries over */
                var known = lastRates[code] || null;
                rate.value = known ? known.rate : '';
                rateFromLastUsed = !!known;
            }

            paintRate();
        }

        function resetForm() {
            form.reset();
            rateFromLastUsed = !!(rate && rate.dataset.autoFilled === '1');
            syncHead();
            syncCurrency(false);
        }

        if (head) head.addEventListener('change', syncHead);

        /* delegated from the form, so the state follows the select however it
           was changed */
        form.addEventListener('change', function (event) {
            if (event.target === currency) syncCurrency(true);
        });

        form.addEventListener('input', function (event) {
            if (event.target === currency || event.target === amount || event.target === rate) paintRate();
        });

        /* and once on load, so what the server rendered and what the script
           thinks are never two different things */
        syncCurrency(false);

        var openBtn = document.getElementById('openCostModal');
        if (openBtn) {
            openBtn.addEventListener('click', function () {
                resetForm();
                form.setAttribute('action', storeAction);
                if (method) method.value = 'POST';
                if (title) title.textContent = 'Add Cost Head';
                if (submit) submit.textContent = 'Add Cost';
                window.MasterModal.open(modal);
            });
        }

        Array.prototype.forEach.call(document.querySelectorAll('.edit-cost'), function (btn) {
            btn.addEventListener('click', function () {
                var data = {};
                try {
                    data = JSON.parse(btn.getAttribute('data-cost') || '{}');
                } catch (error) {
                    data = {};
                }

                resetForm();

                Object.keys(data).forEach(function (key) {
                    var field = form.querySelector('[name="' + key + '"]');
                    if (field && data[key] !== null && data[key] !== undefined) {
                        field.value = data[key];
                    }
                });

                if (updateUrl && data.id) {
                    form.setAttribute('action', updateUrl.replace('COST_ID', data.id));
                }
                /* the rate on the record is the truth for this row, not the
                   last rate used for the currency */
                if (data.exchange_rate) rateFromLastUsed = false;

                if (method) method.value = 'PUT';
                if (title) title.textContent = 'Edit Cost Head';
                if (submit) submit.textContent = 'Save Cost';
                syncHead();
                syncCurrency(false);
                window.MasterModal.open(modal);
            });
        });
    }

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

    /* Run now when the markup is already parsed (this file is pushed at the end
       of the body) so the list is live before the first interaction; otherwise
       wait for it. */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initList('.ship-index'); });
    } else {
        initList('.ship-index');
    }

    document.addEventListener('DOMContentLoaded', bindDeliveryDateDefault);
    document.addEventListener('DOMContentLoaded', initShipmentIndexExtras);
    document.addEventListener('DOMContentLoaded', initCostModal);
    document.addEventListener('DOMContentLoaded', initPartyMemory);
})();

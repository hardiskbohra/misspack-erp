(function () {
    'use strict';

    function byId(id) {
        return document.getElementById(id);
    }

    function openModal(modal) {
        if (!modal) return;
        if (window.MasterModal) {
            window.MasterModal.open(modal);
            return;
        }
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('master-modal-open');
    }

    function fill(form, data) {
        ['kind', 'label', 'line1', 'line2', 'city', 'state', 'pincode', 'country'].forEach(function (name) {
            var field = form.elements[name];
            if (field) field.value = data[name] || '';
        });
        if (form.elements.is_default) {
            form.elements.is_default.checked = !!data.is_default;
        }
    }

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        var modal = byId('orgAddressModal');
        var form = byId('orgAddressForm');
        if (!form || !modal) return;

        var title = modal.querySelector('[data-org-address-title]');
        var subtitle = modal.querySelector('[data-org-address-subtitle]');
        var method = form.querySelector('[data-org-address-method]');
        var storeUrl = form.getAttribute('data-store');

        function openAdd() {
            form.setAttribute('action', storeUrl);
            if (method) method.disabled = true;
            fill(form, { kind: 'billing', country: 'India', is_default: true });
            if (title) title.textContent = 'Add an address';
            if (subtitle) subtitle.textContent = 'Billing, shipping or a branch.';
            openModal(modal);
        }

        function openEdit(data) {
            form.setAttribute('action', data.update_url);
            if (method) method.disabled = false;
            fill(form, data);
            if (title) title.textContent = data.label || 'Edit address';
            if (subtitle) subtitle.textContent = 'Changes apply to new documents. Issued paperwork keeps its snapshot.';
            openModal(modal);
        }

        document.querySelectorAll('[data-org-address-add]').forEach(function (button) {
            button.addEventListener('click', openAdd);
        });

        document.querySelectorAll('[data-org-address-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                try {
                    openEdit(JSON.parse(button.getAttribute('data-org-address-edit') || '{}'));
                } catch (e) {}
            });
        });

        var reopen = document.querySelector('[data-open-address-modal]');
        if (reopen) {
            var payload = reopen.getAttribute('data-open-address-modal');
            if (payload && payload !== 'add') {
                try {
                    openEdit(JSON.parse(payload));
                } catch (e) {
                    openAdd();
                }
            } else {
                openAdd();
            }
        }
    });
})();

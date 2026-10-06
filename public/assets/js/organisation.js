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
        Array.prototype.forEach.call(form.elements, function (field) {
            if (!field.name || field.name === '_token' || field.name === '_method') return;
            if (field.type === 'checkbox') {
                field.checked = !!data[field.name];
                return;
            }
            if (data[field.name] != null) field.value = data[field.name];
        });
    }

    function bindEditor(prefix, defaults) {
        var modal = byId(prefix + 'Modal');
        var form = byId(prefix + 'Form');
        if (!form || !modal) return;

        var title = modal.querySelector('[data-org-title]');
        var subtitle = modal.querySelector('[data-org-subtitle]');
        var method = form.querySelector('[data-org-method]');
        var storeUrl = form.getAttribute('data-store');
        var addTitle = form.getAttribute('data-add-title') || 'Add';
        var editTitle = form.getAttribute('data-edit-title') || 'Edit';

        function openAdd() {
            form.setAttribute('action', storeUrl);
            if (method) method.disabled = true;
            form.reset();
            fill(form, defaults || {});
            if (title) title.textContent = addTitle;
            if (subtitle) subtitle.textContent = form.getAttribute('data-add-sub') || '';
            openModal(modal);
        }

        function openEdit(data) {
            form.setAttribute('action', data.update_url);
            if (method) method.disabled = false;
            fill(form, data);
            if (title) title.textContent = data.title || editTitle;
            if (subtitle) subtitle.textContent = form.getAttribute('data-edit-sub') || '';
            openModal(modal);
        }

        document.querySelectorAll('[data-org-add="' + prefix + '"]').forEach(function (button) {
            button.addEventListener('click', openAdd);
        });

        document.querySelectorAll('[data-org-edit="' + prefix + '"]').forEach(function (button) {
            button.addEventListener('click', function () {
                try {
                    openEdit(JSON.parse(button.getAttribute('data-org-payload') || '{}'));
                } catch (e) {}
            });
        });

        var reopen = document.querySelector('[data-open-org-modal="' + prefix + '"]');
        if (reopen) openAdd();
    }

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        bindEditor('orgAddress', { kind: 'billing', country: 'India', is_default: true });
        bindEditor('orgContact', { department: 'office', is_primary: false });
        bindEditor('orgSocial', { network: 'instagram' });
    });
})();

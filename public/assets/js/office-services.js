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

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        document.querySelectorAll('[data-open-add-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(byId('addModal'));
            });
        });

        var reopen = document.querySelector('[data-open-dialog]');
        if (reopen && reopen.getAttribute('data-open-dialog') === 'add') {
            openModal(byId('addModal'));
        }

        document.querySelectorAll('[data-ofs-delete]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm('Remove this office service?')) {
                    event.preventDefault();
                }
            });
        });
    });
})();

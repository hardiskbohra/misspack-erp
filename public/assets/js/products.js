/**
 * =====================================================================
 * MissPack ERP - Product Module Scripts
 * ---------------------------------------------------------------------
 * Shared by the product views that need behaviour:
 *   - products/index.blade.php  (Quick Product modal)
 *   - products/form.blade.php   (quantity price ladder rows)
 *
 * Load after page markup via @push('scripts'). All element bindings are
 * guarded, so the file is safe on any product page.
 * =====================================================================
 */
(function () {
    'use strict';

    /* ---------------- Quick Product modal (list page) ---------------- */

    document.addEventListener('DOMContentLoaded', function () {
        function openModal(id) {
            document.getElementById(id)?.classList.add('open');
        }

        function closeModal(id) {
            document.getElementById(id)?.classList.remove('open');
        }

        document.getElementById('openQuickProductModal')?.addEventListener('click', function () {
            openModal('quickProductModal');
        });

        document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                closeModal(btn.dataset.closeModal);
            });
        });

        document.querySelectorAll('.master-modal').forEach(function (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal(modal.id);
            });
        });
    });

    /* ---------------- Price ladder rows (form page) ---------------- */

    /** Remove a price ladder row (kept global: called from inline onclick). */
    window.removeLadderRow = function (btn) {
        var tbody = document.querySelector('#priceLadderTable tbody');
        if (tbody && tbody.children.length > 1) btn.closest('tr').remove();
    };
})();

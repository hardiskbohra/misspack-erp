/* Read-only audit evidence uses the ERP modal lifecycle; no data fetch or mutation. */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('auditEvidenceModal');
        if (!modal) return;
        var opener = null;
        var body = modal.querySelector('[data-audit-body]');
        function restoreFocus() {
            if (opener && opener.isConnected) opener.focus({ preventScroll: true });
            opener = null;
        }
        document.querySelectorAll('[data-audit-evidence]').forEach(function (button) {
            button.addEventListener('click', function () {
                var template = document.getElementById(button.getAttribute('data-audit-template'));
                if (!template || !window.MasterModal) return;
                opener = button;
                body.replaceChildren(template.content.cloneNode(true));
                body.scrollTop = 0;
                document.getElementById('auditEvidenceTitle').textContent = button.getAttribute('data-audit-title');
                MasterModal.open(modal);
                modal.querySelector('[data-close-modal]').focus({ preventScroll: true });
            });
        });
        // Shared close/backdrop handlers run first. Return to the originating row.
        modal.addEventListener('click', function (event) {
            if ((event.target === modal || event.target.closest('[data-close-modal]')) && !modal.classList.contains('open')) restoreFocus();
        });
        modal.addEventListener('keydown', function (event) {
            if (!modal.classList.contains('open')) return;
            if (event.key === 'Escape') {
                event.preventDefault(); event.stopPropagation();
                MasterModal.close(modal); restoreFocus(); return;
            }
            if (event.key !== 'Tab') return;
            var stops = Array.from(modal.querySelectorAll('button:not([disabled]), a[href], [tabindex="0"]'))
                .filter(function (node) { return node.getClientRects().length; });
            var first = stops[0], last = stops[stops.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
    });
})();

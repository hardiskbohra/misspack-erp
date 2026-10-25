/* Executive overview: reporting controls and compatibility with old module-tab bookmarks.
 * Detailed analytics have one canonical /dashboards/{module} workspace.
 */
(function () {
    'use strict';
    function init() {
        const periodType = document.getElementById('periodType');
        function togglePeriodControls() {
            if (!periodType) return;
            document.querySelectorAll('.period-control').forEach(function (field) {
                const visible = field.classList.contains('period-' + periodType.value);
                field.hidden = !visible;
                field.style.display = visible ? 'flex' : 'none';
                field.querySelectorAll('input[type="date"]').forEach(function (input) {
                    input.disabled = !visible;
                    input.required = visible;
                });
            });
        }
        const fromDate = document.getElementById('periodStartDate');
        const toDate = document.getElementById('periodEndDate');
        function syncDateBounds() {
            if (fromDate && toDate) toDate.min = fromDate.value;
        }
        fromDate?.addEventListener('input', syncDateBounds);
        fromDate?.addEventListener('change', syncDateBounds);
        syncDateBounds();
        togglePeriodControls();
        // Select2 emits jQuery change events, which native-only listeners miss.
        if (periodType) {
            if (window.jQuery) window.jQuery(periodType).on('change.dashboardPeriod', togglePeriodControls);
            else periodType.addEventListener('change', togglePeriodControls);
        }
        const legacyModule = {sales:'sales', finance:'finance', operations:'operations'}[window.location.hash.replace('#dashboard-', '')];
        if (legacyModule) {
            const canonical = document.querySelector('[data-overview-module="' + legacyModule + '"]');
            if (canonical) window.location.replace(canonical.href);
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

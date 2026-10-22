/* Module dashboard reporting-period controls. */
(function () {
    'use strict';

    function updatePeriodControls() {
        const type = document.getElementById('modulePeriodType')?.value || 'month';
        document.querySelectorAll('.module-period-control').forEach(function (field) {
            field.hidden = !field.classList.contains('module-period-' + type);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updatePeriodControls);
    } else {
        updatePeriodControls();
    }

    document.getElementById('modulePeriodType')?.addEventListener('change', updatePeriodControls);
})();

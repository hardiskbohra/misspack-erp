/* Module dashboard reporting-period controls. */
(function () {
    'use strict';

    function initPeriodControls() {
        const type = document.getElementById('modulePeriodType');
        if (!type) return;

        function updatePeriodControls() {
            document.querySelectorAll('.module-period-control').forEach(function (field) {
                const visible = field.classList.contains('module-period-' + type.value);
                field.hidden = !visible;
                // Author-level flex rules can override the browser's [hidden] rule.
                field.style.display = visible ? 'flex' : 'none';
            });
        }

        updatePeriodControls();
        // Handle both Select2 selections and native select changes, without submitting.
        if (window.jQuery) window.jQuery(type).on('change.moduleDashboardPeriod', updatePeriodControls);
        else type.addEventListener('change', updatePeriodControls);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPeriodControls);
    } else {
        initPeriodControls();
    }
})();

/* ==========================================================================
   EMPLOYEE FIELDS — one role, one set of required fields
   --------------------------------------------------------------------------
   A user is either the office or somebody on the payroll, and the two need
   different things from the same form: an employee is a person the ledger pays,
   so their designation, their joining date and their mobile number are not
   optional — every screen that comes later assumes they exist. An administrator
   is not paid by this module at all.

   The form says so while it is being filled in, instead of refusing it at the
   end: the marker beside a label appears and the input becomes required the
   moment "Employee" is chosen, in the add modal and the edit modal alike. Both
   forms carry `data-role-select`, so a third form would need no new code — and
   the server still validates (App\Http\Controllers\UserController), because a
   browser is not an enforcement point.
   ========================================================================== */
(function () {
    'use strict';

    var REQUIRED_FOR_EMPLOYEE = ['mobile', 'designation', 'date_of_joining'];

    function roleOf(form) {
        var select = form.querySelector('[data-role-select]');

        return select ? select.value : 'admin';
    }

    /* The marker, the requirement and the hint, moved together so they cannot
       say three different things. */
    function sync(form) {
        var role = roleOf(form);
        var employee = role === 'employee';

        REQUIRED_FOR_EMPLOYEE.forEach(function (name) {
            form.querySelectorAll('[name="' + name + '"]').forEach(function (input) {
                input.required = employee;

                var label = input.closest('.master-field');

                if (label) {
                    label.classList.toggle('is-required', employee);
                }
            });
        });

        form.querySelectorAll('[data-role-required]').forEach(function (marker) {
            marker.style.display = employee ? '' : 'none';
        });

        var hint = form.querySelector('[data-role-hint-text]');

        if (hint) {
            hint.textContent = employee
                ? 'An employee sees only their own workspace: profile, salary, payslips and documents.'
                : 'The office account: the whole ERP, including everybody\u2019s payroll.';
        }
    }

    function wire(root) {
        (root || document).querySelectorAll('form').forEach(function (form) {
            var select = form.querySelector('[data-role-select]');

            if (!select || form.dataset.roleWired === '1') {
                return;
            }

            form.dataset.roleWired = '1';
            select.addEventListener('change', function () { sync(form); });
            sync(form);
        });
    }

    window.EmployeeFields = {
        sync: function (root) {
            (root || document).querySelectorAll('form').forEach(function (form) {
                if (form.querySelector('[data-role-select]')) {
                    sync(form);
                }
            });
        },
        wire: wire,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { wire(document); });
    } else {
        wire(document);
    }
})();

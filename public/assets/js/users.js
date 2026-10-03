/* ==========================================================================
   USERS.JS — the user module
   --------------------------------------------------------------------------
   The list (resources/views/users/index.blade.php) and the record
   (resources/views/users/show.blade.php).

   The dialogs are the shared ones: `.master-modal` from master-index.css, and
   the lifecycle in app-layout.js (`window.MasterModal`). They used to be a
   private `.master-modal-overlay` system with its own CSS in users.css — which
   is exactly why they did not scroll: a modal only scrolls when its markup is
   the shared card/body pair, and a private copy of a shared component drifts
   from it the moment the shared one is fixed (task 42). The local fallback at
   the bottom of this file exists so the page still works if the shared script
   has not loaded, not as a second implementation.

   On the record page the tabs are links: every panel has its own URL, so a tab
   can be shared, opened in a new window, and the back button works. The script
   only remembers which tab was last read and marks the clicked one instantly;
   it never rewrites the markup the server rendered.
   ========================================================================== */
(function () {
    'use strict';

    function byId(id) {
        return document.getElementById(id);
    }

    /* One lifecycle, the shared one. The fallback covers the case where
       app-layout.js has not run (a print view, a partially loaded page) — it
       is deliberately the same three lines, not a second system. */
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

    function closeModal(modal) {
        if (!modal) return;

        if (window.MasterModal) {
            window.MasterModal.close(modal);
            return;
        }

        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('master-modal-open');
    }

    /* ------------------------------------------------------------ avatars */

    /* The same four the server takes (`mimes:jpg,jpeg,png,gif,webp`, `max:2048`
       in UserController::rules). Checked here as well so a 4 MB photograph is
       refused where it was chosen instead of after an upload that has already
       happened — two copies of the rule, and `employees-check` holds them
       equal. */
    var AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    var AVATAR_MAX_BYTES = 2 * 1024 * 1024;

    function avatarProblem(file) {
        if (AVATAR_TYPES.indexOf(file.type) === -1) return 'JPG, PNG, GIF or WEBP only';

        return file.size > AVATAR_MAX_BYTES ? 'That photo is over 2 MB' : '';
    }

    /* The band's empty state, button included: `resetAvatar` draws the circle
       and the placeholder, and hides the Clear/Remove button that only makes
       sense once there is something to clear. */
    function resetAvatarBand(circleId, nameId) {
        resetAvatar(circleId, nameId);

        var button = document.querySelector(
            '[data-avatar-drop][data-avatar-circle="' + circleId + '"] .master-remove-avatar-btn');

        if (button) button.hidden = true;
    }

    function resetAvatar(circleId, nameId) {
        var circle = byId(circleId);
        var name = byId(nameId);

        if (circle) circle.innerHTML = '<i class="fas fa-user" aria-hidden="true"></i>';

        if (name) {
            name.textContent = 'No file chosen';
            name.classList.remove('is-chosen', 'is-bad');
        }
    }

    function drawAvatar(file, circleId, nameId) {
        if (!file) return;

        var reader = new FileReader();

        reader.onload = function (event) {
            var circle = byId(circleId);

            if (circle) {
                circle.innerHTML = '<img src="' + event.target.result + '" alt="">';
            }
        };
        reader.readAsDataURL(file);

        var name = byId(nameId);

        if (name) {
            name.textContent = file.name;
            name.classList.add('is-chosen');
            name.classList.remove('is-bad');
        }
    }

    /* A refusal says why, in the slot the file name would have used: silence
       after choosing a file reads as a broken button. */
    function sayAvatarProblem(nameId, message) {
        var name = byId(nameId);

        if (!name) return;

        name.textContent = message;
        name.classList.add('is-bad');
        name.classList.remove('is-chosen');
    }

    function previewAvatar(input, circleId, nameId) {
        if (!input.files || !input.files[0]) return;

        drawAvatar(input.files[0], circleId, nameId);

        if (input.id === 'editAvatarFile') {
            if (byId('editRemoveBtn')) byId('editRemoveBtn').hidden = false;
            if (byId('editRemoveAvatar')) byId('editRemoveAvatar').value = '0';
        }
    }

    /* One band, one input, one row of controls. The circle is a second label for
       the same input — so clicking the photo opens the picker — and
       `data-avatar-remove` says what its button means: `clear` forgets a file
       that was just chosen (the add dialog), `delete` files the photo on record
       for removal (the edit dialog, which posts `remove_avatar`). */
    function wireAvatarBand(row) {
        var input = row.querySelector('input[type="file"]');
        var circleId = row.getAttribute('data-avatar-circle');
        var nameId = row.getAttribute('data-avatar-name');
        var removeButton = row.querySelector('.master-remove-avatar-btn');
        var deletes = row.getAttribute('data-avatar-remove') === 'delete';

        if (input) {
            input.addEventListener('change', function () {
                var file = this.files && this.files[0];

                if (!file) return;

                var problem = avatarProblem(file);

                if (problem) {
                    this.value = '';
                    sayAvatarProblem(nameId, problem);
                    if (removeButton) removeButton.hidden = true;
                    return;
                }

                previewAvatar(this, circleId, nameId);
                if (removeButton) removeButton.hidden = false;
            });
        }

        if (removeButton) {
            removeButton.addEventListener('click', function () {
                if (input) input.value = '';

                resetAvatar(circleId, nameId);
                removeButton.hidden = true;

                if (!deletes) return;

                if (byId('editRemoveAvatar')) byId('editRemoveAvatar').value = '1';
                if (byId(nameId)) byId(nameId).textContent = 'Photo will be removed on save';
            });
        }

        ['dragenter', 'dragover'].forEach(function (name) {
            row.addEventListener(name, function (event) {
                event.preventDefault();
                row.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function (name) {
            row.addEventListener(name, function () {
                row.classList.remove('is-dragover');
            });
        });

        row.addEventListener('drop', function (event) {
            event.preventDefault();

            var file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];

            if (!file || !input) return;

            var problem = avatarProblem(file);

            if (problem) {
                sayAvatarProblem(nameId, problem);
                return;
            }

            /* Put the dropped file into the input itself, so what is shown and
               what is submitted are the same file. If the browser will not take
               it, say so rather than drawing a preview of something that would
               never be uploaded. */
            try {
                var carrier = new DataTransfer();
                carrier.items.add(file);
                input.files = carrier.files;
            } catch (error) {
                sayAvatarProblem(nameId, 'Drop is not available here — use Choose file');
                return;
            }

            previewAvatar(input, circleId, nameId);
            if (removeButton) removeButton.hidden = false;
        });
    }

    /* ---------------------------------------------------------- passwords */

    function togglePassword(fieldId, button) {
        var field = byId(fieldId);
        if (!field) return;

        var show = field.type === 'password';
        field.type = show ? 'text' : 'password';
        button.innerHTML = show
            ? '<i class="fas fa-eye-slash" aria-hidden="true"></i>'
            : '<i class="fas fa-eye" aria-hidden="true"></i>';
    }

    /* ------------------------------------------------------------ dialogs */

    function openAddModal() {
        var form = byId('addForm');

        if (form) form.reset();

        resetAvatarBand('addAvatarCircle', 'addAvName');

        if (window.EmployeeFields) window.EmployeeFields.sync(document);

        openModal(byId('addModal'));
    }

    function openEditModal(userId) {
        ['editPw1', 'editPw2', 'editAvatarFile'].forEach(function (id) {
            if (byId(id)) byId(id).value = '';
        });

        resetAvatarBand('editAvatarCircle', 'editAvName');
        if (byId('editRemoveAvatar')) byId('editRemoveAvatar').value = '0';

        fetch('/users/' + userId + '/data', { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (user) {
                byId('editModalSub').textContent = 'Update the record for ' + user.name;
                byId('editForm').action = '/users/' + user.id;

                setValue('editName', user.name);
                setValue('editEmail', user.email);
                setValue('editMobile', user.mobile);
                setValue('editDepartment', user.department);
                setValue('editDesignation', user.designation);
                setValue('editRole', user.role || 'admin');
                setValue('editCode', user.employee_code);
                setValue('editJoining', user.date_of_joining);
                setValue('editType', user.employment_type || '');
                setValue('editStatus', user.employment_status || 'active');
                setValue('editPan', user.pan_number);
                setValue('editBank', user.bank_name);
                setValue('editAccount', user.bank_account_number);
                setValue('editIfsc', user.bank_ifsc);
                setValue('editAddress', user.address);

                if (byId('editRecordLink')) byId('editRecordLink').href = '/users/' + user.id;

                var circle = byId('editAvatarCircle');
                var removeButton = byId('editRemoveBtn');
                /* A failed save reopens this dialog with a file already chosen
                   (the browser will not re-fill a file input, but the name slot
                   still says what was picked), so do not repaint the circle over
                   it. Only the avatar block is skipped — the rest of the record
                   still has to fill. */
                var chosen = byId('editAvName') && byId('editAvName').classList.contains('is-chosen');

                if (circle && !chosen) {
                    if (user.avatar) {
                        circle.innerHTML = '<img src="' + user.avatar + '" alt="">';
                        if (removeButton) removeButton.hidden = false;
                    } else {
                        circle.innerHTML = '<span class="master-avatar-initials">' +
                            String(user.name || 'U').substring(0, 2).toUpperCase() + '</span>';
                        if (removeButton) removeButton.hidden = true;
                    }
                }

                var note = byId('editRoleNote');

                if (note) {
                    note.textContent = user.is_last_admin
                        ? 'This is the last administrator, so the role cannot be changed.'
                        : (user.is_self ? 'You cannot change your own role.' : '');
                }

                /* The role decides which fields are required, in the dialog as
                   on the page: one sync call, after the values are filled. */
                if (window.EmployeeFields) window.EmployeeFields.sync(document);

                openModal(byId('editModal'));
            })
            .catch(function () {
                if (window.MasterAlert) {
                    window.MasterAlert.alert('Could not load this person. Please try again.',
                        { title: 'Error', type: 'error', danger: true });
                }
            });
    }

    function setValue(id, value) {
        if (byId(id)) byId(id).value = value === null || value === undefined ? '' : value;
    }

    function openDeleteModal(id, name) {
        var description = byId('deleteDesc');

        if (description) {
            description.textContent = 'Delete ' + name + '? This cannot be undone.';
        }

        if (byId('deleteForm')) byId('deleteForm').action = '/users/' + id;

        openModal(byId('deleteModal'));
    }

    /* ------------------------------------------------- the record's tabs */

    function tabs() {
        var record = document.querySelector('.employee-record');
        if (!record) return;

        var userId = record.getAttribute('data-user-id') || '';
        var key = 'misspack.users.tab.' + userId;
        var links = record.querySelectorAll('[data-user-tab-link]');
        var explicit = new URLSearchParams(window.location.search).has('tab');

        links.forEach(function (link) {
            link.addEventListener('click', function () {
                /* Instant feedback; the server re-renders the same state. */
                links.forEach(function (other) {
                    other.classList.remove('is-active');
                    other.setAttribute('aria-selected', 'false');
                });
                link.classList.add('is-active');
                link.setAttribute('aria-selected', 'true');

                try {
                    window.localStorage.setItem(key, link.getAttribute('data-user-tab-link'));
                } catch (e) { /* nothing to remember it with */ }
            });
        });

        /* Somebody who was reading the payslips yesterday opens the record and
           lands on the payslips — the same behaviour the vendor page has. An
           explicit ?tab= in the URL always wins, so a shared link is stable. */
        if (explicit) return;

        var remembered = null;

        try {
            remembered = window.localStorage.getItem(key);
        } catch (e) {
            remembered = null;
        }

        if (!remembered || remembered === 'overview') return;

        var target = record.querySelector('[data-user-tab-link="' + remembered + '"]');

        if (target && !target.classList.contains('is-active')) {
            window.location.replace(target.getAttribute('href'));
        }
    }

    /* --------------------------------------------------------- the list */

    function list() {
        var root = document.querySelector('.user-index');
        if (!root) return;

        if (window.MasterList) {
            window.MasterList.rowNavigation({ root: '.user-index' });
            window.MasterList.density({ root: '.user-index', key: 'misspack.users.density' });
            window.MasterList.gridShadow({ root: '.user-index' });
        }
    }

    /* ------------------------------------------------------- payslip dialogs */

    /* The office's two payslip forms live in dialogs. Opening one from a row is
       a link with the month (or the slip) in the URL, so the server renders the
       dialog already filled and already open — nothing here has to fetch, and a
       validation failure comes back to the same dialog with the values typed. */
    function payslipDialogs() {
        document.querySelectorAll('[data-payslip-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(byId(button.getAttribute('data-payslip-open')));
            });
        });

        var boards = document.querySelectorAll('[data-payslip-form]');

        boards.forEach(function (body) {
            var form = body.closest('form');
            if (!form) return;

            var amounts = body.querySelectorAll('[data-payslip-amount]');
            var netField = body.querySelector('[data-payslip-net]');
            var earned = body.querySelector('[data-payslip-total="earned"]');
            var deducted = body.querySelector('[data-payslip-total="deducted"]');
            var net = body.querySelector('[data-payslip-total="net"]');

            /* The same arithmetic the server does on save, and the same rupee
               format as the figure beside it: misspackFormat is the browser
               half of CommonHelper, so the dialog and the printed slip cannot
               disagree about how a number reads. */
            var inr = function (value) {
                return window.misspackFormat ? window.misspackFormat.inr(value) : String(Math.round(value * 100) / 100);
            };

            var total = function (side) {
                var sum = 0;

                form.querySelectorAll('[name^="' + side + '"][name$="[amount]"]').forEach(function (input) {
                    sum += Number(input.value) || 0;
                });

                return Math.round(sum * 100) / 100;
            };

            var sync = function () {
                var paid = total('earning_lines');
                var taken = total('deduction_lines');
                var typed = netField && netField.value !== '' ? Number(netField.value) : null;

                if (earned) earned.textContent = inr(paid);
                if (deducted) deducted.textContent = inr(taken);
                if (net) net.textContent = inr(typed === null ? paid - taken : typed);
            };

            amounts.forEach(function (input) {
                input.addEventListener('input', sync);
            });

            if (netField) netField.addEventListener('input', sync);

            if (amounts.length) sync();
        });
    }

    /* ------------------------------------------------------------ wiring */

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        document.querySelectorAll('[data-open-add-modal]').forEach(function (button) {
            button.addEventListener('click', openAddModal);
        });

        /* A validation failure re-opens the dialog, so the reader does not have
           to find their way back to the form they just submitted. */
        /* The dialog a failed save came from reopens with what was typed. The
           edit form is server-rendered with `old()` and its own action (see
           `UserController::update`), so it is opened as it stands — fetching the
           record here would replace the office's corrections with the stored
           values, which is the opposite of what they asked for. */
        var reopen = document.querySelector('[data-open-dialog]');
        var reopenKind = reopen ? reopen.getAttribute('data-open-dialog') : '';

        if (reopenKind === 'edit') {
            if (window.EmployeeFields) window.EmployeeFields.sync(document);
            openModal(byId('editModal'));
        } else if (reopenKind === 'add') {
            openAddModal();
        }

        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                togglePassword(button.getAttribute('data-toggle-password'), button);
            });
        });

        document.querySelectorAll('[data-edit-user]').forEach(function (button) {
            button.addEventListener('click', function () {
                openEditModal(button.getAttribute('data-edit-user'));
            });
        });

        document.querySelectorAll('[data-delete-user]').forEach(function (button) {
            button.addEventListener('click', function () {
                openDeleteModal(button.getAttribute('data-delete-user'),
                    button.getAttribute('data-delete-name'));
            });
        });

        document.querySelectorAll('[data-avatar-drop]').forEach(wireAvatarBand);

        list();
        /* The record's own tables open their rows, the way the three lists do:
           the pay table's rows carried data-href and nothing ever read it. */
        if (document.querySelector('.employee-record') && window.MasterList) {
            window.MasterList.rowNavigation({ root: '.employee-record' });
        }

        payslipDialogs();
        tabs();
    });

    /* The dialogs are opened from the shared script's own listeners
       ([data-close-modal], the backdrop, Escape); only the module-specific
       openings live here. */
    window.UserDialogs = { close: closeModal, open: openModal };
})();

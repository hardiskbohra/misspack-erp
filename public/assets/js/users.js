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

    function resetAvatar(circleId, nameId) {
        var circle = byId(circleId);
        var name = byId(nameId);

        if (circle) circle.innerHTML = '<i class="fas fa-user" aria-hidden="true"></i>';
        if (name) name.textContent = 'No file chosen';
    }

    function previewAvatar(input, circleId, nameId) {
        if (!input.files || !input.files[0]) return;

        var reader = new FileReader();

        reader.onload = function (event) {
            var circle = byId(circleId);

            if (circle) {
                circle.innerHTML = '<img src="' + event.target.result + '" alt="">';
            }
        };
        reader.readAsDataURL(input.files[0]);

        var fileName = byId(nameId);
        if (fileName) fileName.textContent = input.files[0].name;

        var removeButton = byId('editRemoveBtn');
        if (removeButton) removeButton.hidden = false;

        var removeInput = byId('editRemoveAvatar');
        if (removeInput) removeInput.value = '0';
    }

    function removeEditAvatar() {
        resetAvatar('editAvatarCircle', 'editAvName');

        if (byId('editAvName')) byId('editAvName').textContent = 'Photo will be removed';
        if (byId('editRemoveAvatar')) byId('editRemoveAvatar').value = '1';
        if (byId('editRemoveBtn')) byId('editRemoveBtn').hidden = true;
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

        resetAvatar('addAvatarCircle', 'addAvName');

        if (window.EmployeeFields) window.EmployeeFields.sync(document);

        openModal(byId('addModal'));
    }

    function openEditModal(userId) {
        ['editPw1', 'editPw2', 'editAvatarFile'].forEach(function (id) {
            if (byId(id)) byId(id).value = '';
        });

        if (byId('editAvName')) byId('editAvName').textContent = 'No file chosen';
        if (byId('editRemoveAvatar')) byId('editRemoveAvatar').value = '0';
        if (byId('editRemoveBtn')) byId('editRemoveBtn').hidden = true;

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

                if (circle) {
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
        var failed = document.querySelector('[data-open-if-errors="1"]');
        if (failed) openAddModal();

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

        if (byId('addAvatarFile')) {
            byId('addAvatarFile').addEventListener('change', function () {
                previewAvatar(this, 'addAvatarCircle', 'addAvName');
            });
        }

        if (byId('editAvatarFile')) {
            byId('editAvatarFile').addEventListener('change', function () {
                previewAvatar(this, 'editAvatarCircle', 'editAvName');
            });
        }

        if (byId('editRemoveBtn')) {
            byId('editRemoveBtn').addEventListener('click', removeEditAvatar);
        }

        list();
        tabs();
    });

    /* The dialogs are opened from the shared script's own listeners
       ([data-close-modal], the backdrop, Escape); only the module-specific
       openings live here. */
    window.UserDialogs = { close: closeModal, open: openModal };
})();

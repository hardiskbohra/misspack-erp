/* ==========================================================================
   USERS.JS — Users module (index view)
   --------------------------------------------------------------------------
   User Management page behaviour:
     - add / edit / delete dialogs (.master-modal-overlay system —
       module-specific design, so the modal lifecycle is owned here rather
       than by the shared .master-modal layer)
     - avatar pick/preview/remove
     - password show/hide toggles
     - edit modal loads the user payload via /users/{id}/data
   When validation fails on create/update the page re-opens the Add modal:
   the button carries data-open-if-errors="1" (set by the view when
   $errors is non-empty).
   ========================================================================== */
(function () {
    'use strict';

    function byId(id) {
        return document.getElementById(id);
    }

    function openModal(id) {
        var modal = byId(id);
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('master-modal-open');
    }

    function closeModal(id) {
        var modal = byId(id);
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('master-modal-open');
    }

    function resetAvatar(circleId, nameId) {
        var circle = byId(circleId);
        var name = byId(nameId);
        if (circle) circle.innerHTML = '<i class="fas fa-user"></i>';
        if (name) name.textContent = 'No file chosen';
    }

    function previewAvatar(input, circleId, nameId) {
        if (!input.files || !input.files[0]) return;

        var reader = new FileReader();
        reader.onload = function (event) {
            var circle = byId(circleId);
            if (circle) {
                circle.innerHTML = '<img src="' + event.target.result + '" alt="Preview">';
            }
        };
        reader.readAsDataURL(input.files[0]);

        var fileName = byId(nameId);
        if (fileName) fileName.textContent = input.files[0].name;

        var removeButton = byId('editRemoveBtn');
        if (removeButton) removeButton.style.display = 'inline-flex';

        var removeInput = byId('editRemoveAvatar');
        if (removeInput) removeInput.value = '0';
    }

    function togglePassword(fieldId, button) {
        var field = byId(fieldId);
        if (!field) return;

        var show = field.type === 'password';
        field.type = show ? 'text' : 'password';
        button.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
    }

    function openAddModal() {
        var form = byId('addForm');
        if (form) form.reset();
        resetAvatar('addAvatarCircle', 'addAvName');
        openModal('addModal');
    }

    function openEditModal(userId) {
        if (byId('editPw1')) byId('editPw1').value = '';
        if (byId('editPw2')) byId('editPw2').value = '';
        if (byId('editAvatarFile')) byId('editAvatarFile').value = '';
        if (byId('editAvName')) byId('editAvName').textContent = 'No file chosen';
        if (byId('editRemoveAvatar')) byId('editRemoveAvatar').value = '0';

        fetch('/users/' + userId + '/data')
            .then(function (response) { return response.json(); })
            .then(function (user) {
                byId('editModalSub').textContent = 'Update details for ' + user.name;
                byId('editForm').action = '/users/' + user.id;
                byId('editName').value = user.name || '';
                byId('editEmail').value = user.email || '';
                byId('editMobile').value = user.mobile || '';
                byId('editDepartment').value = user.department || '';
                byId('editDesignation').value = user.designation || '';

                var circle = byId('editAvatarCircle');
                var removeButton = byId('editRemoveBtn');

                if (user.avatar) {
                    circle.innerHTML = '<img src="' + user.avatar + '" alt="' + user.name + '">';
                    removeButton.style.display = 'inline-flex';
                } else {
                    circle.innerHTML = '<span style="font-weight:500;font-size:20px;">' + String(user.name || 'U').substring(0, 2).toUpperCase() + '</span>';
                    removeButton.style.display = 'none';
                }

                openModal('editModal');
            })
            .catch(function () {
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load user data. Please try again.' });
                } else {
                    alert('Could not load user data. Please try again.');
                }
            });
    }

    function removeEditAvatar() {
        resetAvatar('editAvatarCircle', 'editAvName');
        byId('editAvName').textContent = 'Avatar will be removed';
        byId('editRemoveAvatar').value = '1';
        byId('editRemoveBtn').style.display = 'none';
    }

    function openDeleteModal(id, name) {
        byId('deleteDesc').textContent = 'Are you sure you want to delete "' + name + '"? This action cannot be undone.';
        byId('deleteForm').action = '/users/' + id;
        openModal('deleteModal');
    }

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        var addButton = document.querySelector('[data-open-add-modal]');
        if (addButton) {
            addButton.addEventListener('click', openAddModal);
            /* Validation failure on create/update → re-open Add modal */
            if (addButton.getAttribute('data-open-if-errors') === '1') {
                openAddModal();
            }
        }

        document.querySelectorAll('[data-close-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                closeModal(button.getAttribute('data-close-modal'));
            });
        });

        document.querySelectorAll('.master-modal-overlay').forEach(function (overlay) {
            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) closeModal(overlay.id);
            });
        });

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
                openDeleteModal(button.getAttribute('data-delete-user'), button.getAttribute('data-delete-name'));
            });
        });

        var addAvatar = byId('addAvatarFile');
        if (addAvatar) addAvatar.addEventListener('change', function () { previewAvatar(addAvatar, 'addAvatarCircle', 'addAvName'); });

        var editAvatar = byId('editAvatarFile');
        if (editAvatar) editAvatar.addEventListener('change', function () { previewAvatar(editAvatar, 'editAvatarCircle', 'editAvName'); });

        var removeButton = byId('editRemoveBtn');
        if (removeButton) removeButton.addEventListener('click', removeEditAvatar);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.master-modal-overlay.open').forEach(function (modal) {
                    closeModal(modal.id);
                });
            }
        });
    });
})();

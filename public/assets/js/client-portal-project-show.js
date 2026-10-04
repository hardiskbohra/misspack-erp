/* ==========================================================================
   CLIENT-PORTAL-PROJECT-SHOW.JS — Client Portal module
   --------------------------------------------------------------------------
   Project detail page behaviour (resources/views/client_portal/
   projects/show.blade.php):
     - copy portal link button
     - tab switching with localStorage persistence + URL hash deep-links
       (storage key carries the project id from #projectShowRoot)
     - comment/attachment modals (local .master-modal system, not the
       admin shell's shared layer — the portal uses its own layout)
   ========================================================================== */
(function () {
    'use strict';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        var copyBtn = document.getElementById('copyPortalLink');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                var input = document.getElementById('portalLinkInput');
                input.select();
                input.setSelectionRange(0, 99999);
                document.execCommand('copy');
                copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
                setTimeout(function() {
                    copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy';
                }, 1800);
            });
        }

        var tabButtons = document.querySelectorAll('.pd-tab-btn');
        var tabPanels = document.querySelectorAll('.pd-tab-panel');
        var storageKey = 'project_show_active_tab_' + (document.getElementById('projectShowRoot').getAttribute('data-project-id') || '');

        function openProjectTab(tabName, updateHash) {
            var found = false;
            tabButtons.forEach(function(btn) {
                var isActive = btn.getAttribute('data-tab') === tabName;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                if (isActive) found = true;
            });
            if (!found) {
                tabName = 'overview';
                tabButtons.forEach(function(btn) {
                    var isActive = btn.getAttribute('data-tab') === tabName;
                    btn.classList.toggle('active', isActive);
                    btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
            }
            tabPanels.forEach(function(panel) {
                panel.classList.toggle('active', panel.getAttribute('data-tab-panel') === tabName);
            });
            try {
                localStorage.setItem(storageKey, tabName);
            } catch (e) {}
            if (updateHash) {
                history.replaceState(null, '', '#' + tabName);
            }
        }

        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                openProjectTab(btn.getAttribute('data-tab'), true);
            });
        });

        document.querySelectorAll('[data-tab-jump]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openProjectTab(btn.getAttribute('data-tab-jump'), true);
                window.scrollTo({
                    top: document.querySelector('.pd-tabs-shell').offsetTop - 90,
                    behavior: 'smooth'
                });
            });
        });

        var initialTab = window.location.hash ? window.location.hash.replace('#', '') : '';
        if (!initialTab) {
            try {
                initialTab = localStorage.getItem(storageKey) || 'overview';
            } catch (e) {
                initialTab = 'overview';
            }
        }
        openProjectTab(initialTab, false);



        function openModal(modal){
            modal.classList.add('show');
            document.body.classList.add('master-modal-open');
        }

        function closeModal(modal){
            modal.classList.remove('show');
            document.body.classList.remove('master-modal-open');
        }

        function setValue(form, name, value) {
            const field = form.elements[name];
            if (!field) return;

            if (field.type === 'checkbox') {
                field.checked = !!value;
            } else {
                field.value = value ?? '';
            }
        }

        document.addEventListener('keydown',function(e){
            if(e.key !== 'Escape') return;
            document.querySelectorAll('.master-modal.show').forEach(modal=>{
                closeModal(modal);
            });
        });

        document.querySelectorAll('.master-modal').forEach(modal=>{
            modal.addEventListener('click',function(e){
                if(e.target===modal){
                    closeModal(modal);
                }
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach(btn=>{
            btn.addEventListener('click',()=>{
                closeModal(btn.closest('.master-modal'));
            });
        });

        const editCommentModal = document.getElementById('editCommentModal');
        const editCommentForm = document.getElementById('editCommentForm');

        document.querySelectorAll('.editCommentBtn').forEach(btn => {

            btn.addEventListener('click', function () {

                const comment = JSON.parse(this.dataset.comment);

                editCommentForm.action = `/project-comments/${comment.id}`;

                setValue(editCommentForm, 'project_product_id', comment.project_product_id);
                setValue(editCommentForm, 'body', comment.body);
                setValue(editCommentForm, 'is_pinned', comment.is_pinned);
                setValue(editCommentForm, 'is_public', comment.is_public);

                openModal(editCommentModal);
            });
        });

        const addCommentModal = document.getElementById('addCommentModal');
        document.getElementById('openAddCommentModal')?.addEventListener('click', () => openModal(addCommentModal));
        document.getElementById('openAddCommentModal2')?.addEventListener('click', () => openModal(addCommentModal));

        const addAttachmentModal = document.getElementById('addAttachmentModal');
        document.getElementById('openAddAttachmentModal')?.addEventListener('click', () => openModal(addAttachmentModal));
        document.getElementById('openAddAttachmentModal2')?.addEventListener('click', () => openModal(addAttachmentModal));


    });
})();

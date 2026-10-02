(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.documentElement;
        var sidebarToggle = document.getElementById('sidebarToggle');
        var sidebarOverlay = document.getElementById('sidebarOverlay');
        var mobileBreakpoint = window.matchMedia('(max-width: 1199px)');

        function isMobileOrTablet() {
            return mobileBreakpoint.matches;
        }

        function openMobileSidebar() {
            root.classList.remove('sidebar-collapsed');
            root.classList.add('sidebar-mobile-open');
            document.body.classList.add('sidebar-open-body');
        }

        function closeMobileSidebar() {
            root.classList.remove('sidebar-mobile-open');
            document.body.classList.remove('sidebar-open-body');
        }

        function forceMobileClosed() {
            root.classList.remove('sidebar-collapsed');
            root.classList.remove('sidebar-mobile-open');
            document.body.classList.remove('sidebar-open-body');
        }

        function setDesktopCollapsed(collapsed) {
            if (isMobileOrTablet()) {
                return;
            }

            root.classList.toggle('sidebar-collapsed', collapsed);

            try {
                localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
            } catch (e) {}
        }

        function toggleSidebar() {
            if (isMobileOrTablet()) {
                if (root.classList.contains('sidebar-mobile-open')) {
                    closeMobileSidebar();
                } else {
                    openMobileSidebar();
                }
                return;
            }

            closeMobileSidebar();
            setDesktopCollapsed(!root.classList.contains('sidebar-collapsed'));
        }

        function applyResponsiveState() {
            if (isMobileOrTablet()) {
                forceMobileClosed();
                return;
            }

            closeMobileSidebar();

            var saved = false;
            try {
                saved = localStorage.getItem('sidebarCollapsed') === '1';
            } catch (e) {}

            root.classList.toggle('sidebar-collapsed', saved);
        }

        if (sidebarToggle) {
            sidebarToggle.setAttribute('type', 'button');
            sidebarToggle.addEventListener('click', function (event) {
                event.preventDefault();
                toggleSidebar();
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeMobileSidebar);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMobileSidebar();
            }
        });

        var sidebarLinks = document.querySelectorAll('.sidebar a');
        for (var i = 0; i < sidebarLinks.length; i++) {
            sidebarLinks[i].addEventListener('click', function () {
                if (isMobileOrTablet()) {
                    closeMobileSidebar();
                }
            });
        }

        window.addEventListener('resize', applyResponsiveState);

        if (typeof mobileBreakpoint.addEventListener === 'function') {
            mobileBreakpoint.addEventListener('change', applyResponsiveState);
        } else if (typeof mobileBreakpoint.addListener === 'function') {
            mobileBreakpoint.addListener(applyResponsiveState);
        }

        applyResponsiveState();
        
        /* ==================================================================
           master-* modal system (shared by every module)
           ----------------------------------------------------------------
           Generic lifecycle for .master-modal dialogs (master-index.css):
             - [data-close-modal] buttons  (value = modal id, else nearest)
             - backdrop click
             - Escape key
           Module JS only needs to OPEN its modals (per-page ids/behaviour).
           ================================================================== */
        function openMasterModal(modal) {
            if (!modal || modal.classList.contains('open')) return;
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('master-modal-open');
        }

        function closeMasterModal(modal) {
            if (!modal) return;
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            if (!document.querySelector('.master-modal.open')) {
                document.body.classList.remove('master-modal-open');
            }
        }

        window.MasterModal = { open: openMasterModal, close: closeMasterModal };

        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                var target = btn.dataset.closeModal
                    ? document.getElementById(btn.dataset.closeModal)
                    : btn.closest('.master-modal');
                closeMasterModal(target);
            });
        });

        document.querySelectorAll('.master-modal').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeMasterModal(modal);
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.master-modal.open').forEach(closeMasterModal);
        });

        /* A menu button has to say whether its menu is open, and Escape has to
           close it: without that the popover is invisible to a screen reader
           and cannot be dismissed from the keyboard. */
        const setMenuState = (dropdown, open) => {
            dropdown.classList.toggle('open', open);

            const toggle = dropdown.querySelector('.master-dropdown-toggle');
            if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        const closeMenus = (except) => {
            document.querySelectorAll('.master-dropdown').forEach(d => {
                if (d !== except) setMenuState(d, false);
            });
        };

        document.querySelectorAll('.master-dropdown-toggle').forEach(btn => {

            btn.addEventListener('click', function(e){
        
                e.stopPropagation();

                const dropdown = this.parentElement;
                const willOpen = ! dropdown.classList.contains('open');

                closeMenus(dropdown);
                setMenuState(dropdown, willOpen);
        
            });
        
        });
        
        document.addEventListener('click', () => closeMenus());

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;

            document.querySelectorAll('.master-dropdown.open').forEach(dropdown => {
                setMenuState(dropdown, false);
                dropdown.querySelector('.master-dropdown-toggle')?.focus();
            });
        });
    });
})();

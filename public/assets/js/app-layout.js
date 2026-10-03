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
        const MENU_GAP = 8;
        const MENU_EDGE = 8;
        const MENU_MIN = 120;

        /* While a menu is open its panel is moved out of the table and into
           <body>. That settles the whole class of problems at once: a panel
           inside a table cell is painted in that row's pass — so every row
           after it draws over it — it is clipped by any scrolling box above
           it, and any ancestor that creates a stacking context (a sticky cell,
           a transformed card, an opacity group, a filter) traps it, whatever
           z-index the panel asks for. On the body it is just a fixed box above
           everything. It goes back where it came from when it closes, so the
           markup stays as authored and its links and forms keep working. */
        const menuHomes = new WeakMap();
        const dropdownMenus = new WeakMap();

        document.querySelectorAll('.master-dropdown').forEach(dropdown => {
            const menu = dropdown.querySelector('.master-dropdown-menu');
            if (menu) dropdownMenus.set(dropdown, menu);
        });

        /* once portaled the menu is no longer inside the dropdown, so it is
           remembered rather than looked up again */
        const menuOf = (dropdown) => dropdownMenus.get(dropdown)
            || dropdown.querySelector('.master-dropdown-menu');

        const portalMenu = (menu) => {
            if (!menu || menu.parentNode === document.body) return;

            menuHomes.set(menu, { parent: menu.parentNode, next: menu.nextSibling });
            document.body.appendChild(menu);

            /* `.master-dropdown.open …` cannot reach a portaled panel */
            menu.style.display = 'block';
        };

        const restoreMenu = (menu) => {
            if (!menu) return;

            const home = menuHomes.get(menu);

            if (home) {
                if (home.next && home.next.parentNode === home.parent) {
                    home.parent.insertBefore(menu, home.next);
                } else {
                    home.parent.appendChild(menu);
                }

                menuHomes.delete(menu);
            }

            menu.style.display = '';
        };

        /* The button is measured and the panel is placed under it,
           right-aligned with it, kept inside the viewport, flipped above it
           when there is more room there, and capped to the room the viewport
           actually has so it scrolls inside itself instead of disappearing
           past an edge. */
        const placeMenu = (dropdown) => {
            const menu = menuOf(dropdown);
            const toggle = dropdown.querySelector('.master-dropdown-toggle');
            if (!menu || !toggle) return;

            portalMenu(menu);

            /* measure the panel at its full size before capping it */
            menu.style.maxHeight = 'none';
            menu.style.overflowY = '';
            menu.style.top = '';
            menu.style.bottom = '';
            menu.style.left = '';
            menu.style.right = '';

            const button = toggle.getBoundingClientRect();
            const width = menu.offsetWidth || 220;
            const height = menu.offsetHeight || 0;
            const viewport = window.innerHeight;

            /* right-aligned with the button it belongs to, kept on screen */
            const left = Math.max(MENU_EDGE, Math.min(
                button.right - width,
                window.innerWidth - width - MENU_EDGE
            ));

            const below = viewport - button.bottom - MENU_GAP - MENU_EDGE;
            const above = button.top - MENU_GAP - MENU_EDGE;
            const up = height > below && above > below;
            const room = Math.max(MENU_MIN, up ? above : below);

            menu.style.left = left + 'px';

            if (up) {
                menu.style.bottom = (viewport - button.top + MENU_GAP) + 'px';
            } else {
                menu.style.top = (button.bottom + MENU_GAP) + 'px';
            }

            menu.style.maxHeight = room + 'px';
            menu.style.overflowY = height > room ? 'auto' : 'hidden';
        };

        const setMenuState = (dropdown, open) => {
            dropdown.classList.toggle('open', open);

            const toggle = dropdown.querySelector('.master-dropdown-toggle');
            if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open) {
                placeMenu(dropdown);
                return;
            }

            const menu = menuOf(dropdown);

            if (menu) {
                menu.style.maxHeight = '';
                menu.style.overflowY = '';
                menu.style.top = '';
                menu.style.bottom = '';
                menu.style.left = '';
                menu.style.right = '';
                restoreMenu(menu);
            }
        };

        /* an open panel is anchored to its button, so it follows the page */
        const placeOpenMenus = () => {
            document.querySelectorAll('.master-dropdown.open').forEach(placeMenu);
        };

        /* capture phase: a scroll inside the table's own box does not bubble */
        document.addEventListener('scroll', placeOpenMenus, true);
        window.addEventListener('resize', placeOpenMenus);

        const closeMenus = (except) => {
            document.querySelectorAll('.master-dropdown').forEach(d => {
                if (d !== except) setMenuState(d, false);
            });
        };

        document.querySelectorAll('.master-dropdown-toggle').forEach(btn => {

            btn.addEventListener('click', function(e){
        
                e.stopPropagation();

                const dropdown = this.closest('.master-dropdown');
                if (!dropdown) return;

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

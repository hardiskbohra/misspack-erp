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
        
        document.querySelectorAll('.master-dropdown-toggle').forEach(btn => {

            btn.addEventListener('click', function(e){
        
                e.stopPropagation();
        
                document.querySelectorAll('.master-dropdown')
                    .forEach(d => {
                        if(d !== this.parentElement){
                            d.classList.remove('open');
                        }
                    });
        
                this.parentElement.classList.toggle('open');
        
            });
        
        });
        
        document.addEventListener('click', () => {
            document.querySelectorAll('.master-dropdown')
                .forEach(d => d.classList.remove('open'));
        });
    });
})();

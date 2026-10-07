/* Shared client workspace interactions: responsive navigation, command palette
   and a local, no-account-setting-required colour preference. */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    ready(function () {
        var shell = document.getElementById('cpShell');
        var toggle = document.getElementById('cpToggle');
        var overlay = document.getElementById('cpOverlay');
        var command = document.getElementById('cpCommand');
        var commandInput = document.getElementById('cpCommandInput');
        var previousFocus = null;

        function setSidebar(open) {
            if (!shell) return;
            shell.classList.toggle('sidebar-open', open);
            if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        document.querySelectorAll('.cp-nav-link').forEach(function (link) {
            var label = link.querySelector('span:not(.cp-nav-count)');
            var text = label ? label.textContent.trim() : '';
            if (text && !link.hasAttribute('aria-label')) link.setAttribute('aria-label', text);
            if (text && !link.hasAttribute('title')) link.setAttribute('title', text);
        });

        if (toggle) toggle.addEventListener('click', function () { setSidebar(!shell.classList.contains('sidebar-open')); });
        if (overlay) overlay.addEventListener('click', function () { setSidebar(false); });
        window.addEventListener('resize', function () { if (window.innerWidth >= 992) setSidebar(false); });

        function applyTheme(theme) {
            document.documentElement.dataset.theme = theme;
            localStorage.setItem('misspack-portal-theme', theme);
            document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
                var isDark = theme === 'dark';
                button.setAttribute('aria-label', isDark ? 'Use light theme' : 'Use dark theme');
                button.innerHTML = '<i class="fa-regular ' + (isDark ? 'fa-sun' : 'fa-moon') + '"></i>';
            });
        }
        applyTheme(document.documentElement.dataset.theme || 'light');
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                applyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
            });
        });

        function filterCommands() {
            if (!commandInput) return;
            var query = commandInput.value.trim().toLowerCase();
            var visible = 0;
            command.querySelectorAll('[data-command-item]').forEach(function (item) {
                var show = !query || item.dataset.search.indexOf(query) !== -1;
                item.hidden = !show;
                if (show) visible++;
            });
            command.querySelectorAll('.cp-command-group').forEach(function (group) {
                var next = group.nextElementSibling;
                var groupVisible = false;
                while (next && !next.classList.contains('cp-command-group') && !next.hasAttribute('data-command-empty')) {
                    if (!next.hidden) groupVisible = true;
                    next = next.nextElementSibling;
                }
                group.hidden = !groupVisible;
            });
            var empty = command.querySelector('[data-command-empty]');
            if (empty) empty.hidden = visible !== 0;
        }

        function openCommand(opener) {
            if (!command) return;
            previousFocus = opener || document.activeElement;
            command.hidden = false;
            document.body.classList.add('cp-command-open');
            if (commandInput) {
                commandInput.value = '';
                filterCommands();
                window.setTimeout(function () { commandInput.focus(); }, 0);
            }
        }
        function closeCommand() {
            if (!command || command.hidden) return;
            command.hidden = true;
            document.body.classList.remove('cp-command-open');
            if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
        }

        document.querySelectorAll('[data-command-open]').forEach(function (button) {
            button.addEventListener('click', function () { openCommand(button); });
        });
        document.querySelectorAll('[data-command-close]').forEach(function (button) {
            button.addEventListener('click', closeCommand);
        });
        if (commandInput) commandInput.addEventListener('input', filterCommands);

        document.addEventListener('keydown', function (event) {
            var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement && document.activeElement.tagName);
            if (event.key === '/' && !typing && command && command.hidden) {
                event.preventDefault();
                openCommand(document.querySelector('[data-command-open]'));
            } else if (event.key === 'Escape') {
                if (command && !command.hidden) closeCommand();
                else setSidebar(false);
            } else if (event.key === 'Tab' && command && !command.hidden) {
                var focusable = Array.prototype.slice.call(command.querySelectorAll('input, button, a:not([hidden])'));
                if (!focusable.length) return;
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });
    });
})();

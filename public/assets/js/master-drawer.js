/* Shared accessible drawer behavior for quick details and lightweight tools. */
(function () {
    'use strict';

    var activeLayer = null;
    var activeTrigger = null;
    var closeTimer = null;
    var CLOSE_MS = 230;
    var focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function panelFor(layer) {
        return layer ? layer.querySelector('.core-drawer') : null;
    }

    function focusableIn(panel) {
        return panel ? Array.prototype.slice.call(panel.querySelectorAll(focusableSelector)).filter(function (element) {
            return !element.hidden && element.getAttribute('aria-hidden') !== 'true';
        }) : [];
    }

    function safeHref(value) {
        var href = (value || '').trim();
        if (!href) return '';
        if (/^(https?:\/\/|mailto:|tel:)/i.test(href) || !/^[a-z][a-z\d+.-]*:/i.test(href)) return href;
        return '';
    }

    function bindTriggerData(trigger, panel) {
        if (!trigger || !panel) return;

        var title = trigger.getAttribute('data-drawer-title');
        var subtitle = trigger.getAttribute('data-drawer-subtitle');
        var eyebrow = trigger.getAttribute('data-drawer-eyebrow');
        var heading = panel.querySelector('[data-drawer-heading]');
        var subtitleNode = panel.querySelector('[data-drawer-subtitle]');
        var eyebrowNode = panel.querySelector('[data-drawer-eyebrow]');

        if (heading && title !== null) heading.textContent = title;
        if (subtitleNode && subtitle !== null) {
            subtitleNode.textContent = subtitle;
            subtitleNode.hidden = subtitle.trim() === '';
        }
        if (eyebrowNode && eyebrow !== null) {
            eyebrowNode.textContent = eyebrow;
            eyebrowNode.hidden = eyebrow.trim() === '';
        }

        panel.querySelectorAll('[data-drawer-bind]').forEach(function (node) {
            var field = node.getAttribute('data-drawer-bind');
            var value = trigger.getAttribute('data-drawer-' + field) || '';
            node.textContent = value || node.getAttribute('data-drawer-empty') || '—';
            if (node.hasAttribute('data-drawer-hide-if-empty')) {
                var wrapper = node.closest('[data-drawer-field]') || node.parentElement;
                if (wrapper) wrapper.hidden = value.trim() === '';
            }
        });

        panel.querySelectorAll('[data-drawer-href-bind]').forEach(function (node) {
            var field = node.getAttribute('data-drawer-href-bind');
            var value = safeHref(trigger.getAttribute('data-drawer-' + field) || '');
            if (value) node.setAttribute('href', value);
            else node.removeAttribute('href');
            if (node.hasAttribute('data-drawer-hide-if-empty')) {
                var wrapper = node.closest('[data-drawer-field]') || node.parentElement;
                if (wrapper) wrapper.hidden = value.trim() === '';
            }
        });
    }

    function openDrawer(target, trigger) {
        var panel = typeof target === 'string' ? document.getElementById(target.replace(/^#/, '')) : target;
        if (!panel || !panel.classList.contains('core-drawer')) return false;

        var layer = panel.closest('[data-drawer-layer]');
        if (!layer) return false;

        if (closeTimer) {
            window.clearTimeout(closeTimer);
            closeTimer = null;
        }
        if (activeLayer && activeLayer !== layer) closeDrawer(false);

        bindTriggerData(trigger, panel);
        activeLayer = layer;
        activeTrigger = trigger || document.activeElement;
        layer.hidden = false;
        layer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('core-drawer-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');

        window.requestAnimationFrame(function () {
            if (activeLayer !== layer) return;
            layer.classList.add('is-open');
            var focusables = focusableIn(panel);
            var initial = panel.querySelector('[data-drawer-initial-focus]') || focusables[0] || panel;
            initial.focus();
        });

        return true;
    }

    function closeDrawer(restoreFocus) {
        if (!activeLayer) return;

        var layer = activeLayer;
        var panel = panelFor(layer);
        var trigger = activeTrigger;
        activeLayer = null;
        activeTrigger = null;
        layer.classList.remove('is-open');
        layer.setAttribute('aria-hidden', 'true');
        if (trigger && trigger.setAttribute) trigger.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('core-drawer-open');

        closeTimer = window.setTimeout(function () {
            if (!layer.classList.contains('is-open')) layer.hidden = true;
            closeTimer = null;
        }, CLOSE_MS);

        if (restoreFocus !== false && trigger && document.contains(trigger) && typeof trigger.focus === 'function') {
            trigger.focus();
        }
        if (panel) panel.scrollTop = 0;
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-drawer-open]');
        if (trigger) {
            var target = trigger.getAttribute('data-drawer-open');
            if (openDrawer(target, trigger)) event.preventDefault();
            return;
        }

        var closer = event.target.closest('[data-drawer-close]');
        if (closer && activeLayer && activeLayer.contains(closer)) {
            event.preventDefault();
            closeDrawer(true);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!activeLayer) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDrawer(true);
            return;
        }
        if (event.key !== 'Tab') return;

        var panel = panelFor(activeLayer);
        var focusables = focusableIn(panel);
        if (!focusables.length) {
            event.preventDefault();
            if (panel) panel.focus();
            return;
        }

        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        if (event.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    window.MasterDrawer = {
        open: openDrawer,
        close: function () { closeDrawer(true); },
    };
})();

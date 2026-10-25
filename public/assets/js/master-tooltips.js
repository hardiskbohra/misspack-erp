(() => {
    'use strict';

    const SELECTOR = '[data-ui-tooltip]';
    const GAP = 8;
    const EDGE = 8;
    let bubble = null;
    let active = null;
    let showTimer = null;
    let hideTimer = null;

    const interactive = element => element.matches('a,button,input,select,textarea,[role="button"],[tabindex]');

    function promote(element) {
        if (!(element instanceof Element)) return;
        if (element.hasAttribute('title')) {
            const text = element.getAttribute('title')?.trim();
            element.removeAttribute('title');
            if (text) element.dataset.uiTooltip = text;
            if (text && interactive(element) && !element.getAttribute('aria-label') && !element.textContent.trim()) {
                element.setAttribute('aria-label', text);
            }
        }
        if (element.dataset.tooltip && !element.dataset.uiTooltip) {
            element.dataset.uiTooltip = element.dataset.tooltip;
        }
    }

    function promoteTree(root) {
        if (!(root instanceof Element) && root !== document) return;
        if (root instanceof Element) promote(root);
        root.querySelectorAll?.('[title],[data-tooltip]').forEach(promote);
    }

    function ensureBubble() {
        if (bubble) return bubble;
        bubble = document.createElement('div');
        bubble.id = 'uiGlobalTooltip';
        bubble.className = 'ui-tooltip';
        bubble.setAttribute('role', 'tooltip');
        bubble.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bubble);
        return bubble;
    }

    function position(target) {
        const tip = ensureBubble();
        const targetRect = target.getBoundingClientRect();
        const tipRect = tip.getBoundingClientRect();
        const roomAbove = targetRect.top;
        const placement = roomAbove >= tipRect.height + GAP + EDGE ? 'top' : 'bottom';
        let top = placement === 'top'
            ? targetRect.top - tipRect.height - GAP
            : targetRect.bottom + GAP;
        let left = targetRect.left + targetRect.width / 2 - tipRect.width / 2;
        left = Math.max(EDGE, Math.min(left, window.innerWidth - tipRect.width - EDGE));
        top = Math.max(EDGE, Math.min(top, window.innerHeight - tipRect.height - EDGE));

        const targetCentre = targetRect.left + targetRect.width / 2;
        const arrow = Math.max(8, Math.min(targetCentre - left, tipRect.width - 8));
        tip.dataset.placement = placement;
        tip.style.left = `${Math.round(left)}px`;
        tip.style.top = `${Math.round(top)}px`;
        tip.style.setProperty('--ui-tooltip-arrow-x', `${Math.round(arrow)}px`);
    }

    function describe(target) {
        const current = (target.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        if (!current.includes('uiGlobalTooltip')) current.push('uiGlobalTooltip');
        target.setAttribute('aria-describedby', current.join(' '));
    }

    function undescribe(target) {
        const remaining = (target.getAttribute('aria-describedby') || '')
            .split(/\s+/).filter(id => id && id !== 'uiGlobalTooltip');
        if (remaining.length) target.setAttribute('aria-describedby', remaining.join(' '));
        else target.removeAttribute('aria-describedby');
    }

    function open(target, immediate = false) {
        clearTimeout(hideTimer);
        clearTimeout(showTimer);
        const text = target.dataset.uiTooltip?.trim();
        if (!text) return;

        showTimer = window.setTimeout(() => {
            if (active && active !== target) undescribe(active);
            active = target;
            const tip = ensureBubble();
            tip.textContent = text;
            tip.setAttribute('aria-hidden', 'false');
            tip.dataset.state = 'visible';
            describe(target);
            position(target);
        }, immediate ? 0 : 180);
    }

    function close(target = active) {
        clearTimeout(showTimer);
        clearTimeout(hideTimer);
        hideTimer = window.setTimeout(() => {
            if (target) undescribe(target);
            if (bubble) {
                bubble.dataset.state = 'hidden';
                bubble.setAttribute('aria-hidden', 'true');
            }
            if (active === target) active = null;
        }, 40);
    }

    function triggerFrom(event) {
        return event.target instanceof Element ? event.target.closest(SELECTOR) : null;
    }

    document.addEventListener('mouseover', event => {
        const target = triggerFrom(event);
        if (!target || (event.relatedTarget instanceof Node && target.contains(event.relatedTarget))) return;
        open(target);
    });
    document.addEventListener('mouseout', event => {
        const target = triggerFrom(event);
        if (!target || (event.relatedTarget instanceof Node && target.contains(event.relatedTarget))) return;
        close(target);
    });
    document.addEventListener('focusin', event => {
        const target = triggerFrom(event);
        if (target) open(target, true);
    });
    document.addEventListener('focusout', event => {
        const target = triggerFrom(event);
        if (target) close(target);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && active) close(active);
    });
    document.addEventListener('pointerdown', () => active && close(active), true);
    window.addEventListener('resize', () => active && position(active));
    window.addEventListener('scroll', () => active && close(active), true);

    function boot() {
        promoteTree(document);
        new MutationObserver(records => records.forEach(record => {
            if (record.type === 'attributes') promote(record.target);
            record.addedNodes.forEach(node => promoteTree(node));
        })).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['title', 'data-tooltip'] });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
})();

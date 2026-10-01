/* ==========================================================================
   MASTER ALERT — shared custom alert engine (toasts + dialogs)
   window.MasterAlert.toast(message, type, options)
   window.MasterAlert.confirm(message, options)  -> Promise<boolean>
   window.MasterAlert.alert(message, options)    -> Promise<void>
   Also handles <form data-confirm="message"> natively (no inline JS needed).
   ========================================================================== */
(function () {
    'use strict';

    var SVG = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16v-4m0-4h.01M22 12a10 10 0 11-20 0 10 10 0 0120 0z"/></svg>',
        confirm: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M8.2 9.5a3.8 3.8 0 116.9 2.4c-1 1.1-2.1 1.7-2.1 3.1m0 3.5h.01"/></svg>',
        close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>'
    };

    function esc(value) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(value == null ? '' : value)));
        return div.innerHTML;
    }

    function el(cls, html) {
        var node = document.createElement('div');
        if (cls) node.className = cls;
        if (html != null) node.innerHTML = html;
        return node;
    }

    /* ---------------- Toasts ---------------- */

    var stack = null;

    function ensureStack() {
        if (!stack || !document.body.contains(stack)) {
            stack = el('ma-stack');
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }
        return stack;
    }

    function toast(message, type, options) {
        type = type || 'success';
        options = options || {};
        var timer = options.timer != null ? options.timer : 3200;
        var title = options.title || null;

        var box = document.createElement('div');
        box.className = 'ma-toast ma-' + type;
        box.setAttribute('role', type === 'error' ? 'alert' : 'status');

        var icon = el('ma-toast-icon');
        icon.innerHTML = SVG[type] || SVG.info;
        box.appendChild(icon);

        var body = el('ma-toast-body');
        var html = '';
        if (title) html += '<span class="ma-toast-title">' + esc(title) + '</span>';
        html += '<span class="ma-toast-msg">' + (options.html ? String(message) : esc(message)) + '</span>';
        body.innerHTML = html;
        box.appendChild(body);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'ma-toast-close';
        close.setAttribute('aria-label', 'Dismiss');
        close.innerHTML = SVG.close;
        box.appendChild(close);

        var progress = el('ma-toast-progress');
        progress.style.animationDuration = timer + 'ms';
        box.appendChild(progress);

        var done = false;
        function dismiss() {
            if (done) return;
            done = true;
            window.clearTimeout(handle);
            box.classList.add('ma-leaving');
            window.setTimeout(function () {
                if (box.parentNode) box.parentNode.removeChild(box);
                if (stack && !stack.children.length && stack.parentNode) {
                    stack.parentNode.removeChild(stack);
                    stack = null;
                }
            }, 210);
        }

        var handle = window.setTimeout(dismiss, timer);
        close.addEventListener('click', dismiss);
        box.addEventListener('click', function (e) {
            if (e.target === box || e.target === body) dismiss();
        });

        ensureStack().appendChild(box);
        return box;
    }

    /* ---------------- Dialogs ---------------- */

    var openOverlay = null;
    var openResolver = null;

    function closeOverlay(result) {
        if (!openOverlay) return;
        var overlay = openOverlay;
        openOverlay = null;
        var resolver = openResolver;
        openResolver = null;
        overlay.classList.add('ma-leaving');
        window.setTimeout(function () {
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        }, 170);
        if (resolver) resolver(result);
    }

    function dialog(options) {
        options = options || {};
        // Replace any dialog already open (resolve it as cancelled).
        if (openOverlay) closeOverlay(false);

        var type = options.type || 'info';
        var icon = options.icon || (type === 'confirm' ? 'confirm' : type);
        var title = options.title || 'Are you sure?';
        var message = options.message != null ? options.message : '';
        var confirmText = options.confirmText || 'OK';
        var cancelText = options.cancelText != null ? options.cancelText : null;
        var danger = options.danger === true;

        var overlay = el('ma-overlay');
        var dialog = document.createElement('div');
        dialog.className = 'ma-dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'maDialogTitle');

        var iconBox = el('ma-dialog-icon ma-' + icon);
        iconBox.innerHTML = SVG[icon] || SVG.info;
        dialog.appendChild(iconBox);

        var titleEl = el('ma-dialog-title');
        titleEl.id = 'maDialogTitle';
        titleEl.textContent = title;
        dialog.appendChild(titleEl);

        var msgEl = el('ma-dialog-msg');
        if (message) msgEl.innerHTML = options.html ? String(message) : esc(message);
        dialog.appendChild(msgEl);

        var actions = el('ma-dialog-actions');
        var confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.className = 'ma-btn ' + (danger ? 'ma-btn-danger' : 'ma-btn-primary');
        confirmBtn.textContent = confirmText;
        actions.appendChild(confirmBtn);

        var cancelBtn = null;
        if (cancelText != null) {
            cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'ma-btn ma-btn-ghost';
            cancelBtn.textContent = cancelText;
            actions.appendChild(cancelBtn);
        }
        dialog.appendChild(actions);
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);

        openOverlay = overlay;

        return new Promise(function (resolve) {
            openResolver = resolve;
            confirmBtn.focus();
            confirmBtn.addEventListener('click', function () { closeOverlay(true); });
            if (cancelBtn) cancelBtn.addEventListener('click', function () { closeOverlay(false); });
            overlay.addEventListener('mousedown', function (e) {
                if (e.target === overlay) closeOverlay(false);
            });
            document.addEventListener('keydown', function onKey(e) {
                if (e.key === 'Escape') {
                    document.removeEventListener('keydown', onKey);
                    closeOverlay(false);
                }
            });
        });
    }

    function confirmDialog(message, options) {
        options = options || {};
        options.message = message;
        if (!options.title) options.title = 'Are you sure?';
        if (!options.type) options.type = 'confirm';
        if (options.danger == null) options.danger = true;
        if (!options.confirmText) options.confirmText = 'Confirm';
        if (options.cancelText == null) options.cancelText = 'Cancel';
        return dialog(options);
    }

    function alertDialog(message, options) {
        options = options || {};
        options.message = message;
        if (!options.title) options.title = options.type === 'error' ? 'Error' : 'Notice';
        if (!options.type) options.type = 'info';
        if (!options.confirmText) options.confirmText = 'OK';
        options.cancelText = null;
        return dialog(options);
    }

    /* ---------------- data-confirm forms ---------------- */

    document.addEventListener('submit', function (event) {
        var form = event.target && event.target.closest ? event.target.closest('form[data-confirm]') : null;
        if (!form) return;
        event.preventDefault();
        confirmDialog(
            form.getAttribute('data-confirm') || 'Are you sure?',
            {
                title: form.getAttribute('data-confirm-title') || 'Are you sure?',
                confirmText: form.getAttribute('data-confirm-text') || 'Delete',
                danger: form.hasAttribute('data-confirm-danger') || true
            }
        ).then(function (ok) {
            if (ok) form.submit();
        });
    });

    /* ---------------- data-confirm buttons (submit without form) ---------------- */

    document.addEventListener('click', function (event) {
        var btn = event.target && event.target.closest ? event.target.closest('button[data-confirm]') : null;
        if (!btn) return;
        if (btn.hasAttribute('data-ma-confirmed')) {
            btn.removeAttribute('data-ma-confirmed');
            return; // re-entry after confirm — let the click through
        }
        event.preventDefault();
        confirmDialog(
            btn.getAttribute('data-confirm') || 'Are you sure?',
            {
                title: btn.getAttribute('data-confirm-title') || 'Are you sure?',
                confirmText: btn.getAttribute('data-confirm-text') || 'Delete',
                danger: btn.hasAttribute('data-confirm-danger') || true
            }
        ).then(function (ok) {
            if (!ok) return;
            btn.setAttribute('data-ma-confirmed', '1');
            btn.click();
        });
    });

    /* ---------------- shared clipboard helper ---------------- */

    window.maCopy = function (valueOrId, title) {
        var value = valueOrId;
        if (typeof valueOrId === 'string' && document.getElementById(valueOrId)) {
            var node = document.getElementById(valueOrId);
            value = node.value != null ? node.value : node.textContent;
        }
        value = String(value == null ? '' : value);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(function () {
                toast('Copied to clipboard.', 'success', { title: title || 'Copied' });
            }).catch(function () {
                alertDialog(value, { title: (title || 'Copy') + ' — select and copy manually', type: 'info' });
            });
        } else {
            alertDialog(value, { title: (title || 'Copy') + ' — select and copy manually', type: 'info' });
        }
    };

    /* ---------------- Public API ---------------- */

    window.MasterAlert = {
        toast: toast,
        confirm: confirmDialog,
        alert: alertDialog
    };
})();

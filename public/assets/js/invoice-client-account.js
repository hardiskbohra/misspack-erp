/* Read-only, fresh client account position. Does not write invoice fields. */
(function () {
    'use strict';
    function init() {
        var select = document.getElementById('clientSelect');
        var panel = document.querySelector('[data-invoice-client-account]');
        if (!select || !panel) return;
        var content = panel.querySelector('[data-client-account-content]');
        var status = panel.querySelector('[data-client-account-status]');
        var message = panel.querySelector('[data-client-account-message]');
        var retry = panel.querySelector('[data-client-account-retry]');
        var request = null;
        var sequence = 0;
        async function refresh() {
            var version = ++sequence;
            if (request) request.abort();
            content.replaceChildren();
            retry.hidden = true;
            if (!select.value) {
                panel.hidden = true;
                panel.removeAttribute('aria-busy');
                return;
            }
            var clientId = select.value;
            request = new AbortController();
            panel.hidden = false;
            panel.setAttribute('aria-busy', 'true');
            status.hidden = false;
            message.textContent = 'Loading client payment summary…';
            try {
                var response = await fetch(panel.dataset.summaryUrl.replace('__CLIENT__', encodeURIComponent(clientId)), {
                    credentials: 'same-origin', cache: 'no-store', signal: request.signal,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok || response.redirected) throw new Error('Account summary unavailable');
                var data = await response.json();
                if (version !== sequence || select.value !== clientId) return;
                if (String(data.client_id) !== clientId || typeof data.html !== 'string') throw new Error('Unexpected account response');
                // This is the same escaped Blade partial used on the client profile.
                content.innerHTML = data.html;
                status.hidden = true;
            } catch (error) {
                if (version !== sequence) return;
                message.textContent = 'Payment summary unavailable. Your invoice details have not changed.';
                retry.hidden = false;
            } finally {
                if (version === sequence) panel.removeAttribute('aria-busy');
            }
        }
        if (window.jQuery) window.jQuery(select).on('change.clientAccount', refresh);
        else select.addEventListener('change', refresh);
        retry.addEventListener('click', refresh);
        refresh(); // Includes preselected clients, edits and validation recovery.
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

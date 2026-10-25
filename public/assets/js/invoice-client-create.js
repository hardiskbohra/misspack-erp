/* Inline client creation owns only its dialog. The invoice's existing change
   handler owns snapshots, Select2 updates and automatic GST selection. */
(() => {
    'use strict';
    function init() {
        const opener = document.querySelector('[data-create-invoice-client]');
        const modal = document.getElementById('invoiceClientModal');
        const select = document.getElementById('clientSelect');
        if (!opener || !modal || !select || !window.MasterModal) return;
        const form = modal.querySelector('[data-invoice-client-form]');
        const button = form.querySelector('[data-save-invoice-client]');
        const error = form.querySelector('[data-client-create-error]');
        const status = document.querySelector('[data-invoice-client-status]');
        const sameAddress = form.querySelector('[data-client-same-address]');
        const shipping = form.querySelector('[data-client-shipping-fields]');
        const buttonText = button.textContent;
        let busy = false;
        let focusAfterClose = opener;

        function syncShipping() {
            shipping.hidden = sameAddress.checked;
            shipping.disabled = sameAddress.checked;
        }
        sameAddress.addEventListener('change', syncShipping);
        syncShipping();

        function clearErrors() {
            error.hidden = true;
            error.textContent = '';
            form.querySelectorAll('[aria-invalid]').forEach(field => field.removeAttribute('aria-invalid'));
            form.querySelectorAll('[data-client-field-error]').forEach(note => {
                note.hidden = true;
                note.textContent = '';
            });
        }
        function showError(message) {
            error.textContent = message;
            error.hidden = false;
        }
        opener.addEventListener('click', () => {
            focusAfterClose = opener;
            window.MasterModal.open(modal);
            form.elements.company_name.focus();
        });
        // The shared lifecycle handles Cancel, backdrop and Escape. Restore
        // focus whichever of those paths closed this particular dialog.
        new MutationObserver(() => {
            if (!modal.classList.contains('open')) focusAfterClose.focus();
        }).observe(modal, { attributes: true, attributeFilter: ['class'] });
        modal.addEventListener('keydown', event => {
            if (event.key !== 'Tab') return;
            const fields = [...modal.querySelectorAll('button, input, textarea, select, [tabindex]')]
                .filter(field => !field.disabled && field.tabIndex >= 0 && field.getClientRects().length);
            const first = fields[0], last = fields[fields.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); first.focus();
            }
        });

        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy || !form.reportValidity()) return;
            busy = true;
            button.disabled = true;
            button.textContent = 'Saving client…';
            form.setAttribute('aria-busy', 'true');
            clearErrors();
            try {
                const response = await fetch(form.action, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form)
                });
                const data = (response.headers.get('content-type') || '').includes('application/json')
                    ? await response.json() : {};
                if (response.status === 422) {
                    showError('Please correct the client details below. Your invoice has not changed.');
                    Object.entries(data.errors || {}).forEach(([name, messages]) => {
                        [...form.elements].filter(field => field.name === name && field.type !== 'hidden').forEach(field => {
                            field.setAttribute('aria-invalid', 'true');
                            const note = document.getElementById(field.id + 'Error');
                            if (note) {
                                note.textContent = Array.isArray(messages) ? messages.join(' ') : String(messages);
                                note.hidden = false;
                            }
                        });
                    });
                    (form.querySelector('[aria-invalid="true"]') || error).focus();
                    return;
                }
                if (response.status === 401 || response.status === 419 || response.redirected) {
                    throw new Error('Your session may have expired. Keep this invoice open, sign in in another tab, then try again.');
                }
                if (response.status === 403) throw new Error('You do not have permission to create clients. Your invoice has not changed.');
                if (!response.ok || !data.client?.id || !data.client.snapshot) {
                    throw new Error('Could not confirm that the client was saved. Check the client list before retrying. Your invoice entries are still here.');
                }
                const client = data.client;
                let option = [...select.options].find(row => row.value === String(client.id));
                if (!option) {
                    option = new Option(client.company_name, String(client.id));
                    select.add(option);
                }
                option.textContent = client.company_name;
                option.dataset.snapshot = JSON.stringify(client.snapshot);
                select.value = String(client.id);
                // Select2 announces changes via jQuery, not a native DOM event.
                if (window.jQuery && window.jQuery.fn) window.jQuery(select).trigger('change');
                else select.dispatchEvent(new Event('change', { bubbles: true }));
                status.textContent = client.company_name + ' saved and selected. Contact and address details are ready below.';
                status.hidden = false;
                focusAfterClose = select.nextElementSibling?.querySelector('.select2-selection') || select;
                window.MasterModal.close(modal);
                form.reset();
                syncShipping();
            } catch (failure) {
                showError(failure instanceof TypeError
                    ? 'Could not confirm that the client was saved. Check your connection and the client list before retrying. Your invoice entries are still here.'
                    : failure.message);
            } finally {
                busy = false;
                button.disabled = false;
                button.textContent = buttonText;
                form.removeAttribute('aria-busy');
            }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

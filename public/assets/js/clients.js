/* Client directory interactions: accessible dialogs and safe link copying. */
document.addEventListener('DOMContentLoaded', function () {
    if (window.MasterList) {
        window.MasterList.rowNavigation({ root: '.client-index' });
        window.MasterList.gridShadow({ root: '.client-index' });
        window.MasterList.saveViewToggle();
    }

    const quickModal = document.getElementById('quickClientModal');
    const deleteModal = document.getElementById('deleteClientModal');
    const deleteForm = document.getElementById('deleteClientForm');
    const deleteDescription = document.getElementById('deleteClientDesc');
    let activeModal = null;
    let returnFocusTo = null;

    const focusableIn = (modal) => Array.from(modal?.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    ) || []).filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true');

    function openModal(modal, trigger, initialFocus) {
        if (!modal) return;
        if (activeModal && activeModal !== modal) closeModal(activeModal, false);
        returnFocusTo = trigger || document.activeElement;
        activeModal = modal;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('master-modal-open');
        window.requestAnimationFrame(() => {
            const target = initialFocus || focusableIn(modal)[0] || modal.querySelector('[role="dialog"]');
            target?.focus();
        });
    }

    function closeModal(modal, restoreFocus = true) {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        if (activeModal === modal) activeModal = null;
        document.body.classList.toggle('master-modal-open', Boolean(activeModal));
        if (restoreFocus && returnFocusTo?.isConnected) returnFocusTo.focus();
        if (restoreFocus) returnFocusTo = null;
    }

    document.getElementById('openQuickClientModal')?.addEventListener('click', function () {
        openModal(quickModal, this, document.getElementById('quick_company_name'));
    });

    ['closeQuickClientModal', 'cancelQuickClientModal'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', () => closeModal(quickModal));
    });

    document.querySelectorAll('.master-delete-btn').forEach((button) => {
        button.addEventListener('click', function () {
            if (deleteDescription) {
                deleteDescription.textContent = `Delete “${button.dataset.name || 'this client'}”? Related records may also be affected.`;
            }
            if (deleteForm) deleteForm.action = button.dataset.deleteUrl || '';
            openModal(deleteModal, button, document.getElementById('cancelDeleteClientModal'));
        });
    });

    ['closeDeleteClientModal', 'cancelDeleteClientModal'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', () => closeModal(deleteModal));
    });

    [quickModal, deleteModal].forEach((modal) => {
        modal?.addEventListener('click', (event) => {
            if (event.target === modal) closeModal(modal);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (!activeModal) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal(activeModal);
            return;
        }
        if (event.key !== 'Tab') return;

        const focusable = focusableIn(activeModal);
        if (!focusable.length) {
            event.preventDefault();
            activeModal.querySelector('[role="dialog"]')?.focus();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    async function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }

        const helper = document.createElement('textarea');
        helper.value = text;
        helper.setAttribute('readonly', '');
        helper.style.position = 'fixed';
        helper.style.opacity = '0';
        document.body.appendChild(helper);
        helper.select();
        const copied = document.execCommand('copy');
        helper.remove();
        if (!copied) throw new Error('Clipboard copy was not available.');
    }

    document.querySelectorAll('.client-copy-kyc').forEach((button) => {
        button.addEventListener('click', async function () {
            try {
                await copyText(button.dataset.kycUrl || '');
                window.MasterAlert?.toast('KYC link copied to clipboard.', 'success');
            } catch (error) {
                window.MasterAlert?.alert('Could not copy the KYC link. Open the client record to copy it manually.', {
                    title: 'Copy link', type: 'error'
                });
            }
        });
    });
});

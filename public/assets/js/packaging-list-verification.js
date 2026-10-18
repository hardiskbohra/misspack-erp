(() => {
  'use strict';

  const openModal = modal => {
    if (!modal || modal.open) return;
    if (typeof modal.showModal === 'function') modal.showModal();
    else modal.setAttribute('open', '');
    window.requestAnimationFrame(() => {
      modal.querySelector('input:not([type="hidden"]), textarea, select, button')?.focus();
    });
  };

  const closeModal = modal => {
    if (!modal) return;
    if (typeof modal.close === 'function' && modal.open) modal.close();
    else modal.removeAttribute('open');
  };

  document.addEventListener('click', event => {
    const opener = event.target.closest('[data-open-pl-modal]');
    if (opener) {
      openModal(document.getElementById(opener.dataset.openPlModal));
      return;
    }

    const closer = event.target.closest('[data-close-pl-modal]');
    if (closer) {
      closeModal(closer.closest('dialog'));
      return;
    }

    if (event.target.matches('dialog.pl-verification-modal')) closeModal(event.target);
  });

  document.querySelectorAll('dialog.pl-verification-modal[data-auto-open]').forEach(openModal);
})();

(() => {
  'use strict';
  const start = async () => {
    const button = document.querySelector('[data-print-labels]');
    const error = document.querySelector('[data-label-print-error]');
    if (!button || !error) return;
    const print = async () => {
      button.disabled = true;
      try {
        // qr.js renders synchronously at DOMContentLoaded, before this listener.
        // Wait for fonts and layout too, so the first printed batch is complete.
        if (document.fonts?.ready) await document.fonts.ready;
        // The sender's mark must be ready before a batch opens the print dialog.
        await Promise.all([...document.querySelectorAll('.label-brand-logo')].map(async image => {
          try {
            if (image.decode) await image.decode();
            else if (!image.complete) await new Promise((resolve, reject) => {
              image.addEventListener('load', resolve, { once: true });
              image.addEventListener('error', reject, { once: true });
            });
            if (!image.naturalWidth) throw new Error('Missing logo image');
          } catch (_) {
            throw new Error('The sender logo could not be loaded. Reload this page before printing.');
          }
        }));
        const targets = [...document.querySelectorAll('[data-ship-qr]')];
        if (!targets.length || targets.some(node => !node.querySelector('svg') || node.classList.contains('ship-qr-failed'))) {
          throw new Error('Some label QR codes could not be generated. Reload this page before printing.');
        }
        error.hidden = true;
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        window.print();
      } catch (failure) {
        error.textContent = failure.message || 'Labels could not be prepared for printing. Please reload and try again.';
        error.hidden = false;
      } finally {
        button.disabled = false;
      }
    };
    button.addEventListener('click', print);
    if (document.body.dataset.autoPrint === 'true') await print();
  };
  if (document.readyState === 'complete') start();
  else document.addEventListener('DOMContentLoaded', start);
})();

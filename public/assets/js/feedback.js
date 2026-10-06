/* ==========================================================================
   FEEDBACK — copy the link, copy the quote.

   The module works with JavaScript off: the form submits, the score is stored,
   the link can be selected by hand. This file only removes two bits of
   friction — copying a 48-character token and copying a consented quote — and
   it says so out loud when the clipboard is not available, rather than failing
   silently on a click the office believes worked.
   ========================================================================== */
(function () {
    'use strict';

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        /* http:// installs (a LAN address, an office laptop) have no clipboard
           API; the old selection trick still works there. */
        return new Promise(function (resolve, reject) {
            var field = document.createElement('textarea');
            field.value = text;
            field.setAttribute('readonly', 'readonly');
            field.style.position = 'fixed';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();

            try {
                document.execCommand('copy') ? resolve() : reject();
            } catch (error) {
                reject(error);
            } finally {
                document.body.removeChild(field);
            }
        });
    }

    /* The label changes for a moment and comes back: a button that says
       "Copied" for ever cannot be used twice. */
    function flash(button, text) {
        var original = button.getAttribute('data-feedback-label') || button.innerHTML;
        button.setAttribute('data-feedback-label', original);
        button.innerHTML = text;

        window.setTimeout(function () {
            button.innerHTML = button.getAttribute('data-feedback-label');
        }, 1600);
    }

    document.addEventListener('click', function (event) {
        var target = event.target.closest('[data-copy-target]');

        if (target) {
            event.preventDefault();
            var field = document.getElementById(target.getAttribute('data-copy-target'));

            if (!field) {
                return;
            }

            copyText(field.value || field.textContent || '').then(function () {
                flash(target, 'Copied');
            }).catch(function () {
                field.focus();
                field.select();
                flash(target, 'Press Ctrl+C');
            });

            return;
        }

        var quote = event.target.closest('[data-copy-quote]');

        if (quote) {
            event.preventDefault();

            copyText(quote.getAttribute('data-quote') || '').then(function () {
                flash(quote, 'Copied');
            }).catch(function () {
                flash(quote, 'Select the text');
            });
        }
    });

    /* The list offers the three density presets in its toolbar, so the shared
       toolkit binds them here — one switch for every list on the ERP, and the
       choice is remembered on the device like the others. */
    if (window.MasterList) {
        window.MasterList.density({ root: '.fb-index', key: 'misspack.feedback.density' });
    }

    /* A low score on the public form highlights the "we will call you" note, so
       the promise is beside the answer that triggers it. The note is always on
       the page; this only draws the eye. */
    var form = document.querySelector('.fb-form');

    if (form) {
        form.addEventListener('change', function (event) {
            if (!event.target.name || event.target.name.indexOf('overall_rating') !== 0) {
                return;
            }

            var note = document.getElementById('fb-branch-fix');
            var low = parseInt(event.target.value, 10) <= 2;

            if (note) {
                note.style.borderLeftColor = low ? 'var(--ui-danger, #ef4770)' : '';
                note.style.background = low ? '#fff7f9' : '';
            }
        });
    }
})();

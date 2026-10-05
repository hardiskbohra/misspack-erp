/* Public client KYC — four-step form, local draft, step checks, submit confirm. */
(function () {
    'use strict';

    var GSTIN = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i;
    var PAN = /^[A-Z]{5}[0-9]{4}[A-Z]$/i;
    var IFSC = /^[A-Z]{4}0[A-Z0-9]{6}$/i;

    function toastAll() {
        (window.kycToast || []).forEach(function (t) {
            if (!window.MasterAlert) return;
            if (t.type === 'success') {
                MasterAlert.toast(t.message, 'success', { title: 'Saved' });
            } else {
                MasterAlert.alert(t.message, { title: 'Cannot continue', type: 'error', danger: true });
            }
        });
    }

    function setError(field, message) {
        var wrap = field.closest('.kyc-field');
        if (!wrap) return;
        wrap.classList.add('has-error');
        var node = wrap.querySelector('[data-live-error]');
        if (!node) {
            node = document.createElement('p');
            node.className = 'kyc-field-error';
            node.setAttribute('data-live-error', '1');
            wrap.appendChild(node);
        }
        node.textContent = message;
        field.setAttribute('aria-invalid', 'true');
    }

    function clearError(field) {
        var wrap = field.closest('.kyc-field');
        if (!wrap) return;
        wrap.classList.remove('has-error');
        wrap.querySelectorAll('.kyc-field-error').forEach(function (node) { node.remove(); });
        field.removeAttribute('aria-invalid');
    }

    function hideErrorBannerIfClean(form) {
        var banner = document.querySelector('[data-kyc-error-banner]');
        if (banner && !form.querySelector('.kyc-field.has-error')) {
            banner.hidden = true;
        }
    }

    function valueOf(field) {
        return (field.value || '').trim();
    }

    document.addEventListener('DOMContentLoaded', function () {
        toastAll();

        var form = document.querySelector('[data-kyc-form]');
        if (!form) return;

        var panels = Array.prototype.slice.call(form.querySelectorAll('[data-kyc-panel]'));
        var steps = Array.prototype.slice.call(document.querySelectorAll('[data-kyc-goto]'));
        var prevBtn = form.querySelector('[data-kyc-prev]');
        var nextBtn = form.querySelector('[data-kyc-next]');
        var submitBtn = form.querySelector('[data-kyc-submit]');
        var draftBtn = form.querySelector('[data-kyc-draft]');
        var hint = form.querySelector('[data-kyc-hint]');
        var intent = document.getElementById('kycIntent');
        var sameShip = form.querySelector('[data-kyc-same-ship]');
        var shipBox = form.querySelector('[data-kyc-shipping]');
        var review = form.querySelector('[data-kyc-review]');
        var readonly = form.getAttribute('data-kyc-readonly') === '1';
        var token = form.getAttribute('data-kyc-token') || '';
        var storageKey = 'misspack.kyc.' + token;
        var total = panels.length;
        var current = 1;
        var sending = false;

        function panel(n) {
            return form.querySelector('[data-kyc-panel="' + n + '"]');
        }

        function fieldsIn(n) {
            return Array.prototype.slice.call(panel(n).querySelectorAll('input, select, textarea'))
                .filter(function (el) { return !el.disabled && el.type !== 'hidden' && el.type !== 'checkbox'; });
        }

        function persist() {
            if (readonly || !window.localStorage) return;
            var data = { step: current, values: {} };
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!el.name || el.type === 'hidden' && el.name === '_token') return;
                if (el.type === 'checkbox') {
                    data.values[el.name] = el.checked;
                } else if (el.name !== 'intent' && el.name !== '_token') {
                    data.values[el.name] = el.value;
                }
            });
            try { localStorage.setItem(storageKey, JSON.stringify(data)); } catch (e) {}
        }

        function restore() {
            if (readonly || !window.localStorage) return;
            if (form.getAttribute('data-kyc-error-step')) return;
            var raw = localStorage.getItem(storageKey);
            if (!raw) return;
            try {
                var data = JSON.parse(raw);
                Object.keys(data.values || {}).forEach(function (name) {
                    var el = form.elements[name];
                    if (!el) return;
                    if (el.type === 'checkbox') {
                        el.checked = Boolean(data.values[name]);
                    } else if (!el.value) {
                        el.value = data.values[name];
                    }
                });
            } catch (e) {}
        }

        function clearStepErrors(n) {
            fieldsIn(n).forEach(clearError);
            var btn = document.querySelector('[data-kyc-goto="' + n + '"]');
            if (btn) btn.classList.remove('is-error');
            hideErrorBannerIfClean(form);
        }

        function show(n) {
            current = Math.min(Math.max(n, 1), total);
            panels.forEach(function (p) {
                var on = Number(p.getAttribute('data-kyc-panel')) === current;
                p.hidden = !on;
                p.classList.toggle('is-current', on);
            });
            steps.forEach(function (btn) {
                var i = Number(btn.getAttribute('data-kyc-goto'));
                btn.classList.toggle('is-current', i === current);
                btn.classList.toggle('is-done', i < current);
            });
            if (prevBtn) prevBtn.hidden = current === 1;
            if (nextBtn) nextBtn.hidden = current === total;
            if (submitBtn) submitBtn.hidden = current !== total || readonly;
            if (hint) hint.textContent = 'Step ' + current + ' of ' + total;
            if (current === total) fillReview();
            history.replaceState(null, '', '#step-' + current);
            var first = fieldsIn(current).find(function (el) { return !el.readOnly; });
            if (first) first.focus({ preventScroll: true });
            persist();
        }

        function validateField(field) {
            clearError(field);
            var name = field.name;
            var val = valueOf(field);

            if (field.required && !val) {
                setError(field, 'This field is required.');
                return false;
            }
            if (field.type === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                setError(field, 'That email does not look valid.');
                return false;
            }
            if (name === 'gstin' && val && !GSTIN.test(val)) {
                setError(field, 'GSTIN should look like 24AAAAA0000A1Z5.');
                return false;
            }
            if (name === 'pan' && val && !PAN.test(val)) {
                setError(field, 'PAN should look like ABCDE1234F.');
                return false;
            }
            if (name === 'ifsc_code' && val && !IFSC.test(val)) {
                setError(field, 'IFSC should look like HDFC0001234.');
                return false;
            }
            if ((name === 'ceo_contact' || name.slice(-8) === '_contact') && val && val.replace(/\D/g, '').length < 8) {
                setError(field, 'Enter a phone number with at least 8 digits.');
                return false;
            }
            return true;
        }

        function validateStep(n) {
            if (readonly) return true;
            var ok = true;
            var firstBad = null;
            fieldsIn(n).forEach(function (field) {
                if (!validateField(field) && !firstBad) firstBad = field;
                if (field.closest('.kyc-field') && field.closest('.kyc-field').classList.contains('has-error')) ok = false;
            });
            if (ok) clearStepErrors(n);
            if (firstBad) firstBad.focus();
            return ok;
        }

        function fillReview() {
            if (!review) return;
            var rows = [
                ['Company', 'company_name'],
                ['CEO / Director', 'ceo_name'],
                ['Email', 'ceo_email'],
                ['Phone', 'ceo_contact'],
                ['Billing city', 'billing_city'],
                ['GSTIN', 'gstin'],
                ['PAN', 'pan']
            ];
            var html = '<h3>Check before you send</h3><dl>';
            rows.forEach(function (row) {
                var el = form.elements[row[1]];
                var val = el ? valueOf(el) : '';
                var missing = el && el.required && !val;
                html += '<dt>' + row[0] + '</dt><dd class="' + (missing ? 'is-missing' : '') + '">'
                    + (val || 'Not filled') + '</dd>';
            });
            html += '</dl>';
            review.innerHTML = html;
            review.classList.add('is-on');
        }

        function syncShipping() {
            if (!sameShip || !shipBox) return;
            var on = sameShip.checked;
            shipBox.hidden = on;
            ['shipping_address', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_pincode'].forEach(function (name) {
                var el = form.elements[name];
                var src = form.elements[name.replace('shipping_', 'billing_')];
                if (!el) return;
                el.disabled = on;
                if (on && src) el.value = src.value;
            });
        }

        form.querySelectorAll('[data-kyc-upper]').forEach(function (el) {
            el.addEventListener('blur', function () {
                el.value = el.value.toUpperCase();
                validateField(el);
            });
        });

        fieldsIn(1).concat(fieldsIn(2), fieldsIn(3), fieldsIn(4)).forEach(function (field) {
            field.addEventListener('blur', function () { validateField(field); persist(); hideErrorBannerIfClean(form); });
            field.addEventListener('input', function () {
                if (field.closest('.kyc-field') && field.closest('.kyc-field').classList.contains('has-error')) {
                    validateField(field);
                }
                persist();
                hideErrorBannerIfClean(form);
            });
        });

        sameShip && sameShip.addEventListener('change', function () { syncShipping(); persist(); });

        steps.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = Number(btn.getAttribute('data-kyc-goto'));
                if (!readonly) {
                    for (var i = current; i < target; i++) {
                        if (!validateStep(i)) {
                            show(i);
                            return;
                        }
                    }
                }
                show(target);
            });
        });

        nextBtn && nextBtn.addEventListener('click', function () {
            if (!validateStep(current)) return;
            clearStepErrors(current);
            show(current + 1);
        });

        prevBtn && prevBtn.addEventListener('click', function () { show(current - 1); });

        draftBtn && draftBtn.addEventListener('click', function () {
            if (sending) return;
            intent.value = 'draft';
            persist();
            sending = true;
            form.submit();
        });

        form.addEventListener('submit', function (event) {
            if (sending && intent.value === 'draft') return;
            event.preventDefault();
            if (readonly) return;

            for (var i = 1; i <= total; i++) {
                if (!validateStep(i)) {
                    show(i);
                    return;
                }
            }

            var go = function () {
                intent.value = 'submit';
                sending = true;
                try { localStorage.removeItem(storageKey); } catch (e) {}
                form.submit();
            };

            if (window.MasterAlert && MasterAlert.confirm) {
                MasterAlert.confirm(
                    'After this, the form is locked until MissPack reviews it or asks for a change.',
                    { title: 'Submit KYC for review?', confirmText: 'Yes, submit', cancelText: 'Keep editing', danger: false }
                ).then(function (ok) { if (ok) go(); });
            } else if (window.confirm('Submit KYC for review?')) {
                go();
            }
        });

        restore();
        syncShipping();

        var start = Number(form.getAttribute('data-kyc-error-step') || 0);
        var hash = Number((location.hash.match(/step-(\d+)/) || [])[1] || 0);
        show(start || hash || 1);
    });
})();

(function () {
    'use strict';

    var root = document.querySelector('[data-ob]');
    if (!root) return;

    var list = root.querySelector('[data-ob-list]');
    var modal = document.getElementById('officeBriefingModal');
    var badge = document.querySelector('[data-ob-badge]');
    var payload = {};
    var currentPopup = null;
    var token = document.querySelector('meta[name="csrf-token"]');
    token = token ? token.getAttribute('content') : '';

    try {
        payload = JSON.parse(root.getAttribute('data-ob-payload') || '{}');
    } catch (e) {
        payload = {};
    }

    function patch(url) {
        return fetch(url, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (res) { return res.json(); });
    }

    function urlFor(kind, id) {
        var inbox = root.getAttribute('data-ob-inbox') || '/office-alerts';
        return inbox.replace(/\/?$/, '') + '/' + id + '/' + kind;
    }

    function apply(next) {
        payload = next || payload;
        renderList();
        renderBadge();
    }

    function renderBadge() {
        if (!badge) return;
        var n = payload.unread || 0;
        badge.hidden = n === 0;
        badge.textContent = n > 9 ? '9+' : String(n);
        badge.classList.toggle('is-critical', (payload.critical || 0) > 0);
    }

    function renderList() {
        if (!list) return;
        var items = payload.items || [];
        if (!items.length) {
            list.innerHTML = '<p class="ob-empty">Nothing waiting. New KYC, in-transit shipments and pending books will land here.</p>';
            return;
        }
        list.innerHTML = items.map(function (item) {
            return '<article class="ob-item is-' + item.severity + '">' +
                '<div class="ob-item-head">' +
                    '<span class="ob-pill">' + esc(item.severity_label) + '</span>' +
                    (item.team_label ? '<span class="ob-team">' + esc(item.team_label) + '</span>' : '') +
                    '<span class="ob-when">' + esc(item.when || '') + '</span>' +
                '</div>' +
                '<h3>' + esc(item.title) + '</h3>' +
                '<p>' + esc(item.body) + '</p>' +
                '<div class="ob-item-actions">' +
                    (item.action_url ? '<a class="master-btn master-btn-soft master-btn-sm" href="' + esc(item.action_url) + '">' + esc(item.action_label) + '</a>' : '') +
                    '<button type="button" class="master-btn master-btn-primary master-btn-sm" data-ob-' +
                        (item.requires_ack ? 'ack' : 'seen') + '="' + item.id + '">' +
                        (item.requires_ack ? 'Mark read for the office' : 'Got it') +
                    '</button>' +
                '</div>' +
            '</article>';
        }).join('');
    }

    function esc(value) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(value == null ? '' : value)));
        return div.innerHTML;
    }

    function openPanel() {
        root.hidden = false;
        document.body.classList.add('ob-open');
    }

    function closePanel() {
        root.hidden = true;
        document.body.classList.remove('ob-open');
    }

    function showPopup(item) {
        if (!modal || !item) return;
        currentPopup = item;
        modal.querySelector('[data-ob-modal-title]').textContent = item.title;
        modal.querySelector('[data-ob-modal-body]').textContent = item.body;
        var team = modal.querySelector('[data-ob-modal-team]');
        team.textContent = (item.severity_label || '') + (item.team_label ? ' · ' + item.team_label : '');
        var link = modal.querySelector('[data-ob-modal-link]');
        if (item.action_url) {
            link.hidden = false;
            link.href = item.action_url;
            link.textContent = item.action_label || 'Open';
        } else {
            link.hidden = true;
        }
        var ack = modal.querySelector('[data-ob-modal-ack]');
        ack.textContent = item.requires_ack ? 'Mark read for the office' : 'Got it';
        if (window.MasterModal) window.MasterModal.open(modal);
        else {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function hidePopup() {
        currentPopup = null;
        if (window.MasterModal) window.MasterModal.close(modal);
        else {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    function handle(kind, id) {
        return patch(urlFor(kind, id)).then(function (next) {
            apply(next);
            if (currentPopup && String(currentPopup.id) === String(id)) hidePopup();
            if (next.popup && (!currentPopup || String(next.popup.id) !== String(id))) {
                showPopup(next.popup);
            }
        });
    }

    document.querySelectorAll('[data-ob-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openPanel();
        });
    });

    root.querySelectorAll('[data-ob-close]').forEach(function (btn) {
        btn.addEventListener('click', closePanel);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !root.hidden) closePanel();
    });

    root.addEventListener('click', function (event) {
        var ack = event.target.closest('[data-ob-ack]');
        var seen = event.target.closest('[data-ob-seen]');
        if (ack) handle('ack', ack.getAttribute('data-ob-ack'));
        if (seen) handle('seen', seen.getAttribute('data-ob-seen'));
    });

    if (modal) {
        modal.querySelector('[data-ob-modal-ack]').addEventListener('click', function () {
            if (!currentPopup) return;
            handle(currentPopup.requires_ack ? 'ack' : 'seen', currentPopup.id);
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal && currentPopup && currentPopup.requires_ack) {
                event.stopPropagation();
            }
        }, true);
    }

    apply(payload);

    (payload.toasts || []).forEach(function (item) {
        if (window.MasterAlert) {
            window.MasterAlert.toast(item.body, 'info', { title: item.title, timer: 6000 });
        }
        handle('seen', item.id);
    });

    if (payload.popup) {
        showPopup(payload.popup);
    }
})();

/* Global office search: one box, every module, grouped suggestions. */
(function () {
    'use strict';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text || ''));
        return div.innerHTML;
    }

    onReady(function () {
        var root = document.querySelector('[data-gs]');
        if (!root) return;

        var input = document.getElementById('globalSearchInput');
        var results = root.querySelector('[data-gs-results]');
        var url = root.getAttribute('data-gs-url') || '/search';
        var timer = null;
        var seq = 0;
        var active = -1;
        var links = [];

        function open() {
            root.hidden = false;
            document.body.classList.add('gs-open');
            window.setTimeout(function () { input && input.focus(); }, 20);
        }

        function close() {
            root.hidden = true;
            document.body.classList.remove('gs-open');
            active = -1;
        }

        function setActive(index) {
            links = Array.prototype.slice.call(root.querySelectorAll('.gs-hit'));
            if (!links.length) {
                active = -1;
                return;
            }
            active = (index + links.length) % links.length;
            links.forEach(function (link, i) {
                link.classList.toggle('is-active', i === active);
            });
            links[active].scrollIntoView({ block: 'nearest' });
        }

        function render(data) {
            if (!results) return;

            if (!data.q || data.q.length < 2) {
                results.innerHTML = '<p class="gs-empty">Clients, vendors, products, projects, leads, invoices, cashflow, shipments, tasks, people and settings.</p>';
                return;
            }

            if (!data.total) {
                results.innerHTML = '<p class="gs-empty">No matches for “' + escapeHtml(data.q) + '”. Try a number, company name, or GSTIN.</p>';
                return;
            }

            results.innerHTML = data.groups.map(function (group) {
                var hits = group.results.map(function (hit) {
                    return '<a class="gs-hit" href="' + escapeHtml(hit.url) + '">'
                        + '<span class="gs-hit-title">' + escapeHtml(hit.title) + '</span>'
                        + (hit.subtitle ? '<span class="gs-hit-sub">' + escapeHtml(hit.subtitle) + '</span>' : '')
                        + '</a>';
                }).join('');

                return '<section class="gs-group">'
                    + '<h3><i class="' + escapeHtml(group.icon) + '" aria-hidden="true"></i> ' + escapeHtml(group.label) + '</h3>'
                    + hits
                    + '</section>';
            }).join('');

            active = -1;
        }

        function lookup(q) {
            var id = ++seq;
            results.innerHTML = '<p class="gs-empty">Searching…</p>';

            fetch(url + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) { return res.json(); }).then(function (data) {
                if (id !== seq) return;
                render(data);
            }).catch(function () {
                if (id !== seq) return;
                results.innerHTML = '<p class="gs-empty">Search could not run. Try again.</p>';
            });
        }

        document.querySelectorAll('[data-gs-open]').forEach(function (btn) {
            btn.addEventListener('click', open);
        });

        root.querySelectorAll('[data-gs-close]').forEach(function (el) {
            el.addEventListener('click', close);
        });

        input && input.addEventListener('input', function () {
            var q = input.value.trim();
            window.clearTimeout(timer);
            if (q.length < 2) {
                render({ q: q, total: 0, groups: [] });
                return;
            }
            timer = window.setTimeout(function () { lookup(q); }, 220);
        });

        document.addEventListener('keydown', function (event) {
            var typing = /^(INPUT|TEXTAREA|SELECT)$/.test((event.target && event.target.tagName) || '')
                || (event.target && event.target.isContentEditable);

            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                root.hidden ? open() : close();
                return;
            }

            if (!typing && event.key === '/' && root.hidden) {
                event.preventDefault();
                open();
                return;
            }

            if (root.hidden) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                close();
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive(active + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(active - 1);
            } else if (event.key === 'Enter' && active >= 0 && links[active]) {
                event.preventDefault();
                window.location.href = links[active].href;
            }
        });
    });
})();

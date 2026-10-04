/* Shared table behavior: row navigation, density presets, column preferences,
   pinned-grid shadows, and saved-view affordances. */
'use strict';

window.MasterList = (function () {
    var DENSITIES = ['comfortable', 'standard', 'compact'];
    var chooserSequence = 0;

    function normalizeDensity(value) {
        return DENSITIES.indexOf(value) >= 0 ? value : 'standard';
    }

    function densityButtons(root, selector) {
        if (root && root.querySelectorAll) return Array.prototype.slice.call(root.querySelectorAll(selector));
        return [];
    }

    function applyDensity(root, value, buttons) {
        var next = normalizeDensity(value);
        root.setAttribute('data-density', next);
        buttons.forEach(function (button) {
            var active = button.getAttribute('data-density') === next;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function bindDensity(root, buttons, key) {
        if (!root || !buttons.length || root.getAttribute('data-density-bound') === 'true') return;
        root.setAttribute('data-density-bound', 'true');

        var stored = null;
        try {
            stored = window.localStorage.getItem(key);
        } catch (e) {
            /* Private mode: the buttons still work, the choice is not kept. */
        }

        applyDensity(root, stored || root.getAttribute('data-density'), buttons);
        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var value = normalizeDensity(button.getAttribute('data-density'));
                applyDensity(root, value, buttons);
                try {
                    window.localStorage.setItem(key, value);
                } catch (e) { /* Keep this choice for the current page. */ }
            });
        });
    }

    function density(options) {
        var root = typeof options.root === 'string' ? document.querySelector(options.root) : options.root;
        if (!root) return;
        var selector = options.buttons || '.master-list-density-btn';
        bindDensity(root, densityButtons(root, selector), options.key || 'misspack.table-density.default');
    }

    /* --------------------------------------------------- row navigation */

    function rowNavigation(options) {
        document.querySelectorAll(options.root + ' tbody tr[data-href]').forEach(function (row) {
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, select, textarea, label, form, .master-dropdown')) return;
                if (window.getSelection && String(window.getSelection()).length > 0) return;
                window.location.href = row.dataset.href;
            });
        });
    }

    /* ------------------------------------------------------ grid shadow */

    function gridShadow(options) {
        var wrap = document.querySelector(options.root + ' .master-table-wrap');
        if (!wrap) return;
        var sync = function () { wrap.classList.toggle('is-scrolled', wrap.scrollTop > 0); };
        wrap.addEventListener('scroll', sync, { passive: true });
        sync();
    }

    /* ------------------------------------------------------ saved views */

    function saveViewToggle(toggleId, formId) {
        var toggle = document.getElementById(toggleId || 'toggleSaveView');
        var form = document.getElementById(formId || 'saveViewForm');
        if (!toggle || !form) return;
        toggle.addEventListener('click', function () {
            form.hidden = !form.hidden;
            if (!form.hidden) {
                var name = form.querySelector('input[name="name"]');
                if (name) name.focus();
            }
        });
    }

    /* ---------------------------------------------------- column chooser */

    function columnLabel(header, index) {
        var declared = header.getAttribute('data-column-label');
        if (declared) return declared.trim();
        var clone = header.cloneNode(true);
        clone.querySelectorAll('input, button, i, svg, [aria-hidden="true"]').forEach(function (node) {
            node.remove();
        });
        var label = (clone.textContent || '').replace(/\s+/g, ' ').trim();
        return label || ('Column ' + (index + 1));
    }

    /* Keep each visible column's original share of the table, then normalize
       the remaining shares when preferences hide columns. This lets fixed
       colgroups fill their table again instead of leaving a blank strip at the
       right; restoring every column hands sizing back to the module sheet. */
    function captureColumnSizing(table, count) {
        var groups = Array.prototype.slice.call(table.children).filter(function (child) {
            return child.tagName === 'COLGROUP';
        });
        if (groups.length !== 1) return null;

        var columns = Array.prototype.slice.call(groups[0].children);
        if (columns.length !== count || columns.some(function (col) { return col.span !== 1; })) return null;

        var widths = columns.map(function (col) {
            var width = col.getBoundingClientRect().width;
            if (!(width > 0)) width = parseFloat(window.getComputedStyle(col).width) || 0;
            return width;
        });
        if (widths.some(function (width) { return !(width > 0); })) return null;

        var minWidth = parseFloat(window.getComputedStyle(table).minWidth) || 0;
        return {
            columns: columns,
            widths: widths,
            totalWidth: widths.reduce(function (total, width) { return total + width; }, 0),
            inlineWidths: columns.map(function (col) { return col.style.width; }),
            minWidth: minWidth,
            inlineMinWidth: table.style.minWidth,
        };
    }

    function applyColumnVisibility(table, columns, sizing) {
        Array.prototype.slice.call(table.rows).forEach(function (row) {
            var columnIndex = 0;
            Array.prototype.slice.call(row.cells).forEach(function (cell) {
                var span = Number(cell.getAttribute('data-column-original-span')) || cell.colSpan || 1;
                if (!cell.hasAttribute('data-column-original-span')) {
                    cell.setAttribute('data-column-original-span', String(span));
                }
                var visibleCount = 0;
                for (var offset = 0; offset < span; offset++) {
                    if (columnIndex + offset >= columns.length || columns[columnIndex + offset]) visibleCount++;
                }
                if (span > 1) cell.colSpan = Math.max(1, visibleCount);
                cell.toggleAttribute('data-column-hidden', visibleCount === 0);
                columnIndex += span;
            });
        });

        var groups = Array.prototype.slice.call(table.children).filter(function (child) {
            return child.tagName === 'COLGROUP';
        });
        groups.forEach(function (group) {
            var index = 0;
            Array.prototype.slice.call(group.children).forEach(function (col) {
                var span = Number(col.getAttribute('data-column-original-span')) || col.span || 1;
                if (!col.hasAttribute('data-column-original-span')) {
                    col.setAttribute('data-column-original-span', String(span));
                }
                var visibleCount = 0;
                for (var offset = 0; offset < span; offset++) {
                    if (index + offset >= columns.length || columns[index + offset]) visibleCount++;
                }
                col.toggleAttribute('data-column-hidden', visibleCount === 0);
                if (span > 1) col.span = Math.max(1, visibleCount);
                index += span;
            });
        });

        if (!sizing) return;

        var visibleWidth = sizing.widths.reduce(function (total, width, index) {
            return total + (columns[index] ? width : 0);
        }, 0);
        if (!(visibleWidth > 0)) return;

        var allVisible = columns.every(Boolean);
        sizing.columns.forEach(function (col, index) {
            if (allVisible) {
                col.style.width = sizing.inlineWidths[index];
            } else {
                col.style.width = columns[index]
                    ? ((sizing.widths[index] / visibleWidth) * 100).toFixed(4) + '%'
                    : '0px';
            }
        });

        table.style.minWidth = allVisible || !sizing.minWidth
            ? sizing.inlineMinWidth
            : Math.ceil(sizing.minWidth * visibleWidth / sizing.totalWidth) + 'px';
    }

    function preferenceKey(table, kind) {
        var tableKey = table.getAttribute(kind === 'columns' ? 'data-table-key' : 'data-table-density-key');
        if (!tableKey) {
            var root = table.closest('.master-list');
            tableKey = (root && (root.id || Array.prototype.slice.call(root.classList).filter(function (name) {
                return name !== 'master-list';
            }).join('-'))) || table.id || table.className || 'table';
            tableKey = window.location.pathname + '.' + tableKey;
        }
        return 'misspack.datatable.' + kind + '.' + tableKey;
    }

    function controlHost(table) {
        var listRoot = table.closest('.master-list');
        if (listRoot) {
            var actions = listRoot.querySelector('.master-list-toolbar-actions');
            if (actions) return actions;
        }

        var wrapper = table.closest('.master-table-wrap, .core-table-wrap, .cp-table-wrap, .cq-table-wrap');
        if (!wrapper || !wrapper.parentNode) return null;
        var toolbar = document.createElement('div');
        toolbar.className = 'core-table-toolbar desktop-only';
        toolbar.setAttribute('role', 'group');
        toolbar.setAttribute('aria-label', 'Table view options');
        wrapper.parentNode.insertBefore(toolbar, wrapper);
        return toolbar;
    }

    function createDensityControl(host, table) {
        var group = document.createElement('div');
        group.className = 'core-table-density';
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', 'Table density');

        DENSITIES.forEach(function (value) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'core-table-density-btn';
            button.setAttribute('data-density', value);
            button.setAttribute('aria-pressed', value === 'standard' ? 'true' : 'false');
            button.textContent = value.charAt(0).toUpperCase() + value.slice(1);
            group.appendChild(button);
        });

        host.appendChild(group);
        bindDensity(table, Array.prototype.slice.call(group.querySelectorAll('[data-density]')),
            preferenceKey(table, 'density'));
    }

    function createColumnChooser(table, host) {
        if (table.getAttribute('data-column-chooser-ready') === 'true') return;
        if (!table.tHead || table.tHead.rows.length !== 1) return;

        var headerCells = Array.prototype.slice.call(table.tHead.rows[0].cells);
        if (!headerCells.length || headerCells.some(function (cell) { return cell.colSpan !== 1; })) return;

        table.setAttribute('data-column-chooser-ready', 'true');
        chooserSequence++;
        var id = 'core-column-chooser-' + chooserSequence;
        var key = preferenceKey(table, 'columns');
        var columns = headerCells.map(function (header, index) {
            var label = columnLabel(header, index);
            var locked = header.hasAttribute('data-column-locked') || /^(actions?)$/i.test(label)
                || (label === 'Column ' + (index + 1) && index === headerCells.length - 1);
            return {
                label: label,
                locked: locked,
                defaultVisible: !header.hasAttribute('data-column-default-hidden'),
            };
        });
        var columnSizing = captureColumnSizing(table, columns.length);
        var defaults = columns.map(function (column) { return column.defaultVisible || column.locked; });
        var visibility = defaults.slice();

        try {
            var stored = JSON.parse(window.localStorage.getItem(key) || 'null');
            if (Array.isArray(stored) && stored.length === columns.length
                && stored.every(function (value) { return typeof value === 'boolean'; })) {
                visibility = stored.map(function (value, index) { return columns[index].locked || value; });
            }
        } catch (e) { /* Ignore unavailable or stale preferences. */ }

        var root = document.createElement('div');
        root.className = 'core-column-chooser desktop-only';
        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'master-btn master-btn-soft master-btn-sm core-column-chooser-trigger';
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-controls', id + '-panel');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.textContent = 'Columns';

        var panel = document.createElement('div');
        panel.className = 'core-column-chooser-panel';
        panel.id = id + '-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Choose visible columns');
        panel.setAttribute('aria-modal', 'false');
        panel.hidden = true;

        var panelHead = document.createElement('div');
        panelHead.className = 'core-column-chooser-head';
        var panelTitle = document.createElement('strong');
        panelTitle.textContent = 'Columns';
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'core-column-chooser-close';
        close.setAttribute('aria-label', 'Close column chooser');
        close.textContent = '×';
        panelHead.appendChild(panelTitle);
        panelHead.appendChild(close);

        var list = document.createElement('div');
        list.className = 'core-column-chooser-list';
        var checkboxes = [];
        columns.forEach(function (column, index) {
            var label = document.createElement('label');
            label.className = 'core-column-chooser-option';
            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = visibility[index];
            checkbox.disabled = column.locked;
            checkbox.setAttribute('aria-label', 'Show ' + column.label + ' column');
            var text = document.createElement('span');
            text.textContent = column.label;
            label.appendChild(checkbox);
            label.appendChild(text);
            if (column.locked) {
                var required = document.createElement('small');
                required.textContent = 'Required';
                label.appendChild(required);
            }
            list.appendChild(label);
            checkboxes.push(checkbox);
        });

        var panelFoot = document.createElement('div');
        panelFoot.className = 'core-column-chooser-foot';
        var reset = document.createElement('button');
        reset.type = 'button';
        reset.className = 'core-column-chooser-reset';
        reset.textContent = 'Restore defaults';
        panelFoot.appendChild(reset);

        panel.appendChild(panelHead);
        panel.appendChild(list);
        panel.appendChild(panelFoot);
        root.appendChild(trigger);
        root.appendChild(panel);
        host.appendChild(root);

        function save() {
            try {
                window.localStorage.setItem(key, JSON.stringify(visibility));
            } catch (e) { /* The table still updates for this page. */ }
            applyColumnVisibility(table, visibility, columnSizing);
        }

        function setOpen(open, focusFirst) {
            panel.hidden = !open;
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open && focusFirst) {
                var firstAvailable = checkboxes.find(function (checkbox) { return !checkbox.disabled; }) || close;
                firstAvailable.focus();
            } else if (!open) {
                trigger.focus();
            }
        }

        checkboxes.forEach(function (checkbox, index) {
            checkbox.addEventListener('change', function () {
                if (checkbox.disabled) return;
                visibility[index] = checkbox.checked;
                if (!visibility.some(Boolean)) {
                    visibility[index] = true;
                    checkbox.checked = true;
                }
                save();
            });
        });
        trigger.addEventListener('click', function () {
            setOpen(panel.hidden, panel.hidden);
        });
        close.addEventListener('click', function () { setOpen(false, false); });
        reset.addEventListener('click', function () {
            visibility = defaults.slice();
            checkboxes.forEach(function (checkbox, index) { checkbox.checked = visibility[index]; });
            try { window.localStorage.removeItem(key); } catch (e) {}
            applyColumnVisibility(table, visibility, columnSizing);
        });
        document.addEventListener('click', function (event) {
            if (!root.contains(event.target) && !panel.hidden) {
                panel.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
        panel.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                setOpen(false, false);
            }
        });

        applyColumnVisibility(table, visibility, columnSizing);
    }

    function tableSettings() {
        document.querySelectorAll('table[data-table-settings]:not([data-table-settings="off"])').forEach(function (table) {
            var host = controlHost(table);
            if (!host) return;

            var listRoot = table.closest('.master-list');
            var hasDensityButtons = listRoot && listRoot.querySelector('.master-list-density-btn');
            if (!hasDensityButtons && !host.querySelector('.core-table-density')) {
                createDensityControl(host, table);
            }
            createColumnChooser(table, host);
        });
    }

    function onReady(callback) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback);
        else callback();
    }

    onReady(tableSettings);

    return {
        density: density,
        rowNavigation: rowNavigation,
        gridShadow: gridShadow,
        saveViewToggle: saveViewToggle,
        tableSettings: tableSettings,
    };
})();

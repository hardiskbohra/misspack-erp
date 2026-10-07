/* ==========================================================================
   ASSETS DIALOG CHECK — the module's dialogs, run instead of read.

   --------------------------------------------------------------------------
   Run:  node tools/checks/assets-dialog-check.cjs

   Optional dependency: `jsdom` (`npm i jsdom`). Without it the file checks what
   it can statically, says plainly that the behaviour was not run, and exits 0 —
   the same bargain `mark-check.cjs` makes with `jsqr`.

   Why this file exists. There is no PHP runtime where this module was built, so
   every other check is a reading of the source: it can see that a name is passed
   and that a helper is written, but it cannot see that a lookup **returns null**.
   The dialog wiring is JavaScript, and JavaScript can be run — so the parts that
   are behaviour (which form a door points at, which verb it posts, what a save
   that failed comes back to) are executed here against a real DOM, and a
   regression in them fails a promise rather than a browser.

   It was written because of exactly that class of bug, three times over:

     * a dialog rendered by two pages, fed by lists only one of them passed;
     * a dialog that saves a record the form only half carried;
     * a reopen marker that spoke the modal's id while every door and form spoke
       the action's word — so `form[data-asset-form="assetEditModal"]` matched
       nothing and **no failed save ever reopened its dialog**. A grep sees a
       lookup; only running it sees the null.

   The fixtures below are not free inventions: each one names the view it stands
   for, and this file reads that view's own `data-asset-form` and `_dialog`
   before it runs anything, so a fixture cannot drift into describing a product
   that no longer exists.
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');
const exists = (file) => fs.existsSync(path.join(ROOT, file));
const has = (text, needle) => text.includes(needle);

let jsdom = null;
let jsdomNote = '';

try {
    /* Resolved from the repository first, then from wherever the runner put it
       (a sandbox may install it outside the checkout, as `mark-check.cjs` does
       with `jsqr`). */
    jsdom = require('jsdom');
} catch (e) {
    try {
        jsdom = require(path.join(process.env.JSDOM_PATH || '/tmp/dom/node_modules', 'jsdom'));
    } catch (e2) {
        jsdomNote = 'jsdom not installed — the dialogs were not run (npm i jsdom)';
    }
}

let passed = 0;
let failed = 0;
const failures = [];
const skipped = [];

const check = (name, ok, detail = '') => {
    if (ok) {
        passed++;
    } else {
        failed++;
        failures.push(`${name}${detail ? ` — ${detail}` : ''}`);
    }
};

/* ---------------------------------------------------------------- the files */

const files = {
    script: 'public/assets/js/assets.js',
    settings: 'resources/views/settings/assets.blade.php',
    index: 'resources/views/assets/index.blade.php',
    show: 'resources/views/assets/show.blade.php',
    editDialog: 'resources/views/assets/partials/modal-edit.blade.php',
    assetForm: 'resources/views/assets/partials/asset-form.blade.php',
    overview: 'resources/views/assets/partials/tab-overview.blade.php',
};

const missing = Object.entries(files).filter(([, file]) => !exists(file)).map(([key]) => key);
check('every file this check reads exists', missing.length === 0, missing.join(', '));

const script = read(files.script);

/* The two words a view uses for one dialog: the marker a door carries and the
   name the form answers to. They are the same word by design — a door is a
   marker, not an address — and the check reads them from the view rather than
   trusting the fixture. */
const formKeyOf = (text) => (text.match(/data-asset-form="([a-z0-9-]+)"/) || [])[1] || null;
const dialogNameOf = (text) => (text.match(/name="_dialog" value="([a-zA-Z0-9-]+)"/) || [])[1] || null;
const fieldNamesOf = (text) => new Set([...text.matchAll(/name="([a-z_]+)"/g)].map((m) => m[1]));

const settingsView = read(files.settings);
const editDialogView = read(files.editDialog);

/* The verbs the suite clicks with. They are read out of the view rather than
   assumed: the add door posts the collection, the change door puts the row, and
   if the view stops saying so the fixture is clicking a door the product does
   not render. */
const doorMethods = (view, key) => [...view.matchAll(
    new RegExp(`<button\\b[^>]*data-open-asset-modal="${key}"[\\s\\S]*?data-method="([A-Z]+)"`, 'g'),
)].map((m) => m[1]);

const categoryKey = formKeyOf(settingsView);
const categoryDialog = dialogNameOf(settingsView);
const editKey = formKeyOf(editDialogView);
const editDialog = dialogNameOf(editDialogView);

check('the dialog a failed save names is the dialog its own door names',
    categoryKey !== null && categoryKey === categoryDialog
    && editKey !== null && editKey === editDialog,
    `settings: form="${categoryKey}" _dialog="${categoryDialog}" · edit: form="${editKey}" _dialog="${editDialog}"`);

/* Every door into the classes dialog, one by one. The dialog both creates and
   changes, so a door's verb is the whole difference between an add and a save:
   the doors that carry a row are saves and must say `PUT` and carry the row's
   values; the doors that carry none are adds and must say `POST` and carry no
   payload. A view with two add doors — the toolbar and the empty state — has two
   chances to forget, which is why this reads every door rather than counting. */
const categoryDoors = settingsView.split('<button').slice(1)
    .map((chunk) => chunk.split('<')[0])
    .filter((el) => el.includes(`data-open-asset-modal="${categoryKey}"`));
const doorFaults = categoryDoors.flatMap((door, i) => {
    const faults = [];
    const isSave = door.includes('data-record=');

    if (!door.includes('data-action=')) faults.push(`${i}: no address`);
    if (!isSave && !door.includes('data-method="POST"')) faults.push(`${i}: an add door without POST`);
    if (!isSave && door.includes('data-payload=')) faults.push(`${i}: an add door carrying a payload`);
    if (isSave) {
        if (!door.includes('data-method="PUT"')) faults.push(`${i}: a save door without PUT`);
        if (!door.includes('data-payload=')) faults.push(`${i}: a save door without the row`);
        if (!door.includes('data-subject=') || !door.includes('data-submit=')) faults.push(`${i}: a save door with no words`);
    }

    return faults;
});

check('every door into the classes dialog says which verb it means and which row it is about',
    categoryDoors.length >= 2 && doorFaults.length === 0,
    doorFaults.join(', '));

check('the reopen marker in the view is the word the script looks up',
    has(read(files.show), `data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"`)
    && has(script, "form[data-asset-form=\"' + reopen + '\"]"),
    'a marker that speaks one vocabulary on the way out and another on the way back finds nothing');

/* ------------------------------------------------------------- the fixtures */

/* Each fixture stands for one view: it is built with that view's own marker and
   dialog name (read above), and every field and payload key it uses has to exist
   in that view. */

const rowPayload = {
    code: 'LAPTOP', name: 'Laptop', useful_life_years: 5, depreciation_method: 'straight_line',
    residual_percent: '5.00', sort_order: 10, notes: '', is_active: true,
};

const retiredPayload = {
    ...rowPayload, code: 'OLD', name: 'Retired thing', depreciation_method: 'wdv', is_active: false,
};

const categoryPage = (formAction, reopened = '', typedName = '') => `<!doctype html><html><body>
<span hidden data-open-dialog="${reopened ? categoryDialog : ''}"></span>

<button type="button" data-open-asset-modal="${categoryKey}"
    data-action="/settings/assets/categories" data-method="POST"
    data-subject="A class of assets" data-submit="Add the class">Add a class</button>

<button type="button" data-open-asset-modal="${categoryKey}" data-record="7"
    data-action="/settings/assets/categories/7" data-method="PUT"
    data-payload='${JSON.stringify(rowPayload)}'
    data-subject="LAPTOP · Laptop" data-submit="Save the class">Change</button>

<button type="button" data-open-asset-modal="${categoryKey}" data-record="8"
    data-action="/settings/assets/categories/8" data-method="PUT"
    data-payload='${JSON.stringify(retiredPayload)}'
    data-subject="OLD · Retired thing" data-submit="Save the class">Change</button>

<div class="master-modal" id="assetCategoryModal" aria-hidden="true">
  <form method="POST" action="${formAction}" data-asset-form="${categoryKey}">
    <input type="hidden" name="_record" value="${reopened}">
    <input type="hidden" name="_dialog" value="${categoryDialog}">
    <h3 data-asset-subject>A class of assets</h3>
    <p data-asset-current>Furniture &amp; fixtures…</p>
    <input id="categoryCode" name="code" value="${reopened ? 'LAPTOP' : ''}">
    <input id="categoryName" name="name" value="${typedName}">
    <input id="categoryLife" type="number" name="useful_life_years" value="10">
    <select id="categoryMethod" name="depreciation_method">
      <option value="straight_line">SLM</option><option value="wdv">WDV</option>
    </select>
    <input id="categoryResidual" name="residual_percent" value="5">
    <input id="categorySort" name="sort_order" value="10">
    <input type="hidden" name="is_active" value="0">
    <input id="isActive" type="checkbox" name="is_active" value="1" checked>
    <input id="categoryNotes" name="notes" value="">
    <button type="submit"><span data-asset-submit>Add the class</span></button>
  </form>
</div></body></html>`;

/* The register's own dialog: a door that says nothing about verbs, and a form
   that has declared its own (a PUT). */
const recordPage = `<!doctype html><html><body>
<button type="button" data-open-asset-modal="${editKey}">Change the details</button>
<div class="master-modal" id="assetEditModal" aria-hidden="true">
  <form method="POST" action="/fixed-assets/3" data-asset-form="${editKey}">
    <input type="hidden" name="_method" value="PUT">
    <input type="hidden" name="_dialog" value="${editDialog}">
    <input name="name" value="Chair">
  </form>
</div></body></html>`;

/* The fixture describes the product, or it is a test of nothing. */
const fixtures = [
    {
        name: 'the classes page',
        html: categoryPage('/settings/assets/categories'),
        view: files.settings,
        fields: true,
        payload: Object.keys(rowPayload),
        /* The two doors the suite clicks: the change door has to post the row,
           carry its verb and its values; the add door has to post the
           collection and carry none of the three. */
        doors: [
            { key: categoryKey, record: true, attrs: ['data-action', 'data-method', 'data-record', 'data-payload', 'data-subject', 'data-submit'] },
            { key: categoryKey, record: false, attrs: ['data-action', 'data-subject', 'data-submit', 'data-method'] },
        ],
    },
    {
        name: 'the record page',
        html: recordPage,
        view: [files.editDialog, files.assetForm, files.overview],
        fields: true,
        payload: [],
        doors: [{ key: editKey, attrs: ['data-open-asset-modal'] }],
    },
];

fixtures.forEach((fixture) => {
    const view = (Array.isArray(fixture.view) ? fixture.view : [fixture.view]).map(read).join('\n');
    const names = fieldNamesOf(fixture.html);
    const carried = fieldNamesOf(view);
    /* `@method('PUT')` *is* a `_method` field — Blade writes it, the file does
       not spell it — and `_dialog` is the marker rather than a value. */
    const unknown = [...names].filter((name) => name !== '_dialog'
        && !carried.has(name)
        && !(name === '_method' && has(view, "@method('PUT')")));

    check(`the fixture for ${fixture.name} still speaks the view's own fields`,
        unknown.length === 0,
        unknown.join(', '));

    const payloadKeys = Object.keys(rowPayload);
    const payloadInView = new Set([...view.matchAll(/'([a-z_]+)' =>/g)].map((m) => m[1]));
    const unnamed = fixture.payload.filter((key) => !payloadInView.has(key));

    check(`the payload for ${fixture.name} is the payload the view renders`,
        unnamed.length === 0,
        unnamed.join(', '));

    /* And the doors the fixture clicks have to be the doors the view renders:
       an attribute the fixture relies on and the view does not carry is a test
       of the fixture. The fixture is what gets clicked, so a view that stopped
       carrying `data-payload` would otherwise be clicked by a door that has one
       — green, and wrong. */
    /* A tag, not a regex: an attribute here can hold `$category->id` or `a > b`,
       so "up to the first `>`" cuts a door in half. The next `<` is the boundary
       Blade markup actually has. */
    const doors = view.split('<button').slice(1)
        .map((chunk) => chunk.split('<')[0])
        .filter((el) => el.includes('data-open-asset-modal="'));
    const crowded = fixture.doors || [];
    const missingAttrs = crowded.flatMap((door) => {
        /* A fixture door is not any door with the key: the change door carries
           the row it changes, the add door carries none. Matching by key alone
           lets the add door's own `data-subject` answer for the change door's
           missing one. */
        const kind = doors.filter((el) => el.includes(`data-open-asset-modal="${door.key}"`)
            && (door.record === undefined || el.includes('data-record=') === door.record));

        return door.attrs
            .filter((attr) => !kind.some((el) => el.includes(`${attr}=`)))
            .map((attr) => `${door.key}${door.record ? ' (save)' : ''}/${attr}`);
    });

    check(`the doors the ${fixture.name} fixture clicks are the doors the view renders`,
        crowded.length === 0 || (doors.length > 0 && missingAttrs.length === 0),
        missingAttrs.join(', '));
});

/* ------------------------------------------------------------------ running */

if (!jsdom) {
    skipped.push(jsdomNote);
    report();
} else {
    const open = (html) => {
        const dom = new jsdom.JSDOM(html, { runScripts: 'outside-only', url: 'http://localhost/settings/assets' });

        /* The script binds on DOMContentLoaded when the document is still
           loading, so the page is allowed to finish before it is run. */
        if (dom.window.document.readyState !== 'complete') {
            return new Promise((resolve) => dom.window.addEventListener('load', () => {
                dom.window.eval(script);
                resolve(dom.window);
            }));
        }

        dom.window.eval(script);

        return Promise.resolve(dom.window);
    };

    /* Every lookup is null-safe: a missing element is a failed promise with a
       sentence, not a stack trace. */
    const formIn = (w) => w.document.querySelector('form[data-asset-form]');
    const fieldIn = (w, name) => {
        const form = formIn(w);
        return form ? form.querySelector(`[name="${name}"]`) : null;
    };
    const valueIn = (w, name) => {
        const field = fieldIn(w, name);
        return field ? field.value : null;
    };
    const textIn = (w, selector) => {
        const form = formIn(w);
        const modal = form ? form.closest('.master-modal') : null;
        const node = modal ? modal.querySelector(selector) : null;
        return node ? node.textContent : null;
    };
    const click = (w, selector) => {
        const node = w.document.querySelector(selector);
        if (node) node.click();
        return !!node;
    };

    (async () => {
        /* ------------------------------------------------ a change, then an add */
        {
            const w = await open(categoryPage('/settings/assets/categories'));

            check('the change door has a door to click',
                click(w, `[data-open-asset-modal="${categoryKey}"][data-record="7"]`));

            check('the change door points at the row it means',
                formIn(w).getAttribute('action') === '/settings/assets/categories/7',
                String(formIn(w).getAttribute('action')));
            check('the change door posts the verb it named',
                valueIn(w, '_method') === 'PUT',
                String(valueIn(w, '_method')));
            check('the change door fills the fields it carries',
                valueIn(w, 'name') === 'Laptop' && valueIn(w, 'useful_life_years') === '5'
                && valueIn(w, 'depreciation_method') === 'straight_line' && valueIn(w, 'residual_percent') === '5.00'
                && valueIn(w, 'sort_order') === '10',
                [valueIn(w, 'name'), valueIn(w, 'depreciation_method'), valueIn(w, 'residual_percent')].join(' · '));
            check('the change door remembers which row it was for',
                valueIn(w, '_record') === '7',
                String(valueIn(w, '_record')));
            check('the change door says which row it is about',
                textIn(w, '[data-asset-subject]') === 'LAPTOP · Laptop'
                && textIn(w, '[data-asset-submit]') === 'Save the class');
            check('the dialog is open',
                formIn(w).closest('.master-modal').classList.contains('open'));

            click(w, '[data-method="POST"]');

            check('the add door takes the dialog back',
                formIn(w).getAttribute('action') === '/settings/assets/categories'
                && valueIn(w, '_method') === 'POST'
                && valueIn(w, '_record') === '',
                [formIn(w).getAttribute('action'), valueIn(w, '_method'), valueIn(w, '_record')].join(' · '));
            check('the add door clears the typing the change door left',
                valueIn(w, 'name') === '' && valueIn(w, 'code') === '');
            check('the add door says its own words again',
                textIn(w, '[data-asset-submit]') === 'Add the class');
        }

        /* ------------------------------------------------- a retired class opens retired */
        {
            const w = await open(categoryPage('/settings/assets/categories'));

            click(w, `[data-open-asset-modal="${categoryKey}"][data-record="8"]`);

            const box = w.document.querySelector('#isActive');

            check('a retired class opens with its box clear',
                box !== null && box.checked === false);
            check('…and the hidden answer is not written by the payload',
                w.document.querySelector('input[type="hidden"][name="is_active"]').value === '0',
                String(w.document.querySelector('input[type="hidden"][name="is_active"]').value));
        }

        /* ------------------------------- a save that failed comes back to its row */
        {
            const w = await open(categoryPage('/settings/assets/categories', '7', 'Laptop (as typed)'));

            check('a failed save reopens the dialog it came from',
                formIn(w).closest('.master-modal').classList.contains('open'));
            check('…addressed at the row it was about',
                formIn(w).getAttribute('action') === '/settings/assets/categories/7',
                String(formIn(w).getAttribute('action')));
            check('…with the verb it was saved with',
                valueIn(w, '_method') === 'PUT',
                String(valueIn(w, '_method')));
            check('…and without throwing away what was typed',
                valueIn(w, 'name') === 'Laptop (as typed)',
                String(valueIn(w, 'name')));
        }

        /* -------------------------- a door that says nothing leaves the form alone */
        {
            const w = await open(recordPage);

            click(w, `[data-open-asset-modal="${editKey}"]`);

            check('a door with no verb leaves the form its own method',
                valueIn(w, '_method') === 'PUT',
                'the asset edit dialog posts as a PUT and says so in its own markup');
            check('…and leaves the action the server rendered',
                formIn(w).getAttribute('action') === '/fixed-assets/3',
                String(formIn(w).getAttribute('action')));
        }

        report();
    })().catch((e) => {
        failed++;
        failures.push(`the behaviour suite threw — ${e.message}`);
        report();
    });
}

function report() {
    console.log('');

    if (skipped.length) {
        skipped.forEach((note) => console.log(`  ~  ${note}`));
        console.log('');
    }

    if (failures.length) {
        console.log(`assets dialog: ${passed} passed, ${failed} failed\n`);
        failures.forEach((line) => console.log(`  · ${line}`));
        process.exit(1);
    }

    console.log(`assets dialog: ${passed} passed, 0 failed`);

    if (skipped.length) {
        console.log('  (the behaviour suite did not run — install jsdom to run the dialogs)');
    }
}

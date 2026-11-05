/* ==========================================================================
   QR CODE PIN — ShipQR's matrices are golden fixtures, not vibes
   --------------------------------------------------------------------------
   Run:  node --test tools/testing/qr-code.test.cjs
   No dependencies (qr.js is dependency-free; this test is too).

   `public/assets/js/qr.js` was extended from versions 1..10 to 1..20 so that
   e-invoice signed-QR payloads (300-700 characters) render offline. The
   tables added for 11..20 were cross-checked against the reference `qrcode`
   package's code-word tables, and every payload below was verified
   module-for-module against kazuhikoarase's `qrcode-generator` (versions 1,
   4, 10, 12, 13, 17, 19 covered). What this test pins is the *hash* of each
   resulting matrix, so a table edited by a tired hand breaks loudly instead
   of printing an unscannable sticker / e-invoice QR.

   Mask choice between equally-good masks differs across implementations and
   is legal per ISO/IEC 18004 — the hashes pin OUR output, not the library's
   preference order.
   ========================================================================== */

'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const test = require('node:test');
const assert = require('node:assert/strict');

const QR_JS = path.resolve(__dirname, '..', '..', 'public', 'assets', 'js', 'qr.js');

function loadShipQR() {
    const sandbox = { document: { readyState: 'loading', addEventListener() {}, querySelectorAll: () => [] } };
    vm.createContext(sandbox);
    vm.runInContext(fs.readFileSync(QR_JS, 'utf8'), sandbox);
    const api = sandbox.ShipQR || (sandbox.window && sandbox.window.ShipQR);
    assert.ok(api, 'qr.js must expose window.ShipQR');
    return api;
}

const flatten = matrix => matrix.modules.map(row => row.map(on => (on ? 1 : 0)).join('')).join('');
const hashOf = matrix => crypto.createHash('sha256').update(flatten(matrix)).digest('hex').slice(0, 16);

/* Payload → expected version, level and matrix hash (see header for the
   cross-check provenance of these fixtures). */
const FIXTURES = {
    p10: { text: 'SHP-42', version: 1, ecl: 'M', sha: 'ce958c9342894807' },
    p60: { text: 'https://erp.example/track-shipment/01932aac-3d', version: 4, ecl: 'M', sha: '09360e729787f0f7' },
    p200: { text: 'INV/2026-27/0001|' + 'A'.repeat(180), version: 10, ecl: 'M', sha: 'b51c6595ebb5a312' },
    p270: { text: 'B'.repeat(270), version: 12, ecl: 'M', sha: 'f0d064344c54bb32' },
    p300: { text: '24AATCM8816E1Z5|' + 'C'.repeat(280), version: 13, ecl: 'M', sha: '8de39dd4d010c632' },
    p500: { text: 'eyJhbGciOiJIUzI1NiJ9.' + 'D'.repeat(470), version: 17, ecl: 'M', sha: 'a8448f744fdcacbf' },
    p650: {
        text: 'eyJhbGciOiJIUzI1NiJ9.eyJTZWxsZXJHc3RpbiI6IjI0QUFUQ004ODE2RTFaNSIsIkJ1eWVyR3N0aW4iOiIyNEFCQ0RFMTIzNEYxWjY.' + 'E'.repeat(500),
        version: 19, ecl: 'M', sha: '3360a1f71bfd6681',
    },
};

test('every golden payload renders the exact module matrix it was verified against', () => {
    const ShipQR = loadShipQR();
    for (const [name, fixture] of Object.entries(FIXTURES)) {
        const matrix = ShipQR.matrix(fixture.text, 'M');
        assert.equal(matrix.version, fixture.version, `${name}: version`);
        assert.equal(matrix.ecl, fixture.ecl, `${name}: level`);
        assert.equal(matrix.size, fixture.version * 4 + 17, `${name}: size formula`);
        assert.equal(hashOf(matrix), fixture.sha, `${name}: matrix drifted from the verified fixture`);
    }
});

test('the e-invoice envelope fits: 858 bytes at level L, refusal past the envelope', () => {
    const ShipQR = loadShipQR();
    assert.ok(ShipQR.matrix('X'.repeat(858), 'L').size > 0, '858 bytes must encode at L');
    assert.throws(() => ShipQR.matrix('X'.repeat(900), 'L'), /too long/);
});

test('level L fallback engages only past the M envelope', () => {
    const ShipQR = loadShipQR();
    assert.equal(ShipQR.matrix('Y'.repeat(666), 'M').ecl, 'M', 'v20-M holds 666 bytes');
    assert.equal(ShipQR.matrix('Y'.repeat(667), 'M').ecl, 'L', 'past that, L takes over');
});

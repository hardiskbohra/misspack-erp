/* ==========================================================================
   COST CHECK — what a freight cost owes the ledger
   --------------------------------------------------------------------------
   Run:  node tools/checks/cost-check.cjs
   No dependencies. Exits non-zero on failure.

   A cost head is entered in the currency it was actually billed in, so the
   amount alone never says what it cost. Three things have to hold together:

     - the exchange rate is part of the *form*. It used to be a field that
       appeared and disappeared with the currency select, which is how it ends
       up missing exactly when a foreign bill is being entered — and the user
       is then blocked by a validation error with nowhere to type the number.
       It is now always on screen: locked at 1 for the base currency, required
       with the last rate used for any other.
     - the INR value is *derived once*: amount × rate, frozen on the row, and
       the one number the cashflow mirror, the paid-so-far figure and the
       landed-cost view all read. It may never be invented from a missing rate.
     - the server refuses a foreign cost with no rate, so a rate the script
       failed to fill in can never quietly become a 1:1 conversion.
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = file => fs.readFileSync(path.join(ROOT, file), 'utf8');

const out = [];
const check = (name, ok, detail = '') => out.push([name, !!ok, detail]);

const modal = read('resources/views/shipments/partials/cost-modal.blade.php');
const js = read('public/assets/js/shipments.js');
const css = read('public/assets/css/shipments.css');
const controller = read('app/Http/Controllers/ShipmentController.php');
const ledger = read('app/Services/ShipmentCostLedger.php');

/* ------------------------------------------------------------- 1. the field */

check('the modal always renders the rate field, never only for a currency',
    /<div class="master-field\{\{ \$costIsBase \? ' is-base' : '' \}\}" id="costRateField"/.test(modal)
    && /name="exchange_rate"/.test(modal)
    && /data-last-rates="\{\{ json_encode\(\$lastCostRates/.test(modal));

check('its first state is decided by the server, from the shipment currency',
    /\$costCurrency = strtoupper\(\(string\) old\('currency', \$shipment->currency/.test(modal)
    && /\$costIsBase = \$costCurrency === 'INR'/.test(modal)
    && /\{\{ \$costIsBase \? ' is-base' : '' \}\}/.test(modal)
    && /value="\{\{ \$costRateValue \}\}"/.test(modal));

check('the rate is editable in every case — nothing is locked',
    !/readonly/.test(modal)
    && !/readOnly/.test(js)
    && !/#costRateField\.is-base \.master-input/.test(css));

check('the base currency still starts at 1 and asks for no conversion',
    /\$costRateValue = old\('exchange_rate', \$costIsBase \? '1' :/.test(modal)
    && /#costRateField\.is-base \.master-required \{\s*display: none;/.test(css)
    && /rate\.value = '1';\s*\n\s*rateFromLastUsed = false;/.test(js));

check('the required marker and hint say which case the row is in',
    /aria-hidden="true">\*<\/span>/.test(modal)
    && /INR bill — the amount is already in rupees, so the ledger keeps the rate at 1\./.test(modal)
    && /The INR value is frozen at this rate: amount × rate\./.test(modal));

/* --------------------------------------------------------- 2. the default */

check('the server offers the rate each currency was last billed at',
    /private function lastCostRates\(Shipment \$shipment\): array/.test(controller)
    && /'lastCostRates' => \$this->lastCostRates\(\$shipment\)/.test(controller));

check('the shipment\'s own history outranks the rest of the book',
    /\$collect\(ShipmentCost::query\(\)->orderBy\('id'\)->get\(\$columns\)\);[\s\S]{0,120}\$collect\(\$shipment->costs\(\)->orderBy\('id'\)->get\(\$columns\)\);/.test(controller));

check('it only offers real foreign rates',
    /if \(\$currency === 'INR' \|\| \(float\) \$cost->exchange_rate <= 0\) \{\s*continue;\s*\}/.test(controller));

/* ------------------------------------------------------------ 3. the script */

check('the script never hides the field again',
    !/rateWrap\.hidden/.test(js)
    && /rateWrap\.classList\.toggle\('is-base', !foreign\)/.test(js));

check('it syncs on load, so the rendered state and the script agree',
    (js.match(/syncCurrency\(false\);/g) || []).length >= 2
    && /and once on load[\s\S]{0,160}syncCurrency\(false\);/.test(js)
    && /function syncCurrency\(suggest\)/.test(js));

check('it follows a currency change however the select was changed',
    /form\.addEventListener\('change', function \(event\) \{\s*\n\s*if \(event\.target === currency\) syncCurrency\(true\);/.test(js)
    && /form\.addEventListener\('input', function \(event\) \{/.test(js));

check('a rate entered for one currency never carries over to another',
    /var known = lastRates\[code\] \|\| null;\s*\n\s*rate\.value = known \? known\.rate : '';/.test(js));

check('the rate is required on a foreign currency and optional on the base',
    /rate\.required = foreign;/.test(js)
    && /rateWrap\.classList\.toggle\('is-base', !foreign\);/.test(js));

check('the row shows the INR value it will freeze, while it is typed',
    /function paintRate\(\)/.test(js)
    && /var value = \(parseFloat\(\(amount && amount\.value\) \|\| 0\) \|\| 0\) \* rateValue;/.test(js)
    && /if \(!foreign\) \{\s*rateValue = 1;\s*\} else if \(!\(rateValue > 0\)\) \{\s*rateValue = 0;\s*\}/.test(js)
    && /posted to the ledger/.test(js)
    && /last rate used for /.test(js));

/* ------------------------------------------------------------ 4. the ledger */

check('the INR value follows the rate in the field, whichever currency it is',
    /if \(\$currency === 'INR' && \$rate <= 0\) \{\s*\$rate = 1\.0;\s*\}/.test(ledger)
    && /\$cost->amount_in_inr = \$rate > 0 \? round\(\$amount \* \$rate, 2\) : 0;/.test(ledger));

check('a foreign row without a rate is never quietly converted at 1:1',
    !/\$rate > 0 \? \$rate : 1/.test(ledger)
    && !/round\(\$amount, 2\)/.test(ledger));

check('a rupee bill is stored at 1, so a stray rate can never multiply it',
    /if \(\$data\['currency'\] === 'INR'\) \{\s*\$data\['exchange_rate'\] = 1;\s*\}/.test(controller));

check('the ledger is recalculated on both save and update',
    (controller.match(/recalculateInr\(\$cost\)/g) || []).length >= 2);

check('a foreign cost without a rate is refused with a way forward',
    /if \(\$data\['currency'\] !== 'INR' && \(float\) \(\$data\['exchange_rate'\] \?\? 0\) <= 0\)/.test(controller)
    && /Enter the exchange rate this bill was raised at, so the INR value is right\./.test(controller));

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\ncost: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed');
process.exit(failed.length ? 1 : 0);

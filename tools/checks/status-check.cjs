/* ==========================================================================
   STATUS CHECK — the rules a shipment status change has to keep
   --------------------------------------------------------------------------
   Run:  node tools/checks/status-check.cjs
   No dependencies. Exits non-zero on failure.

   Two rules live here, and they are easy to break in opposite directions:

     - a delivered shipment has a delivery date. A missing one is *filled*
       with the day of the change (the office's day, not the UTC one), never
       refused — an operator must not be blocked by a field they did not fill;
     - a hold or a delay still needs a reason. That one is *refused*, because
       an unexplained hold is noise in every report downstream.

   The date is only ever filled, never overwritten: a delivery recorded late
   keeps the date it was recorded with.

   PHP cannot run in every environment this is checked from, so the rules are
   asserted against the source — the same way design-check reads the
   stylesheets. The behaviour itself is verified by running the app.
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = file => fs.readFileSync(path.join(ROOT, file), 'utf8');

const out = [];
const check = (name, ok, detail = '') => out.push([name, !!ok, detail]);

const model = read('app/Models/Shipment.php');
const controller = read('app/Http/Controllers/ShipmentController.php');
const form = read('resources/views/shipments/form.blade.php');
const show = read('resources/views/shipments/show.blade.php');
const js = read('public/assets/js/shipments.js');
const appConfig = read('config/app.php');

/* ------------------------------------------------------- 1. the default */

check('the model owns the delivery default, keyed on the delivered status',
    /public static function withDeliveryDefaults\(/.test(model)
    && /\(\$attributes\['status'\] \?\? null\) !== self::STATUS_DELIVERED/.test(model));

check('it writes the office\'s today, not the UTC clock',
    /public static function businessToday\(\)/.test(model)
    && /now\(config\('app\.business_timezone'/.test(model));

check('the business timezone is configured, not hard-coded in a view',
    /'business_timezone'\s*=>/.test(appConfig)
    && !/now\('Asia\/Kolkata'\)/.test(show));

check('an existing delivery date is never overwritten',
    /filled\(\$attributes\['drop_date'\] \?\? null\) \|\| filled\(\$shipment\?->drop_date\)/.test(model));

check('the fill really writes the date onto the record',
    /\|\| filled\(\$shipment\?->drop_date\)\) \{\s*\n\s*return \$attributes;\s*\n\s*\}\s*\n\s*\$attributes\['drop_date'\] = static::businessToday\(\);\s*\n\s*\n\s*return \$attributes;/.test(model));

check('today is a date, not a timestamp',
    /now\(config\('app\.business_timezone', 'Asia\/Kolkata'\)\)->toDateString\(\)/.test(model));

/* -------------------------------------------------- 2. every status write */

const appliesDefault = (controller.match(/Shipment::withDeliveryDefaults\(/g) || []).length;
check('every path that writes a status applies the default',
    appliesDefault >= 4, `${appliesDefault} call sites`);

check('the tracking history form applies it',
    /storeHistory[\s\S]{0,1400}Shipment::withDeliveryDefaults\(/.test(controller));
check('the edit form applies it',
    /public function update\(Request \$request, Shipment \$shipment\)[\s\S]{0,900}Shipment::withDeliveryDefaults\(\$data, \$shipment\)/.test(controller));
check('creating a shipment that is already delivered applies it',
    /Shipment::withDeliveryDefaults\(\$data\)/.test(controller));
check('a status derived from a history edit applies it',
    /syncShipmentStatusFromLatestHistory[\s\S]{0,700}withDeliveryDefaults\(/.test(controller));

/* ------------------------------------------- 3. nothing refuses a delivery */

check('the status change no longer refuses a missing delivery date',
    !/Add the delivery \(drop\) date before marking this shipment delivered/.test(controller)
    && !/['"]status['"]\s*=>[\s\S]{0,120}delivery \(drop\) date/.test(controller));

check('the hold/delay reason rule is still enforced',
    /in_array\(\$status, \[Shipment::STATUS_CUSTOM_HOLD, Shipment::STATUS_DELAYED\], true\)/.test(controller)
    && /Add a remark explaining why the shipment is on hold or delayed\./.test(controller));

const pickupChecks = (controller.match(/\$this->assertDeliveryDateAllowed\(/g) || []).length;
check('a delivery date cannot precede the pickup, on every path that writes one',
    /private function assertDeliveryDateAllowed\(\?string \$deliveryDate, \?string \$pickupDate\)/.test(controller)
    && /The delivery date cannot be before the pickup date\./.test(controller)
    && pickupChecks >= 3, `${pickupChecks} call sites`);

/* ------------------------------------------------------ 4. what the user sees */

check('the history form offers the delivery date, prefilled with today',
    /name="drop_date"/.test(show)
    && /data-today="\{\{ now\(config\('app\.business_timezone'\)\)->toDateString\(\) \}\}"/.test(show)
    && /data-delivery-optional/.test(show));

check('the edit form says what the empty field will record',
    /name="drop_date"[\s\S]{0,300}data-today/.test(form)
    && /records today here when it is empty/.test(form));

check('a date left from another status is ignored, not recorded',
    /if \(\$data\['status'\] !== Shipment::STATUS_DELIVERED\) \{\s*\n\s*\$deliveryDate = null;/.test(controller));

check('the flash message names the date that was recorded',
    /The delivery date was recorded as[\s\S]{0,120}because none was given/.test(controller));

check('the script fills the field for a delivered status and only when empty',
    /function bindDeliveryDateDefault\(\)/.test(js)
    && /status\.value === 'delivered'/.test(js)
    && /if \(!date\.value\) \{/.test(js)
    && /dataset\.autoFilled/.test(js));

check('the script undoes only its own fill',
    /if \(date\.dataset\.autoFilled === '1'\) \{[\s\S]{0,140}date\.value = '';/.test(js));

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\nstatus: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed');
process.exit(failed.length ? 1 : 0);

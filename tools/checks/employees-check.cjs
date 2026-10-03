/* ==========================================================================
   EMPLOYEES CHECK — the employee side of the user module
   --------------------------------------------------------------------------
   Run:  node tools/checks/employees-check.cjs
   No dependencies. Exits non-zero on failure.

   Task 56 made employees users: one `users` table, a `role`, an office door and
   a personal one. The failures that matter here are not cosmetic, so they are
   the ones asserted:

     - an employee must not reach the office. The `office` middleware has to be
       on the whole admin group, and it has to ask the question on the *server*;
     - an employee's own pages must never take a user id from the URL. The
       moment `/my/documents/{user}` exists, somebody will try somebody else's
       id. Two routes do take an id (a payslip, a document) and both must be
       ownership-checked before a byte is served;
     - a draft payslip must be invisible to the employee and visible to the
       office;
     - a document the office marked seen must not be removable by the person
       who uploaded it;
     - the two roles must exist everywhere the role is read — the model, the
       migration default, the validation, and the forms;
     - no query may name a column no migration declares. The list screen died on
       `whereNull('joining_date')` — a column that was never created — while the
       tile it fed was never even printed. A missing column is a 500 in front of
       somebody's payroll, so the whole application is swept for it, not just
       this module;
     - the page must offer a filter only when the query can apply it. A control
       that silently does nothing is worse than no control, and a chip claiming
       a filter the query never applied is a lie about the rows on screen.

   Source-level, like every other gate in this folder: the behaviour itself is
   exercised by the application. What this catches is the change that *looks*
   right — a route that lost its middleware, a query that forgot the owner.
   ========================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = file => fs.readFileSync(path.join(ROOT, file), 'utf8');
const exists = file => fs.existsSync(path.join(ROOT, file));

const out = [];
const check = (name, ok, detail = '') => out.push([name, !!ok, detail]);

const routes = read('routes/web.php');
const bootstrap = read('bootstrap/app.php');
const middleware = read('app/Http/Middleware/EnsureUserIsAdmin.php');
const userModel = read('app/Models/User.php');
const userController = read('app/Http/Controllers/UserController.php');
const workspace = read('app/Http/Controllers/EmployeeWorkspaceController.php');
const payslipController = read('app/Http/Controllers/EmployeePayslipController.php');
const documentController = read('app/Http/Controllers/EmployeeDocumentController.php');
const access = read('app/Services/EmployeeAccess.php');
const profile = read('app/Services/EmployeeProfile.php');
const upload = read('app/Services/DocumentUpload.php');
const layout = read('resources/views/layouts/app.blade.php');
const nav = layout.slice(0, layout.indexOf('</nav>'));
const userIndex = read('resources/views/users/index.blade.php');
const userShow = read('resources/views/users/show.blade.php');
const seeder = read('database/seeders/DatabaseSeeder.php');
const entryModel = read('app/Models/CashflowEntry.php');
const employeeSalary = read('resources/views/employees/salary.blade.php');
const payMonths = read('resources/views/employees/partials/pay-months.blade.php');
const cashflowController = read('app/Http/Controllers/CashflowController.php');
const payslipDoc = read('app/Services/PayslipDocument.php');
const payslipSheet = read('resources/views/employees/partials/payslip.blade.php');
const payTable = read('resources/views/employees/partials/pay-table.blade.php');
const payslipForm = read('resources/views/employees/partials/payslip-form.blade.php');
const payMonthsService = read('app/Services/EmployeeProfile.php');
const payslipModel = read('app/Models/EmployeePayslip.php');
const payslipPrint = read('resources/views/employees/payslip-pdf.blade.php');
const payslipCss = read('public/assets/css/payslip.css');
/* the sheet without its prose: a comment that says "@media print" is not a
   print rule, and a guard that cannot tell the two apart passes on a sheet
   that no longer prints */
const payslipRules = payslipCss.replace(/\/\*[\s\S]*?\*\//g, '');
const helper = read('app/Helpers/CommonHelper.php');
const userJs = read('public/assets/js/users.js');

/* ------------------------------------------------------------- 1. the door */

check('the office middleware is registered under a name routes can use',
    /'office'\s*=>\s*\\?App\\Http\\Middleware\\EnsureUserIsAdmin::class/.test(bootstrap));

check('the office middleware asks the question on the server',
    /Auth::user\(\)/.test(middleware)
    && /\$user->isEmployee\(\)/.test(middleware)
    && /route\('my\.dashboard'\)/.test(middleware));

check('an employee is turned around rather than shown a 403 page',
    /with\('error',/.test(middleware) && !/abort\(403/.test(middleware));

/* The office group wraps everything that was there before this task, and the
   employee's group sits outside it — otherwise an employee could never reach
   their own pages. */
const officeGroup = routes.indexOf("Route::middleware('office')->group(");
const myGroup = routes.indexOf("Route::prefix('my')");
const authGroup = routes.indexOf("Route::middleware('auth')->group(");
check('the office group is inside the signed-in group, and wraps the ledger',
    authGroup !== -1 && officeGroup > authGroup
    && routes.slice(officeGroup, myGroup).includes("Route::resource('cashflows'")
    && routes.slice(officeGroup, myGroup).includes("Route::get('/users'"));
check('the personal group is outside the office group',
    myGroup > officeGroup
    && routes.slice(officeGroup, myGroup).split('});').length >= 2);

/* --------------------------------------------- 2. no id in the personal URLs */

const myRoutes = routes.slice(myGroup, routes.indexOf("Route::prefix('client-portal')"));
const myDefinitions = [...myRoutes.matchAll(/Route::(get|post|put|patch|delete)\('([^']+)'/g)]
    .map(m => m[2]);

check('the personal group has the four pages and the change-password post',
    myDefinitions.includes('/') && myDefinitions.includes('/profile')
    && myDefinitions.includes('/salary') && myDefinitions.includes('/documents')
    && myDefinitions.includes('/payslips') && myDefinitions.includes('/password'),
    myDefinitions.join(' '));

/* A user id in one of these paths would be the whole bug: the page would be
   reachable by changing a number. */
const withUserParam = myDefinitions.filter(p => /\{user\}|\{id\}|user_id/.test(p));
check('no personal route takes a user id', withUserParam.length === 0, withUserParam.join(', '));

check('the two id-taking routes are ownership-checked before serving',
    /access->canView\(/.test(workspace)
    && /assertOwned\(\$user, \$document\)/.test(documentController)
    && /assertOwned\(\$user, \$payslip\)/.test(payslipController));

check('ownership is one rule, not five ad-hoc comparisons',
    /public function canView\(\?User \$actor, \?User \$owner\)/.test(access)
    && /return \$actor->isAdmin\(\) \|\| \(int\) \$actor->id === \(int\) \$owner->id;/.test(access));

check('the office\'s side never trusts the id in the URL either',
    /abort_unless\(\(int\) \$document->user_id === \(int\) \$user->id, 404\)/.test(documentController)
    && /abort_unless\(\(int\) \$payslip->user_id === \(int\) \$user->id, 404\)/.test(payslipController));

/* -------------------------------------------------- 3. the role vocabulary */

check('the two roles are declared once, with a safe default',
    /public const ROLE_ADMIN = 'admin'/.test(userModel)
    && /public const ROLE_EMPLOYEE = 'employee'/.test(userModel)
    && /public const DEFAULT_ROLE = self::ROLE_ADMIN|public const DEFAULT_ROLE = 'admin'/.test(userModel));

/* "Not an employee" and not "=== admin": any role this version does not know —
   and a row written before the column existed — stays able to work. An office
   locked out of its own ledger by a missing value is the worse failure. */
check('the role helpers read the column, not the name',
    /public function isAdmin\(\): bool/.test(userModel)
    && /return \$this->role !== self::ROLE_EMPLOYEE;/.test(userModel)
    && /public function isEmployee\(\): bool/.test(userModel)
    && /return \$this->role === self::ROLE_EMPLOYEE;/.test(userModel));

const migration = fs.readdirSync(path.join(ROOT, 'database/migrations'))
    .find(f => /add_employment_profile_to_users_table/.test(f));
check('the migration exists and defaults every existing account to the office',
    !!migration
    && /->default\('admin'\)/.test(read('database/migrations/' + migration)));

check('an employee is validated as one, on the server',
    /if \(\$role === User::ROLE_EMPLOYEE\) \{/.test(userController)
    && /\$rules\['designation'\] = \['required'/.test(userController)
    && /\$rules\['date_of_joining'\] = \['required'/.test(userController)
    && /\$rules\['mobile'\] = \['required'/.test(userController));

/* Two ways an office locks itself out: demoting the only administrator, and
   deleting them. Both are refused, at the one place each decision is made. */
check('the office must keep an administrator',
    /private function isLastAdmin\(User \$user\): bool/.test(userController)
    && /if \(! \$user->isAdmin\(\)\) \{\s*return false;/.test(userController)
    && /if \(\$user && \$role !== User::ROLE_ADMIN && \$this->isLastAdmin\(\$user\)\) \{/.test(userController)
    && /if \(\$this->isLastAdmin\(\$user\)\) \{/.test(userController)
    && /if \(\$user->id === auth\(\)->id\(\)\) \{/.test(userController)
    && /You cannot delete your own account\./.test(userController));

check('the forms ask the role question before anything else',
    /name="role"[\s\S]{0,400}data-role-select/.test(userIndex)
    && userIndex.match(/data-role-select/g).length >= 2,
    String(userIndex.match(/data-role-select/g) || []).length + ' selects');

check('the browser marks the employee fields, and says the server is not replaced',
    exists('public/assets/js/employees.js')
    && /REQUIRED_FOR_EMPLOYEE/.test(read('public/assets/js/employees.js'))
    && /designation/.test(read('public/assets/js/employees.js')));

/* -------------------------------------------------------- 4. what an employee sees */

check('the sidebar is role-aware: an employee gets their own menu only',
    /\$employeeItems = \[/.test(nav)
    && /isEmployee\(\)\s*\?\s*\$employeeItems\s*:/.test(nav));

check('that menu is built from the personal routes, not from admin ones',
    /'route' => 'my\.dashboard'/.test(nav)
    && /'route' => 'my\.salary'/.test(nav)
    && /'route' => 'my\.documents'/.test(nav)
    && /'route' => 'my\.profile'/.test(nav));

/* Task 61: one salary door. A payslip is a month of the salary, so a second
   menu entry listing the same months was two places to read one month — and
   whichever the employee opened first decided what they believed. The route is
   kept as a redirect, because a menu entry is a URL somebody has already
   bookmarked, mailed or typed. */
check('there is one salary door, and the old payslips URL still opens',
    !/'route' => 'my\.payslips'/.test(nav)
    && nav.includes("'route' => 'my.salary'")
    && /return redirect\(\)->to\(route\('my\.salary', \['focus' => 'payslips'\]\)\.'#payslips'\);/.test(workspace)
    && /\$slips = \$this->profile->issuedPayslips\(\$this->user\(\)\);/.test(workspace));

check('the menu the employee sees names no office screen, and the office keeps its own',
    !/'route' => 'cashflows\.index'/.test(nav.slice(0, nav.indexOf('$sidebarItems = Auth::user()')))
    && /'route' => 'cashflows\.index'/.test(nav)
    && /'route' => 'users\.index'/.test(nav));

check('the employee sees their salary, their payslips and their documents',
    ['my.salary', 'my.documents'].every(r => nav.includes(r))
    && /employees\.partials\.pay-table/.test(employeeSalary)
    && /\$slips = \$this->profile->issuedPayslips\(\$this->user\(\)\);/.test(workspace)
    && /'payRows' => \$this->profile->payRows\(\$year, \$entries, \$slips\)/.test(workspace)
    && !/partials\.payslip-table/.test(employeeSalary));

/* ------------------------------------------------- 3b. the way the money went
   A salary the office filed as a **debit** — money out of the company, the
   ledger's own default — read as ₹0 on the person's record, because the record
   was written as "the credits filed against them". Every line was spelled
   correctly, so no source check could see it: the direction was assumed rather
   than read. These guards assert that the direction *is* read, that the amount
   comes from the column the direction wrote, and that a test which runs
   (`tests/Unit/EmployeePayTest.php`) proves the arithmetic. */

/** How many times a literal appears in a string. */
const times = (text, needle) => text.split(needle).length - 1;

check('a salary is every ledger entry filed against the person, both ways',
    profile.includes('public function salaryQuery(User $user)')
    && profile.includes("->where('employee_id', $user->id)")
    /* a direction pinned in the fetch is the bug, written again */
    && ! profile.slice(profile.indexOf('public function salaryQuery'), profile.indexOf('public function salaryQuery') + 300)
        .includes('transaction_type'));

check('the direction is read off the entry, not assumed',
    entryModel.includes('public function isMoneyOut(): bool')
    && entryModel.includes("return $this->transaction_type !== 'credit';")
    && entryModel.includes('public function scopeMoneyOut(Builder $query): Builder')
    && entryModel.includes('public function scopeMoneyIn(Builder $query): Builder'));

check('the amount is read from the column the direction wrote',
    entryModel.includes('public function amountMoved(): float')
    && entryModel.includes('public function signedAmount(): float')
    && entryModel.includes('$this->isMoneyOut() ? $this->amountMoved() : -$this->amountMoved()')
    /* the profile adds up the signed amount, never a raw column: one reading,
       both ways. The single exception is the team total, which sums
       `debit_amount` in SQL over `moneyOut()` rows — the same rows, added up by
       the database instead of by PHP. */
    && times(profile, 'credit_amount') + times(profile, 'debit_amount') === 1
    && profile.indexOf("->sum('debit_amount')") > profile.indexOf('public function paidToTeam')
    && profile.indexOf("->sum('debit_amount')") > profile.indexOf('->moneyOut()'));

check('a credit filed against a person reduces their pay, and the month agrees with the total',
    profile.includes('public function payFrom(Collection $entries): array')
    && profile.includes('public function monthsFrom(Collection $entries): array')
    && profile.includes('$this->payFrom($this->salaryEntries($user, $from, $to))')
    && profile.includes('$this->monthsFrom($this->salaryEntries(')
    && profile.includes("'total' => round($paid - $recovered, 2)")
    && profile.includes("'months_paid' => $entries")
    /* the month row does the same subtraction the year total does */
    && profile.includes("$months[$key]['net'] = round($months[$key]['paid'] - $months[$key]['recovered'], 2);"));

check('the paid-this-month tile counts what a person\'s record counts',
    userController.includes('private function profile(): EmployeeProfile')
    && userController.includes('$this->profile()->paidToTeam(')
    && profile.indexOf("->whereNotNull('employee_id')") > profile.indexOf('public function paidToTeam')
    /* and the credit-only figure it used to compute is gone, not shadowed */
    && ! userController.includes("'transaction_type', 'credit'"));

check('the record and the employee\'s own pages print the same figures',
    payTable.includes('$entry->signedAmountLabel()')
    && entryModel.includes('public function signedAmountLabel(): string')
    && ! (userShow + employeeSalary).includes('credit_amount')
    && payMonths.includes("$month['net']")
    && ! payMonths.includes("$month['credit']"));

check('the ledger link opens the person\'s entries, either way',
    userShow.includes("route('cashflows.index', ['employee_id' => $user->id])")
    && ! userShow.includes("'transaction_type' => 'credit'"));

check('the arithmetic of pay has a test that runs',
    exists('tests/Unit/EmployeePayTest.php')
    && read('tests/Unit/EmployeePayTest.php').includes('test_a_salary_debit_is_money_paid_to_the_person')
    && read('tests/Unit/EmployeePayTest.php').includes('php artisan test --filter=EmployeePayTest'));

check('a draft payslip is invisible to the employee',
    /public function issuedPayslips/.test(profile)
    && /isIssued\(\)/.test(profile)
    && /! \$payslip->isIssued\(\)/.test(workspace));

/* The office's record page lists every slip, drafts included — it is the page
   an office closes a month on. Only the employee's own list filters to issued. */
check('the office sees drafts on the record page',
    /\$payslips = \$profile->payslips\(\$user\);/.test(userController)
    && !/issuedPayslips/.test(userController)
    && /drafts stay private/.test(userShow)
    && /\$edit \? \$slip->status : 'issued'/.test(payslipForm));

check('an employee cannot remove a paper the office marked seen',
    /if \(\$document->isVerified\(\)\) \{/.test(workspace)
    && /can no longer be removed/.test(workspace)
    && /if \(! \$document->uploadedByEmployee\(\)\) \{/.test(workspace));

check('the profile has two owners, written down once',
    /public function ownEditableFields\(\): array/.test(access)
    && /\['mobile', 'address', 'emergency_contact_name', 'emergency_contact_mobile', 'date_of_birth'\]/.test(access)
    && /public function officeOnlyFields\(\): array/.test(access));

check('the employee\'s own form only posts the fields that are theirs',
    (() => {
        const form = read('resources/views/employees/profile.blade.php');
        const block = form.slice(form.indexOf("route('my.profile.update')"), form.indexOf('</form>', form.indexOf("route('my.profile.update')")));

        return ['name="mobile"', 'name="address"', 'name="date_of_birth"']
            .every(field => block.includes(field))
            && !/name="designation"|name="bank_account_number"|name="role"/.test(block);
    })());

check('a paper belongs to the person who uploaded it, and the office can tell',
    /uploaded_by/.test(read('app/Models/EmployeeDocument.php'))
    && /uploadedByEmployee/.test(read('app/Models/EmployeeDocument.php'))
    && /'uploaded_by' => Auth::id\(\)/.test(documentController)
    && /'uploaded_by' => \$this->user\(\)->id/.test(workspace));

/* ------------------------------------------------------ 5. one upload allowlist */

check('the upload allowlist is shared, not copied a sixth time',
    /public const ALLOWED_EXTENSIONS = \[/.test(upload)
    && /DocumentUpload::rules\(\)/.test(documentController)
    && /DocumentUpload::rules\(\)/.test(workspace)
    && /DocumentUpload::assertAllowed/.test(documentController)
    && /DocumentUpload::assertAllowed/.test(workspace)
    && /DocumentUpload::assertAllowed/.test(payslipController));

check('a stored file is served by its real name, or not at all',
    /public static function download\(\?string \$path, string \$name\): \?BinaryFileResponse/.test(upload)
    && /DocumentUpload::download\(/.test(workspace)
    && /DocumentUpload::download\(/.test(documentController)
    && /DocumentUpload::download\(/.test(payslipController));

/* -------------------------------------------------- 6. every page has a sheet */

const employeeViews = ['dashboard', 'profile', 'salary', 'documents']
    .map(name => 'resources/views/employees/' + name + '.blade.php');

check('every employee page exists and wears the module stylesheet',
    employeeViews.every(f => exists(f) && read(f).includes("assets/css/employees.css")),
    employeeViews.filter(f => !exists(f) || !read(f).includes('employees.css')).join(', '));

check('the standalone payslips page is retired, not orphaned',
    !exists('resources/views/employees/payslips.blade.php')
    && routes.includes("Route::get('/payslips', [EmployeeWorkspaceController::class, 'payslips'])->name('payslips')"));

check('the stylesheet exists and carries a dark value for every light token',
    (() => {
        if (!exists('public/assets/css/employees.css')) {
            return false;
        }

        const css = read('public/assets/css/employees.css');
        const light = (css.slice(0, css.indexOf('[data-theme="dark"]')).match(/--emp-[a-z-]+:/g) || [])
            .map(token => token.replace(':', ''));
        const dark = (css.slice(css.indexOf('[data-theme="dark"]')).match(/--emp-[a-z-]+:/g) || [])
            .map(token => token.replace(':', ''));

        return light.length >= 8 && light.every(token => dark.includes(token));
    })());

check('the record page shows the four things it is for',
    /document-checklist/.test(userShow) && /Edit the record/.test(userShow)
    && /users\.payslips\.store/.test(userShow) && /users\.documents\.store/.test(userShow));

/* ---- the record, read in tabs (the vendor page's pattern) ---- */
check('the record is a tabbed page, and the tabs come from one list',
    /private const TABS = \[/.test(userController)
    && /'tabs' => self::TABS,/.test(userController)
    && /@foreach \(\$tabs as \$key => \$label\)/.test(userShow)
    && /\$tabs\['?\w+'?\]|array_key_exists\(\$tab, self::TABS\)/.test(userController));

check('the tabs are links, so every panel has a URL',
    /class="master-tab \{\{ \$tab === \$key \? 'is-active' : '' \}\}"/.test(userShow)
    && /data-user-tab-link/.test(userShow)
    && /route\('users\.show', \['user' => \$user, 'tab' => \$key/.test(userShow)
    && /\$tabUrl = fn \(string \$key\)/.test(userShow));

check('the record uses the shared tab vocabulary, not a private one',
    /master-tabs-card/.test(userShow) && /master-tabs-panels/.test(userShow)
    && /\.master-tabs-card \{/.test(read('public/assets/css/master-detail.css'))
    && /\.master-tab\.is-active \{/.test(read('public/assets/css/master-detail.css')));

check('a panel is drawn only for the tab that is open',
    /\$tab === 'overview'/.test(userShow)
    && /\$tab === 'salary'/.test(userShow)
    && /\$tab === 'documents'/.test(userShow)
    && /\$tab === 'work'/.test(userShow)
    && !/\$tab === 'payslips'/.test(userShow)
    && /self::TABS\) \? \$tab : 'overview';/.test(userController));

/* The salary panel carries the ledger entries *and* the slips, which is the
   whole point of merging the tabs: the month and the paper for the month are
   read together, not one tab apart. */
check('the salary tab is one table, and its forms are dialogs',
    /employees\.partials\.pay-table/.test(userShow)
    && !/partials\.payslip-table/.test(userShow)
    && /data-payslip-open="payslipForm"/.test(userShow)
    && (userShow.match(/master-modal-card is-wide/g) || []).length === 2
    && /@include\('employees\.partials\.payslip-form'/.test(userShow));

check('the pay table carries the month, the money and the payslip on one row',
    /<th scope="col">Payslip<\/th>/.test(payTable)
    && /<th scope="col" class="is-num">Amount<\/th>/.test(payTable)
    && /route\('users\.payslips\.pdf'/.test(payTable)
    && /route\('my\.payslips\.pdf'/.test(payTable)
    && /slipNumber\(\)/.test(payTable));

/* One row per entry, and the month's slip drawn once — on the month's newest
   row — with the row below it pointing up rather than saying "no payslip". */
check('a month with two entries draws its payslip once',
    /public function payRows\(int \$year, Collection \$entries, Collection \$slips\): Collection/.test(payMonthsService)
    && /'slip' => \$owns \? \(\$byPeriod\[\$period\] \?\? null\) : null,/.test(payMonthsService)
    && /'owns_slip' => \$owns,/.test(payMonthsService)
    && /Same month as the row above/.test(payTable)
    && /No ledger entry/.test(payTable));

check('the retired payslips tab is a name that resolves, not a panel',
    /private const TAB_ALIASES = \[/.test(userController)
    && /'payslips' => 'salary'/.test(userController)
    && /self::TAB_ALIASES\[\$tab\] \?\? \$tab/.test(userController)
    && /'tab' => 'salary'/.test(userIndex));

/* ---- the list, on the same surface as shipments and the ledger ---- */
check('the list wears the same five-tile strip as the other two lists',
    /master-stats desktop-only/.test(userIndex)
    && (userIndex.match(/master-stat--flat/g) || []).length === 5);

check('the list opens with role chips that carry their counts',
    /master-list-chips/.test(userIndex)
    && /master-list-chip-count/.test(userIndex)
    && /\{\{ \$roleCounts\[\$key\] \?\? 0 \}\}/.test(userIndex)
    && (userIndex.match(/master-list-chip /g) || []).length >= 1);

check('the chip-owned role survives the filter form below it',
    /type="hidden" name="role"/.test(userIndex));

check('every filter the list can hold is offered as a removable chip',
    /\$filterChips/.test(userIndex)
    && /master-list-applied-chip/.test(userIndex)
    && /\$chipUrl\(\$chip\['key'\]\)/.test(userIndex)
    && /private function filterChips/.test(userController)
    && /'joined' => DateRanges::LABELS/.test(userController));

check('the list can be exported, under the filters on screen',
    /route\('users\.export', \$baseFilters\)/.test(userIndex)
    && /\$baseFilters = request\(\)->except\(\['page'\]\)/.test(userIndex)
    && /public function export\(Request \$request\)/.test(userController)
    && routes.includes("/users/export")
    && routes.includes("'users.export'"));

check('the export is registered before the record wildcard',
    (() => {
        const at = routes.indexOf("Route::get('/users/export'");
        const wildcard = routes.indexOf("Route::get('/users/{user}'");

        return at !== -1 && wildcard !== -1 && at < wildcard;
    })());

check('the export does not put a full account number in a spreadsheet',
    /->mask\(\$person->bank_account_number\)/.test(userController));

check('the list is dense enough and clickable, through the shared toolkit',
    /data-density="comfortable"/.test(userIndex)
    && /data-density="compact"/.test(userIndex)
    && /data-href="\{\{ route\('users\.show', \$user\) \}\}"/.test(userIndex)
    && /MasterList\.rowNavigation\(\{ root: '\.user-index' \}\)/.test(read('public/assets/js/users.js'))
    && /MasterList\.density\(\{ root: '\.user-index', key: 'misspack\.users\.density' \}\)/.test(read('public/assets/js/users.js')));

check('the row menu carries an icon on every item',
    (() => {
        /* the menu only — the empty state's buttons come later in the file and
           are not menu items */
        const from = userIndex.indexOf('master-dropdown-menu');
        const to = userIndex.indexOf('master-list-empty', from);
        const menu = userIndex.slice(from, to === -1 ? undefined : to);
        const items = [...menu.matchAll(/<(a|button)\b[\s\S]*?<\/\1>/g)].map(m => m[0]);

        return items.length >= 4 && items.every(item => /<i class="fa/.test(item));
    })());

check('the users list says which side of the line each account is on',
    /roleLabel\(\)/.test(userIndex)
    && /master-list-chip/.test(userIndex)
    && /users\.show/.test(userIndex));

check('the list paints the shared chrome without restating it',
    /class="users user-index master-list"/.test(userIndex)
    && ! /\.master-list/.test(read('public/assets/css/users.css'))
    /* the sheet itself is the shell's, loaded once for every page */
    && /assets\/css\/master-list\.css/.test(read('resources/views/layouts/app.blade.php')));

check('the ledger\'s employee field is one list, employees first',
    /public static function employeePicker\(\)/.test(userModel)
    && /User::employeePicker\(\)/.test(cashflowController)
    && /sortBy\(fn \(self \$user\) => \$user->isEmployee\(\) \? 0 : 1\)/.test(userModel));

/* ------------------------------------------------------------ 7. the seed */

check('a fresh install has both sides of the line',
    /User::ROLE_ADMIN/.test(seeder) && /User::ROLE_EMPLOYEE/.test(seeder)
    && /'employee_code'/.test(seeder));

check('the seed never overwrites an employment record somebody edited',
    /wasRecentlyCreated === false && blank\(\$user->role\)/.test(seeder)
    || /firstOrCreate/.test(seeder));

/* --------------------------------------------------- 8. the schema is asked */

/** Every column any migration declares — `up()` only: a `down()` drops them. */
const declaredColumns = (() => {
    const columns = new Set();
    const types = 'string|text|longText|mediumText|integer|bigInteger|unsignedBigInteger|unsignedInteger|tinyInteger|smallInteger|'
        + 'boolean|date|dateTime|timestamp|decimal|double|float|json|enum|foreignId|foreignUuid|uuid|binary|ipAddress|rememberToken';

    fs.readdirSync(path.join(ROOT, 'database/migrations')).forEach(file => {
        const sql = read('database/migrations/' + file);
        const down = sql.indexOf('function down(');
        const up = sql.slice(sql.indexOf('function up('), down > -1 ? down : sql.length);

        [...up.matchAll(new RegExp('->(?:' + types + ')\\(\\s*\'([a-z_]+)\'', 'g'))]
            .forEach(match => columns.add(match[1]));

        if (/->timestamps\(\)/.test(up)) {
            columns.add('created_at');
            columns.add('updated_at');
        }
    });

    return columns;
})();

/** A result column is not a table column (`select(... as total)`). */
const SELECT_ALIASES = new Set(['bucket', 'total', 'total_value', 'entry', 'done', 'required']);

const walk = dir => fs.readdirSync(path.join(ROOT, dir), { withFileTypes: true }).flatMap(entry =>
    entry.isDirectory()
        ? walk(dir + '/' + entry.name)
        : (entry.name.endsWith('.php') ? [dir + '/' + entry.name] : []));

const undeclared = [...new Set(walk('app').flatMap(file =>
    [...read(file).matchAll(/->(?:where|whereNull|whereNotNull|orderBy|orderByDesc|whereDate|whereYear|whereMonth|whereDay|pluck|groupBy|increment|decrement)\(\s*'([a-z_][a-z_0-9]*)'/g)]
        .map(match => match[1])
        .filter(column => !declaredColumns.has(column) && !SELECT_ALIASES.has(column))
        .map(column => file + ' → ' + column)))];

check('no query names a column no migration declares',
    undeclared.length === 0, undeclared.slice(0, 6).join(', '));

check('the list offers a filter only when the query can apply it',
    /private function availableFilters\(\): array/.test(userController)
    && /'availableFilters' => \$available/.test(userController)
    && /@if \(\$availableFilters\['role'\]/.test(userIndex)
    && /@if \(\$availableFilters\['department'\]/.test(userIndex)
    && /@if \(\$availableFilters\['status'\]/.test(userIndex)
    && /@if \(\$availableFilters\['code'\]/.test(userIndex)
    && /@if \(\$availableFilters\['joined'\]/.test(userIndex));

check('the query claims a filter only when its column is there',
    /private function filtered\(array \$filters, array \$available\)/.test(userController)
    && /\$available\['code'\]/.test(userController)
    && /\$available\['status'\]/.test(userController)
    && /\$available\['joined'\]/.test(userController)
    && /array_filter\(self::SEARCH_COLUMNS/.test(userController));

check('a filter this database cannot apply is never shown as applied',
    /private function filterChips\(array \$filters, array \$available\)/.test(userController)
    && /! \(\$available\[\$key\] \?\? false\)/.test(userController)
    && /'filtersActive' => \$chips !== \[\]/.test(userController));

check('the row counts are asked for only when their tables exist',
    /private function counting\(Builder \$query\): Builder/.test(userController)
    && /'payslips' => 'employee_payslips', 'employeeDocuments' => 'employee_documents'/.test(userController)
    && /\$this->counting\(\$this->filtered\(/.test(userController));

check('a tile shows a figure, not arithmetic on other figures',
    (() => {
        const values = [...userIndex.matchAll(/<p class="master-stat-value">([\s\S]*?)<\/p>/g)].map(match => match[1].trim());

        return values.length >= 5 && values.every(value =>
            /^\{\{\s*(?:\\App\\Helpers\\CommonHelper::indianCurrency\()?\$stats\['[a-z_]+'\](?:\))?\s*\}\}$/.test(value));
    })());

check('the employee-code tile counts what the filter counts',
    /\$stats\['with_code'\]/.test(userIndex)
    && /'with_code' => \$withCode/.test(userController)
    && /whereNotNull\('employee_code'\)->where\('employee_code', '!=', ''\)->count\(\)/.test(userController));

/* ------------------------------------ 8. one payslip, three surfaces (61)
   Task 61 asked for a detailed payslip that generates itself and can be
   printed and shared. The failure this section exists to catch is the slip
   assembled twice — once for the screen and once for the paper — so that the
   figure the employee is holding stops matching the figure on the record.
   There is one document (App\Services\PayslipDocument), one sheet (the
   partial), and the totals on it are sums of its own lines. */

check('one payslip, one document: the paper and the download are one build',
    /public function build\(EmployeePayslip \$payslip, \?string \$context = 'app'\): array/.test(payslipDoc)
    && /public function download\(EmployeePayslip \$payslip\): Response/.test(payslipDoc)
    && /class_exists\(\\Barryvdh\\DomPDF\\Facade\\Pdf::class\)/.test(payslipDoc)
    && /employees\.partials\.payslip', \['doc' => \$doc/.test(payslipPrint));

check('the slip is built from the row, not read back from a stored file',
    !/Storage::/.test(payslipDoc)
    && /route\('users\.payslips\.pdf'/.test(payTable)
    && /route\('my\.payslips\.pdf'/.test(payTable)
    && !/payslips\.pdf/.test(payslipModel));

check('the printed slip prints: standalone page, A4 paper, print dialog without dompdf',
    /window\.print\(\);/.test(payslipPrint)
    && /pdfFallbackMessage/.test(payslipPrint)
    && /@media print/.test(payslipRules)
    && /@page \{/.test(payslipRules)
    && /body\.ps-standalone/.test(payslipCss));

/* A form key is either a number or a list. When it was both, PHP kept the
   last rule it saw and the one-button issue / back-to-draft form — which sends
   the totals it read off the row — failed validation. */
check('the slip\'s lines and its totals are different fields, not one key twice',
    /'earning_lines' => \['nullable', 'array', 'max:10'\]/.test(payslipController)
    && /'deduction_lines' => \['nullable', 'array', 'max:10'\]/.test(payslipController)
    && (payslipController.match(/'deductions' => \[/g) || []).length === 1
    && /'deductions' => \['nullable', 'numeric', 'min:0'\]/.test(payslipController)
    && /array_key_exists\('earning_lines', \$data\)/.test(payslipController)
    && /name="\{\{ \$meta\['field'\] \}\}\[\{\{ \$i \}\}\]\[label\]"/.test(payslipForm)
    && /'earnings' => \['field' => 'earning_lines'/.test(payslipForm));

check('a slip total is the sum of the slip\'s own lines, on the page and on the paper',
    /array_sum\(array_column\(\$earnings, 'amount'\)\)/.test(payslipDoc)
    && /array_sum\(array_column\(\$components\['earnings'\], 'amount'\)\)/.test(payslipController)
    && /array_sum\(array_column\(\$components\['deductions'\], 'amount'\)\)/.test(payslipController)
    && /'net' => \(float\) \$payslip->net_amount/.test(payslipDoc));

check('the net is spelled out, by the one helper that spells money',
    /function inWords\(\$amount, \?string \$currency = 'INR'\): string/.test(helper)
    && /CommonHelper::inWords\(\(float\) \$payslip->net_amount, \$currency\)/.test(payslipDoc)
    && /net_in_words/.test(payslipSheet));

check('a draft slip cannot be printed either, not only unlisted',
    /private function guardPayslip\(EmployeePayslip \$payslip\): \?RedirectResponse/.test(workspace)
    && times(workspace, '$denied = $this->guardPayslip($payslip);') === 2
    && /has not been issued yet/.test(workspace));

check('the office hands the slip over without the figures in the message',
    /public function shareMessage\(\): string/.test(payslipModel)
    && /public function whatsappUrl\(\?string \$phone\): \?string/.test(payslipModel)
    && /public function mailUrl\(\?string \$email\): \?string/.test(payslipModel)
    && /'https:\/\/wa\.me\/'\.\$digits\.'\?text='\.rawurlencode\(\$this->shareMessage\(\)\)/.test(payslipModel)
    && /'mailto:'\.\$email/.test(payslipModel)
    && /whatsappUrl\(\$user->mobile\)/.test(payTable)
    && /mailUrl\(\$user->email\)/.test(payTable));

check('one table of months, read by the office and by the employee',
    /employees\.partials\.pay-table/.test(userShow)
    && /employees\.partials\.pay-table/.test(employeeSalary)
    && /'context' => 'office'/.test(userShow)
    && /'context' => 'employee'/.test(employeeSalary));

check('the office on the slip is the office on the invoice, and the account is masked',
    /SalesInvoice::defaultSellerDetails\(\)/.test(payslipDoc)
    && times(payslipDoc, '->mask(') >= 2
    && /bank_account_number/.test(payslipDoc));

/* A dialog is the shared sheet, and the shared sheet is what scrolls: the
   card is the flex column, the body is the scroll region, and the form is the
   card's own child. A private copy of that trio inherits none of its fixes. */
check('the payslip dialogs are the shared sheet, and they scroll',
    /<div class="master-modal ({{ \$openPayslipDialog === 'create'|<)/.test(userShow)
    && /<div class="master-modal-card is-wide" role="dialog" aria-modal="true"/.test(userShow)
    && /class="master-modal-body" data-payslip-form/.test(payslipForm)
    && /class="master-modal-footer"/.test(payslipForm)
    && /class="master-modal-close" data-close-modal/.test(payslipForm));

check('the two dialogs are the two halves of one form',
    /'mode' => 'create'/.test(userShow)
    && /'mode' => 'edit'/.test(userShow)
    && /@method\('PUT'\)/.test(userShow)
    && /route\('users\.payslips\.store', \$user\)/.test(userShow)
    && /route\('users\.payslips\.update'/.test(userShow)
    && /route\('users\.payslips\.destroy'/.test(userShow)
    && (payslipForm.match(/\$edit \?/g) || []).length >= 4);

/* A dialog cannot hold a second form, so remove submits the form that waits
   outside the card — through the button's own form attribute. */
check('the remove button submits a form outside the dialog card',
    /<form method="POST" id="payslipEditDelete"/.test(userShow)
    && /form="\{\{ \$dialog \}\}Delete"/.test(payslipForm)
    && !/payslipForm[\s\S]{0,4000}<form method="POST"[\s\S]{0,200}<form method="POST"/.test(userShow));

check('a ledger row can generate its month\'s payslip, prefilled from the row',
    /'month' => \$row\['period'\]/.test(payTable)
    && /Generate payslip/.test(payTable)
    && /private function payslipPrefill\(\$entries\): array/.test(userController)
    && /\$row->entry_date\?->format\('Y-m'\) === \$month && \$row->isMoneyOut\(\)/.test(userController)
    && /'earning_amount' => \$entry \? \(string\) \$entry->amountMoved\(\)/.test(userController)
    && /filled\(\$prefill\['earning_amount'\] \?\? null\)/.test(payslipForm)
    && /@method\(\\'GET\\'\)/.test('') === false);

/* The dialog reopens on a validation failure — a form that loses what was
   typed is worse than no dialog. */
check('a failed save reopens the dialog it came from',
    /private function payslipDialog\(\?EmployeePayslip \$editing\): \?string/.test(userController)
    && /getBag\('default'\)->getMessages\(\)/.test(userController)
    && /Str::startsWith\(\$key, \$fields\)/.test(userController)
    && /'openPayslipDialog' => \$this->payslipDialog\(\$editingPayslip\)/.test(userController)
    && /\$openPayslipDialog === 'create' \? 'open' : ''/.test(userShow));

/* The totals as they are typed, in the browser's own rupee format, which is
   the same contract as the helper the printed slip uses. */
check('the dialog totals the lines as they are typed, in the shared format',
    /data-payslip-amount/.test(payslipForm)
    && /data-payslip-total="earned"/.test(payslipForm)
    && /data-payslip-total="deducted"/.test(payslipForm)
    && /data-payslip-total="net"/.test(payslipForm)
    && /function payslipDialogs\(\)/.test(userJs)
    && /window\.misspackFormat\.inr\(value\)/.test(userJs)
    && /total\('earning_lines'\)/.test(userJs)
    && /total\('deduction_lines'\)/.test(userJs));

/* One field grid, defined once, for every dialog that names it. */
check('the field grid a dialog lays its fields out in exists, once',
    /^\.master-form-grid \{/m.test(read('public/assets/css/master-form.css'))
    && /grid-template-columns: repeat\(2, minmax\(0, 1fr\)\)/.test(read('public/assets/css/master-form.css'))
    && /\.master-modal-card\.is-wide \{/.test(read('public/assets/css/master-index.css'))
    && /master-form-grid/.test(payslipForm));

check('the arithmetic of a payslip has a test that runs',
    exists('tests/Unit/EmployeePayslipTest.php')
    && read('tests/Unit/EmployeePayslipTest.php').includes('test_the_totals_are_the_sum_of_the_slips_own_lines')
    && read('tests/Unit/EmployeePayslipTest.php').includes('php artisan test --filter=EmployeePayslipTest'));

check('the slip sheet loads its own stylesheet wherever it is drawn',
    /@push\('styles'\)/.test(payslipSheet)
    && /assets\/css\/payslip\.css/.test(payslipSheet)
    && /assets\/css\/payslip\.css/.test(payslipPrint)
    && /'payslip\.css',/.test(read('tools/checks/design-check.cjs')));

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\nemployees: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed');
process.exit(failed.length ? 1 : 0);

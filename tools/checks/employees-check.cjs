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
/* declared with the rest: a `const` read above its own line is a TDZ crash, not
   a failing check — and it takes the whole gate with it */
const accountController = read('app/Http/Controllers/AccountController.php');
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
const appLayoutJs = read('public/assets/js/app-layout.js');

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

/* The form and the rule, together. The two pages that edit a person's own
   fields render one partial, and the two controllers that accept them filter
   through one service list — so "only the fields that are theirs" is a property
   of the code rather than of the markup on one page. */
const ownDetails = read('resources/views/users/partials/own-details.blade.php');
const ownPassword = read('resources/views/users/partials/own-password.blade.php');
const accountView = read('resources/views/account/index.blade.php');
const usersSheet = read('public/assets/css/users.css');

check('the fields that are yours are one form, rendered by both pages',
    /name="mobile"/.test(ownDetails)
    && /name="address"/.test(ownDetails)
    && /name="date_of_birth"/.test(ownDetails)
    && /name="emergency_contact_name"/.test(ownDetails)
    && /name="emergency_contact_mobile"/.test(ownDetails)
    && !/name="designation"|name="bank_account_number"|name="role"|name="salary"/.test(ownDetails)
    && (read('resources/views/employees/profile.blade.php').match(/users\.partials\.own-details/g) || []).length === 1
    && (accountView.match(/users\.partials\.own-details/g) || []).length === 1,
    'one partial, two pages — a second copy of the field list is how they drift');

check('and both controllers validate with the service rule, then filter by the service list',
    /ownFieldRules\(\): array/.test(access)
    && (workspace.match(/array_intersect_key\(/g) || []).length >= 1
    && /array_flip\(\$this->access->ownEditableFields\(\)\)/.test(workspace)
    && /array_intersect_key\(/.test(accountController)
    && /array_flip\(\$fields\)/.test(accountController),
    'the door is the field list, not the names the form happens to post');

/* A rule with no matching writable field is either a typo or a permission that
   slipped in through the rule list: `designation` in `ownFieldRules()` reads as
   "the person may set their designation", and only the intersection stops it. */
check('the rule list and the field list are the same five names',
    (() => {
        const rules = access.slice(access.indexOf('ownFieldRules()'), access.indexOf('ownFieldRules()') + 900);
        const names = [...rules.matchAll(/^\s*'(\w+)' => \[/gm)].map(m => m[1]);
        const fields = (access.match(/return \['mobile', 'address', 'emergency_contact_name', 'emergency_contact_mobile', 'date_of_birth'\];/) || [])[0];

        return fields
            && names.length === 5
            && ['mobile', 'address', 'emergency_contact_name', 'emergency_contact_mobile', 'date_of_birth']
                .every(name => names.includes(name));
    })(),
    'the rules ask for exactly what the person may write — no more, no less');

check('the password is one form too, and it asks for the current one',
    /name="current_password"/.test(ownPassword)
    && /name="password_confirmation"/.test(ownPassword)
    && (accountView.match(/users\.partials\.own-password/g) || []).length === 1
    && (read('resources/views/employees/profile.blade.php').match(/users\.partials\.own-password/g) || []).length === 1
    /* One form, and no second one hiding behind it: a hand-written password
       field anywhere but the partial is the copy that drifts, and a second form
       posting to the same action is two forms for one action. */
    && (accountView.match(/name="password"/g) || []).length === 0
    && (accountView.match(/name="password_confirmation"/g) || []).length === 0
    && (read('resources/views/employees/profile.blade.php').match(/name="password"/g) || []).length === 0
    && (accountView.match(/account\.password\.update/g) || []).length === 1
    && (read('resources/views/employees/profile.blade.php').match(/my\.password\.update/g) || []).length === 1
    && /'current_password' => \['required', 'current_password'\]/.test(accountController),
    'a session left open on a shared machine is what this field is for');

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
/* The salary panel is sliced out of the page, so the count is of *that* panel:
   the documents and work panels carry tables of their own. */
const salaryPanel = (() => {
    const at = userShow.indexOf("$tab === 'salary'");
    const end = userShow.indexOf("$tab === 'documents'", at);
    return at === -1 || end === -1 ? '' : userShow.slice(at, end);
})();

check('the salary tab is one table, and its forms are dialogs',
    salaryPanel !== ''
    && /employees\.partials\.pay-table/.test(salaryPanel)
    && !/partials\.payslip-table/.test(userShow)
    && (salaryPanel.match(/<table/g) || []).length === 0
    && (salaryPanel.match(/master-table-card/g) || []).length === 1
    && /data-payslip-open="payslipForm"/.test(salaryPanel)
    && (salaryPanel.match(/master-modal-card is-wide/g) || []).length === 2
    && /@include\('employees\.partials\.payslip-form'/.test(salaryPanel),
    'one table in the salary panel — found '
    + (salaryPanel.match(/master-table-card/g) || []).length + ' table cards and '
    + (salaryPanel.match(/<table/g) || []).length + ' inline tables');

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
const SELECT_ALIASES = new Set(['bucket', 'total', 'total_value', 'entry', 'done', 'required', 'aggregate']);

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

check('URL-opened payslip dialogs lock the page and unlock it when closed',
    /document\.querySelectorAll\('\.master-modal\.open'\)\.forEach\(openMasterModal\)/.test(appLayoutJs)
    && /document\.body\.classList\.add\('master-modal-open'\)/.test(appLayoutJs)
    && /if \(!document\.querySelector\('\.master-modal\.open'\)\)[\s\S]*?classList\.remove\('master-modal-open'\)/.test(appLayoutJs));

check('the two dialogs are the two halves of one form',
    /'mode' => 'create'/.test(userShow)
    && /'mode' => 'edit'/.test(userShow)
    && /@method\('PUT'\)/.test(userShow)
    && /route\('users\.payslips\.store', (?:\$user|\['user' => \$user)/.test(userShow)
    && /route\('users\.payslips\.update'/.test(userShow)
    && /route\('users\.payslips\.destroy'/.test(userShow)
    && (payslipForm.match(/\$edit \?/g) || []).length >= 4);

/* A dialog cannot hold a second form, so remove submits the form that waits
   outside the card — through the button's own form attribute. */
check('the remove button submits a form outside the dialog card',
    /<form method="POST" id="payslipEditDelete"/.test(userShow)
    && /@method\('DELETE'\)/.test(userShow)
    && /form="\{\{ \$dialog \}\}Delete"/.test(payslipForm)
    && !/payslipForm[\s\S]{0,4000}<form method="POST"[\s\S]{0,200}<form method="POST"/.test(userShow));

check('payslip deletion is ownership-checked, removes its file, and flashes success',
    /Route::delete\('\/users\/\{user\}\/payslips\/\{payslip\}'/.test(routes)
    && /public function destroy\(Request \$request, User \$user, EmployeePayslip \$payslip\): RedirectResponse/.test(payslipController)
    && /public function destroy[\s\S]*?assertOwned\(\$user, \$payslip\)/.test(payslipController)
    && /Storage::disk\('public'\)->delete\(\$payslip->file_path\)/.test(payslipController)
    && /\$payslip->delete\(\)/.test(payslipController)
    && /The payslip for '\.\$label\.' was removed\./.test(payslipController));

check('a ledger row can generate its month\'s payslip, prefilled from the row',
    /'month' => \$row\['period'\]/.test(payTable)
    && /Generate payslip/.test(payTable)
    && /private function payslipPrefill\(\$entries\): array/.test(userController)
    && /\$row->entry_date\?->format\('Y-m'\) === \$month && \$row->isMoneyOut\(\)/.test(userController)
    && /'earning_amount' => \$entry \? \(string\) \$entry->amountMoved\(\)/.test(userController)
    && /filled\(\$prefill\['earning_amount'\] \?\? null\)/.test(payslipForm)
    && /'#payslipForm'/.test(payTable)
    && /'#payslipEdit'/.test(payTable)
    && /@method\(\\'GET\\'\)/.test('') === false);

/* The dialog reopens on a validation failure — a form that loses what was
   typed is worse than no dialog. */
check('a failed save reopens the dialog it came from',
    /private function payslipDialog\(\?EmployeePayslip \$editing\): \?string/.test(userController)
    && /getBag\('default'\)->getMessages\(\)/.test(userController)
    && /Str::startsWith\(\$key, \$fields\)/.test(userController)
    && /'openPayslipDialog' => \$this->payslipDialog\(\$editingPayslip\)/.test(userController)
    && /\$openPayslipDialog === 'create' \? 'open' : ''/.test(userShow));

check('successful payslip actions return to salary with a visible success flash',
    /private function returnToSalary\(Request \$request, User \$user, string \$message\): RedirectResponse/.test(payslipController)
    && /\$parameters = \['user' => \$user, 'tab' => 'salary'\]/.test(payslipController)
    && /\$request->query\('year', ''\)/.test(payslipController)
    && (payslipController.match(/return \$this->returnToSalary\(\$request, \$user/g) || []).length === 3
    && /route\('users\.payslips\.store', \['user' => \$user, 'tab' => 'salary', 'year' => \$year\]\)/.test(userShow)
    && /route\('users\.payslips\.update', \['user' => \$user, 'payslip' => \$editingPayslip, 'tab' => 'salary', 'year' => \$year\]\)/.test(userShow)
    && /route\('users\.payslips\.destroy', \['user' => \$user, 'payslip' => \$editingPayslip, 'tab' => 'salary', 'year' => \$year\]\)/.test(userShow)
    && /@if\(session\('success'\)\)[\s\S]*?MasterAlert\.toast\(@json\(session\('success'\)\), 'success'/.test(layout));

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

/* ----------------------------- 9. a view array is evaluated top to bottom
   `return view('x', [...])` reads each value as the array is built, so a
   variable first assigned *inside* that array — or anywhere below it — is an
   undefined variable at the line that reads it, and the page is a 500. That is
   how `/users/2?tab=salary` died: `'payRows' => $profile->payRows($year,
   $salaryEntries, $payslips)` sat above the `'salaryEntries' => ...` that
   created it. Simple to write, invisible in review, fatal in front of payroll —
   so every controller's view arrays are swept for it, not just this one. */
const viewArrayOffenders = (() => {
    const files = [];
    const walk = dir => fs.readdirSync(dir, { withFileTypes: true }).forEach(entry => {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full);
        else if (entry.name.endsWith('.php')) files.push(full);
    });
    walk(path.join(ROOT, 'app/Http/Controllers'));

    const out = [];

    for (const file of files) {
        const text = fs.readFileSync(file, 'utf8');

        for (const match of text.matchAll(/return view\(\s*'[^']+'\s*,\s*\[/g)) {
            const open = text.indexOf('[', match.index + match[0].length - 1);
            let depth = 0;
            let end = open;

            for (let i = open; i < text.length; i++) {
                if (text[i] === '[') depth++;
                else if (text[i] === ']') {
                    depth--;
                    if (depth === 0) { end = i; break; }
                }
            }

            const array = text.slice(open, end + 1);

            /* the enclosing *method*: a `function ()` inside a query chain would
               otherwise cut the window of declared variables short */
            let methodAt = -1;
            for (const m of text.slice(0, match.index).matchAll(/^[ \t]*(?:public|protected|private)\s+(?:static\s+)?function\s+\w+/gm)) {
                methodAt = m.index;
            }

            const signature = text.slice(methodAt, text.indexOf('{', methodAt));
            const declared = new Set([...signature.matchAll(/\$(\w+)/g)].map(m => m[1]));

            for (const assignment of text.slice(methodAt, match.index).matchAll(/\$(\w+)\s*=[^=]/g)) {
                declared.add(assignment[1]);
            }

            /* a closure's own parameters are locals of the closure, not of the
               array it is written inside */
            const closureParams = new Set([...array.matchAll(/(?:fn\s*\(|function\s*\()([^)]*)\)/g)]
                .flatMap(m => [...m[1].matchAll(/\$(\w+)/g)].map(x => x[1])));

            for (const used of new Set([...array.matchAll(/\$(\w+)/g)].map(m => m[1]))) {
                if (used === 'this' || declared.has(used) || closureParams.has(used)) continue;
                out.push(path.relative(ROOT, file) + ':' + text.slice(0, open).split('\n').length + ' $' + used);
            }
        }
    }

    return out;
})();

check('every variable a view array reads is assigned above it',
    viewArrayOffenders.length === 0, viewArrayOffenders.join(', '));

/* ------------------------------- 10. the record's two long sections (63)
   The details tab held the same twenty-one fields twice: once as read-only
   cards and once as a form, laid out three answers deep. The read-only half was
   a box per field (`.master-detail-list`, a class no sheet defined, so each
   field fell back to the bordered `.master-info`), and the form was two to a
   row with no groups. Both are now the shared vocabulary: the facts grid, and
   the three-up field grid under a section label. This holds the shape — and
   holds the field list, because a regrouping that loses a field loses the
   office's ability to record it. */

const profileView = read('resources/views/employees/profile.blade.php');

/* The rule is defined once (design-check guards the CSS); this is the other
   half — the tab that is four cards of one weight has to ask for it, or the
   definition is decoration. */
check('the record\'s four cards are read in two equal columns',
    /<div class="master-grid is-even">/.test(userShow)
    && (userShow.match(/<div class="master-grid is-even">/g) || []).length === 1,
    'the details tab is four cards of the same weight, not a main column and an aside');

check('the two read-only records are the shared facts grid, not a box per field',
    /<div class="master-facts">/.test(userShow)
    && /<div class="master-facts">/.test(profileView)
    && !/master-detail-list/.test(userShow + profileView),
    'expected `.master-facts` in both, and no `.master-detail-list` anywhere');

check('and a value nobody filled in is muted without wearing an empty class',
    (userShow.match(/<strong @class\(\['is-blank' => blank\(\$field\['value'\]\)\]\)>/g) || []).length === 2
    && /<strong @class\(\['is-blank' => blank\(\$field\['value'\]\)\]\)>/.test(profileView),
    '`@class` omits the attribute; `class=""` keeps it and defeats `:not([class])`');

/* The record form, from its opening tag to its action bar. `@endif` is not a
   terminator — the form holds `@if ($isSelf)` blocks of its own, and the
   cutoff silently truncated the slice to the first four fields. */
const recordFormStart = userShow.indexOf('action="{{ route(\'users.update\', $user) }}"');
const recordForm = userShow.slice(recordFormStart, userShow.indexOf('Save the record', recordFormStart) + 40);

check('the record form was located, not guessed',
    recordFormStart > 0 && /<form method="POST"/.test(userShow.slice(recordFormStart - 80, recordFormStart)),
    'the form action moved; every guard below would be reading an empty string');

const FIELDS = [
    'name', 'email', 'role', 'employee_code', 'designation', 'department',
    'date_of_joining', 'date_of_birth', 'employment_type', 'employment_status',
    'pan_number', 'mobile', 'bank_name', 'bank_account_name', 'bank_account_number',
    'bank_ifsc', 'address', 'emergency_contact_name', 'emergency_contact_mobile',
    'password', 'password_confirmation',
];

const missingFields = FIELDS.filter(field => !recordForm.includes('name="' + field + '"'));

check('the record form still posts every field the office can set',
    missingFields.length === 0, missingFields.join(', '));

check('the form is grouped, and three fields to a row',
    (recordForm.match(/<p class="master-section-label">/g) || []).length === 5
    && (recordForm.match(/<div class="master-form-grid is-three">/g) || []).length === 5,
    'expected five labelled groups, each its own three-up grid');

check('and its save button stays in reach while the form scrolls',
    /<div class="master-actions is-sticky">/.test(recordForm)
    && /Save the record/.test(recordForm),
    'a nine-row form hides its own action bar without one');

/* --------------------------- 11. the photo band and the password fields (64)
   Both user dialogs name six classes for the photo band and one for the
   password field's wrapper. Every one of them was undefined, so the band was
   three stacked lines — the label, the avatar's own contents, the two controls
   — and the eye toggle for a password resolved against the dialog card and
   floated into a corner. `design-check` now holds "named but undefined" for the
   whole application; this holds the *shape*, because a guard that only asks for
   a declaration would pass on `.master-avatar-row { display: block }`. */

const usersIndex = read('resources/views/users/index.blade.php');
const formSheet = read('public/assets/css/master-form.css');
const indexSheet = read('public/assets/css/master-index.css');

/* A rule is read by its own body, never by a lazy match across the file: the
   first version of these guards matched `.master-avatar-row { … display: flex`
   by running past the row's closing brace into the next rule that happened to
   say the same thing, so the band could stack and the guard stayed green. */
const cssRule = (sheet, selector) => {
    const at = sheet.indexOf(selector + ' {');
    if (at === -1) return '';
    const from = sheet.indexOf('{', at);
    const to = sheet.indexOf('}', from);
    return from === -1 || to === -1 ? '' : sheet.slice(from + 1, to);
};

check('the photo band is a row: a circle, a sentence and the two controls',
    /display:\s*flex/.test(cssRule(formSheet, '.master-avatar-row'))
    && /align-items:\s*center/.test(cssRule(formSheet, '.master-avatar-row'))
    && /width:\s*56px/.test(cssRule(formSheet, '.master-avatar-preview'))
    && /height:\s*56px/.test(cssRule(formSheet, '.master-avatar-preview'))
    && /border-radius:\s*50%/.test(cssRule(formSheet, '.master-avatar-preview'))
    && /flex:\s*1 1 auto/.test(cssRule(formSheet, '.master-avatar-info'))
    && /flex-wrap:\s*wrap/.test(cssRule(formSheet, '.master-avatar-actions')),
    'the band, the circle, the column and the action row are one layout');

check('the avatar circle shows a photo when there is one and initials when there is not',
    /object-fit:\s*cover/.test(cssRule(formSheet, '.master-avatar-preview img'))
    && cssRule(formSheet, '.master-avatar-initials') !== '',
    'the preview swaps an `<img>` in; the initials span is what it swaps to');

check('a long file name truncates instead of wrapping the band',
    /text-overflow:\s*ellipsis/.test(cssRule(formSheet, '.master-file-name')),
    'expected `text-overflow: ellipsis` on `.master-file-name`');

check('the password eye is positioned against its own field, not the card',
    /position:\s*relative/.test(cssRule(formSheet, '.master-password-wrap'))
    && /padding-right:\s*42px/.test(cssRule(formSheet, '.master-password-wrap .master-input')),
    'without the wrapper the absolutely positioned eye escapes to the dialog');

check('both dialogs ask the same two questions',
    (usersIndex.match(/<div class="master-section-label">Photo<\/div>/g) || []).length === 2
    && (usersIndex.match(/<div class="master-section-label">Account security<\/div>/g) || []).length === 2
    && (usersIndex.match(/up to 2 MB/g) || []).length === 2,
    'the photo and the password blocks must not drift apart between add and edit');

check('a password rule that fails in either dialog says so',
    (usersIndex.match(/@error\('password'\)/g) || []).length === 2,
    'the edit dialog reopened with no reason given — the add dialog showed one');

check('the way out of a dialog sits on the footer line',
    /<p class="master-sub master-modal-lead">/.test(usersIndex)
    && /\.master-modal-lead \{[\s\S]*?margin: 0 auto 0 0/.test(indexSheet)
    && !/user-modal-foot[\s\S]{0,80}editRecordLink/.test(usersIndex),
    'a link at the end of a form reads as one more field');

/* ------------------------------ 12. the photo band, as a control (65)
   A file input cannot be themed and cannot be dropped on, so the band around it
   is the control: the circle is a second label for the same input, the row takes
   a dropped photo, and a refusal says why in the slot the file name would have
   used. The three parts have to agree — the circle points at the input, the row
   names the circle and the name slot, and the script reads both from the row. */

const usersScript = read('public/assets/js/users.js');
const controller = read('app/Http/Controllers/UserController.php');

const bands = [...usersIndex.matchAll(/<div class="master-avatar-row"[\s\S]*?data-avatar-remove="(\w+)"/g)];
const circles = [...usersIndex.matchAll(/<label class="master-avatar-preview" id="(\w+)" for="(\w+)"/g)];

check('the circle is a second label for the file input, so clicking it picks',
    circles.length === 2
    && circles.every(([, , input]) => usersIndex.includes('type="file" id="' + input + '"'))
    && (usersIndex.match(/class="master-avatar-camera"/g) || []).length === 2,
    'found ' + circles.length + ' labels for a file input');

check('the band says which input, which circle and which name slot it owns',
    bands.length === 2
    && bands.map(b => b[1]).sort().join() === 'clear,delete'
    && (usersIndex.match(/data-avatar-circle="\w+"\s*\n?\s*data-avatar-name="\w+"/g) || []).length === 2,
    'the add dialog clears a choice, the edit dialog files a removal — the row says which');

check('the script reads the band from the row rather than from two hard-coded ids',
    /data-avatar-drop/.test(usersScript)
    && /getAttribute\('data-avatar-circle'\)/.test(usersScript)
    && /getAttribute\('data-avatar-name'\)/.test(usersScript)
    && /wireAvatarBand/.test(usersScript),
    'one wiring function, driven by the markup');

check('a photo is refused before it is uploaded, for the reasons the server refuses it',
    (() => {
        const types = (usersScript.match(/var AVATAR_TYPES = \[([^\]]*)\]/) || [, ''])[1];
        const bytes = (usersScript.match(/var AVATAR_MAX_BYTES = ([^;]+);/) || [, ''])[1];
        const rule = controller.slice(controller.indexOf("'avatar' => ["), controller.indexOf("'avatar' => [") + 120);
        const ruleKb = (rule.match(/max:(\d+)/) || [, ''])[1];

        return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'].every(t => types.includes(t))
            && /2 \* 1024 \* 1024/.test(bytes)
            && ruleKb === '2048';
    })(),
    'the list and the limit are one rule in two languages — change one and this fails');

check('a refused photo says so, in the slot the file name would have used',
    /function sayAvatarProblem/.test(usersScript)
    && /JPG, PNG, GIF or WEBP only/.test(usersScript)
    && /is-bad/.test(usersScript)
    && /\.master-file-name\.is-bad/.test(formSheet)
    /* and the refusal is *reached*: the handler clears the input and says why,
       rather than returning quietly with a broken file still attached */
    && /this\.value = '';[\s\S]{0,120}sayAvatarProblem\(nameId, problem\)/.test(usersScript),
    'silence after choosing a file reads as a broken button');

check('a photo can be dropped on the band, or the band says it cannot take it',
    /addEventListener\('drop'/.test(usersScript)
    && /new DataTransfer\(\)/.test(usersScript)
    && /input\.files = carrier\.files/.test(usersScript)
    && /Drop is not available here/.test(usersScript),
    'the dropped file goes into the input, so what is shown is what is submitted');

check('the picker is reachable with the keyboard',
    !/id="addAvatarFile"[^>]*\shidden/.test(usersIndex)
    && !/id="editAvatarFile"[^>]*\shidden/.test(usersIndex)
    && /\.master-upload-btn input\[type="file"\] \{[\s\S]*?clip: rect\(0 0 0 0\)/.test(formSheet)
    && /\.master-avatar-picker:focus-within \.master-avatar-preview/.test(formSheet),
    '`hidden` takes the input out of the tab order — the dialog would have no keyboard route to the picker');

/* ------------------------- 13. a failed save comes back to its own dialog (65)
   After a failed *edit* the page ran `openAddModal()`: the office corrected a
   person, submitted, and got a New-user form wearing their errors — and the edit
   fields were never rendered with values, so there was nothing to come back to.
   The failure now travels with the person's id and reopens that dialog filled. */

check('a failed edit is sent back to the person it was editing',
    /catch \(ValidationException \$invalid\)/.test(controller)
    && /route\('users\.index', \['edit' => \$user->getKey\(\)\]\)/.test(controller)
    && /withErrors\(\$invalid->validator\)/.test(controller)
    && /use Illuminate\\Validation\\ValidationException;/.test(controller),
    'back() drops them at the top of the list with no way to tell which dialog failed');

check('the page reopens the dialog the errors belong to, and not the other one',
    (() => {
        const from = usersScript.indexOf("[data-open-dialog]");
        const to = usersScript.indexOf("[data-toggle-password]", from);
        const block = from === -1 || to === -1 ? '' : usersScript.slice(from, to);
        const addAt = block.indexOf("reopenKind === 'add'");

        return /\$reopenUserId = \$errors->any\(\) \? max\(0, \(int\) request\(\)->query\('edit'\)\) : 0;/.test(usersIndex)
            && /data-open-dialog="\{\{ \$errors->any\(\) \? \(\$reopenUserId \? 'edit' : 'add'\) : '' \}\}"/.test(usersIndex)
            && /reopenKind === 'edit'[\s\S]*?openModal\(byId\('editModal'\)\)/.test(block)
            && addAt > -1
            && !block.slice(0, addAt).includes('openAddModal()')
            && !/data-open-if-errors/.test(usersIndex);
    })(),
    'the guess is the bug: the add dialog opened for an edit failure');

check('and its fields still hold what was typed',
    (() => {
        const at = usersIndex.indexOf('id="editForm"');
        const form = usersIndex.slice(at, usersIndex.indexOf('master-modal-footer', at));

        return ['name', 'email', 'mobile', 'employee_code', 'department', 'designation',
            'date_of_joining', 'pan_number', 'bank_name', 'bank_account_number', 'bank_ifsc']
            .every(field => form.includes("old('" + field + "')"))
            && /\{\{ old\('address'\) \}\}/.test(form)
            && (form.match(/@selected\(old\(/g) || []).length === 3;
    })(),
    'the edit fields are filled by the fetch on the normal path and by old() on the failed one');

/* And the edit dialog must not refetch over the corrections: its action is
   server-rendered when the page came back with errors. */
check('the reopened edit form posts to the person, not to nowhere',
    /action="\{\{ \$reopenUserId \? route\('users\.update', \$reopenUserId\) : '' \}\}"/.test(usersIndex),
    'the form action is set by the fetch on the normal path and by the server on the failed one');

/* ------------------------------- 14. the user menu (66)
   The shell shows the person twice — a chip in the top bar and a card at the
   foot of the sidebar — and both were labels: a link for an employee, a dead
   `<div>` for the office, which is a menu that does nothing for the person most
   likely to want one. One partial now renders both, and every item in it leads
   somewhere a signed-in person may actually go.

   The trap this section exists for: a panel that offers doors its reader cannot
   open. An employee following a link into the office's half is turned around by
   `EnsureUserIsAdmin`, so a menu item that points there is a promise the
   middleware breaks — the layout's own comment says as much. */

const menu = read('resources/views/layouts/partials/user-menu.blade.php');
const shell = read('resources/views/layouts/app.blade.php');
const layoutSheet = read('public/assets/css/app-layout.css');
const webRoutes = read('routes/web.php');

check('the shell shows one menu, in the two places it shows the person',
    (shell.match(/layouts\.partials\.user-menu/g) || []).length === 2
    && /'surface' => 'sidebar'/.test(shell)
    && /'surface' => 'topbar'/.test(shell),
    'the chip and the sidebar card are the same menu, not two');

check('the card at the foot of the sidebar is the menu, not a logout button',
    !/sidebar-logout-btn/.test(shell)
    && !/sidebar-logout-btn/.test(menu)
    && /class="master-dropdown user-menu is-\{\{ \$surface/.test(menu),
    'one control per action: Sign out lives in the panel now');

check('the panel is the shared dropdown, so the placement and the escape work',
    /class="master-dropdown user-menu is-/.test(menu)
    && /class="master-dropdown-toggle/.test(menu)
    && /class="master-dropdown-menu user-menu-panel"/.test(menu),
    'a private menu would be clipped by the sidebar and never flip upwards');

check('and its own styles are written for a panel that has left the menu',
    /\.master-dropdown-menu\.user-menu-panel \{/.test(layoutSheet)
    && !/\.user-menu \.user-menu-panel/.test(layoutSheet)
    && /\.user-menu-head \{/.test(layoutSheet),
    'the layout script portals the panel to <body> — `.user-menu .panel` stops matching there');

check('every item in the menu is a page the reader may open',
    (() => {
        /* The header is not an item: it holds the role ask, which has to name
           both halves of the answer. The items are what is left. */
        const items = menu
            .replace(/@php[\s\S]*?@endphp/, '')
            .replace(/@if \(\$menuEmployee\)[\s\S]*?@endif/, '');

        const linked = [...items.matchAll(/route\('([\w.\-]+)'\)/g)].map(m => m[1]);
        const roleSpecific = linked.filter(name => /^(my|users)\./.test(name));

        return /\{\{ \$menuRecord \}\}/.test(menu)
            && /route\('my\.dashboard'\) : route\('users\.show', \$menuUser\)/.test(menu)
            && linked.includes('account.index')
            && /route\('account\.index'\) \}\}#password/.test(menu)
            && linked.includes('logout')
            && linked.includes('theme.toggle')
            /* the employee's doors are inside the employee branch and nowhere else */
            && /@if \(\$menuEmployee\)[\s\S]*?my\.salary[\s\S]*?my\.documents[\s\S]*?@endif/.test(menu)
            && roleSpecific.length === 0;
    })(),
    'an employee following an office link is turned around at the door — a menu item that does is a broken promise');

check('the account page is reachable by both roles, not filed under the office',
    /^    Route::get\('\/account', \[AccountController::class, 'index'\]\)->name\('account\.index'\);/m.test(webRoutes)
    && /^    Route::put\('\/account\/profile'/m.test(webRoutes)
    && /^    Route::put\('\/account\/password'/m.test(webRoutes),
    'four spaces is the auth group; eight would be the office group');

/* The theme switch said "for anybody signed in" in its own comment while sitting
   inside the office group, so an employee pressing Dark was bounced with an
   error and the control was dead on every page of their workspace. */
check('the theme switch belongs to anybody signed in, and is registered as such',
    /^    Route::post\('\/theme\/toggle'/m.test(webRoutes)
    && (webRoutes.match(/theme\.toggle/g) || []).length === 1,
    'a theme route inside the office group is a dead control for every employee');

check('the account page is the person\'s, and says which half of the record is theirs',
    /route\('account\.profile\.update'\)/.test(accountView)
    && /route\('account\.password\.update'\)/.test(accountView)
    && /route\('theme\.toggle'\)/.test(accountView)
    /* Both branches say something, and each says it in its own words: an
       employee is told which fields belong to the office, and the office is told
       where their own record is. A page that greets one of them with the other's
       sentence is the failure this holds shut. */
    && /@if \(\$employee\)[\s\S]*?the office's record — ask them to[\s\S]*?@else[\s\S]*?signed in as the office[\s\S]*?@endif/.test(accountView),
    'both roles reach this page, so both have to be addressed');

check('the account page has a sheet for its own cells, and the shell owns the menu',
    /users\.css/.test(accountView)
    && /\.user-account \.account-head \{/.test(read('public/assets/css/users.css'))
    && /\.user-menu\.is-topbar \.master-dropdown-toggle \{/.test(layoutSheet)
    && /\.user-menu\.is-sidebar \.master-dropdown-toggle \{/.test(layoutSheet),
    'the trigger geometry is the shell\'s, the page\'s cells are the module\'s');

/* ---- the layout of that page, which is the part a screenshot judges ---------
   The page is a form and an aside: the details card is the tallest thing on it
   (five fields, one of them a textarea) and the other column holds two cards, so
   a third card on that side left the tall column ending some three hundred
   pixels above the bottom of the grid. The switch card sits in the tall column
   now — and it is a *row*: a button under its own sentence is a card-shaped hole,
   where beside it the card is as tall as the button it holds. */
const accountTitles = ['Your details', 'Appearance', 'Password', 'Yours to reach']
    .map(title => accountView.indexOf('>' + title + '<'));

check('the account page ends both its columns together',
    accountTitles.every((at, i) => at > 0 && (i === 0 || at > accountTitles[i - 1]))
    && /class="account-switch"/.test(accountView),
    'the tallest card decides where its column ends, so it takes a companion');

check('the theme switch sits beside the sentence it belongs to',
    /\.user-account \.account-switch \{[^}]*display: flex[^}]*justify-content: space-between/.test(usersSheet)
    && /\.user-account \.account-switch \.master-section-title \{\s*margin: 0/.test(usersSheet),
    'stacked, the switch was a card with a button somewhere under it');

/* ---------------------------------------------------------------- report */

const failed = out.filter(([, ok]) => !ok);
out.forEach(([name, ok, detail]) =>
    console.log((ok ? '  ok   ' : '  FAIL ') + name + (ok || !detail ? '' : '  -> ' + detail)));
console.log('\nemployees: ' + (out.length - failed.length) + ' passed, ' + failed.length + ' failed');
process.exit(failed.length ? 1 : 0);

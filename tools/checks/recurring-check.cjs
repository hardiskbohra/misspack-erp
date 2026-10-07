/* ==========================================================================
   RECURRING CHECK — a standing payment, and the six ways it goes wrong
   --------------------------------------------------------------------------
   Run:  node tools/checks/recurring-check.cjs
   No dependencies. Exits non-zero on failure.

   The recurring-cashflow module is the first thing in this application that can
   move money without anybody pressing a button **for that specific payment**.
   That is the whole point of it, and it is also the whole risk, so the
   assertions below are about the ways it stops being safe:

     - **the calendar** — one calculator, one step, a month-end that clamps and
       then finds the anchor day again (a rent rule that slides to the 28th for
       ever is money, not a rounding error);
     - **the state machine** — a rule is born a draft, a draft does nothing, and
       only an approved rule has a plan. One writer for the rule, one writer for
       the dates;
     - **the ask** — a payment is approved **on or after its effective date**,
       never before; the office is told once, on the day, by the module's one
       notifier;
     - **the posting** — approving writes exactly one ledger entry, through the
       one ledger service; a row that was already posted returns the entry it
       has instead of writing a second salary;
     - **the count** — no counter beside the log, no stored "overdue", no second
       copy of an amount: the plan is the log and every reading is derived;
     - **the page** — the shared shell, the shared drawer, the shared modal, the
       shared badge, one form for both doors, and a sheet that declares every
       class this module's own views wear and no shared class of its own.

   Every assertion reads source with its comments stripped, because the prose in
   these files explains the rules and a comment must never answer a check.
   ========================================================================== */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = relative => fs.readFileSync(path.join(ROOT, relative), 'utf8');
const exists = relative => fs.existsSync(path.join(ROOT, relative));
const plain = text => text.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '').replace(/^\s*\/\/.*$/gm, '');
const blade = text => text.replace(/\{\{--[\s\S]*?--\}\}/g, '');
const has = (text, needle) => text.includes(needle);
const times = (text, needle) => text.split(needle).length - 1;

let passed = 0;
let failed = 0;
const check = (name, ok, detail = '') => {
    console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${name}${ok || !detail ? '' : ` -> ${detail}`}`);
    ok ? passed++ : failed++;
};

/* ----------------------------------------------------------------- sources */

const schedule = plain(read('app/Services/RecurrenceSchedule.php'));
const vocabulary = plain(read('app/Services/RecurrenceVocabulary.php'));
const ruleModel = plain(read('app/Models/CashflowRecurrenceRule.php'));
const occurrenceModel = plain(read('app/Models/CashflowRecurrenceOccurrence.php'));
const intake = plain(read('app/Services/RecurrenceIntake.php'));
const plan = plain(read('app/Services/RecurrencePlan.php'));
const posting = plain(read('app/Services/RecurrencePosting.php'));
const figures = plain(read('app/Services/RecurrenceFigures.php'));
const filters = plain(read('app/Services/RecurrenceFilters.php'));
const controller = plain(read('app/Http/Controllers/CashflowRecurrenceController.php'));
const cashflowController = plain(read('app/Http/Controllers/CashflowController.php'));
const indexView = blade(read('resources/views/cashflows/recurring/index.blade.php'));
const showView = blade(read('resources/views/cashflows/recurring/show.blade.php'));
const formPartial = blade(read('resources/views/cashflows/recurring/partials/rule-form.blade.php'));
const sheet = plain(read('public/assets/css/cashflow-recurring.css'));
const script = plain(read('public/assets/js/cashflow-recurring.js'));
const routes = plain(read('routes/web.php'));
const consoleRoutes = plain(read('routes/console.php'));
const shell = plain(read('resources/views/layouts/app.blade.php'));
const ledgerIndex = blade(read('resources/views/cashflows/index.blade.php'));
const ledgerShow = blade(read('resources/views/cashflows/show.blade.php'));
const briefing = plain(read('app/Services/OfficeBriefing.php'));
const officeSetting = plain(read('app/Models/OfficeSetting.php'));
const coreSheet = plain(read('public/assets/css/core.css'));
const migration = plain(read('database/migrations/2026_10_07_130000_create_cashflow_recurrence_rules_table.php'));
const occurrencesMigration = plain(read('database/migrations/2026_10_07_130100_create_cashflow_recurrence_occurrences_table.php'));
const command = plain(read('app/Console/Commands/ReleaseRecurringCashflows.php'));

/* ------------------------------------------------------------ 1. the calendar */

/* One calculator. The plan writes the dates and the draft's preview shows them;
   they must not be two implementations, or the office approves one calendar and
   receives another. */
check('the date maths lives in one class and the plan reads it',
    has(plan, '$this->schedule->step(')
    && has(plan, '$this->schedule->firstOnOrAfter(')
    && has(plan, '$this->schedule->dates(')
    && ! /addMonth|addWeek|addDay\(|->modify\(/.test(plan),
    'the plan computes dates itself instead of asking RecurrenceSchedule');

check('and the preview the draft shows is the same calculator as the plan',
    /public function preview\([^)]*\)[\s\S]{0,400}\$this->schedule->dates\(/.test(plan)
    && has(controller, '$this->plan->preview($recurrence)'),
    'the preview and the plan disagree about what approval will write');

check('no browser or template does the arithmetic with it',
    ! /addMonth|addDays|setMonth/.test(script + sheet)
    && ! /addMonth|addDays/.test(indexView + showView),
    'a date computed in the view is a second calendar');

/* The clamp: the step moves to the FIRST of the target month and then sets the
   anchor day into it, so "one month after the 31st" cannot overflow into March
   and the following step cannot inherit the clamped day. */
const stepBody = schedule.match(/private function anchored\([\s\S]*?\n    \}/);
check('a month-based step clamps into the target month instead of overflowing',
    !!stepBody
    && /startOfMonth\(\)/.test(stepBody[0])
    && /addMonths\(\$months\)/.test(stepBody[0])
    && /min\(\$anchorDay, \$target->daysInMonth\)/.test(stepBody[0]),
    stepBody ? stepBody[0].replace(/\s+/g, ' ').slice(0, 120) : 'anchored() not found');

check('and the anchor day is carried, never read back from a clamped date',
    /public function step\([^)]*int \$anchorDay\)/.test(schedule)
    && /public function dates\([\s\S]{0,500}\$anchor = \(int\) \$start->day;/.test(schedule)
    && has(schedule, 'this->step($date, $frequency, $anchor)'),
    'a rule anchored on the 31st would slide to the 28th for ever');

const frequencyKeys = [...(vocabulary.match(/const FREQUENCIES = \[([\s\S]*?)\];/) || [, ''])[1]
    .matchAll(/'([a-z_]+)'\s*=>/g)].map(m => m[1]);
check('every rhythm the form offers is a case the step handles',
    frequencyKeys.length >= 6
    && frequencyKeys.every(key => new RegExp(`'${key}'\\s*=>`).test(schedule)),
    frequencyKeys.filter(key => !new RegExp(`'${key}'\\s*=>`).test(schedule)).join(', ') || 'no rhythms found');

check('an unknown rhythm is an error rather than a silent default',
    /default => throw new InvalidArgumentException\('Unknown frequency: '\.\$frequency\)/.test(schedule));

/* -------------------------------------------------- 2. the state machine (one writer) */

check('the rule table is born a draft and a draft is the default in the column',
    /string\('status', 20\)->default\('draft'\)/.test(migration)
    && /public const STATUS_DRAFT = 'draft';/.test(vocabulary)
    && /\$rule->status = RecurrenceVocabulary::STATUS_DRAFT;/.test(intake));

check('the intake is the only thing that saves a rule',
    has(intake, '$rule->save();')
    && ! /\$rule->save\(\)|CashflowRecurrenceRule::create\(|\$recurrence->save\(/.test(controller),
    'the controller writes the row itself');

check('and the controller hands every state change to it',
    ['create', 'update', 'requestApproval', 'approve', 'sendBack', 'pause', 'resume', 'end', 'delete']
        .every(method => has(controller, `$this->intake->${method}`)),
    'a state change that skipped the intake is a state change with no rules');

check('the four states are the vocabulary\'s, not strings in the code',
    ['STATUS_DRAFT', 'STATUS_ACTIVE', 'STATUS_PAUSED', 'STATUS_ENDED']
        .every(key => has(vocabulary, `public const ${key} = '`))
    && ! /'active'|'paused'|'ended'/.test(intake.replace(/RecurrenceVocabulary::STATUS_[A-Z]+ = '([a-z]+)';/g, '')),
    'a status written as a bare string is a status outside the vocabulary');

/* Approval is from the ask: the queue is rules in that state, and the page may
   not approve a rule nobody has sent. */
check('a rule is approved from the ask, not from nowhere',
    /isWaitingApproval\(\)/.test(ruleModel)
    && /if \(! \$recurrence->isWaitingApproval\(\)\)/.test(controller)
    && /if \(! \$rule->isDraft\(\)\)/.test(intake),
    'approval must require that somebody asked');

check('approving writes the plan in the same call — no active rule without dates',
    /public function approve\([\s\S]*?\$this->plan->plan\(\$rule\);/.test(intake),
    'an active rule with no plan is a promise nobody can see');

check('only a draft is editable, and the refusal is a sentence',
    /if \(! \$rule->isDraft\(\)\) \{\s*throw new RuntimeException/.test(intake)
    && has(controller, 'catch (RuntimeException $e)'),
    'an approved rule edited in place would rewrite history');

check('pausing and ending withdraw the tail; resuming plans again',
    /public function pause\([\s\S]*?\$this->plan->withdraw\(\$rule\);/.test(intake)
    && /public function end\([\s\S]*?\$this->plan->withdraw\(\$rule\);/.test(intake)
    && /public function resume\([\s\S]*?\$this->plan->plan\(\$rule\);/.test(intake),
    'a paused rule that keeps its tail keeps asking');

check('a rule that has decided something is ended, never deleted',
    /throw new RuntimeException\('A rule with decided occurrences is ended, not deleted\.'\)/.test(intake)
    && /releasedCount\(\) > 0/.test(controller));

/* ------------------------------------------------------- 3. the plan (one writer) */

check('the plan is the only thing that writes an occurrence',
    has(plan, '$rule->occurrences()->create(')
    && times(plan, '->save();') >= 2
    && ! /occurrences\(\)->create\(|CashflowRecurrenceOccurrence::create\(|->update\(\['status' => RecurrenceVocabulary::OCCURRENCE_(APPROVED|SKIPPED)/.test(controller),
    'the controller writes dates instead of asking the plan');

check('the plan is a window, and the page says so in words',
    has(vocabulary, 'public const PLAN_WINDOW = 24;')
    && /RecurrenceVocabulary::PLAN_WINDOW - \$open/.test(plan)
    && has(showView, 'undecided dates at a'),
    'a plan that is not a window is a table that grows for ever');

check('re-planning continues the numbering instead of reusing it',
    /* Read to the column list and stop: the constraint may carry a name of its
       own (it must, in fact — see php-check's 64-character guard), and a check
       that insists on the unnamed form is a check that breaks when the fix
       lands. */
    /unique\(\['cashflow_recurrence_rule_id', 'sequence'\]/.test(occurrencesMigration)
    && /\$sequence = \(int\) \$rule->occurrences\(\)->max\('sequence'\);/.test(plan)
    && /\+\+\$sequence/.test(plan),
    'a reused sequence number is a row colliding with its own history');

check('withdrawing cancels the promise and deletes nothing',
    /'status' => RecurrenceVocabulary::OCCURRENCE_CANCELLED/.test(plan)
    && ! /->delete\(\)/.test(plan),
    'a withdrawn promise is history: it is cancelled, not removed');

/* The count is the log. A counter column beside a log is the classic drift. */
check('there is no counter, no stored next date and no stored amount-paid',
    ! /occurrences_released|released_count|next_date|total_paid|paid_to_date/.test(migration)
    && /public function releasedCount\(\): int/.test(ruleModel)
    && /->where\('status', RecurrenceVocabulary::OCCURRENCE_APPROVED\)->count\(\)/.test(ruleModel),
    'a stored count beside a log is a second answer that drifts');

check('and no occurrence copies an amount the rule already owns',
    ! /'amount'/.test(occurrencesMigration)
    && /public function moneyAmount\(\): float/.test(occurrenceModel)
    && has(occurrenceModel, '$this->rule->amount'),
    'two copies of an amount drift, and the copy that drifts is the one nobody reads');

/* Due, overdue and late are readings of two dates — never words in a column. */
check('"due", "overdue" and "late" are computed from today, never stored',
    /public function isDue\([\s\S]*?effective_date->toDateString\(\) <= \$today->toDateString\(\)/.test(occurrenceModel)
    && /public function isOverdue\(/.test(occurrenceModel)
    && ! /'overdue'|\boverdue\b\s*=>/.test(occurrencesMigration),
    'a stored "overdue" is wrong by tomorrow morning');

check('the closed set of statuses is one constant, and the plan opens on pending',
    /public const OCCURRENCE_OPEN = \[self::OCCURRENCE_PENDING\];/.test(vocabulary)
    && ['pending', 'approved', 'skipped', 'cancelled']
        .every(key => new RegExp(`OCCURRENCE_${key.toUpperCase()} = '${key}'`).test(vocabulary)));

/* ------------------------------------------------ 4. the ask, on the effective date */

check('a payment is approved on or after its effective date, never before',
    /public function approve\([\s\S]*?if \(! \$occurrence->isDue\(\$today \?: Carbon::today\(\)\)\) \{\s*throw new RuntimeException\('This payment opens for approval on its effective date\.'\)/.test(plan),
    'the whole point of the date is that the answer belongs to it');

check('a skip may be made at any time, and the page says so',
    ! /isDue\(/.test(plan.slice(plan.indexOf('public function skip('), plan.indexOf('public function notifyDue(')))
    && has(showView, 'it may be skipped any time'),
    'a skip moves no money, so it is a plan decision and an early one is useful');

check('there is one notifier, and it is the module\'s own method on OfficeBriefing',
    /public function recurringNeedsApproval\(CashflowRecurrenceOccurrence \$occurrence\): \?OfficeAlert/.test(briefing)
    && has(briefing, "if (! \$this->sourceOn('recurring_due'))")
    && has(briefing, "'requires_ack' => true")
    && has(briefing, "'team' => 'accounts'")
    && has(briefing, "'fingerprint' => 'cashflow.recurring_due:'.\$occurrence->id"),
    'an alert raised anywhere else is a second way to tell the office');

check('the ask is stamped as a fact, once, and only when it was actually made',
    /public function scopeUnnotified\(Builder \$query\): Builder[\s\S]*?whereNull\('notified_at'\)/.test(occurrenceModel)
    && /if \(! \$this->briefing->recurringNeedsApproval\(\$occurrence\)\) \{\s*continue;/.test(plan)
    && /dateTime\('notified_at'\)->nullable\(\)/.test(occurrencesMigration),
    'stamping a notification the office switched off swallows the ask for ever');

check('the office can switch this source off, and the settings screen says what it is',
    /'recurring_due' => true,/.test(officeSetting)
    && /'recurring_due' => \['Recurring payment due'/.test(officeSetting));

check('the sweep has one implementation and two doors — the command and the page',
    /public function release\([\s\S]*?\$this->plan->notifyDue\(\$rule, \$today\)/.test(intake)
    && has(controller, '$this->intake->release();')
    && has(command, '$intake->release()')
    && /CashflowRecurrenceRule::query\(\)\s*->where\('status', RecurrenceVocabulary::STATUS_ACTIVE\)/.test(intake),
    'two implementations of the sweep means two answers about what was sent');

check('the sweep runs before the briefings, and ends rules whose window is complete',
    /Schedule::command\('cashflows:recurring'\)->dailyAt\('08:45'\)/.test(consoleRoutes)
    && consoleRoutes.indexOf('cashflows:recurring') < consoleRoutes.indexOf('office:briefings')
    && /public function isFinished\([\s\S]*?where\('status', RecurrenceVocabulary::OCCURRENCE_PENDING\)->exists\(\)/.test(plan)
    && /end\(\$rule, 'The last date on this rule’s plan has been decided\.'\)/.test(intake));

/* ------------------------------------------------------- 5. the posting (one entry) */

check('approving posts exactly one entry and a posted occurrence is idempotent',
    /if \(\$occurrence->cashflow_entry_id\) \{\s*return CashflowEntry::find\(\$occurrence->cashflow_entry_id\);/.test(posting)
    && /DB::transaction\(function \(\) use \(\$decidedBy, \$occurrence, \$note\)/.test(plan)
    && /\$occurrence->cashflow_entry_id = \$entry\?->id;/.test(plan),
    'a double-click must not pay a salary twice');

check('the ledger row is written by the module\'s own mirror writer, not by the controller',
    has(posting, 'CashflowEntry::create(')
    && ! /CashflowEntry::create\(/.test(controller)
    && has(controller, '$this->plan->approve($request->user(), $row'),
    'the controller posts money');

check('the running balance is refreshed through the one service that owns it',
    /CashflowLedger::recalculateAccount\(\$entry->account_id\);/.test(posting)
    && ! /\$entry->balance =|\$account->current_balance =/.test(posting),
    'a second implementation of the balance is a second balance');

check('a rule with no account refuses to post rather than writing a row no ledger holds',
    /if \(! \$rule->account_id\) \{\s*throw new RuntimeException\('This rule has no account to pay from/.test(posting));

check('the entry says which rule wrote it, so the narration explains itself',
    /Posted by the recurring rule/.test(posting)
    && has(posting, '$occurrence->sequence')
    && has(posting, 'alignPartyType'));

check('the entry page can point back at the rule that posted it',
    /public function recurrenceOccurrence\(\)/.test(cashflowController + plain(read('app/Models/CashflowEntry.php')))
    && has(ledgerShow, "route('cashflows.recurring.show', \$entry->recurrenceOccurrence->rule)"),
    'an entry that appeared overnight has to say where it came from');

/* -------------------------------------------------------- 6. the page (the design) */

check('the index is the shared master-list, chips and all',
    ['master-list', 'master-list-bar', 'master-list-chip', 'master-list-applied', 'master-search',
        'master-filter-row', 'master-table-card', 'master-table-wrap', 'master-table', 'master-list-empty']
        .every(className => has(indexView, className)));

check('the secondary criteria open in the shared right drawer',
    has(indexView, '<x-filter-trigger drawer="recurringFiltersDrawer"')
    && has(indexView, '<x-drawer id="recurringFiltersDrawer"')
    && has(indexView, '<x-slot:footer>'));

check('a criterion is asked once: the chips carry the state, the drawer the rest',
    has(indexView, 'name="frequency"') && has(indexView, 'name="window"') && has(indexView, 'name="sort"')
    && ! /name="state"/.test(indexView.slice(indexView.indexOf('<x-drawer'), indexView.indexOf('</x-drawer>'))),
    'the drawer repeats a chip the reader can already see');

check('a search keeps the chips the form does not own',
    /@if \(\$state !== 'everything'\)\s*<input type="hidden" name="state"/.test(indexView),
    'without it, typing a search silently drops the state');

check('every write on the page is a form with a token, and the row menu is the shared one',
    times(indexView, '@csrf') >= 8
    && has(indexView, '<div class="master-dropdown">')
    && has(indexView, '<button type="button" class="master-dropdown-toggle"')
    && ! /href="\{\{ route\('cashflows\.recurring\.(destroy|pause|resume|end|approve)'/.test(indexView + showView),
    'a state change behind a link can be triggered by anything that can follow one');

check('the whole row opens its rule, and the controls inside it still work',
    /<tr class="cfr-row is-clickable" data-href="\{\{ route\('cashflows\.recurring\.show', \$rule\) \}\}">/.test(indexView)
    && has(sheet, '.cfr-index .cfr-row.is-clickable')
    && has(script, 'MasterList.rowNavigation')
    && has(script, 'MasterList.gridShadow')
    && times(script, "root: '.cfr-index'") === 2);

check('the day\'s approvals are answered from the page they are listed on',
    has(indexView, 'Waiting on your approval')
    && times(indexView, "route('cashflows.recurring.occurrences.approve'") >= 1
    && times(indexView, "route('cashflows.recurring.occurrences.skip'") >= 1
    && times(showView, "route('cashflows.recurring.occurrences.approve'") >= 1
    && times(showView, "route('cashflows.recurring.occurrences.skip'") >= 1,
    'a queue you cannot answer from is a queue to go and find');

check('the empty state — both of them — offers a way out',
    has(indexView, 'master-list-empty-actions')
    && has(indexView, 'data-open-rule-modal')
    && has(showView, 'The plan is empty'));

check('the tables become labelled cards on a phone',
    has(indexView, 'ui-mobile-cards') && has(showView, 'ui-mobile-cards')
    && times(indexView, 'data-label=') >= 5 && times(showView, 'data-label=') >= 6);

/* The inset: the shared card is a surface with no padding, so a card of this
   module's own owes its own — and the cards that hold the two tables are the
   deliberate exception, because the shared list's rhythm runs to their edges. */
const inset = sheet.match(/\.cfr \.cfr-card \{([^}]*)\}/);
check('a card of this module\'s own carries the guideline\'s inset',
    !!inset && /padding:\s*20px 22px/.test(inset[1]),
    inset ? inset[1].replace(/\s+/g, ' ').trim() : '.cfr .cfr-card not found');

check('and the record head is a card of its own, padded',
    /\.cfr \.cfr-record-head \{[\s\S]*?padding: 20px 22px;/.test(sheet));

check('and the cards holding the tables do not, so the rows keep the list rhythm',
    ! /class="[^"]*cfr-card[^"]*master-table-card/.test(indexView + showView)
    && ! /master-table-card[^"]*cfr-card/.test(indexView + showView));

check('the module tables drop the ledger\'s 1280px floor, which is for ten columns',
    /\.cfr \.cfr-table,\s*\.cfr \.cfr-plan-table \{\s*min-width: 0;/.test(sheet),
    'a five-column list with a ledger floor is a scrollbar for empty space');

check('money is right-aligned and tabular down the column',
    /\.cfr \.cfr-col-money,[\s\S]{0,120}text-align: right;[\s\S]{0,80}font-variant-numeric: tabular-nums;/.test(sheet));

check('state badges are the shared badge, and their tones are the shared suffixes',
    /class="core-badge core-badge-\{\{ \$rule->stateTone\(\) \}\}"/.test(indexView)
    && ! /class="cfr-badge/.test(indexView + showView)
    && (() => {
        const tones = [...(vocabulary.match(/const STATE_TONES = \[([\s\S]*?)\];/) || [, ''])[1]
            .matchAll(/=>\s*'([a-z]+)'/g)].map(m => m[1])
            .concat([...(vocabulary.match(/const OCCURRENCE_TONES = \[([\s\S]*?)\];/) || [, ''])[1]
                .matchAll(/=>\s*'([a-z]+)'/g)].map(m => m[1]));
        return tones.length >= 8 && tones.every(tone => new RegExp(`\\.core-badge-${tone}\\b`).test(coreSheet));
    })(),
    'a tone with no class in the shared sheet renders as no state at all');

check('the record page does not borrow another module\'s classes',
    ! /\bemp-(pill|avatar|record)/.test(showView + indexView)
    && ! /\bnt-|fb-|si-table/.test(showView + indexView),
    'classes from another module are a copy of a component nobody maintains');

/* The dialog: one form, two doors, and it comes back with the typing kept. */
check('the rule form is written once and included by both dialogs',
    times(read('resources/views/cashflows/recurring/index.blade.php') + read('resources/views/cashflows/recurring/show.blade.php'),
        "include('cashflows.recurring.partials.rule-form'") === 2
    && has(formPartial, "name=\"frequency\"")
    && has(formPartial, "name=\"starts_on\"")
    && has(formPartial, "name=\"amount\""));

check('both dialogs wear the shared modal vocabulary and name their dialog',
    has(indexView, '<div class="master-modal" id="recurrenceRuleModal"')
    && has(showView, '<div class="master-modal" id="recurrenceRuleEditModal"')
    && ['master-modal-card', 'master-modal-header', 'master-modal-heading', 'master-modal-icon',
        'master-modal-title', 'master-modal-subtitle', 'master-modal-close', 'master-modal-body',
        'master-modal-footer'].every(className => has(indexView + showView, className))
    && has(indexView, '<input type="hidden" name="_dialog" value="recurrenceRuleModal">')
    && has(showView, '<input type="hidden" name="_dialog" value="recurrenceRuleEditModal">'));

check('a rejected save reopens the dialog it came from, with the typing kept',
    has(indexView, "data-open-dialog=\"{{ $errors->any() ? old('_dialog') : '' }}\"")
    && has(showView, "data-open-dialog=\"{{ $errors->any() ? old('_dialog') : '' }}\"")
    && has(script, "reopen === 'recurrenceRuleModal'")
    && has(script, "reopen === 'recurrenceRuleEditModal'")
    && has(formPartial, 'old($field')
    && times(formPartial, '$value(') + times(formPartial, '$date(') >= 12,
    'nobody retypes a rule because the server said no');

check('and it is opened from every control that offers to write or edit one',
    times(indexView, 'data-open-rule-modal') >= 2
    && times(showView, 'data-open-rule-edit-modal') >= 1
    && has(script, "querySelectorAll(pair[0])")
    && has(script, 'window.MasterModal'));

/* --------------------------------------------- 7. the reading (one query, four ways) */

check('the figures are one row of sums, cloned and de-ordered',
    has(figures, '(clone $rules)') && has(figures, '->reorder()')
    && times(figures, 'selectRaw(') >= 8
    && ! /->get\(\)/.test(figures));

check('the chip counts are counted with the chip\'s own filter lifted',
    /withoutState\(\$filters\)/.test(controller)
    && /array_merge\(\$filters, \['state' => 'everything'\]\)/.test(filters),
    'a chip that counts its own state reads 0 while you stand on it');

check('the plan-side figures are the same rules, not the whole table',
    /whereIn\('cashflow_recurrence_rule_id', \$ids\)/.test(figures)
    && has(figures, "select('id')"),
    'a figure that counts something the page is not showing is a second scoreboard');

check('the filters narrow through the model\'s own scopes, not raw columns',
    ['->search($search)', '->inState($filters[\'state\'])', '->inFrequency($filters[\'frequency\'])', '->askingBetween(DateRanges::today(), $end)']
        .every(needle => has(filters, needle)),
    'the module re-writes SQL the model already owns');

const sortBlock = filters.match(/const SORT_SCOPES = \[([\s\S]*?)\];/);
const sortScopes = sortBlock ? [...sortBlock[1].matchAll(/'[a-z_]+'\s*=>\s*'([a-zA-Z]+)'/g)].map(m => m[1]) : [];
check('every order a reader can choose is a scope the model owns',
    sortScopes.length >= 4
    && sortScopes.every(scope => new RegExp(`function scope${scope[0].toUpperCase()}${scope.slice(1)}\\(`).test(ruleModel)),
    sortScopes.join(', '));

check('a hand-typed filter value falls back to the default, never to a 500',
    ['$state', '$window', '$sort'].every(name => new RegExp(`array_key_exists\\(\\${name}, (self|RecurrenceVocabulary)::[A-Z_]+`).test(filters))
    && /RecurrenceVocabulary::hasFrequency\(\$frequency\)/.test(filters),
    'a hand-typed ?frequency=fortnightly must be the default view');

check('the state chips the screen offers are the states the query understands',
    (() => {
        const keys = [...(vocabulary.match(/const STATE_LABELS = \[([\s\S]*?)\];/) || [, ''])[1]
            .matchAll(/'([a-z]+)'\s*=>/g)].map(m => m[1]);
        return keys.length >= 6
            && keys.every(key => key === 'everything' || new RegExp(`'${key}'\\s*=>`).test(ruleModel))
            && keys.every(key => has(figures, `'${key}' =>`));
    })(),
    'a chip key the count map does not know prints a silent zero');

check('the periods reach back from the office\'s today, not the server\'s',
    has(filters, 'DateRanges::today()') && has(figures, 'CarbonInterface $today'));

/* ------------------------------------------------------------- 8. the shell + routes */

const recurringRoutes = routes.slice(
    routes.indexOf("Route::prefix('cashflows/recurring')->name('cashflows.recurring.')->group(function ()"),
    routes.indexOf("Route::post('/cashflows/saved-views'"),
);
check('the module\'s routes are declared before the cashflow resource route',
    routes.indexOf("Route::prefix('cashflows/recurring')") > -1
    && routes.indexOf("Route::prefix('cashflows/recurring')") < routes.indexOf("Route::resource('cashflows'"),
    'as /cashflows/{cashflow} the module page would be a 404 with an id of "recurring"');

check('every door is registered with the verb it claims',
    ['index', 'store', 'show', 'update', 'destroy', 'request', 'approve', 'sendBack', 'pause', 'resume', 'end']
        .every(name => new RegExp(`->name\\('${name}'\\)`).test(recurringRoutes))
    && /Route::patch\('\/\{recurrence\}\/occurrences\/\{occurrence\}\/approve'/.test(recurringRoutes)
    && /Route::patch\('\/\{recurrence\}\/occurrences\/\{occurrence\}\/skip'/.test(recurringRoutes),
    'a missing verb is a door the page links to and the router does not have');

check('an occurrence is reached inside its rule, so a stray id is a 404',
    /private function occurrence\(CashflowRecurrenceRule \$rule, string \$id\): CashflowRecurrenceOccurrence\s*\{\s*return \$rule->occurrences\(\)->findOrFail\(\$id\);/.test(controller)
    && ! /CashflowRecurrenceOccurrence \$occurrence/.test(controller),
    'a URL that says "occurrence 41" must not decide somebody else\'s plan');

check('the ids in the URLs are numbers',
    times(recurringRoutes, '->whereNumber(') >= 12);

check('the sidebar carries the module, and the ledger item steps aside for it',
    /'label' => 'Recurring', 'route' => 'cashflows\.recurring\.index'/.test(shell)
    && /'except' => \[[^\]]*'cashflows\.recurring'/.test(shell),
    'two lit items for one page is a menu that cannot say where you are');

check('the ledger has a door to it, so the module is reachable from the money',
    has(ledgerIndex, "route('cashflows.recurring.index')"));

/* ---------------------------------------------------------------- 9. the paperwork */

check('the module is written down, with its reading of the ask',
    exists('docs/recurring-cashflow.md')
    && has(read('docs/recurring-cashflow.md'), 'on or after its effective date')
    && has(read('docs/recurring-cashflow.md'), 'RecurrenceSchedule')
    && has(read('docs/recurring-cashflow.md'), 'RecurrencePlan')
    && has(read('docs/recurring-cashflow.md'), 'RecurrencePosting'));

check('the roadmap marks the feature it was promised under',
    /1E\. Recurring entries \+ reminders/.test(read('docs/cashflow-ux-roadmap.md'))
    && /Shipped/.test(read('docs/cashflow-ux-roadmap.md').slice(read('docs/cashflow-ux-roadmap.md').indexOf('1E. Recurring'))));

check('the check itself is listed with its siblings',
    /recurring-check\.cjs/.test(read('tools/checks/README.md')));

check('the filter drawer is on the shared-drawer contract list',
    has(read('tools/checks/ui-components-check.cjs'), "'resources/views/cashflows/recurring/index.blade.php'"));

/* A source check can see the clamp's shape and not its arithmetic; the calendar
   is asked of the calculator itself. */
check('the calendar is held by a test, not by a comment',
    exists('tests/Unit/RecurrenceScheduleTest.php')
    && /class RecurrenceScheduleTest/.test(read('tests/Unit/RecurrenceScheduleTest.php'))
    && /2027-03-31/.test(read('tests/Unit/RecurrenceScheduleTest.php')),
    'the 31st-of-the-month case is the bug this module must not have');

check('the office settings screen has the source it needs to switch off',
    has(read('app/Models/OfficeSetting.php'), "'recurring_due'")
    && has(read('app/Http/Controllers/OfficeBriefingSettingController.php'), 'sourceLabels'));

/* ------------------------------------------------------------------ report */

console.log(`\nrecurring: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);

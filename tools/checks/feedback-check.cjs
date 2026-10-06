/* ==========================================================================
   FEEDBACK CHECK — the rules a copy would break silently
   --------------------------------------------------------------------------
   Run:  node tools/checks/feedback-check.cjs
   No dependencies. Exits non-zero on failure.

   The module puts a form on the public internet and a number beside the office's
   name, so the things that must stay true are the ones a careless edit would
   quietly change:

     - the link is the whole of the authentication: the public controller reads a
       token and nothing else, and a closed door renders the expired page on GET
       *and* POST rather than a form that fails on submit;
     - one ask, one answer — the unique index, and the second submission;
     - the band is computed, never stored: one rule in the vocabulary, the same
       constants in the model's SQL, no verdict column, no view doing arithmetic;
     - a detractor is never a dead end: the answer that scores low raises its own
       follow-up inside the same transaction, with an owner and a clock;
     - consent is checked where the words are used, not where they arrive;
     - feedback gates nothing — no payment, no document, no project close waits on
       a good score;
     - the figures on the screen and the rows in the CSV are one query;
     - the module's sheet declares no shared class, and every route it links to is
       registered.
   ========================================================================== */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = relative => fs.readFileSync(path.join(ROOT, relative), 'utf8');
const exists = relative => fs.existsSync(path.join(ROOT, relative));
const plain = text => text.replace(/\/\*[\s\S]*?\*\//g, '');

let passed = 0;
let failed = 0;
const check = (name, ok, detail = '') => {
    console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${name}${ok || !detail ? '' : ` -> ${detail}`}`);
    ok ? passed++ : failed++;
};

const walk = (dir, out = []) => {
    for (const entry of fs.readdirSync(path.join(ROOT, dir), { withFileTypes: true })) {
        const rel = path.join(dir, entry.name);
        entry.isDirectory() ? walk(rel, out) : out.push(rel.replaceAll(path.sep, '/'));
    }
    return out;
};

const migrationFiles = walk('database/migrations');
const findMigration = pattern => migrationFiles.find(file => pattern.test(file));

/* ----------------------------------------------------------------- sources */

const controller = read('app/Http/Controllers/FeedbackController.php');
const publicController = read('app/Http/Controllers/PublicFeedbackController.php');
const portalController = read('app/Http/Controllers/ClientPortalFeedbackController.php');
const requestModel = read('app/Models/FeedbackRequest.php');
const responseModel = read('app/Models/FeedbackResponse.php');
const actionModel = read('app/Models/FeedbackAction.php');
const vocabulary = read('app/Services/FeedbackVocabulary.php');
const figures = read('app/Services/FeedbackFigures.php');
const filters = read('app/Services/FeedbackFilters.php');
const intake = read('app/Services/FeedbackIntake.php');
const formRules = read('app/Services/FeedbackFormRules.php');
const routes = read('routes/web.php');
const sheet = plain(read('public/assets/css/feedback.css'));
const script = read('public/assets/js/feedback.js');

const views = walk('resources/views/feedback')
    .concat([
        'resources/views/projects/partials/feedback-tab.blade.php',
        'resources/views/clients/partials/feedback.blade.php',
        'resources/views/client_portal/feedback/index.blade.php',
        'resources/views/client_portal/feedback/show.blade.php',
    ])
    .filter(exists);
const viewText = Object.fromEntries(views.map(file => [file, read(file)]));
const allViews = Object.values(viewText).join('\n');

/* ------------------------------------------------------- 1. the link is the key */

const requestsMigration = findMigration(/create_feedback_requests_table/);
const responsesMigration = findMigration(/create_feedback_responses_table/);
const answersMigration = findMigration(/create_feedback_answers_table/);
const actionsMigration = findMigration(/create_feedback_actions_table/);
const optionsMigration = findMigration(/create_feedback_master_options_table/);

check('every table the module reads has its own migration',
    [requestsMigration, responsesMigration, answersMigration, actionsMigration, optionsMigration].every(Boolean),
    [requestsMigration, responsesMigration, answersMigration, actionsMigration, optionsMigration].filter(Boolean).length + '/5 found');

const requestsText = requestsMigration ? read(requestsMigration) : '';
check('the asks table keeps the token unique and the states it filters on',
    /->string\('token',\s*80\)->unique\(\)/.test(requestsText)
    && /index\(\['project_id',\s*'kind'\]\)/.test(requestsText)
    && /index\('expires_at'\)/.test(requestsText));

/* The public controller may read a token and nothing else. */
check('the public form is looked up by token, never by an id in the request',
    !/request\(\)->(input|query)\(\s*'(project|client|response|feedback)'/.test(publicController)
    && !/\$(request)->(project|client|response)_id/.test(publicController)
    && /where\('token',\s*\$token\)/.test(publicController));

check('the public token is constrained to what a token is',
    /Route::get\('\/feedback\/\{token\}'[\s\S]{0,120}where\('token',\s*'\[A-Za-z0-9\]\{20,80\}'\)/.test(routes)
    && /Route::post\('\/feedback\/\{token\}'[\s\S]{0,200}where\('token',\s*'\[A-Za-z0-9\]\{20,80\}'\)/.test(routes));

check('a closed door renders the expired page on GET and on POST',
    /if\s*\(!\s*\$ask->isLive\(\)\)\s*\{\s*return response\(\)->view\('feedback\.expired'/.test(publicController)
    && (publicController.match(/response\(\)->view\('feedback\.expired'/g) || []).length >= 2);

check('the link can be revoked and can expire, and both are decisions the office makes',
    /'revoked_at'/.test(requestsText) && /'expires_at'/.test(requestsText)
    && /public function scopeLive\(Builder \$query\): Builder/.test(requestModel)
    && /public function canRemind\(\): bool/.test(requestModel));

check('a view is counted once per open, and never by the office testing its own link',
    /if \(Auth::check\(\)\) \{\s*return;\s*\}/.test(publicController)
    && /\$ask->views = \$ask->views \+ 1;/.test(publicController));

/* --------------------------------------------------------- 2. one ask, one answer */

const responsesText = responsesMigration ? read(responsesMigration) : '';
check('the database itself refuses a second answer to the same ask',
    /foreignId\('feedback_request_id'\)->unique\(\)/.test(responsesText));

check('the POST refuses a second answer with the thank-you page, not a validation error',
    /if \(\$ask->hasAnswered\(\)\) \{\s*return redirect\(\)->route\('feedback\.public\.thanks'/.test(publicController));

check('an answer is never edited: the scores are absent from every update call',
    !/update\(\[[^\]]*'(overall_rating|nps_score)'/.test(responseModel)
    && !/->update\(\[[^\]]*'(overall_rating|nps_score)'[\s\S]{0,200}\]\)/.test(controller + intake)
    && /Answer that has been answered cannot be deleted|hasAnswered\(\)/.test(controller));

check('deleting an ask is refused once it has been answered',
    /if \(\$feedbackRequest->hasAnswered\(\)\) \{\s*return back\(\)->with\('error'/.test(controller));

check('a project never takes its feedback rows with it',
    !/feedbackRequests\(\)->delete\(\)|feedbackResponses\(\)->delete\(\)/.test(read('app/Http/Controllers/ProjectController.php'))
    && /nullOnDelete\(\)/.test(requestsText)
    && /nullOnDelete\(\)/.test(responsesText));

/* ------------------------------------------------- 3. the band is computed, not stored */

check('no verdict is stored on the answer',
    !/->string\('band'\)|'band'\s*=>/.test(responsesText)
    && !/'band'/.test(responseModel.split('protected \$fillable')[1].split('];')[0] ?? ''));

check('the thresholds are declared once, as constants',
    /const DETRACTOR_OVERALL = 2;/.test(vocabulary)
    && /const DETRACTOR_NPS = 6;/.test(vocabulary)
    && /const DETRACTOR_DIMENSION = 2;/.test(vocabulary)
    && /const PROMOTER_OVERALL = 4;/.test(vocabulary)
    && /const PROMOTER_NPS = 9;/.test(vocabulary));

check('the SQL that finds detractors uses the same constants as the rule',
    /FeedbackVocabulary::DETRACTOR_OVERALL/.test(responseModel)
    && /FeedbackVocabulary::DETRACTOR_NPS/.test(responseModel)
    && /FeedbackVocabulary::DETRACTOR_DIMENSION/.test(responseModel)
    && /scopeNeedsAttention/.test(responseModel));

check('NPS is computed, and refuses to invent a number from no answers',
    /public static function nps\(array \$npsScores\): \?int/.test(vocabulary)
    && /if \(\$scores === \[\]\) \{\s*return null;/.test(vocabulary)
    && !/promoters.*detractors.*\/ 100/.test(allViews));

check('no view and no script does the arithmetic itself',
    !/->avg\(|array_sum|reduce\(/.test(allViews)
    && !/\bavg\b|percentage|nps\s*=/i.test(script));

check('the average is of answers, not of asks',
    /'average' => \$average/.test(figures)
    && /'response_rate' => \$asked > 0/.test(figures));

/* --------------------------------------------------------- 4. a detractor is never a dead end */

check('a low answer raises a follow-up inside the same transaction that stores it',
    /DB::transaction\(function \(\) use \(\$request, \$data, \$source, \$http\)/.test(intake)
    && /if \(\$response->isDetractor\(\)\) \{\s*\$this->raiseFollowUp\(\$response\);/.test(intake));

check('the follow-up gets an owner and a clock, from the project that earned it',
    /'owner_id' => \$ownerId/.test(intake)
    && /'due_on' => now\(\)->addHours\(FeedbackAction::DETRACTOR_DUE_HOURS\)/.test(intake)
    && /const DETRACTOR_DUE_HOURS = 48;/.test(actionModel));

check('the follow-up reaches the owner where work is actually done',
    /Task::create\(\[/.test(intake) && /'assignee_id' => \$ownerId/.test(intake));

check('the office page leads with what is still unowned, and the figure is one query',
    /'attention' => \$responses->filter\(fn \(FeedbackResponse \$response\) => \$response->isDetractor\(\) && ! \$response->hasOpenAction\(\)\)->count\(\)/.test(figures)
    && /\$this->figures->needsAttention\(\)/.test(controller));

check('closing the loop records both facts: work done, and client told',
    /'resolved_at' =>/.test(actionModel) && /'client_notified_at' =>/.test(actionModel)
    && /if \(\$notifyClient && \$status === FeedbackAction::STATUS_RESOLVED && \$action->client_notified_at === null\)/.test(intake)
    && /boolean\('notify_client'\)/.test(controller));

/* ------------------------------------------------------------ 5. consent, at the point of use */

check('consent is asked for each use, and defaults to no',
    /\['publish_website'\] = \$request->boolean\('publish_website'\)/.test(formRules)
    && (formRules.match(/\$request->boolean\('publish_/g) || []).length === 4
    && /\['attribution_consent'\] = \$request->boolean\('attribution_consent', true\)/.test(formRules));

check('the quotable list is the consented list — a scope, not a filter in a view',
    /public function scopeQuotable\(Builder \$query\): Builder/.test(responseModel)
    && /->quotable\(\)/.test(figures)
    && /publish_website', true\)/.test(responseModel));

check('a quote is only shown with the channels it was consented to',
    /public function consentChannels\(\): array/.test(responseModel)
    && /consentChannels\(\)/.test(allViews)
    && /is_anonymous/.test(responseModel));

check('the office changes consent through one route, and it never touches the score',
    /publish_website' => \$request->boolean/.test(controller)
    && !/publish_[\s\S]{0,200}(overall_rating|nps_score)/.test(controller));

/* ------------------------------------------------- 6. feedback gates nothing */

const gatingFiles = [
    'app/Http/Controllers/ProjectController.php',
    'app/Http/Controllers/SalesInvoiceController.php',
    'app/Http/Controllers/ProjectPaymentController.php',
    'app/Http/Controllers/ClientPortalPaymentController.php',
    'app/Http/Controllers/ClientPortalProjectController.php',
].filter(exists);

/* The words `feedback` may appear in these controllers only where the page is
   guarded for the module's tables, or where the project tab loads its relations.
   A conditional that *decides* on a score is the thing this forbids. */
const gatingOffenders = gatingFiles.filter(file => read(file)
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/\/\/[^\n]*/g, '')
    .replace(/class_exists\([^)]*Feedback[^)]*\)/g, '')
    .replace(/Schema::hasTable\('feedback_[a-z_]+'\)/g, '')
    .replace(/feedbackRequests|feedbackResponses|FeedbackRequest|FeedbackResponse|feedback-tab|feedback_requests/g, '')
    .replace(/[Ff]eedback/g, '')
    .match(/feedback/i));

check('no payment, document or project close waits on a score',
    gatingOffenders.length === 0, gatingOffenders.join(', '));

check('the module is outside auth only where the link is',
    (routes.match(/feedback/g) || []).length > 0
    && !/Route::(get|post)\('\/feedback\/\{token\}[\s\S]{0,200}->middleware\('auth'\)/.test(routes)
    && !/Route::get\('\/feedback\/export'[\s\S]{0,200}->middleware\('auth'\)/.test(routes));

check('the public page is unindexable and stores nothing about the reader it does not say',
    /content="noindex, nofollow"/.test(viewText['resources/views/feedback/public.blade.php'] ?? '')
    && /'ip' => \$http\?->ip\(\)/.test(intake));

check('a forwarded link cannot be used as a spam hole',
    /name="website_url"/.test(allViews) && /throttle:10,1/.test(routes));

/* ------------------------------------------------- 7. one query, two renderings */

check('the figures and the CSV read the same filters and the same service',
    /public function export\(Request \$request\): StreamedResponse\s*\{\s*\$filters = \$this->filters->fromRequest\(\$request\);\s*\$rows = \$this->figures->csvRows\(\$filters\);/.test(controller)
    && /public function csvRows\(array \$filters\): array/.test(figures)
    && /applyToResponses/.test(filters));

check('the list and the figures share one filter vocabulary',
    /public function requestQuery\(array \$filters\)/.test(figures)
    && /applyToRequests\(FeedbackRequest::query\(\)->with\(/.test(figures)
    && /public const DEFAULTS = \[/.test(filters));

/* ------------------------------------------------------ 8. the house rules, kept */

check('the module sheet declares no shared class',
    !/(^|[,{}])\s*\.master-[a-z-]+\s*[,{]/.test(sheet)
    && !/(^|[,{}])\s*\.core-[a-z-]+\s*[,{]/.test(sheet)
    && /\.fb-badge/.test(sheet) && /\.fb-step/.test(sheet));

check('the sheet uses the shared tokens rather than a theme of its own',
    /var\(--ui-text/.test(sheet) && /var\(--ui-border/.test(sheet) && /var\(--ui-card-surface/.test(sheet));

/* The design pass lives here as a check, because a spacing number is the first
   thing an edit loses: `docs/ui-design-guidelines.md` asks for 20-24px outside a
   card, 16px between blocks, and the shell's own 24px between page blocks. */
check('the module mirrors the shell rhythm instead of spacing its own page',
    /\.fb-show > \* \+ \* \{ margin-top: 24px; \}/.test(sheet)
    && /\.fb-settings \.master-card \+ \.master-card \{ margin-top: 24px; \}/.test(sheet));

check('a card of its own is padded 20-24px, or is flush with an inset bar',
    /\.fb-card \{ padding: 20px 22px; \}/.test(sheet)
    && /\.fb-card--flush \{ padding: 0; \}/.test(sheet)
    && /\.fb-card--flush > \.fb-card-head \{\s*padding: 16px 16px 0;/.test(sheet));

check('the shared control carries the shape and the module only the gap',
    /\.fb \.fb-field,\s*\n\.fb \.master-field \{ display: flex; flex-direction: column; gap: 8px;/.test(sheet)
    && !/\.fb-field (input|textarea|select)/.test(sheet));

/* The form is the module's newest surface, and the guideline's own rule for a new
   screen is that it wears the shared component API rather than a look beside it.
   Both halves are pinned: the markup carries the shared classes (a card, a label,
   a control, its error, the button, the alert, the choice-chip group), and the
   sheet declares no card, control or button shape of its own. The last line is
   the one that breaks first if the public page stops loading `core.css`: without
   it the shared geometry the markup depends on is simply not there. */
const formView = viewText['resources/views/feedback/partials/form.blade.php'] ?? '';

check('the form wears the shared components and invents no shape of its own',
    /class="core-card fb-card fb-step"/.test(formView)
    && /class="core-label"/.test(formView)
    && /class="core-text-input"/.test(formView)
    && /class="core-textarea"/.test(formView)
    && /class="core-button core-button-primary fb-submit"/.test(formView)
    && /class="core-alert fb-alert fb-alert--error"/.test(formView)
    && /class="core-required"/.test(formView)
    && /class="master-choice-chip/.test(formView)
    && !/\.fb-step \{[^}]*background/.test(sheet)
    && !/\.fb-submit \{[^}]*background/.test(sheet)
    && /assets\/css\/core\.css/.test(viewText['resources/views/feedback/public.blade.php'] ?? ''));

/* The list is the other half of the module's surface, and it is a list like the
   ERP's other lists: the same chrome in the same places. The toolbar declares
   its right-hand group — the export and the three density presets — instead of
   leaving the shared toolkit to fall back to a line of its own above the table;
   the counts wear the shared pill; the figures wear the module's own icon
   vocabulary rather than an emoji per tile; and the last column says what it is.
   The recipe line is the one that breaks quietly: master-list.js binds only the
   controls it created, so a declared group with no binding is three inert
   buttons that look like a preference. */
const indexView = viewText['resources/views/feedback/index.blade.php'] ?? '';
const headerCells = [...indexView.matchAll(/<th\b[^>]*>/g)].map(match => match[0]);
const statIcons = [...indexView.matchAll(/<span class="icon"[^>]*>([\s\S]*?)<\/span>/g)].map(match => match[1]);

check('the office list wears the shared list chrome in the shared places',
    /class="master-list-toolbar"[\s\S]{0,240}class="master-list-hint"/.test(indexView)
    && /class="master-list-toolbar-actions"/.test(indexView)
    && /class="master-list-toolbar-actions"[\s\S]{0,500}feedback\.export/.test(indexView)
    && /class="master-list-density desktop-only" role="group" aria-label="Table density"[\s\S]{0,500}data-density="compact" aria-pressed="false">Compact/.test(indexView)
    && /MasterList\.density\(\{ root: '\.fb-index'/.test(script)
    && /class="master-list-chip-count"/.test(indexView)
    && headerCells.length > 0 && headerCells.every(cell => /scope="col"/.test(cell))
    && /<th scope="col" class="fb-col-actions">Action<\/th>/.test(indexView)
    && statIcons.length >= 4 && statIcons.every(icon => /<i class="fa-solid fa-/.test(icon)),
    headerCells.length + ' header cells, ' + statIcons.length + ' stat icons');

check('every route the module links to is registered',
    [...allViews.matchAll(/route\('(feedback[.a-z-]*|client-portal\.feedback[.a-z-]*)'/g)]
        .map(m => m[1])
        .every(name => {
            const escaped = name.replace(/\./g, '\\.');
            /* A name inside the portal group is written there without its
               prefix, so both spellings have to count as registered. */
            const bare = name.replace(/^client-portal\./, '').replace(/\./g, '\\.');

            return new RegExp(`->name\\('${escaped}'\\)`).test(routes)
                || new RegExp(`->name\\('${bare}'\\)`).test(routes);
        }),
    [...new Set([...allViews.matchAll(/route\('(client-portal\.feedback[.a-z-]*|feedback[.a-z-]*)'/g)].map(m => m[1]))].join(', '));

check('the module has a page in the office and a door in the client portal',
    /Route::get\('\/feedback', \[FeedbackController::class, 'index'\]\)->name\('feedback\.index'\)/.test(routes)
    && /Route::get\('\/feedback', \[ClientPortalFeedbackController::class, 'index'\]\)->name\('feedback\.index'\)/.test(routes));

check('the portal can only read the asks that belong to the client at the door',
    /\(int\) \$feedbackRequest->client_id === \(int\) \$this->client\(\$request\)->id/.test(portalController)
    && /abort_unless\(/.test(portalController));

check('the mid-project pulse is the same ask and the same tables',
    /const KIND_PULSE = 'pulse';/.test(requestModel)
    && /formIntro/.test(vocabulary)
    && /KIND_CLOSE_OUT/.test(requestModel));

check('the module is written down',
    exists('docs/feedback-module.md')
    && /tools\/checks\/feedback-check\.cjs/.test(read('docs/feedback-module.md'))
    && /FeedbackVocabulary/.test(read('docs/feedback-module.md')));

check('the check itself is listed with its siblings',
    /feedback-check\.cjs/.test(read('tools/checks/README.md')));

/* ---------------------------------------------------------------- report */

const failedChecks = failed;
console.log(`\nfeedback: ${passed} passed, ${failedChecks} failed`);
process.exit(failedChecks ? 1 : 0);

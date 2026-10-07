<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FeedbackAction;
use App\Models\FeedbackMasterOption;
use App\Models\FeedbackRequest;
use App\Models\FeedbackResponse;
use App\Models\Project;
use App\Services\FeedbackFigures;
use App\Services\FeedbackFilters;
use App\Services\FeedbackIntake;
use App\Services\FeedbackVocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The office's half: the list, the figures, the queues and the loop.
 *
 * The page is built around one idea — a score is only worth reading if somebody
 * owns it — so the first card is not the average, it is **needs attention**:
 * every answer that scored badly and has nobody acting on it, oldest first.
 * The average is beside it, with its denominator, and the testimonial bank is
 * where the good answers go to be used.
 *
 * The list is of *asks*, not answers: a link nobody opened is the row the office
 * has to do something about, and it would not exist at all in a list of answers.
 */
class FeedbackController extends Controller
{
    public function __construct(
        private FeedbackFilters $filters,
        private FeedbackFigures $figures,
        private FeedbackIntake $intake,
    ) {
    }

    /* ---------------------------------------------------------------- the list */

    public function index(Request $request): View
    {
        $filters = $this->filters->fromRequest($request);

        $requests = $this->figures->requestQuery($filters)->paginate(20)->withQueryString();

        return view('feedback.index', [
            'requests' => $requests,
            'summary' => $this->figures->summary($filters),
            'dimensions' => $this->figures->dimensions($filters),
            'trend' => $this->figures->monthlyTrend($filters),
            'attention' => $this->figures->needsAttention(),
            'quotable' => $this->figures->quotable(),
            'stateCounts' => $this->stateCounts(),
            'clients' => $this->clientChoices(),
            'projects' => $this->projectChoices(),
            'applied' => $this->filters->applied($filters),
            'kindOptions' => FeedbackRequest::kindOptions(),
            'bandOptions' => FeedbackFilters::BAND_LABELS,
            'stateOptions' => [
                FeedbackRequest::STATE_ANSWERED => 'Answered',
                FeedbackRequest::STATE_OPENED => 'Opened, not answered',
                FeedbackRequest::STATE_LIVE => 'Never opened',
                FeedbackRequest::STATE_EXPIRED => 'Expired',
                FeedbackRequest::STATE_REVOKED => 'Revoked',
            ],
            'can' => [
                'settings' => Schema::hasTable('feedback_master_options'),
            ],
            ...$filters,
        ]);
    }

    /** One ask, with its answer and everything done about it. */
    public function show(FeedbackRequest $feedbackRequest): View
    {
        $feedbackRequest->load([
            'project', 'client', 'creator', 'response.answers', 'response.actions.owner',
            'response.actions.task', 'response.client',
        ]);

        return view('feedback.show', [
            'ask' => $feedbackRequest,
            'response' => $feedbackRequest->response,
            'actions' => $feedbackRequest->response?->actions ?? collect(),
            'actionOwners' => $this->ownerChoices(),
            'actionTypes' => FeedbackAction::typeOptions(),
            'actionSeverities' => FeedbackAction::severityOptions(),
            'actionStatuses' => FeedbackAction::statusOptions(),
            'expiryChoices' => FeedbackRequest::expiryChoices(),
            'kindOptions' => FeedbackRequest::kindOptions(),
        ]);
    }

    /* ------------------------------------------------------------- issuing the ask */

    /**
     * Issue a link for a project.
     *
     * One live ask per kind per project: pressing the button twice on the same
     * project sends a client two links to the same project, and the second one
     * answers into a row the office then has to reconcile. If a live one exists,
     * the office is shown it instead.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(FeedbackRequest::kindOptions()))],
            'expires_in_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'note' => ['nullable', 'string', 'max:255'],
            'shared_via' => ['nullable', Rule::in(array_keys(FeedbackRequest::CHANNELS))],
        ]);

        $existing = FeedbackRequest::query()
            ->forProject($project->id)
            ->where('kind', $data['kind'])
            ->live()
            ->latest('id')
            ->first();

        if ($existing) {
            return redirect()
                ->route('feedback.show', $existing)
                ->with('success', 'This project already has a live feedback link — here it is.');
        }

        $ask = FeedbackRequest::issue([
            'project_id' => $project->id,
            'client_id' => $project->client_id,
            'kind' => $data['kind'],
            'note' => $data['note'] ?? null,
            'expires_in_days' => $data['expires_in_days'] ?? FeedbackRequest::DEFAULT_EXPIRY_DAYS,
            'shared_via' => $data['shared_via'] ?? null,
        ]);

        return redirect()
            ->route('feedback.show', $ask)
            ->with('success', 'Feedback link ready. Send it from here — every open is counted.');
    }

    /** The office says how it went out; the link itself is unchanged. */
    public function markShared(Request $request, FeedbackRequest $feedbackRequest): RedirectResponse
    {
        $data = $request->validate([
            'shared_via' => ['required', Rule::in(array_keys(FeedbackRequest::CHANNELS))],
        ]);

        $feedbackRequest->update(['shared_via' => $data['shared_via']]);

        return back()->with('success', 'Marked as sent by '.$feedbackRequest->channelLabel().'.');
    }

    /**
     * A reminder is a second look at the same link, and there are two of them.
     *
     * Nothing is sent from here — the office sends it from their own WhatsApp or
     * mail, as they do a statement — but the count is kept, because "we reminded
     * them twice and heard nothing" is a fact the follow-up needs.
     */
    public function remind(FeedbackRequest $feedbackRequest): RedirectResponse
    {
        if (! $feedbackRequest->canRemind()) {
            return back()->with('error', $feedbackRequest->hasAnswered()
                ? 'This ask has been answered — there is nothing to remind.'
                : 'This link cannot be reminded again. Issue a fresh one if it is still worth asking.');
        }

        $feedbackRequest->update([
            'reminder_count' => $feedbackRequest->reminder_count + 1,
            'last_reminded_at' => now(),
        ]);

        return back()->with('success', 'Reminder '.$feedbackRequest->reminder_count.' of '.FeedbackRequest::MAX_REMINDERS.' recorded. Send it from the buttons above.');
    }

    public function revoke(FeedbackRequest $feedbackRequest): RedirectResponse
    {
        if ($feedbackRequest->revoked_at === null) {
            $feedbackRequest->update(['revoked_at' => now()]);
        }

        return back()->with('success', 'Link revoked. The answers already given are untouched.');
    }

    /**
     * Delete an ask.
     *
     * Refused once it has been answered: the answer is the client's words about
     * us, and a delete button beside it is what makes a score editable after the
     * fact. Revoke the link instead.
     */
    public function destroy(FeedbackRequest $feedbackRequest): RedirectResponse
    {
        if ($feedbackRequest->hasAnswered()) {
            return back()->with('error', 'This ask has an answer. Revoke the link instead — the answer is kept.');
        }

        $feedbackRequest->delete();

        return redirect()->route('feedback.index')->with('success', 'Ask deleted.');
    }

    /* ------------------------------------------------------------- the answer */

    /** Consent is the client's to change, and the office records the change. */
    public function updateConsent(Request $request, FeedbackResponse $feedbackResponse): RedirectResponse
    {
        $feedbackResponse->update([
            'attribution_consent' => $request->boolean('attribution_consent'),
            'publish_website' => $request->boolean('publish_website'),
            'publish_social' => $request->boolean('publish_social'),
            'publish_sales' => $request->boolean('publish_sales'),
            'publish_case_study' => $request->boolean('publish_case_study'),
        ]);

        return back()->with('success', 'Consent updated. The score and the words are unchanged.');
    }

    /* -------------------------------------------------------------- the loop */

    public function storeAction(Request $request, FeedbackResponse $feedbackResponse): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(FeedbackAction::typeOptions()))],
            'severity' => ['required', Rule::in(array_keys(FeedbackAction::severityOptions()))],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $action = $feedbackResponse->actions()->create([
            'type' => $data['type'],
            'severity' => $data['severity'],
            'owner_id' => $data['owner_id'] ?? null,
            'due_on' => $data['due_on'] ?? now()->addDay()->toDateString(),
            'status' => FeedbackAction::STATUS_OPEN,
            'resolution_note' => $data['note'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Follow-up opened and owned.');
    }

    /**
     * Work the action: move it, resolve it, and — when the office says so — tell
     * the client what was done. Telling them is the part that closes the loop, so
     * it is a checkbox on the same form, not a separate favour.
     */
    public function updateAction(Request $request, FeedbackAction $feedbackAction): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(FeedbackAction::statusOptions()))],
            'severity' => ['nullable', Rule::in(array_keys(FeedbackAction::severityOptions()))],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_on' => ['nullable', 'date'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
            'notify_client' => ['nullable', 'boolean'],
        ]);

        if (isset($data['severity']) || array_key_exists('owner_id', $data) || array_key_exists('due_on', $data)) {
            $feedbackAction->update([
                'severity' => $data['severity'] ?? $feedbackAction->severity,
                'owner_id' => $data['owner_id'] ?? $feedbackAction->owner_id,
                'due_on' => $data['due_on'] ?? $feedbackAction->due_on,
            ]);
        }

        $this->intake->resolve(
            $feedbackAction,
            $data['status'],
            $data['resolution_note'] ?? null,
            $request->boolean('notify_client')
        );

        return back()->with('success', 'Follow-up updated.');
    }

    /* ---------------------------------------------------------- the vocabulary */

    public function settings(): View|RedirectResponse
    {
        if (! Schema::hasTable('feedback_master_options')) {
            return redirect()->route('feedback.index')
                ->with('error', 'The scorecard table is not there yet — run the feedback migrations first.');
        }

        return view('settings.feedback', [
            'dimensions' => FeedbackMasterOption::query()
                ->group(FeedbackMasterOption::GROUP_DIMENSION)
                ->ordered()
                ->get(),
            'fallback' => FeedbackVocabulary::DEFAULT_DIMENSIONS,
        ]);
    }

    public function storeDimension(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'key' => ['nullable', 'string', 'max:60'],
            'hint' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $key = FeedbackMasterOption::keyFrom($data['label'], $data['key'] ?? null);

        if (FeedbackMasterOption::query()->group(FeedbackMasterOption::GROUP_DIMENSION)->where('key', $key)->exists()) {
            return back()->with('error', 'A dimension with that name already exists.');
        }

        FeedbackMasterOption::create([
            'group' => FeedbackMasterOption::GROUP_DIMENSION,
            'key' => $key,
            'label' => $data['label'],
            'hint' => $data['hint'] ?? null,
            'color' => $data['color'] ?? 'blue',
            'sort_order' => (int) FeedbackMasterOption::query()->group(FeedbackMasterOption::GROUP_DIMENSION)->max('sort_order') + 1,
            'is_active' => true,
        ]);

        FeedbackVocabulary::flush();

        return back()->with('success', 'Dimension added. It is on the form from the next ask.');
    }

    public function updateDimension(Request $request, FeedbackMasterOption $dimension): RedirectResponse
    {
        abort_unless($dimension->group === FeedbackMasterOption::GROUP_DIMENSION, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'hint' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        /* Renaming is safe: the answers point at the key, and the report reads the
           label from here — which is the whole reason the label is not copied
           onto each answer. */
        $dimension->update([
            'label' => $data['label'],
            'hint' => $data['hint'] ?? null,
            'color' => $data['color'] ?? $dimension->color,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? $dimension->sort_order,
        ]);

        FeedbackVocabulary::flush();

        return back()->with('success', 'Dimension updated. Answers already given keep their scores and wear the new name.');
    }

    /**
     * A dimension is retired, never deleted — unless nothing has ever been scored
     * on it. The history of a question the business used to ask is not the
     * settings screen's to throw away.
     */
    public function destroyDimension(FeedbackMasterOption $dimension): RedirectResponse
    {
        abort_unless($dimension->group === FeedbackMasterOption::GROUP_DIMENSION, 404);

        $used = Schema::hasTable('feedback_answers')
            && \App\Models\FeedbackAnswer::query()->where('dimension', $dimension->key)->exists();

        if ($used) {
            $dimension->update(['is_active' => false]);
            FeedbackVocabulary::flush();

            return back()->with('success', 'Dimension retired — it is off the form, and the answers that scored it are kept.');
        }

        $dimension->delete();
        FeedbackVocabulary::flush();

        return back()->with('success', 'Dimension removed.');
    }

    /* ---------------------------------------------------------------- the CSV */

    /**
     * The same rows under the same filters, as a spreadsheet.
     *
     * One line per answer, with the ask it came from and every dimension as its
     * own column, so the office can pivot a quarter without asking for a report
     * to be built. This is the file the review meeting is actually held from.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters->fromRequest($request);
        $rows = $this->figures->csvRows($filters);
        $dimensionKeys = FeedbackVocabulary::dimensionKeys(false);

        /* The heading and the key live together, so a column added to the figures
           cannot silently land under the wrong heading. Dimension columns wear the
           vocabulary's label and carry its key. */
        $columns = [
            'Submitted' => 'submitted_at',
            'Client' => 'client',
            'Project' => 'project',
            'Ask' => 'ask',
            'Respondent' => 'respondent',
            'Overall (1–5)' => 'overall_rating',
            'Recommend (0–10)' => 'nps_score',
            'Verdict' => 'band',
            'Order again?' => 'would_order_again',
            'What went well' => 'went_well',
            'What could be better' => 'could_improve',
            'Testimonial' => 'testimonial',
            'Consent' => 'consent',
            'Open follow-ups' => 'open_actions',
        ];

        foreach ($dimensionKeys as $key) {
            $columns[FeedbackVocabulary::dimensionLabel($key)] = $key;
        }

        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_keys($columns));

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($key) => $row[$key] ?? '', $columns));
            }

            fclose($out);
        }, 'feedback-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /* --------------------------------------------------------------- pickers */

    /** How many asks sit in each state, for the chips. */
    private function stateCounts(): array
    {
        return [
            'all' => FeedbackRequest::query()->count(),
            FeedbackRequest::STATE_ANSWERED => FeedbackRequest::query()->state(FeedbackRequest::STATE_ANSWERED)->count(),
            FeedbackRequest::STATE_OPENED => FeedbackRequest::query()->state(FeedbackRequest::STATE_OPENED)->count(),
            FeedbackRequest::STATE_LIVE => FeedbackRequest::query()->state(FeedbackRequest::STATE_LIVE)->count(),
            FeedbackRequest::STATE_EXPIRED => FeedbackRequest::query()->state(FeedbackRequest::STATE_EXPIRED)->count(),
            FeedbackRequest::STATE_REVOKED => FeedbackRequest::query()->state(FeedbackRequest::STATE_REVOKED)->count(),
        ];
    }

    private function clientChoices()
    {
        if (! Schema::hasTable('clients')) {
            return collect();
        }

        return Client::query()->orderBy('company_name')->get(['id', 'company_name', 'brand_name']);
    }

    private function projectChoices()
    {
        $projectIds = FeedbackRequest::query()->whereNotNull('project_id')->distinct()->pluck('project_id');

        return Project::query()
            ->whereIn('id', $projectIds)
            ->orderBy('name')
            ->get(['id', 'name', 'project_number'])
            ->sortBy('name');
    }

    private function ownerChoices()
    {
        return \App\Models\User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->pluck('name', 'id');
    }
}

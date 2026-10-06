<?php

namespace App\Services;

use App\Models\FeedbackAction;
use App\Models\FeedbackAnswer;
use App\Models\FeedbackRequest;
use App\Models\FeedbackResponse;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What happens when an answer arrives.
 *
 * This is the module's one writer: the response, its scorecard, the follow-up it
 * raises, the task the owner will actually see and the project's log are all
 * written here, in one transaction. A controller calls `submit()` and does not
 * decide any of it — which is what makes the detractor rule a rule rather than a
 * habit three endpoints share.
 *
 * The follow-up is the reason this class exists. A bad answer must not sit in a
 * table waiting to be noticed:
 *
 *   - it raises a `FeedbackAction` with an owner and a two-day clock;
 *   - it creates a `Task` for that owner, because the office's work lives in the
 *     task list and the action is the record of *why* the task exists;
 *   - the action's `client_notified_at` is the moment the client was told what we
 *     did about it — closing the loop, which is the part everyone skips.
 */
class FeedbackIntake
{
    public function __construct(private ClientPortalNotifier $notifier)
    {
    }

    /**
     * Store one answer.
     *
     * @param  array{
     *     respondent_name:string, respondent_email:?string, respondent_designation:?string,
     *     overall_rating:int, nps_score:int, would_order_again:?string,
     *     went_well:?string, could_improve:?string, testimonial:?string,
     *     attribution_consent?:bool, publish_website?:bool, publish_social?:bool,
     *     publish_sales?:bool, publish_case_study?:bool, is_anonymous?:bool,
     *     scores:array<string,int>, comments?:array<string,?string>
     * }  $data
     */
    public function submit(FeedbackRequest $request, array $data, string $source = 'link', ?Request $http = null): FeedbackResponse
    {
        return DB::transaction(function () use ($request, $data, $source, $http) {
            $response = FeedbackResponse::create([
                'feedback_request_id' => $request->id,
                'project_id' => $request->project_id,
                'client_id' => $request->client_id,
                'respondent_name' => $data['respondent_name'],
                'respondent_email' => $data['respondent_email'] ?? null,
                'respondent_designation' => $data['respondent_designation'] ?? null,
                'overall_rating' => (int) $data['overall_rating'],
                'nps_score' => (int) $data['nps_score'],
                'would_order_again' => $data['would_order_again'] ?? null,
                'went_well' => $data['went_well'] ?? null,
                'could_improve' => $data['could_improve'] ?? null,
                'testimonial' => $data['testimonial'] ?? null,
                'attribution_consent' => (bool) ($data['attribution_consent'] ?? true),
                'publish_website' => (bool) ($data['publish_website'] ?? false),
                'publish_social' => (bool) ($data['publish_social'] ?? false),
                'publish_sales' => (bool) ($data['publish_sales'] ?? false),
                'publish_case_study' => (bool) ($data['publish_case_study'] ?? false),
                'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
                'source' => $source,
                'submitted_at' => now(),
                'ip' => $http?->ip(),
                'user_agent' => substr((string) $http?->userAgent(), 0, 255) ?: null,
            ]);

            $this->storeScorecard($response, $data['scores'] ?? [], $data['comments'] ?? []);

            /* The band is read from the stored answer, not from the request: the
               rule that decides a follow-up is the same rule the report shows. */
            $response->load('answers');

            if ($response->isDetractor()) {
                $this->raiseFollowUp($response);
            }

            $this->log($request, $response);

            return $response->fresh(['answers', 'actions']);
        });
    }

    /** One line per scored dimension; a dimension left blank is simply not answered. */
    public function storeScorecard(FeedbackResponse $response, array $scores, array $comments = []): void
    {
        foreach (FeedbackVocabulary::dimensionKeys() as $key) {
            $score = $scores[$key] ?? null;

            if ($score === null || $score === '' || (int) $score < 1) {
                continue;
            }

            FeedbackAnswer::create([
                'feedback_response_id' => $response->id,
                'dimension' => $key,
                'score' => min(5, (int) $score),
                'comment' => trim((string) ($comments[$key] ?? '')) ?: null,
            ]);
        }
    }

    /* ------------------------------------------------------------ the loop */

    /**
     * A bad answer becomes somebody's job, now.
     *
     * The owner is the project's own owner: whoever was accountable for the work
     * is the person who can answer for it. When a project has nobody on it, the
     * ask's issuer gets it rather than nobody.
     */
    public function raiseFollowUp(FeedbackResponse $response, ?int $ownerId = null): FeedbackAction
    {
        $response->loadMissing(['request', 'project', 'answers']);

        $project = $response->project;
        $ownerId = $ownerId
            ?? $project?->assigned_to
            ?? $project?->created_by
            ?? $response->request?->created_by;

        $severity = $this->severityFor($response);

        $action = FeedbackAction::create([
            'feedback_response_id' => $response->id,
            'type' => FeedbackAction::TYPE_FIX,
            'severity' => $severity,
            'owner_id' => $ownerId,
            'due_on' => now()->addHours(FeedbackAction::DETRACTOR_DUE_HOURS)->toDateString(),
            'status' => FeedbackAction::STATUS_OPEN,
            'created_by' => Auth::id(),
        ]);

        $task = $this->createTask($response, $action, $ownerId, $severity);

        if ($task !== null) {
            $action->update(['task_id' => $task->id]);
        }

        return $action->fresh('task');
    }

    /** The wrong-run-in-the-print kind of bad, told apart from the merely disappointed. */
    public function severityFor(FeedbackResponse $response): string
    {
        $worst = $response->answers->min('score');

        return match (true) {
            $response->overall_rating <= 1 || $response->nps_score <= 3 || $worst === 1 => FeedbackAction::SEVERITY_CRITICAL,
            $response->overall_rating <= FeedbackVocabulary::DETRACTOR_OVERALL
                || $response->nps_score <= FeedbackVocabulary::DETRACTOR_NPS
                || $worst <= FeedbackVocabulary::DETRACTOR_DIMENSION => FeedbackAction::SEVERITY_HIGH,
            default => FeedbackAction::SEVERITY_NORMAL,
        };
    }

    /**
     * The task the owner will see in their own list.
     *
     * It carries the words the client actually used, because a task called "look
     * into feedback" is a task nobody does.
     */
    private function createTask(FeedbackResponse $response, FeedbackAction $action, ?int $ownerId, string $severity): ?Task
    {
        if (! class_exists(Task::class) || ! Schema::hasTable('tasks')) {
            return null;
        }

        $client = $response->clientName();
        $project = $response->project?->name;
        $complaint = trim((string) ($response->could_improve ?: $response->went_well ?: ''));

        $description = "Client feedback: {$client} scored {$response->overall_rating}/5"
            ." and {$response->nps_score}/10 on recommendation"
            .($project ? " ({$project})" : '')
            .'. '.($complaint !== '' ? '"'.$complaint.'"' : 'No comment left.');

        return Task::create([
            'title' => 'Follow up: '.$client.' rated '.$response->overall_rating.'/5',
            'description' => $description,
            'due_date' => $action->due_on,
            'priority' => $severity === FeedbackAction::SEVERITY_CRITICAL ? Task::PRIORITY_URGENT : Task::PRIORITY_HIGH,
            'status' => Task::STATUS_NEW_REQUEST,
            'assignee_id' => $ownerId,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Close the loop: record what was done, tell the client, and keep both facts.
     *
     * The client is told through the notifications they already read, and the
     * project's own log carries a public line — so the answer to "what did you do
     * about it" is on the record the client can see.
     */
    public function resolve(FeedbackAction $action, string $status, ?string $note = null, bool $notifyClient = false): FeedbackAction
    {
        $action->loadMissing('response.project', 'response.client', 'response.request');

        $action->update([
            'status' => $status,
            'resolution_note' => $note ?: $action->resolution_note,
            'resolved_at' => in_array($status, [FeedbackAction::STATUS_RESOLVED, FeedbackAction::STATUS_DISMISSED], true)
                ? ($action->resolved_at ?? now())
                : null,
        ]);

        if ($notifyClient && $status === FeedbackAction::STATUS_RESOLVED && $action->client_notified_at === null) {
            $this->tellClient($action, $note);
            $action->update(['client_notified_at' => now()]);
        }

        return $action->fresh();
    }

    /** Put the resolution in front of the client, where they already look. */
    private function tellClient(FeedbackAction $action, ?string $note): void
    {
        $response = $action->response;
        $clientId = $response?->client_id;

        if (! $clientId) {
            return;
        }

        $projectName = $response->project?->name ?? 'your recent project';
        $body = trim((string) $note) ?: 'The issue you raised has been reviewed and addressed.';
        $message = 'About '.$projectName.': '.$body;

        $this->notifier->notifyClient(
            (int) $clientId,
            'We acted on your feedback',
            $message,
            'success',
            'feedback',
            $response->id,
            $response->project ? route('client-portal.projects.show', $response->project->id) : null
        );

        $this->writeLog($response->project, 'feedback_action_resolved', 'We acted on the feedback', $message, true);
    }

    /* --------------------------------------------------------------- logging */

    /**
     * The project's log line. Public for the client — "thank you for the
     * feedback" is a sentence they may see — with the score kept in the values
     * so the office tab has the numbers without a second table.
     */
    private function log(FeedbackRequest $request, FeedbackResponse $response): void
    {
        $this->writeLog(
            $request->project,
            'feedback_received',
            'Client feedback received',
            $response->respondent_name.' rated the project '.$response->overall_rating.'/5'
                .' and '.$response->nps_score.'/10 on recommendation.',
            true,
            ['response_id' => $response->id, 'band' => $response->band(), 'source' => $response->source]
        );
    }

    private function writeLog(?Project $project, string $eventType, string $title, ?string $description, bool $public, ?array $values = null): void
    {
        if (! $project || ! class_exists(ProjectLog::class) || ! Schema::hasTable('project_logs')) {
            return;
        }

        ProjectLog::create([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'actor_type' => Auth::check() ? 'user' : 'client',
            'actor_name' => Auth::user()?->name,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'new_values' => $values,
            'is_public' => $public,
        ]);
    }

    /** The user a follow-up should land on when there is a choice to make. */
    public function fallbackOwner(): ?int
    {
        return Auth::id() ?? User::query()->orderBy('id')->value('id');
    }
}

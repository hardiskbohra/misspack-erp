<?php

namespace App\Http\Controllers;

use App\Models\FeedbackRequest;
use App\Models\FeedbackResponse;
use App\Services\FeedbackFormRules;
use App\Services\FeedbackIntake;
use App\Services\FeedbackVocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * The same ask, answered from inside the client's own workspace.
 *
 * A client who has a portal login should not have to open a link to say what
 * they think, and the answer they give here is the same row in the same table as
 * one given by link — the source is recorded, the meaning is identical. The
 * office issues the ask either way; the portal is a second door, not a second
 * process.
 *
 * Every lookup is scoped to the signed-in client's own rows, and a request that
 * belongs to somebody else is a 404 rather than a message that confirms it
 * exists.
 */
class ClientPortalFeedbackController extends ClientPortalBaseController
{
    public function __construct(
        private FeedbackFormRules $rules,
        private FeedbackIntake $intake,
    ) {
    }

    public function index(Request $request): View
    {
        if (! $this->feedbackAvailable()) {
            return view('client_portal.feedback.index', [
                'asks' => collect(),
                'history' => collect(),
                'available' => false,
            ]);
        }

        $client = $this->client($request);

        $asks = FeedbackRequest::query()
            ->where('client_id', $client->id)
            ->with(['project', 'response'])
            ->latest('id')
            ->get();

        $history = FeedbackResponse::query()
            ->forClient($client->id)
            ->with(['project', 'answers'])
            ->latest('submitted_at')
            ->get();

        return view('client_portal.feedback.index', [
            'asks' => $asks,
            'history' => $history,
            'available' => true,
        ]);
    }

    public function show(Request $request, FeedbackRequest $feedbackRequest): View|RedirectResponse
    {
        $ask = $this->askForClient($request, $feedbackRequest);

        if ($ask->hasAnswered()) {
            return redirect()->route('client-portal.feedback.index')
                ->with('success', 'Thank you — you have already answered this one.');
        }

        if (! $ask->isLive()) {
            return redirect()->route('client-portal.feedback.index')
                ->with('error', 'This request is closed. Ask your MissPack contact to send a fresh one.');
        }

        return view('client_portal.feedback.show', [
            'ask' => $ask->load(['project', 'client']),
            'dimensions' => FeedbackVocabulary::dimensions(),
            'intro' => FeedbackVocabulary::formIntro($ask->kind),
            'action' => route('client-portal.feedback.store', $ask->id),
            'minimumAnswers' => FeedbackVocabulary::minimumAnswers(),
            'prefill' => [
                'name' => $this->portalUser($request)->displayName() ?: $ask->contactName(),
                'email' => $this->portalUser($request)->email ?: $ask->contactEmail(),
            ],
        ]);
    }

    public function store(Request $request, FeedbackRequest $feedbackRequest): RedirectResponse
    {
        $ask = $this->askForClient($request, $feedbackRequest);

        if ($ask->hasAnswered()) {
            return redirect()->route('client-portal.feedback.index')
                ->with('success', 'Thank you — you have already answered this one.');
        }

        if (! $ask->isLive()) {
            return redirect()->route('client-portal.feedback.index')
                ->with('error', 'This request is closed. Ask your MissPack contact to send a fresh one.');
        }

        if ($request->filled('website_url')) {
            return redirect()->route('client-portal.feedback.index')->with('success', 'Thank you for your feedback.');
        }

        $this->intake->submit($ask, $this->rules->validate($request), 'portal', $request);

        $ask->update(['shared_via' => 'portal']);

        return redirect()->route('client-portal.feedback.index')
            ->with('success', 'Thank you — your feedback has reached the people who can act on it.');
    }

    /** The ask, but only if it belongs to the client at the door. */
    private function askForClient(Request $request, FeedbackRequest $feedbackRequest): FeedbackRequest
    {
        abort_unless(
            $this->feedbackAvailable()
                && (int) $feedbackRequest->client_id === (int) $this->client($request)->id,
            404
        );

        return $feedbackRequest;
    }

    private function feedbackAvailable(): bool
    {
        return $this->classTableAvailable(FeedbackRequest::class, 'feedback_requests');
    }
}

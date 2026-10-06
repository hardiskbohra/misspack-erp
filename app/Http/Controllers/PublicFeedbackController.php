<?php

namespace App\Http\Controllers;

use App\Models\FeedbackRequest;
use App\Services\FeedbackFormRules;
use App\Services\FeedbackIntake;
use App\Services\FeedbackVocabulary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The form the client opens from a link.
 *
 * Outside the auth middleware on purpose: the person answering is a client, not
 * a user of this ERP. The token is the whole of the authentication, so this
 * controller never accepts a project id, a client id or a response id from the
 * request — it looks the ask up by its token and nothing else. Everything the
 * page shows comes off that one row.
 *
 * A view is counted only when somebody from the office opens the link to check
 * it, because counting that would make "they opened it" a lie the first time
 * anybody tested it. That is the statement-share rule, kept.
 */
class PublicFeedbackController extends Controller
{
    public function __construct(
        private FeedbackIntake $intake,
        private FeedbackFormRules $rules,
    ) {
    }

    public function show(Request $request, string $token): Response
    {
        $ask = FeedbackRequest::query()
            ->where('token', $token)
            ->with(['project', 'client'])
            ->first();

        if (! $ask) {
            return response()->view('feedback.expired', ['reason' => 'link'], 404);
        }

        if ($ask->hasAnswered()) {
            return response()->view('feedback.thanks', ['ask' => $ask, 'already' => true]);
        }

        if (! $ask->isLive()) {
            return response()->view('feedback.expired', ['ask' => $ask, 'reason' => $ask->state()], 410);
        }

        $this->countView($ask, $request);

        return response()->view('feedback.public', [
            'ask' => $ask,
            'dimensions' => FeedbackVocabulary::dimensions(),
            'intro' => FeedbackVocabulary::formIntro($ask->kind),
            'action' => route('feedback.public.store', $ask->token),
            'minimumAnswers' => FeedbackVocabulary::minimumAnswers(),
            'prefill' => [
                'name' => $ask->contactName(),
                'email' => $ask->contactEmail(),
            ],
        ]);
    }

    public function store(Request $request, string $token): Response
    {
        $ask = FeedbackRequest::query()->where('token', $token)->first();

        if (! $ask) {
            abort(404);
        }

        /* A forwarded link that has already been answered goes to the thank-you
           page rather than an error: the second person did nothing wrong. */
        if ($ask->hasAnswered()) {
            return redirect()->route('feedback.public.thanks', $ask->token);
        }

        if (! $ask->isLive()) {
            return response()->view('feedback.expired', ['ask' => $ask, 'reason' => $ask->state()], 410);
        }

        /* The honeypot, exactly as the enquiry form keeps it: a field no person
           sees, filled only by something that reads the HTML. */
        if ($request->filled('website_url')) {
            return redirect()->route('feedback.public.thanks', $ask->token);
        }

        $data = $this->rules->validate($request);

        $this->intake->submit($ask, $data, 'link', $request);

        return redirect()
            ->route('feedback.public.thanks', $ask->token)
            ->with('success', 'Thank you — your feedback has reached the people who can act on it.');
    }

    public function thanks(string $token): Response
    {
        $ask = FeedbackRequest::query()->where('token', $token)->with(['project', 'client'])->first();

        if (! $ask) {
            return response()->view('feedback.expired', ['reason' => 'link'], 404);
        }

        if (! $ask->hasAnswered() && ! $ask->isLive()) {
            return response()->view('feedback.expired', ['ask' => $ask, 'reason' => $ask->state()], 410);
        }

        return response()->view('feedback.thanks', ['ask' => $ask, 'already' => false]);
    }

    /* ---------------------------------------------------------------- counter */

    private function countView(FeedbackRequest $ask, Request $request): void
    {
        if (Auth::check()) {
            return;
        }

        $ask->views = $ask->views + 1;
        $ask->first_viewed_at ??= now();
        $ask->last_viewed_at = now();
        $ask->last_viewed_ip = $request->ip();
        $ask->save();
    }
}

{{--
    The form itself, shared by the public link and the client portal.

    One page, five numbered sections, and no step wizard: a client answering on a
    phone should be able to see how much is left before they start. The two
    "branch" blocks are always on the page rather than revealed by a script — a
    low score highlights one, a high score highlights the other, and with
    JavaScript off both are simply there.

    Every field is named the way the validator reads it, and every score is a
    radio so the form can be completed entirely with a thumb.
--}}
@php
    $scoreOptions = \App\Services\FeedbackVocabulary::SCORE_LABELS;
    $consentOptions = \App\Services\FeedbackVocabulary::consentOptions();
@endphp

<form method="POST" action="{{ $action }}" class="fb-form" novalidate>
    @csrf

    {{-- The honeypot: no person sees this field, and something that reads the
         HTML fills it in. --}}
    <div class="fb-trap" aria-hidden="true">
        <label for="fb-website">Website</label>
        <input type="text" id="fb-website" name="website_url" tabindex="-1" autocomplete="off">
    </div>

    @if ($errors->any())
        <div class="fb-alert fb-alert--error" role="alert">
            <strong>Not quite submitted.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 1 ─────────────────────────────────────────────────────────────── --}}
    <section class="fb-step" aria-labelledby="fb-step-who">
        <h2 class="fb-step-title" id="fb-step-who"><span>1</span> Who is answering?</h2>
        <p class="fb-step-hint">So we know who to thank — and who to ring if something needs explaining.</p>

        <div class="fb-grid">
            <label class="fb-field">
                <span class="master-label">Your name <span class="master-required" aria-hidden="true">*</span></span>
                <input class="master-input" type="text" name="respondent_name" value="{{ old('respondent_name', $prefill['name'] ?? '') }}" required autocomplete="name">
            </label>
            <label class="fb-field">
                <span class="master-label">Designation</span>
                <input class="master-input" type="text" name="respondent_designation" value="{{ old('respondent_designation') }}" autocomplete="organization-title">
            </label>
            <label class="fb-field">
                <span class="master-label">Email <small>(optional)</small></span>
                <input class="master-input" type="email" name="respondent_email" value="{{ old('respondent_email', $prefill['email'] ?? '') }}" autocomplete="email">
            </label>
        </div>
    </section>

    {{-- 2 ─────────────────────────────────────────────────────────────── --}}
    <section class="fb-step" aria-labelledby="fb-step-overall">
        <h2 class="fb-step-title" id="fb-step-overall"><span>2</span> How did the project go?</h2>
        <p class="fb-step-hint">1 is poor, 5 is excellent. There is no right answer — a 3 we can act on is worth more than a 5 we cannot believe.</p>

        <div class="fb-scale" role="radiogroup" aria-label="Overall rating">
            @foreach ($scoreOptions as $value => $label)
                <label class="fb-scale-item">
                    <input type="radio" name="overall_rating" value="{{ $value }}"
                        @checked((int) old('overall_rating') === $value) required>
                    <span class="fb-scale-value">{{ $value }}</span>
                    <span class="fb-scale-label">{{ $label }}</span>
                </label>
            @endforeach
        </div>

        <div class="fb-scale fb-scale--nps" role="radiogroup" aria-label="How likely are you to recommend MissPack">
            <p class="fb-question">{{ \App\Services\FeedbackVocabulary::NPS_QUESTION }}</p>
            <div class="fb-nps-row">
                @for ($i = 0; $i <= 10; $i++)
                    <label class="fb-nps-item">
                        <input type="radio" name="nps_score" value="{{ $i }}" @checked((int) old('nps_score') === $i) required>
                        <span>{{ $i }}</span>
                    </label>
                @endfor
            </div>
            <div class="fb-nps-anchors">
                <span>{{ \App\Services\FeedbackVocabulary::NPS_ANCHORS['low'] }}</span>
                <span>{{ \App\Services\FeedbackVocabulary::NPS_ANCHORS['high'] }}</span>
            </div>
        </div>
    </section>

    {{-- 3 ─────────────────────────────────────────────────────────────── --}}
    <section class="fb-step" aria-labelledby="fb-step-lines">
        <h2 class="fb-step-title" id="fb-step-lines"><span>3</span> The lines that matter</h2>
        <p class="fb-step-hint">Score the lines you have an opinion about — at least {{ $minimumAnswers }}. A line you leave blank is a line we did not ask well.</p>

        <div class="fb-lines">
            @foreach ($dimensions as $dimension)
                <div class="fb-line" data-dimension="{{ $dimension['key'] }}">
                    <div class="fb-line-head">
                        <p class="fb-line-label">
                            <i class="fb-dot fb-dot--{{ $dimension['color'] }}" aria-hidden="true"></i>
                            {{ $dimension['label'] }}
                        </p>
                        @if ($dimension['hint'])
                            <p class="fb-line-hint">{{ $dimension['hint'] }}</p>
                        @endif
                    </div>

                    <div class="fb-line-score" role="radiogroup" aria-label="{{ $dimension['label'] }}">
                        @foreach ($scoreOptions as $value => $label)
                            <label class="fb-score-pill" title="{{ $label }}">
                                <input type="radio" name="scores[{{ $dimension['key'] }}]" value="{{ $value }}"
                                    @checked((int) old('scores.'.$dimension['key']) === $value)>
                                <span>{{ $value }}</span>
                            </label>
                        @endforeach
                    </div>

                    <label class="fb-line-comment">
                        <span class="fb-sr">Comment on {{ $dimension['label'] }}</span>
                        <input class="master-input" type="text" name="comments[{{ $dimension['key'] }}]"
                            value="{{ old('comments.'.$dimension['key']) }}"
                            placeholder="Anything to add about this line? (optional)">
                    </label>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 4 ─────────────────────────────────────────────────────────────── --}}
    <section class="fb-step" aria-labelledby="fb-step-words">
        <h2 class="fb-step-title" id="fb-step-words"><span>4</span> In your own words</h2>
        <p class="fb-step-hint">One of the two is enough — but the sentence is the part we can actually fix something with.</p>

        <label class="fb-field">
            <span class="master-label">What went well?</span>
            <textarea class="master-textarea" name="went_well" rows="3" maxlength="4000">{{ old('went_well') }}</textarea>
        </label>

        <label class="fb-field fb-field--attention" id="fb-could-improve">
            <span class="master-label">What could be better?</span>
            <textarea class="master-textarea" name="could_improve" rows="3" maxlength="4000" placeholder="A late dispatch, a print mismatch, a call that never came back — the specific thing helps.">{{ old('could_improve') }}</textarea>
        </label>

        <fieldset class="fb-choice">
            <legend>Would you work with us again?</legend>
            <div class="fb-choice-row">
                @foreach (\App\Services\FeedbackVocabulary::wouldOrderAgainOptions() as $value => $label)
                    <label class="fb-choice-item">
                        <input type="radio" name="would_order_again" value="{{ $value }}" @checked(old('would_order_again') === $value)>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <p class="fb-note" id="fb-branch-fix">
            <strong>If anything scored low</strong>, we will call you — a low score raises a follow-up with the person who
            owned the project, and they will come back to you with what changed. That is the point of asking.
        </p>
    </section>

    {{-- 5 ─────────────────────────────────────────────────────────────── --}}
    <section class="fb-step" aria-labelledby="fb-step-quote">
        <h2 class="fb-step-title" id="fb-step-quote"><span>5</span> May we quote you?</h2>
        <p class="fb-step-hint">Optional, and entirely your call. We will only ever use your words where you tick.</p>

        <label class="fb-field">
            <span class="master-label">A line about working with us <small>(optional)</small></span>
            <textarea class="master-textarea" name="testimonial" rows="3" maxlength="2000" placeholder="If you are happy, a sentence we can put on the website helps other businesses find us.">{{ old('testimonial') }}</textarea>
        </label>

        <fieldset class="fb-choice">
            <legend>You may use it on</legend>
            <div class="fb-choice-row">
                @foreach ($consentOptions as $key => $label)
                    <label class="fb-choice-item">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <label class="fb-choice-item fb-choice-item--single">
            <input type="checkbox" name="attribution_consent" value="1" @checked(old('attribution_consent', true))>
            <span>You may name me and my company beside the quote</span>
        </label>
    </section>

    <div class="fb-actions">
        <button type="submit" class="fb-submit">Send feedback</button>
        <p class="fb-actions-note">
            Your answers are read by the MissPack team and counted in our own quality figures.
            Nothing is posted anywhere without the tick above.
        </p>
    </div>
</form>

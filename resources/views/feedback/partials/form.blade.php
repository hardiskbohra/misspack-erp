{{--
    The form itself, shared by the public link and the client portal.

    One page, five numbered sections, and no step wizard: a client answering on a
    phone should be able to see how much is left before they start. The two
    "branch" blocks are always on the page rather than revealed by a script —
    a low score highlights one, a high score highlights the other, and with
    JavaScript off both are simply there.

    Every field is named the way the validator reads it, and every score is a
    radio so the form can be completed entirely with a thumb.

    The form wears the shared component API rather than a look of its own: a
    section is a `.core-card` (the module adds nothing but `.fb-card`'s 20–24px
    padding and `.fb-step`'s rhythm), a control is `.core-label` followed by
    `.core-text-input` / `.core-textarea` with its `.core-field-error` under it,
    the submit is the shared `.core-button`, the error summary is a `.core-alert`,
    and the pick-one-of-few questions are the app's `.master-choice-chip` group.
--}}
@php
    $scoreOptions = \App\Services\FeedbackVocabulary::SCORE_LABELS;
    $consentOptions = \App\Services\FeedbackVocabulary::consentOptions();
    $wouldOrderAgain = \App\Services\FeedbackVocabulary::wouldOrderAgainOptions();
    /* One tone per answer, so the chosen chip reads the same way the office
       reports it: green for yes, yellow for "it depends", red for no. */
    $chipTones = ['yes' => 'green-chip', 'maybe' => 'yellow-chip', 'no' => 'red-chip'];
    $invalid = fn (string $field) => $errors->has($field) ? 'true' : 'false';
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
        <div class="core-alert fb-alert fb-alert--error" role="alert">
            <strong>Not quite submitted.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 1 ─────────────────────────────────────────────────────────────── --}}
    <section class="core-card fb-card fb-step" aria-labelledby="fb-step-who">
        <h2 class="fb-step-title" id="fb-step-who"><span>1</span> Who is answering?</h2>
        <p class="fb-step-hint">So we know who to thank — and who to ring if something needs explaining.</p>

        <div class="fb-grid">
            <label class="fb-field">
                <span class="core-label">Your name <span class="core-required" aria-hidden="true">*</span></span>
                <input class="core-text-input" type="text" name="respondent_name" value="{{ old('respondent_name', $prefill['name'] ?? '') }}" required autocomplete="name" aria-invalid="{{ $invalid('respondent_name') }}">
                @error('respondent_name')<p class="core-field-error">{{ $message }}</p>@enderror
            </label>
            <label class="fb-field">
                <span class="core-label">Designation</span>
                <input class="core-text-input" type="text" name="respondent_designation" value="{{ old('respondent_designation') }}" autocomplete="organization-title" aria-invalid="{{ $invalid('respondent_designation') }}">
                @error('respondent_designation')<p class="core-field-error">{{ $message }}</p>@enderror
            </label>
            <label class="fb-field">
                <span class="core-label">Email <small>(optional)</small></span>
                <input class="core-text-input" type="email" name="respondent_email" value="{{ old('respondent_email', $prefill['email'] ?? '') }}" autocomplete="email" aria-invalid="{{ $invalid('respondent_email') }}">
                @error('respondent_email')<p class="core-field-error">{{ $message }}</p>@enderror
            </label>
        </div>
    </section>

    {{-- 2 ─────────────────────────────────────────────────────────────── --}}
    <section class="core-card fb-card fb-step" aria-labelledby="fb-step-overall">
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
            @error('overall_rating')<p class="core-field-error">{{ $message }}</p>@enderror
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
            @error('nps_score')<p class="core-field-error">{{ $message }}</p>@enderror
        </div>
    </section>

    {{-- 3 ─────────────────────────────────────────────────────────────── --}}
    <section class="core-card fb-card fb-step" aria-labelledby="fb-step-lines">
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
                        <input class="core-text-input" type="text" name="comments[{{ $dimension['key'] }}]"
                            value="{{ old('comments.'.$dimension['key']) }}"
                            placeholder="Anything to add about this line? (optional)">
                    </label>
                </div>
            @endforeach
            @error('scores')<p class="core-field-error">{{ $message }}</p>@enderror
        </div>
    </section>

    {{-- 4 ─────────────────────────────────────────────────────────────── --}}
    <section class="core-card fb-card fb-step" aria-labelledby="fb-step-words">
        <h2 class="fb-step-title" id="fb-step-words"><span>4</span> In your own words</h2>
        <p class="fb-step-hint">One of the two is enough — but the sentence is the part we can actually fix something with.</p>

        <label class="fb-field">
            <span class="core-label">What went well?</span>
            <textarea class="core-textarea" name="went_well" rows="3" maxlength="4000">{{ old('went_well') }}</textarea>
            @error('went_well')<p class="core-field-error">{{ $message }}</p>@enderror
        </label>

        <label class="fb-field fb-field--attention" id="fb-could-improve">
            <span class="core-label">What could be better?</span>
            <textarea class="core-textarea" name="could_improve" rows="3" maxlength="4000" placeholder="A late dispatch, a print mismatch, a call that never came back — the specific thing helps.">{{ old('could_improve') }}</textarea>
            @error('could_improve')<p class="core-field-error">{{ $message }}</p>@enderror
        </label>

        <fieldset class="fb-choice">
            <legend class="core-label">Would you work with us again?</legend>
            <div class="master-choice-group" role="radiogroup" aria-label="Would you work with us again?">
                @foreach ($wouldOrderAgain as $value => $label)
                    <label class="master-choice-chip {{ $chipTones[$value] ?? 'blue-chip' }}">
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
    <section class="core-card fb-card fb-step" aria-labelledby="fb-step-quote">
        <h2 class="fb-step-title" id="fb-step-quote"><span>5</span> May we quote you?</h2>
        <p class="fb-step-hint">Optional, and entirely your call. We will only ever use your words where you tick.</p>

        <label class="fb-field">
            <span class="core-label">A line about working with us <small>(optional)</small></span>
            <textarea class="core-textarea" name="testimonial" rows="3" maxlength="2000" placeholder="If you are happy, a sentence we can put on the website helps other businesses find us.">{{ old('testimonial') }}</textarea>
            @error('testimonial')<p class="core-field-error">{{ $message }}</p>@enderror
        </label>

        <fieldset class="fb-choice">
            <legend class="core-label">You may use it on</legend>
            <div class="master-choice-group">
                @foreach ($consentOptions as $key => $label)
                    <label class="master-choice-chip green-chip">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="master-choice-group">
            <label class="master-choice-chip green-chip">
                <input type="checkbox" name="attribution_consent" value="1" @checked(old('attribution_consent', true))>
                <span>You may name me and my company beside the quote</span>
            </label>
        </div>
    </section>

    <div class="fb-actions">
        <button type="submit" class="core-button core-button-primary fb-submit">Send feedback</button>
        <p class="fb-actions-note">
            Your answers are read by the MissPack team and counted in our own quality figures.
            Nothing is posted anywhere without the tick above.
        </p>
    </div>
</form>

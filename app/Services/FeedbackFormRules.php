<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The form's rules, in one place.
 *
 * The public form and the client portal ask exactly the same questions, so they
 * must accept exactly the same answers — a validation rule written twice is how
 * a portal submission ends up able to say something a link submission cannot,
 * and then the figures mean two different things depending on where the client
 * clicked.
 *
 * Two rules are the module's own rather than the framework's:
 *
 *   - **at least half the lines are scored**, because one score is an opinion and
 *     six is a measurement;
 *   - **one of the two comment boxes is filled**, because a score with no
 *     sentence is not actionable, and the sentence is the part that changes
 *     something.
 */
class FeedbackFormRules
{
    /**
     * @return array<string, mixed> the validated data, ready for `FeedbackIntake::submit()`
     *
     * @throws ValidationException
     */
    public function validate(Request $request): array
    {
        $dimensionKeys = FeedbackVocabulary::dimensionKeys();

        $data = $request->validate([
            'respondent_name' => ['required', 'string', 'max:255'],
            'respondent_email' => ['nullable', 'email', 'max:255'],
            'respondent_designation' => ['nullable', 'string', 'max:255'],

            'overall_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'nps_score' => ['required', 'integer', 'min:0', 'max:10'],
            'would_order_again' => ['nullable', Rule::in(array_keys(FeedbackVocabulary::wouldOrderAgainOptions()))],

            /* `array:key,...` rather than a loop: a hand-posted dimension that is
               not on the form is rejected, not stored as a line no report knows
               about. */
            'scores' => $dimensionKeys === [] ? ['nullable', 'array'] : ['required', 'array:'.implode(',', $dimensionKeys)],
            'scores.*' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comments' => ['nullable', 'array'],
            'comments.*' => ['nullable', 'string', 'max:1000'],

            'went_well' => ['nullable', 'string', 'max:4000'],
            'could_improve' => ['nullable', 'string', 'max:4000'],
            'testimonial' => ['nullable', 'string', 'max:2000'],

            'publish_website' => ['nullable', 'boolean'],
            'publish_social' => ['nullable', 'boolean'],
            'publish_sales' => ['nullable', 'boolean'],
            'publish_case_study' => ['nullable', 'boolean'],
            'attribution_consent' => ['nullable', 'boolean'],
        ]);

        $scored = collect($data['scores'] ?? [])
            ->filter(fn ($score) => $score !== null && $score !== '')
            ->count();

        if ($scored < FeedbackVocabulary::minimumAnswers()) {
            throw ValidationException::withMessages([
                'scores' => 'Please score at least '.FeedbackVocabulary::minimumAnswers().' of the lines above.',
            ]);
        }

        if (trim((string) ($data['went_well'] ?? '')) === '' && trim((string) ($data['could_improve'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'could_improve' => 'One sentence — what went well, or what could be better — is what makes this useful.',
            ]);
        }

        /* Checkboxes arrive only when ticked; each one is asked for itself, and
           "no" is the default for every use of a quote. */
        $data['attribution_consent'] = $request->boolean('attribution_consent', true);
        $data['publish_website'] = $request->boolean('publish_website');
        $data['publish_social'] = $request->boolean('publish_social');
        $data['publish_sales'] = $request->boolean('publish_sales');
        $data['publish_case_study'] = $request->boolean('publish_case_study');

        return $data;
    }
}

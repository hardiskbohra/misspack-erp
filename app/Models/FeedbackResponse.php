<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One answer, written once.
 *
 * There is no editing path in this class, and that is the point rather than an
 * omission: the office may change a consent flag when the client asks and may
 * add internal notes on the actions, but the score and the words stay as they
 * arrived. A revision is a revoked ask and a fresh one, with both rows kept.
 *
 * The band is computed here — through `FeedbackVocabulary`, so there is still
 * one rule — from the overall rating, the NPS score and the dimension answers.
 * Nothing stores a verdict; `scopeDetractors()` and its siblings express the
 * same rule in SQL so the queues and the figures never disagree with the model.
 */
class FeedbackResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'feedback_request_id', 'project_id', 'client_id',
        'respondent_name', 'respondent_email', 'respondent_designation',
        'overall_rating', 'nps_score', 'would_order_again',
        'went_well', 'could_improve', 'testimonial',
        'attribution_consent', 'publish_website', 'publish_social', 'publish_sales',
        'publish_case_study', 'is_anonymous', 'source', 'submitted_at', 'ip', 'user_agent',
    ];

    protected $casts = [
        'overall_rating' => 'integer',
        'nps_score' => 'integer',
        'attribution_consent' => 'boolean',
        'publish_website' => 'boolean',
        'publish_social' => 'boolean',
        'publish_sales' => 'boolean',
        'publish_case_study' => 'boolean',
        'is_anonymous' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    /* -------------------------------------------------------------- relations */

    public function request()
    {
        return $this->belongsTo(FeedbackRequest::class, 'feedback_request_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function answers()
    {
        return $this->hasMany(FeedbackAnswer::class)->orderBy('id');
    }

    public function actions()
    {
        return $this->hasMany(FeedbackAction::class)->latest('id');
    }

    public function openActions()
    {
        return $this->hasMany(FeedbackAction::class)->whereNotIn('status', [FeedbackAction::STATUS_RESOLVED, FeedbackAction::STATUS_DISMISSED]);
    }

    /* -------------------------------------------------------------------- band */

    /** promoter | passive | detractor — one rule, read from the service. */
    public function band(): string
    {
        return \App\Services\FeedbackVocabulary::band(
            $this->overall_rating,
            $this->nps_score,
            $this->answers->pluck('score')->all()
        );
    }

    public function bandLabel(): string
    {
        return \App\Services\FeedbackVocabulary::bandLabel($this->band());
    }

    public function bandTone(): string
    {
        return \App\Services\FeedbackVocabulary::bandTone($this->band());
    }

    public function isDetractor(): bool
    {
        return $this->band() === \App\Services\FeedbackVocabulary::BAND_DETRACTOR;
    }

    public function isPromoter(): bool
    {
        return $this->band() === \App\Services\FeedbackVocabulary::BAND_PROMOTER;
    }

    /* ----------------------------------------------------------------- helpers */

    public function npsLabel(): string
    {
        return \App\Services\FeedbackVocabulary::npsLabel($this->nps_score);
    }

    public function wouldOrderAgainLabel(): string
    {
        return [
            'yes' => 'Yes, happily',
            'maybe' => 'Maybe',
            'no' => 'No',
        ][$this->would_order_again] ?? '—';
    }

    /** The name the office may print, and the name it must not. */
    public function displayName(): string
    {
        return $this->is_anonymous ? 'Anonymous' : ($this->respondent_name ?: 'Client');
    }

    public function clientName(): string
    {
        $client = $this->client;

        return $client?->company_name ?: ($client?->brand_name ?: 'Client');
    }

    /** Which channels this answer may be quoted in. Empty means none. */
    public function consentChannels(): array
    {
        return array_keys(array_filter([
            'website' => $this->publish_website,
            'social' => $this->publish_social,
            'sales' => $this->publish_sales,
            'case_study' => $this->publish_case_study,
        ]));
    }

    public function hasConsent(): bool
    {
        return $this->consentChannels() !== [];
    }

    /** Quotable: consented to publish *and* not asked to stay unnamed. */
    public function isQuotable(): bool
    {
        return $this->hasConsent() && ! $this->is_anonymous;
    }

    public function hasOpenAction(): bool
    {
        return $this->relationLoaded('actions')
            ? $this->actions->contains(fn (FeedbackAction $action) => $action->isOpen())
            : $this->openActions()->exists();
    }

    /* ----------------------------------------------------------------- scopes */

    public function scopeKind(Builder $query, ?string $kind): Builder
    {
        return $kind ? $query->whereHas('request', fn ($q) => $q->where('kind', $kind)) : $query;
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /** The one place the detractor rule is written in SQL. */
    public function scopeDetractors(Builder $query): Builder
    {
        return $query->where(function (Builder $rule) {
            $rule->where('overall_rating', '<=', \App\Services\FeedbackVocabulary::DETRACTOR_OVERALL)
                ->orWhere('nps_score', '<=', \App\Services\FeedbackVocabulary::DETRACTOR_NPS)
                ->orWhereHas('answers', fn ($q) => $q->where('score', '<=', \App\Services\FeedbackVocabulary::DETRACTOR_DIMENSION));
        });
    }

    public function scopePromoters(Builder $query): Builder
    {
        return $query->where('overall_rating', '>=', \App\Services\FeedbackVocabulary::PROMOTER_OVERALL)
            ->where('nps_score', '>=', \App\Services\FeedbackVocabulary::PROMOTER_NPS)
            ->whereDoesntHave('answers', fn ($q) => $q->where('score', '<', \App\Services\FeedbackVocabulary::PROMOTER_MIN_DIMENSION));
    }

    /** Answers nobody is doing anything about yet — the first thing read. */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->detractors()
            ->whereDoesntHave('actions', fn ($q) => $q->whereIn('status', [FeedbackAction::STATUS_RESOLVED, FeedbackAction::STATUS_DISMISSED]));
    }

    public function scopeQuotable(Builder $query): Builder
    {
        return $query->where('is_anonymous', false)
            ->where(fn ($q) => $q->where('publish_website', true)
                ->orWhere('publish_social', true)
                ->orWhere('publish_sales', true)
                ->orWhere('publish_case_study', true));
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $nested) use ($search) {
            $nested->where('respondent_name', 'like', "%{$search}%")
                ->orWhere('went_well', 'like', "%{$search}%")
                ->orWhere('could_improve', 'like', "%{$search}%")
                ->orWhere('testimonial', 'like', "%{$search}%")
                ->orWhereHas('project', fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('project_number', 'like', "%{$search}%"))
                ->orWhereHas('client', fn ($q) => $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('brand_name', 'like', "%{$search}%"));
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One score on one line of the scorecard.
 *
 * The dimension is a key, and the label lives in the vocabulary — which is what
 * lets a dimension be renamed without rewriting history, and lets the report
 * group by a key whose meaning is defined in exactly one place.
 */
class FeedbackAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'feedback_response_id', 'dimension', 'score', 'comment',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function response()
    {
        return $this->belongsTo(FeedbackResponse::class, 'feedback_response_id');
    }

    public function dimensionLabel(): string
    {
        return \App\Services\FeedbackVocabulary::dimensionLabel($this->dimension);
    }

    public function scoreLabel(): string
    {
        return \App\Services\FeedbackVocabulary::scoreLabel($this->score);
    }

    /** The tone of a single line: the same five-point scale as the overall. */
    public function tone(): string
    {
        return \App\Services\FeedbackVocabulary::scoreTone($this->score);
    }
}

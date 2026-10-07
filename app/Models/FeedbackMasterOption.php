<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A word the module uses, in the office's own keeping.
 *
 * The same shape as `LeadMasterOption`, for the same reason: the questions a
 * packaging client is asked about a project are the business's vocabulary, not a
 * developer's. The settings screen writes these rows; `FeedbackVocabulary`
 * reads them; nothing else does.
 *
 * `group` says which list a row belongs to — today `dimension`, and the action
 * types and severities use the built-in lists until the office needs to change
 * them, at which point they move here without a schema change.
 */
class FeedbackMasterOption extends Model
{
    use HasFactory;

    public const GROUP_DIMENSION = 'dimension';

    protected $fillable = [
        'group', 'key', 'label', 'color', 'hint', 'sort_order', 'is_active', 'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    public function groupLabel(): string
    {
        return self::groupOptions()[$this->group] ?? Str::headline($this->group);
    }

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }

    /** A key safe to store in `feedback_answers.dimension`. */
    public static function keyFrom(string $label, ?string $key = null): string
    {
        $key = trim((string) $key);

        if ($key !== '') {
            return Str::slug($key, '_');
        }

        return Str::slug($label, '_') ?: 'dimension_'.Str::lower(Str::random(6));
    }

    public static function groupOptions(): array
    {
        return [
            self::GROUP_DIMENSION => 'Scorecard dimension',
            'action_type' => 'Follow-up type',
            'severity' => 'Follow-up severity',
        ];
    }
}

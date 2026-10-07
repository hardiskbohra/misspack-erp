<?php

namespace App\Models;

use App\Services\AssetVocabulary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A class of fixed asset, and the depreciation recipe its members inherit.
 *
 * Read the migration first — it explains why the life, the method and the
 * residual percent live on the class rather than on each chair. This model adds
 * only the readings a screen needs (the labels, the formatted numbers) and the
 * two scopes the settings page and the asset form ask for.
 *
 * It is a **setting**, not a record: it lives in the settings module
 * (`/settings/assets`) and is edited there, because changing a class's useful
 * life changes how every asset in it is depreciated for everybody. Deleting one
 * is refused while assets still point at it — `AssetSettingController` says so in
 * words rather than orphaning a register.
 */
class FixedAssetCategory extends Model
{
    protected $table = 'fixed_asset_categories';

    protected $fillable = [
        'code', 'name', 'useful_life_years', 'depreciation_method',
        'residual_percent', 'sort_order', 'notes', 'is_active',
    ];

    protected $casts = [
        'useful_life_years' => 'integer',
        'residual_percent' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /* --------------------------------------------------------------- relations */

    public function assets(): HasMany
    {
        return $this->hasMany(FixedAsset::class, 'category_id');
    }

    /* ------------------------------------------------------------------ scopes */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /* ---------------------------------------------------------------- readings */

    public function methodLabel(): string
    {
        return AssetVocabulary::methodLabel($this->depreciation_method);
    }

    /** "10 years", or "—" for a class nobody depreciates (land). */
    public function lifeLabel(): string
    {
        if (! $this->useful_life_years || $this->depreciation_method === AssetVocabulary::METHOD_NONE) {
            return 'Not depreciated';
        }

        return $this->useful_life_years.' '.\Illuminate\Support\Str::plural('year', $this->useful_life_years);
    }

    /** The life, the method and the residual as one line — the form's help text. */
    public function recipeLabel(): string
    {
        if ($this->depreciation_method === AssetVocabulary::METHOD_NONE) {
            return 'Not depreciated';
        }

        return $this->lifeLabel().' · '.$this->methodLabel().' · '.AssetVocabulary::percentLabelTrimmed((float) $this->residual_percent).'% left';
    }

    /** The residual as the column says it — one formatter, the register's own. */
    public function residualLabel(): string
    {
        return AssetVocabulary::percentLabel((float) $this->residual_percent);
    }

    /** Whether the class is still offered to new assets. */
    public function stateLabel(): string
    {
        return $this->is_active ? 'In use' : 'Retired';
    }

    public function stateTone(): string
    {
        return $this->is_active ? 'success' : 'neutral';
    }
}

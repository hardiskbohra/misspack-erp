<?php

namespace App\Http\Controllers;

use App\Models\FixedAssetCategory;
use App\Services\AssetVocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The assets area of the settings module: the classes, and the depreciation
 * recipe each one hands to its assets.
 *
 * This is a **setting** and it lives here — under the boundary rule in
 * `docs/settings-module.md`, a class changes how the module behaves for
 * everybody, and a class is not something the company owns. The chairs are, so
 * the register is a module of its own (`/fixed-assets`) and this page links to
 * it. One page, one list, and the two are not mixed up.
 *
 * It owns its writes the way every settings area does: the validation, the
 * uniqueness of the code, and the one refusal that matters — a class still
 * holding assets is not deleted, because a register cannot have a row whose
 * class has stopped existing.
 */
class AssetSettingController extends Controller
{
    public function index(): View
    {
        return view('settings.assets', [
            'categories' => FixedAssetCategory::query()
                ->withCount('assets')
                ->ordered()
                ->get(),
            'methodOptions' => AssetVocabulary::METHODS,
            'defaultResidual' => AssetVocabulary::DEFAULT_RESIDUAL_PERCENT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        FixedAssetCategory::create($data);

        return redirect()
            ->route('settings.assets')
            ->with('success', 'Class added — new assets in it inherit its life, method and residual value.');
    }

    public function update(Request $request, FixedAssetCategory $category): RedirectResponse
    {
        $data = $this->validatedData($request, $category);

        $category->update($data);

        return redirect()
            ->route('settings.assets')
            ->with('success', 'Class updated. Assets that follow the class are depreciated by the new recipe from now on.');
    }

    /**
     * Remove a class — unless it is holding assets.
     *
     * The refusal is the point of this method. An asset points at its class for
     * the recipe it inherits, so deleting a class out from under a register would
     * silently change how those assets depreciate (or leave them unclassified).
     * The office is told how many are in the way and what to do instead.
     */
    public function destroy(FixedAssetCategory $category): RedirectResponse
    {
        $count = $category->assets()->count();

        if ($count > 0) {
            $sentence = $count.' '.\Illuminate\Support\Str::plural('asset', $count).' '
                .($count === 1 ? 'still uses' : 'still use')
                .' this class — move them to another class first, then remove it.';

            return back()->with('error', $sentence);
        }

        $category->delete();

        return redirect()
            ->route('settings.assets')
            ->with('success', 'Class removed.');
    }

    /**
     * The class's own fields, checked — the residual value is a percentage of
     * cost, so it is bounded to 0–100 rather than left to arithmetic.
     */
    private function validatedData(Request $request, ?FixedAssetCategory $category = null): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('fixed_asset_categories', 'code')->ignore($category?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'depreciation_method' => ['required', Rule::in(array_keys(AssetVocabulary::METHODS))],
            'residual_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['residual_percent'] = $data['residual_percent'] ?? AssetVocabulary::DEFAULT_RESIDUAL_PERCENT;
        $data['sort_order'] = $data['sort_order'] ?? 10;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}

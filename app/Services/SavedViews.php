<?php

namespace App\Services;

use App\Models\SavedView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Named filter sets ("Arriving this week", "Overdue", "My projects") that a
 * user can jump back to with one click.
 *
 * Deliberately module-agnostic: the row stores the module name and the query
 * string exactly as it came from the URL, so any list screen in the ERP can
 * use the same service without a new table.
 */
class SavedViews
{
    public function available(): bool
    {
        return class_exists(SavedView::class) && Schema::hasTable('saved_views');
    }

    /**
     * Views the user saved, plus anything shared by colleagues.
     */
    public function forUser(?int $userId, string $module): Collection
    {
        if (! $this->available() || ! $userId) {
            return collect();
        }

        return SavedView::query()
            ->where('module', $module)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)->orWhere('is_shared', true);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters  the request query (without page/sort noise)
     */
    public function save(int $userId, string $module, string $name, array $filters, bool $shared = false): SavedView
    {
        $clean = $this->cleanFilters($filters);

        return SavedView::updateOrCreate(
            ['user_id' => $userId, 'module' => $module, 'name' => trim($name)],
            ['query' => http_build_query($clean), 'is_shared' => $shared]
        );
    }

    public function delete(int $userId, int $viewId): bool
    {
        if (! $this->available()) {
            return false;
        }

        return (bool) SavedView::query()
            ->where('user_id', $userId)
            ->whereKey($viewId)
            ->delete();
    }

    /**
     * @return array<string, string>
     */
    public function queryFor(SavedView $view): array
    {
        parse_str((string) $view->query, $params);

        return array_filter($params, fn ($value) => $value !== '' && $value !== null);
    }

    /**
     * Drop empty values plus transient params (paging, per-request tokens) so a
     * saved view always resolves to the same list.
     */
    public function cleanFilters(array $filters): array
    {
        $ignored = ['page', '_token', '_method', 'saved_view', 'save_view'];

        return collect($filters)
            ->reject(fn ($value, $key) => in_array($key, $ignored, true) || $value === '' || $value === null || $value === 'all')
            ->map(fn ($value) => is_scalar($value) ? (string) $value : null)
            ->filter(fn ($value) => $value !== null)
            ->all();
    }
}

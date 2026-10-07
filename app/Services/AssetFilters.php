<?php

namespace App\Services;

use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Everything the register can be narrowed by, in one place.
 *
 * The register's figures, its chip counts, its table, its export and the
 * depreciation report are all this one builder or a clone of it, so "In use" has
 * to mean the same thing to every one of them; a filter is exactly where that
 * agreement breaks. The keys and the words live here, the SQL is asked of the
 * asset's own scopes, and there is one `apply()`.
 *
 * Two things are deliberately shaped by what the module is:
 *
 *   - **the sort options stop at what SQL can order.** Newest, oldest, name and
 *     invoice value are columns; *net book value* is a curve, and a sort that had
 *     to compute every asset's depreciation before it could order the page is a
 *     register that dies at a thousand assets. The drawer offers the four, and
 *     the register's own figures answer the fifth question;
 *   - **the attention filters are the compliance ones** — warranty running out,
 *     insurance cover expired, nobody having laid hands on the asset in a year,
 *     and a service that has come due. Those are the readings the office acts on,
 *     so they are filters rather than numbers buried in a report, and the count
 *     printed beside each one is that same filter run as a count.
 */
class AssetFilters
{
    /** The filter keys, and what an unset one means. */
    public const DEFAULTS = [
        'q' => null,
        'status' => 'everything',   // an AssetVocabulary::STATE_LABELS key
        'category' => 'all',        // a fixed_asset_categories id, or all
        'location' => 'all',
        'department' => 'all',
        'custodian' => 'all',       // a user id, or all
        'condition' => 'all',       // an AssetVocabulary::CONDITIONS key
        'warranty' => 'any',        // any | expiring | expired | covered | unrecorded
        'verification' => 'any',    // any | overdue | never | recent
        'service' => 'any',         // any | due — the repair diary, 60 days out
        'sort' => 'newest',         // newest | oldest | name | cost
    ];

    /** The drawer: the four orders SQL can give the register. */
    public const SORT_LABELS = [
        'newest' => 'Newest purchase first',
        'oldest' => 'Oldest purchase first',
        'name' => 'Name A–Z',
        'cost' => 'Largest invoice value',
    ];

    /**
     * Each order choice is one of the model's order scopes — the map is the
     * contract, and `assets-check` reads both ends.
     */
    public const SORT_SCOPES = [
        'newest' => 'newestFirst',
        'oldest' => 'oldestFirst',
        'name' => 'nameOrder',
        'cost' => 'costOrder',
    ];

    /** The drawer: the warranty window. */
    public const WARRANTY_LABELS = [
        'any' => 'Any warranty state',
        'expiring' => 'Warranty ends in 60 days',
        'expired' => 'Warranty expired',
        'covered' => 'Covered for a while yet',
        'unrecorded' => 'No warranty recorded',
    ];

    /** The drawer: the physical-verification window (CARO's "reasonable interval"). */
    public const VERIFICATION_LABELS = [
        'any' => 'Any verification state',
        'overdue' => 'Not verified in a year',
        'never' => 'Never verified',
        'recent' => 'Verified within a year',
    ];

    /** The words the "Filtered by" strip wears. */
    /** The service diary, as a filter — the chip the repair log's count links to. */
    public const SERVICE_LABELS = [
        'any' => 'Any service state',
        'due' => 'Service due (60 days)',
    ];

    public const LABELS = [
        'category' => 'Class',
        'location' => 'Location',
        'department' => 'Department',
        'custodian' => 'Custodian',
        'condition' => 'Condition',
        'warranty' => 'Warranty',
        'verification' => 'Verification',
        'service' => 'Service due',
        'sort' => 'Order',
    ];

    /**
     * The filters a request carries, every value checked against what the screen
     * offers: a hand-typed `?warranty=garbage` is the default view, not an empty
     * list and not a 500.
     *
     * @return array<string, mixed>
     */
    public function fromRequest(Request $request): array
    {
        $status = (string) $request->query('status', self::DEFAULTS['status']);
        if (! array_key_exists($status, AssetVocabulary::STATE_LABELS)) {
            $status = self::DEFAULTS['status'];
        }

        $condition = (string) $request->query('condition', 'all');
        if ($condition !== 'all' && ! AssetVocabulary::hasCondition($condition)) {
            $condition = 'all';
        }

        $warranty = (string) $request->query('warranty', self::DEFAULTS['warranty']);
        if (! array_key_exists($warranty, self::WARRANTY_LABELS)) {
            $warranty = self::DEFAULTS['warranty'];
        }

        $verification = (string) $request->query('verification', self::DEFAULTS['verification']);
        if (! array_key_exists($verification, self::VERIFICATION_LABELS)) {
            $verification = self::DEFAULTS['verification'];
        }

        $service = (string) $request->query('service', self::DEFAULTS['service']);
        if (! array_key_exists($service, self::SERVICE_LABELS)) {
            $service = self::DEFAULTS['service'];
        }

        $sort = (string) $request->query('sort', self::DEFAULTS['sort']);
        if (! array_key_exists($sort, self::SORT_LABELS)) {
            $sort = self::DEFAULTS['sort'];
        }

        $category = $this->identifier($request->query('category'));
        $custodian = $this->identifier($request->query('custodian'));

        return [
            'q' => trim((string) $request->query('q', '')) ?: null,
            'status' => $status,
            'category' => $category,
            'location' => $this->text($request->query('location')),
            'department' => $this->text($request->query('department')),
            'custodian' => $custodian,
            'condition' => $condition,
            'warranty' => $warranty,
            'verification' => $verification,
            'service' => $service,
            'sort' => $sort,
        ];
    }

    /** The filters that are actually narrowing something, as a query string. */
    public function toQuery(array $filters): array
    {
        $query = [];

        foreach (self::DEFAULTS as $key => $default) {
            $value = $filters[$key] ?? null;

            if ($value === null || $value === '' || $value === $default) {
                continue;
            }

            $query[$key] = (string) $value;
        }

        return $query;
    }

    /** The same filters read as if no state chip were selected. */
    public function withoutStatus(array $filters): array
    {
        return array_merge($filters, ['status' => 'everything']);
    }

    /**
     * The chips the "Filtered by" strip wears: the drawer's criteria only. The
     * status is on the chip bar already and the search term is in its box.
     *
     * @return array<int, array{key: string, label: string, value: string, query: array<int, string>}>
     */
    public function applied(array $filters): array
    {
        $applied = [];

        $add = function (string $key, string $value) use (&$applied) {
            $applied[] = [
                'key' => $key,
                'label' => self::LABELS[$key] ?? $key,
                'value' => $value,
                'query' => [$key],
            ];
        };

        if (($filters['category'] ?? 'all') !== 'all') {
            $name = FixedAssetCategory::query()->whereKey($filters['category'])->value('name');
            $add('category', $name ?: 'Class #'.$filters['category']);
        }

        foreach (['location', 'department'] as $key) {
            if (($filters[$key] ?? 'all') !== 'all' && $filters[$key] !== null) {
                $add($key, (string) $filters[$key]);
            }
        }

        if (($filters['custodian'] ?? 'all') !== 'all') {
            $name = User::query()->whereKey($filters['custodian'])->value('name');
            $add('custodian', $name ?: 'Person #'.$filters['custodian']);
        }

        if (($filters['condition'] ?? 'all') !== 'all') {
            $add('condition', AssetVocabulary::conditionLabel($filters['condition']));
        }

        if (($filters['warranty'] ?? 'any') !== 'any') {
            $add('warranty', self::WARRANTY_LABELS[$filters['warranty']] ?? $filters['warranty']);
        }

        if (($filters['verification'] ?? 'any') !== 'any') {
            $add('verification', self::VERIFICATION_LABELS[$filters['verification']] ?? $filters['verification']);
        }

        if (($filters['service'] ?? 'any') !== 'any') {
            $add('service', 'Service due in the next 60 days');
        }

        if (($filters['sort'] ?? 'newest') !== 'newest') {
            $add('sort', self::SORT_LABELS[$filters['sort']] ?? $filters['sort']);
        }

        return $applied;
    }

    /* ------------------------------------------------------------------ queries */

    /** **The** query. Every readout on the register is this builder or a clone of it. */
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->search($filters['q'] ?? null)
            ->inStatus(($filters['status'] ?? 'everything') !== 'everything' ? $filters['status'] : null)
            ->inCategory(($filters['category'] ?? 'all') !== 'all' ? (int) $filters['category'] : null)
            ->atLocation($filters['location'] ?? null)
            ->inDepartment($filters['department'] ?? null)
            ->heldBy(($filters['custodian'] ?? 'all') !== 'all' ? (int) $filters['custodian'] : null)
            ->withCondition(($filters['condition'] ?? 'all') !== 'all' ? $filters['condition'] : null)
            ->warrantyState(($filters['warranty'] ?? 'any') !== 'any' ? $filters['warranty'] : null)
            ->verificationState(($filters['verification'] ?? 'any') !== 'any' ? $filters['verification'] : null)
            ->serviceDue(($filters['service'] ?? 'any') === 'due' ? 60 : null);
    }

    /** The order the register is read in. */
    public function order(Builder $query, array $filters): Builder
    {
        $scope = self::SORT_SCOPES[$filters['sort'] ?? 'newest'] ?? self::SORT_SCOPES['newest'];

        return $query->{$scope}();
    }

    /**
     * A page of the register — taken from a **clone**, never from the builder the
     * figures and the attention counts borrow.
     *
     * The house rule the recurring desk paid for in production: a builder is
     * either a filter or a page, never both — `paginate()` writes its LIMIT on the
     * builder it is called on, and MySQL refuses a LIMIT inside the IN subquery
     * that a later read of the same builder would produce (error 1235).
     * `tools/checks/sql-check.cjs` guards it for the whole application; this
     * method is where this page keeps it.
     */
    public function page(Builder $query, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->order(clone $query, $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /* ------------------------------------------------------ what the drawer offers */

    /** The locations actually in use, so the select offers the office's own words. */
    public function locations(): array
    {
        return FixedAsset::query()
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->orderBy('location')
            ->pluck('location')
            ->all();
    }

    public function departments(): array
    {
        return FixedAsset::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->all();
    }

    /** A number an id came in as, or the sentinel for "any". */
    private function identifier($value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return preg_match('/^\d+$/', $value) && (int) $value > 0 ? $value : 'all';
    }

    private function text($value): ?string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return ($text === '' || $text === 'all') ? null : $text;
    }
}

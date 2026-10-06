<?php

namespace App\Services;

use App\Models\CashflowEntry;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * A project's receipts, as the client is allowed to see them.
 *
 * A project payment used to be its own row (`project_payments`), recorded on
 * the project and — if the office published it — shown to the client. That
 * table is gone: the cashflow ledger is the source of truth for project-level
 * money, so a receipt *is* a ledger entry tagged to the project. Two rules
 * decide what a client sees, and they are the same rules the old published
 * rows carried: the money came in, and the row is booked or reconciled. A
 * tentative ledger row never reaches a client.
 *
 * One reader, four screens: the portal's payments page, a project's own
 * receipts panel, the portal dashboard's tally and the statement of account.
 * They ask here so the four can never disagree about what a receipt is.
 */
class ProjectReceipts
{
    /** Whether the ledger can answer at all (a deployment mid-migration cannot). */
    public static function available(): bool
    {
        return class_exists(CashflowEntry::class)
            && Schema::hasTable('cashflow_entries')
            && Schema::hasColumn('cashflow_entries', 'project_id')
            && Schema::hasColumn('cashflow_entries', 'accounting_status')
            && Schema::hasColumn('cashflow_entries', 'transaction_type');
    }

    /**
     * The projects a client may see at all — the same flag the portal's
     * project list, shipments and invoices filter on.
     *
     * @return list<int>
     */
    public static function publishedProjectIds(int $clientId): array
    {
        if (! class_exists(Project::class) || ! Schema::hasTable('projects')
            || ! Schema::hasColumn('projects', 'show_client_portal')) {
            return [];
        }

        return Project::query()
            ->where('client_id', $clientId)
            ->where('show_client_portal', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Receipts on any of these projects. The one definition of the fact:
     * money in, booked or reconciled, oldest first (a statement reads down
     * the page).
     */
    public static function query(array $projectIds): Builder
    {
        return CashflowEntry::query()
            ->with('project')
            ->whereIn('project_id', $projectIds)
            ->moneyIn()
            ->whereIn('accounting_status', ['booked', 'reconciled'])
            ->orderBy('entry_date')
            ->orderBy('id');
    }

    /** @return Collection<int, CashflowEntry> */
    public static function forProjects(array $projectIds): Collection
    {
        if (! self::available() || $projectIds === []) {
            return collect();
        }

        return self::query($projectIds)->get();
    }

    /** @return Collection<int, CashflowEntry> */
    public static function forClient(int $clientId): Collection
    {
        return self::forProjects(self::publishedProjectIds($clientId));
    }

    /** The figure the portal dashboard counts. */
    public static function countForClient(int $clientId): int
    {
        $ids = self::publishedProjectIds($clientId);

        return self::available() && $ids !== [] ? self::query($ids)->count() : 0;
    }
}

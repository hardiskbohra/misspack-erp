<?php

namespace App\Console\Commands;

use App\Services\RecurrenceIntake;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * The morning round for the recurring-cashflow module.
 *
 * Three things, in the order they depend on each other: top every running rule's
 * plan up, ask for the approvals that have reached their effective date, and end
 * the rules whose last date has been decided. All of it is idempotent — the
 * module's own page runs the same sweep when nobody's cron has — so a missed day
 * costs nothing but a later email, and a run twice in one morning asks nobody
 * twice.
 *
 * It is scheduled before the office briefings, so the approvals that need
 * attention are already in the list when the 09:15 briefing goes out.
 */
class ReleaseRecurringCashflows extends Command
{
    protected $signature = 'cashflows:recurring';

    protected $description = 'Plan the next dates of running recurring rules and raise the approval asks that are due today.';

    public function handle(RecurrenceIntake $intake): int
    {
        if (! Schema::hasTable('cashflow_recurrence_rules')) {
            $this->warn('The recurring-cashflow tables are not migrated yet.');

            return self::SUCCESS;
        }

        $notified = $intake->release();

        $count = $notified->count();

        $this->info($count.' approval request'.($count === 1 ? '' : 's').' raised.');

        return self::SUCCESS;
    }
}

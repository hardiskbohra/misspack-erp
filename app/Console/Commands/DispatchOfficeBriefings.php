<?php

namespace App\Console\Commands;

use App\Services\OfficeBriefing;
use Illuminate\Console\Command;

class DispatchOfficeBriefings extends Command
{
    protected $signature = 'office:briefings {--chase : Email watchers about unacked critical items}';

    protected $description = 'Raise scheduled office briefings, and optionally chase unacked critical ones by email.';

    public function handle(OfficeBriefing $briefing): int
    {
        if ($this->option('chase')) {
            $sent = $briefing->chaseUnacked();
            $this->info($sent.' unacked briefing'.($sent === 1 ? '' : 's').' emailed.');

            return self::SUCCESS;
        }

        $raised = $briefing->runScheduled();
        $this->info(count($raised).' briefing'.(count($raised) === 1 ? '' : 's').' for today.');

        return self::SUCCESS;
    }
}

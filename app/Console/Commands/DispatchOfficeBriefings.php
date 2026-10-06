<?php

namespace App\Console\Commands;

use App\Services\OfficeBriefing;
use Illuminate\Console\Command;

class DispatchOfficeBriefings extends Command
{
    protected $signature = 'office:briefings';

    protected $description = 'Raise the daily office briefings (in-transit shipments, holds, pending cashflow).';

    public function handle(OfficeBriefing $briefing): int
    {
        $raised = $briefing->runScheduled();
        $this->info(count($raised).' briefing'.(count($raised) === 1 ? '' : 's').' for today.');

        return self::SUCCESS;
    }
}

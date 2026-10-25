<?php

namespace App\Console\Commands;

use App\Services\ProjectFeedbackClosure;
use App\Services\ProjectWorkflowNotifications;
use Illuminate\Console\Command;

class AutomateProjectFeedbackClosure extends Command
{
    protected $signature = 'projects:feedback-closure';

    protected $description = 'Synchronize final-feedback gates, issue close-out asks, send due reminders and raise workflow alerts.';

    public function handle(ProjectFeedbackClosure $closure, ProjectWorkflowNotifications $notifications): int
    {
        $stats = $closure->runScheduled();
        $stats['alerts'] += $notifications->run();
        $this->info(sprintf(
            '%d eligible project(s): %d ask(s) issued, %d reminder(s) sent, %d new workflow alert(s).',
            $stats['projects'], $stats['issued'], $stats['reminded'], $stats['alerts']
        ));

        return self::SUCCESS;
    }
}

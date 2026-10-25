<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/* The recurring cashflow sweep runs before the briefings, so the payments that
   reached their effective date this morning are already waiting in the list the
   09:15 briefing sends. `--chase` then keeps nudging the ones nobody answered. */
Schedule::command('cashflows:recurring')->dailyAt('08:45');
/* Final-feedback state is refreshed before the briefing so newly due closure
   actions are already in the acknowledged office inbox and watcher email. */
Schedule::command('projects:feedback-closure')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('office:briefings')->dailyAt('09:15');
Schedule::command('office:briefings --chase')->hourly();

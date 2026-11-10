<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendly:prune-attendance-selfies')->daily();
Schedule::command('attendly:prune-backups')->dailyAt('02:30');
Schedule::command('attendly:notify-missing-attendance')->everyFiveMinutes();

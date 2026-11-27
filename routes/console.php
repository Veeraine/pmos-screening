<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Daily backup, checked every hour so a day isn't missed if the PC is switched on late.
 * Needs Task Scheduler (or cron) to run "php artisan schedule:run" every minute.
 */
Schedule::command('pmos:backup')
    ->hourly()
    ->withoutOverlapping(60);

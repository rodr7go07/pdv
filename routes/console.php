<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mantenimiento diario (el cron del servidor ejecuta `php artisan schedule:run` cada minuto).
Schedule::command('queue:prune-failed --hours=720')->dailyAt('03:10');
Schedule::command('queue:prune-batches --hours=720')->dailyAt('03:15');

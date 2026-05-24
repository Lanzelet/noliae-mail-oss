<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reset nightly de l'instance démo (no-op si DEMO_RESET != true dans .env).
// Active le scheduler dans le container : ajoute un cron host
//   * * * * * docker compose exec -T web php artisan schedule:run
Schedule::command('demo:reset')->dailyAt('03:00')->onOneServer();

// Purge audit log > 90 jours (compliance RGPD)
Schedule::command('model:prune')->daily()->onOneServer();

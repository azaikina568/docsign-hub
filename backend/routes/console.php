<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// onOneServer: если планировщиков несколько, задачу выполнит только один (Redis-lock).
Schedule::command('documents:expire')->daily()->onOneServer();

// Чистим протухшие токены (в т.ч. ротированные refresh) — они уже невалидны.
Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// onOneServer: если планировщиков несколько, задачу выполнит только один (Redis-lock).
Schedule::command('documents:expire')->daily()->onOneServer();

<?php

use Illuminate\Support\Facades\Schedule;

// onOneServer: если планировщиков несколько, задачу выполнит только один (Redis-lock).
Schedule::command('documents:expire')->daily()->onOneServer();

// Чистим протухшие токены (в т.ч. ротированные refresh) — они уже невалидны.
Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();

// Чистим доставленные outbox- и обработанные inbox-строки, чтобы служебные таблицы не росли без предела.
Schedule::command('messaging:prune')->daily()->onOneServer();

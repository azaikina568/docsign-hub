<?php

/*
 * Тестовое окружение должно быть детерминированным независимо от того, где запускаемся.
 * В Docker реальные env из .env (CACHE_STORE=redis, DB_CONNECTION=pgsql) перекрывают значения
 * phpunit.xml (Dotenv не перетирает уже существующие переменные), и тесты пошли бы на общий
 * Redis/Postgres — тогда rate-limit «протекает» между тестами (ложные 429). Выставляем нужные
 * сторы здесь, до загрузки фреймворка, чтобы перебить и .env, и реальное окружение контейнера.
 */
$testEnv = [
    'APP_ENV' => 'testing',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
];

foreach ($testEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';

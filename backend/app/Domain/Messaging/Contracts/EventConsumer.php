<?php

namespace App\Domain\Messaging\Contracts;

use App\Domain\Messaging\Data\InboundEvent;

/**
 * Обработчик доменных событий из брокера. Один consumer = одна очередь и одно имя
 * (имя — ключ дедупа в inbox). Реализации идемпотентны на уровне эффекта (см. InboxGuard).
 */
interface EventConsumer
{
    /**
     * Имя consumer'а (ключ inbox/идемпотентности).
     */
    public function name(): string;

    /**
     * Очередь, из которой consumer читает события.
     */
    public function queue(): string;

    public function handle(InboundEvent $event): void;
}

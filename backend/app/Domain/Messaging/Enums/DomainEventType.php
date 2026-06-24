<?php

namespace App\Domain\Messaging\Enums;

/**
 * Реестр доменных событий: единый источник истины по типам, версиям и routing key'ам событий,
 * которые пишутся в outbox и публикуются в exchange `docsign.events`. Форма payload'а каждого —
 * в `contracts/events/v1`. Новый тип события заводится здесь, чтобы продюсер и consumer'ы не
 * расходились по «магическим строкам».
 */
enum DomainEventType: string
{
    case DocumentCreated = 'document.created';
    case DocumentSent = 'document.sent';
    case DocumentSigned = 'document.signed';
    case DocumentCompleted = 'document.completed';
    case DocumentCancelled = 'document.cancelled';
    case DocumentExpired = 'document.expired';

    /**
     * Версия схемы payload'а. Растёт только при ломающем изменении формы (новое поле — та же версия).
     */
    public function version(): int
    {
        return 1;
    }

    /**
     * Routing key для брокера, например `document.signed.v1`.
     */
    public function routingKey(): string
    {
        return "{$this->value}.v{$this->version()}";
    }

    /**
     * Тип агрегата — префикс типа события (`document`).
     */
    public function aggregateType(): string
    {
        return strtok($this->value, '.') ?: $this->value;
    }
}

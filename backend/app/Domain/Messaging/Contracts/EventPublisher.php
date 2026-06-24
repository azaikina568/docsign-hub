<?php

namespace App\Domain\Messaging\Contracts;

/**
 * Публикация доменного события в брокер. За контрактом — конкретный транспорт (RabbitMQ),
 * который подменяется фейком в тестах и может быть заменён без правки доменного кода.
 */
interface EventPublisher
{
    /**
     * Публикует одно событие. Бросает исключение при сбое доставки — вызывающий решает про ретрай.
     * `$messageId` (event_id) проставляется в свойство сообщения для дедупликации на стороне consumer'а.
     *
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $routingKey, array $payload, string $messageId): void;
}

<?php

namespace App\Infrastructure\Messaging;

use App\Domain\Messaging\Data\InboundEvent;
use Closure;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * Блокирующий consumer-цикл поверх php-amqplib. Manual ack: сообщение подтверждается только после
 * успешной обработки; на ошибке — nack без requeue, и брокер уводит его в DLX/DLQ (ручной разбор).
 * prefetch=1 — не набираем в воркер больше одного необработанного сообщения.
 */
class RabbitMqConsumer
{
    public function __construct(private readonly RabbitMqConnection $connection) {}

    /**
     * @param  Closure(InboundEvent): void  $handler
     */
    public function consume(string $queue, Closure $handler): void
    {
        $channel = $this->connection->channel();
        $channel->basic_qos(0, 1, false);

        $channel->basic_consume(
            $queue,
            consumer_tag: '',
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: function (AMQPMessage $message) use ($handler): void {
                $this->dispatch($message, $handler);
            },
        );

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    /**
     * @param  Closure(InboundEvent): void  $handler
     */
    private function dispatch(AMQPMessage $message, Closure $handler): void
    {
        try {
            /** @var array<string, mixed> $envelope */
            $envelope = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);

            $handler(InboundEvent::fromEnvelope($envelope));

            $message->ack();
        } catch (Throwable $e) {
            report($e);
            // requeue=false → сообщение уходит в DLX/DLQ, а не крутится в бесконечном retry.
            $message->nack(false);
        }
    }
}

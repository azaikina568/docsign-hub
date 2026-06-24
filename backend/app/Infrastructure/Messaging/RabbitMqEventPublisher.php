<?php

namespace App\Infrastructure\Messaging;

use App\Domain\Messaging\Contracts\EventPublisher;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class RabbitMqEventPublisher implements EventPublisher
{
    private ?AMQPChannel $channel = null;

    public function __construct(
        private readonly RabbitMqConnection $connection,
        private readonly string $exchange,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function publish(string $routingKey, array $payload, string $messageId): void
    {
        try {
            $channel = $this->channel();

            $message = new AMQPMessage(
                (string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'message_id' => $messageId,
                    'type' => (string) ($payload['event_type'] ?? $routingKey),
                    'timestamp' => time(),
                ],
            );

            $channel->basic_publish($message, $this->exchange, $routingKey);
            // Подтверждение издателя: ждём ack брокера. Без него publish «успешен» даже когда сообщение не дошло.
            $channel->wait_for_pending_acks(5.0);
        } catch (Throwable $e) {
            // Канал/соединение могли испортиться — сбрасываем, чтобы следующая попытка переподключилась.
            $this->reset();

            throw $e;
        }
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel === null) {
            $this->channel = $this->connection->channel();
            $this->channel->confirm_select();
            // durable topic exchange; объявление идемпотентно (совпадает с messaging:setup).
            $this->channel->exchange_declare($this->exchange, 'topic', false, true, false);
        }

        return $this->channel;
    }

    private function reset(): void
    {
        $this->channel = null;
        $this->connection->close();
    }
}

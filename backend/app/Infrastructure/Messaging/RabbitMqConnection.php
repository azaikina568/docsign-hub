<?php

namespace App\Infrastructure\Messaging;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Ленивое соединение с RabbitMQ. Держим одно соединение на процесс (воркер/команда),
 * переоткрываем при разрыве. Инфраструктурный слой — за доменными контрактами.
 */
class RabbitMqConnection
{
    private ?AMQPStreamConnection $connection = null;

    /**
     * @param  array<string, mixed>  $config  config('messaging.rabbitmq')
     */
    public function __construct(private readonly array $config) {}

    public function channel(): AMQPChannel
    {
        if ($this->connection === null || ! $this->connection->isConnected()) {
            $this->connection = new AMQPStreamConnection(
                (string) $this->config['host'],
                (int) $this->config['port'],
                (string) $this->config['user'],
                (string) $this->config['password'],
                (string) $this->config['vhost'],
                connection_timeout: (float) $this->config['connection_timeout'],
                read_write_timeout: (float) $this->config['read_write_timeout'],
                heartbeat: (int) $this->config['heartbeat'],
            );
        }

        return $this->connection->channel();
    }

    public function close(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }
}

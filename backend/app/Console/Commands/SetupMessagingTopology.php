<?php

namespace App\Console\Commands;

use App\Infrastructure\Messaging\RabbitMqConnection;
use Illuminate\Console\Command;
use PhpAmqpLib\Wire\AMQPTable;

class SetupMessagingTopology extends Command
{
    protected $signature = 'messaging:setup';

    protected $description = 'Идемпотентно объявляет exchange, DLX/DLQ и очереди в RabbitMQ.';

    public function handle(RabbitMqConnection $connection): int
    {
        $exchange = (string) config('messaging.rabbitmq.exchange');
        $dlx = (string) config('messaging.topology.dead_letter_exchange');
        $dlq = (string) config('messaging.topology.dead_letter_queue');
        /** @var array<string, string> $queues */
        $queues = config('messaging.topology.queues');

        $channel = $connection->channel();

        // Основной topic-exchange бизнес-событий и fanout dead-letter exchange.
        $channel->exchange_declare($exchange, 'topic', false, true, false);
        $channel->exchange_declare($dlx, 'fanout', false, true, false);

        // DLQ — сюда падают сообщения, которые consumer отверг (nack); разбираем вручную.
        $channel->queue_declare($dlq, false, true, false, false);
        $channel->queue_bind($dlq, $dlx);

        // Рабочие очереди с dead-letter на DLX (consumers — Этап 5c).
        foreach ($queues as $queue => $binding) {
            $channel->queue_declare($queue, false, true, false, false, false, new AMQPTable([
                'x-dead-letter-exchange' => $dlx,
            ]));
            $channel->queue_bind($queue, $exchange, $binding);
            $this->line("queue {$queue} <- {$binding}");
        }

        $connection->close();
        $this->info('Messaging topology is ready.');

        return self::SUCCESS;
    }
}

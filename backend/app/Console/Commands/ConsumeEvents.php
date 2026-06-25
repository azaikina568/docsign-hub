<?php

namespace App\Console\Commands;

use App\Domain\Messaging\Contracts\EventConsumer;
use App\Domain\Messaging\Data\InboundEvent;
use App\Domain\Messaging\Services\InboxGuard;
use App\Infrastructure\Messaging\RabbitMqConsumer;
use Illuminate\Console\Command;
use Throwable;

class ConsumeEvents extends Command
{
    protected $signature = 'messaging:consume {consumer : Имя consumer\'а (notifications|audit)}';

    protected $description = 'Читает доменные события из очереди и передаёт их consumer\'у (manual ack, inbox-дедуп).';

    public function handle(RabbitMqConsumer $rabbit, InboxGuard $inbox): int
    {
        $name = (string) $this->argument('consumer');

        /** @var array<string, class-string<EventConsumer>> $registry */
        $registry = config('messaging.consumers');

        if (! isset($registry[$name])) {
            $this->error("Unknown consumer: {$name}. Available: ".implode(', ', array_keys($registry)));

            return self::INVALID;
        }

        $consumer = app($registry[$name]);

        $this->info("Consuming queue {$consumer->queue()} as '{$consumer->name()}'...");

        $rabbit->consume($consumer->queue(), function (InboundEvent $event) use ($consumer, $inbox): void {
            // Дедуп: повторную доставку уже обработанного события просто подтверждаем.
            if (! $inbox->claim($consumer->name(), $event->eventId)) {
                return;
            }

            try {
                $consumer->handle($event);
            } catch (Throwable $e) {
                // Снимаем заявку, чтобы ручной реплей из DLQ смог переобработать событие.
                $inbox->release($consumer->name(), $event->eventId);

                throw $e;
            }
        });

        return self::SUCCESS;
    }
}

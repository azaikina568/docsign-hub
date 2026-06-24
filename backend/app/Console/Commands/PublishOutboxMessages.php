<?php

namespace App\Console\Commands;

use App\Domain\Messaging\Actions\PublishPendingOutboxMessages;
use Illuminate\Console\Command;
use Throwable;

class PublishOutboxMessages extends Command
{
    protected $signature = 'outbox:publish {--daemon : Бесконечный цикл воркера (для контейнера publisher)}';

    protected $description = 'Публикует готовые события из outbox в RabbitMQ.';

    public function handle(PublishPendingOutboxMessages $action): int
    {
        $batch = (int) config('messaging.publisher.batch');
        $sleep = max(1, (int) config('messaging.publisher.idle_sleep_seconds'));
        $daemon = (bool) $this->option('daemon');

        do {
            try {
                $count = $action->execute($batch);

                if ($count > 0) {
                    $this->info("Published batch: {$count} message(s).");
                }
            } catch (Throwable $e) {
                // Брокер/БД недоступны — не валим воркер, ждём и пробуем снова.
                $this->error('Outbox publish error: '.$e->getMessage());
                report($e);
                $count = 0;
            }

            if ($daemon && $count === 0) {
                sleep($sleep);
            }
            // Не-демон: вычёрпываем всё готовое и выходим. Демон: крутимся бесконечно.
        } while ($daemon || $count > 0);

        return self::SUCCESS;
    }
}

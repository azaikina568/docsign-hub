<?php

namespace App\Console\Commands;

use App\Domain\Messaging\Enums\OutboxStatus;
use App\Domain\Messaging\Models\InboxMessage;
use App\Domain\Messaging\Models\OutboxMessage;
use Illuminate\Console\Command;

class PruneMessaging extends Command
{
    protected $signature = 'messaging:prune';

    protected $description = 'Чистит доставленные outbox- и обработанные inbox-строки старше окна хранения.';

    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) config('messaging.retention_days')));

        // Опубликованные outbox-строки своё отслужили; failed оставляем для ручного разбора.
        $outbox = OutboxMessage::query()
            ->where('status', OutboxStatus::Published)
            ->where('published_at', '<', $cutoff)
            ->delete();

        // Обработанные inbox-строки старше окна брокер уже не переотправит — дедуп больше не нужен.
        $inbox = InboxMessage::query()
            ->where('processed_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned outbox: {$outbox}, inbox: {$inbox} (older than {$cutoff->toDateString()}).");

        return self::SUCCESS;
    }
}

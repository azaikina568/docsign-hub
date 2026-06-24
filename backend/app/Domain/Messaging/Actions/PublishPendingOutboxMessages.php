<?php

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Contracts\EventPublisher;
use App\Domain\Messaging\Enums\OutboxStatus;
use App\Domain\Messaging\Models\OutboxMessage;
use Illuminate\Support\Str;
use Throwable;

class PublishPendingOutboxMessages
{
    public function __construct(private readonly EventPublisher $publisher) {}

    /**
     * Публикует готовые (`pending`, `available_at <= now`) события из outbox, до $limit за вызов.
     * Возвращает число обработанных строк (опубликованных + переотложенных).
     *
     * Модель — один воркер-публишер. At-least-once: при падении после publish, но до отметки
     * `published`, строка опубликуется повторно; дубли гасит consumer по `event_id` (Этап 5c).
     * Горизонтальное масштабирование (несколько воркеров) — через `FOR UPDATE SKIP LOCKED`,
     * см. SCALING_PERFORMANCE; сейчас намеренно проще.
     */
    public function execute(int $limit): int
    {
        $messages = OutboxMessage::query()
            ->where('status', OutboxStatus::Pending)
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $maxAttempts = (int) config('messaging.publisher.max_attempts');

        foreach ($messages as $message) {
            try {
                $this->publisher->publish($message->routing_key, $message->payload, $message->event_id);

                $message->forceFill([
                    'status' => OutboxStatus::Published,
                    'published_at' => now(),
                    'error' => null,
                ])->save();
            } catch (Throwable $e) {
                // Сбой обычно означает недоступность брокера, а не одно «битое» сообщение:
                // откладываем текущее и прерываем батч, чтобы не молотить мёртвый брокер по таймауту N раз.
                $this->reschedule($message, $maxAttempts, $e);

                throw $e;
            }
        }

        return $messages->count();
    }

    private function reschedule(OutboxMessage $message, int $maxAttempts, Throwable $e): void
    {
        $attempts = $message->attempts + 1;
        $base = (int) config('messaging.publisher.backoff_base_seconds');
        $max = (int) config('messaging.publisher.backoff_max_seconds');
        $delay = min($max, $base * (2 ** ($attempts - 1)));

        $message->forceFill([
            'attempts' => $attempts,
            'error' => Str::limit($e->getMessage(), 1000),
            // Исчерпали попытки — уводим в failed (dead-letter продюсера, разбираем вручную).
            'status' => $attempts >= $maxAttempts ? OutboxStatus::Failed : OutboxStatus::Pending,
            'available_at' => now()->addSeconds($delay),
        ])->save();
    }
}

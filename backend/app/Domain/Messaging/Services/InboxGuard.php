<?php

namespace App\Domain\Messaging\Services;

use App\Domain\Messaging\Models\InboxMessage;
use Illuminate\Database\QueryException;

/**
 * Идемпотентность consumer'ов: «застолбить» событие до обработки, чтобы повторная доставка
 * (at-least-once) не повторяла эффект. Заявка снимается, если обработчик упал — тогда ручной
 * реплей из DLQ переобработает событие.
 */
class InboxGuard
{
    /**
     * Пытается застолбить событие за consumer'ом. true — первый раз (обрабатываем),
     * false — уже обрабатывалось (дубль, просто подтверждаем доставку).
     */
    public function claim(string $consumer, string $eventId): bool
    {
        try {
            InboxMessage::create([
                'consumer' => $consumer,
                'event_id' => $eventId,
                'processed_at' => now(),
            ]);

            return true;
        } catch (QueryException $e) {
            // Нарушение unique(consumer, event_id) = событие уже обработано этим consumer'ом.
            if ($this->isUniqueViolation($e)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Снимает заявку — вызывать при падении обработчика, чтобы событие можно было переобработать.
     */
    public function release(string $consumer, string $eventId): void
    {
        InboxMessage::query()
            ->where('consumer', $consumer)
            ->where('event_id', $eventId)
            ->delete();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // 23505 (Postgres) / 23000 (SQLite/MySQL) — нарушение уникального ограничения.
        return in_array($e->getCode(), ['23505', '23000'], true);
    }
}

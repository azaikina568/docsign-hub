<?php

namespace App\Domain\Messaging\Models;

use App\Domain\Messaging\Enums\OutboxStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_id
 * @property string $event_type
 * @property string $routing_key
 * @property string|null $aggregate_type
 * @property string|null $aggregate_id
 * @property array<string, mixed> $payload
 * @property OutboxStatus $status
 * @property int $attempts
 * @property Carbon $available_at
 * @property Carbon|null $published_at
 * @property string|null $error
 */
class OutboxMessage extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'routing_key',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'attempts',
        'available_at',
        'published_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => OutboxStatus::class,
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}

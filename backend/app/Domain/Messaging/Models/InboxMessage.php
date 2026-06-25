<?php

namespace App\Domain\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $consumer
 * @property string $event_id
 * @property Carbon $processed_at
 */
class InboxMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'consumer',
        'event_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}

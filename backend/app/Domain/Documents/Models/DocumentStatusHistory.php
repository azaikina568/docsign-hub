<?php

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_id
 * @property DocumentStatus|null $from_status
 * @property DocumentStatus $to_status
 * @property string|null $reason
 * @property int|null $changed_by_user_id
 * @property Carbon|null $created_at
 */
class DocumentStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'document_status_history';

    protected $fillable = [
        'document_id',
        'from_status',
        'to_status',
        'reason',
        'changed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => DocumentStatus::class,
            'to_status' => DocumentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}

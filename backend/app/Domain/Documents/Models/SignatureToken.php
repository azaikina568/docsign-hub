<?php

namespace App\Domain\Documents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_party_id
 * @property string $token_hash
 * @property Carbon|null $expires_at
 * @property Carbon|null $used_at
 */
class SignatureToken extends Model
{
    protected $fillable = [
        'document_party_id',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DocumentParty, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(DocumentParty::class, 'document_party_id');
    }
}

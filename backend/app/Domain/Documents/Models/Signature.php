<?php

namespace App\Domain\Documents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_id
 * @property int $document_party_id
 * @property string $signature_hash
 * @property array<string, mixed>|null $signed_payload
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $signed_at
 */
class Signature extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'document_party_id',
        'signature_hash',
        'signed_payload',
        'ip_address',
        'user_agent',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'signed_payload' => 'array',
            'signed_at' => 'datetime',
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
     * @return BelongsTo<DocumentParty, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(DocumentParty::class, 'document_party_id');
    }
}

<?php

namespace App\Domain\Documents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

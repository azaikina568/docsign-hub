<?php

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\PartyRole;
use App\Domain\Documents\Enums\PartyStatus;
use App\Models\User;
use Database\Factories\DocumentPartyFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property PartyRole $role
 * @property int|null $signing_order
 * @property PartyStatus $status
 * @property Carbon|null $signed_at
 * @property Carbon|null $invited_at
 */
class DocumentParty extends Model
{
    /** @use HasFactory<DocumentPartyFactory> */
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'name',
        'email',
        'role',
        'signing_order',
        'status',
        'signed_at',
        'invited_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => PartyRole::class,
            'status' => PartyStatus::class,
            'signing_order' => 'integer',
            'signed_at' => 'datetime',
            'invited_at' => 'datetime',
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
     * Зарегистрированный пользователь, если участник совпадает с аккаунтом по email.
     * Может быть null — подписант не обязан иметь аккаунт в системе.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<SignatureToken, $this>
     */
    public function signatureToken(): HasOne
    {
        return $this->hasOne(SignatureToken::class);
    }

    /**
     * @return HasOne<Signature, $this>
     */
    public function signature(): HasOne
    {
        return $this->hasOne(Signature::class);
    }

    protected static function newFactory(): Factory
    {
        return DocumentPartyFactory::new();
    }
}

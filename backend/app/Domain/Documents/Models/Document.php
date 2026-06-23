<?php

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\User;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $owner_id
 * @property string $title
 * @property DocumentStatus $status
 * @property string|null $content_hash
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'title',
        'status',
        'content_hash',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Публичный идентификатор документа — ULID; int PK остаётся для FK.
        static::creating(function (Document $document): void {
            $document->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<DocumentParty, $this>
     */
    public function parties(): HasMany
    {
        return $this->hasMany(DocumentParty::class)->orderBy('signing_order');
    }

    /**
     * @return HasMany<DocumentStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(DocumentStatusHistory::class);
    }

    /**
     * @return HasMany<Signature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    protected static function newFactory(): Factory
    {
        return DocumentFactory::new();
    }
}

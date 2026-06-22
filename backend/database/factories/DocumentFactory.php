<?php

namespace Database\Factories;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'title' => fake()->sentence(3),
            'status' => DocumentStatus::Draft,
            'content_hash' => null,
            'expires_at' => null,
            'completed_at' => null,
        ];
    }

    public function status(DocumentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}

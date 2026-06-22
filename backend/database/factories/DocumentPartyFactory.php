<?php

namespace Database\Factories;

use App\Domain\Documents\Enums\PartyRole;
use App\Domain\Documents\Enums\PartyStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentParty>
 */
class DocumentPartyFactory extends Factory
{
    protected $model = DocumentParty::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => PartyRole::Signer,
            'signing_order' => 1,
            'status' => PartyStatus::Pending,
            'signed_at' => null,
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Domain\Documents\Actions\AddDocumentPartyAction;
use App\Domain\Documents\Actions\CreateDocumentAction;
use App\Domain\Documents\Actions\SendDocumentAction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Демо-владелец для локального прогона и записи демо: owner@docsign.test / password.
     */
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Demo Owner',
            'email' => 'owner@docsign.test',
        ]);

        $create = app(CreateDocumentAction::class);
        $addParty = app(AddDocumentPartyAction::class);
        $send = app(SendDocumentAction::class);

        $draft = $create->execute($owner, ['title' => 'Consulting agreement (draft)']);
        $addParty->execute($draft, ['name' => 'Alice Carter', 'email' => 'alice@example.com', 'signing_order' => 1]);
        $addParty->execute($draft, ['name' => 'Bob Stone', 'email' => 'bob@example.com', 'signing_order' => 2]);

        $pending = $create->execute($owner, ['title' => 'Mutual NDA (sent)']);
        $addParty->execute($pending, ['name' => 'Carol Diaz', 'email' => 'carol@example.com']);
        $send->execute($pending, $owner);
    }
}

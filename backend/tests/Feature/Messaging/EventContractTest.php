<?php

namespace Tests\Feature\Messaging;

use App\Domain\Documents\Actions\AddDocumentPartyAction;
use App\Domain\Documents\Actions\CancelDocumentAction;
use App\Domain\Documents\Actions\CreateDocumentAction;
use App\Domain\Documents\Actions\ExpireDocumentsAction;
use App\Domain\Documents\Actions\SendDocumentAction;
use App\Domain\Documents\Actions\SignDocumentAction;
use App\Domain\Documents\Models\Document;
use App\Domain\Messaging\Enums\DomainEventType;
use App\Domain\Messaging\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonSchema\Validator;
use Tests\TestCase;

class EventContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Гоняем весь жизненный цикл и проверяем, что КАЖДОЕ реально записанное событие
     * соответствует своей JSON Schema из contracts/events/v1 — это «честный» контракт, не пример.
     */
    public function test_emitted_events_conform_to_their_json_schema(): void
    {
        $owner = User::factory()->create();

        // created + sent + signed + completed (одиночный подписант завершает документ).
        $document = app(CreateDocumentAction::class)->execute($owner, ['title' => 'NDA']);
        app(AddDocumentPartyAction::class)->execute($document, ['name' => 'A', 'email' => 'a@example.com', 'signing_order' => 1]);
        app(SendDocumentAction::class)->execute($document->load('parties'), $owner);
        $party = $document->parties()->firstOrFail();
        app(SignDocumentAction::class)->execute($party, null, '127.0.0.1', 'phpunit', $party->signatureToken);

        // cancelled.
        $cancelled = app(CreateDocumentAction::class)->execute($owner, ['title' => 'Cancelled']);
        app(CancelDocumentAction::class)->execute($cancelled, $owner, 'Client postponed');

        // expired.
        $expiring = app(CreateDocumentAction::class)->execute($owner, ['title' => 'Expiring']);
        app(AddDocumentPartyAction::class)->execute($expiring, ['name' => 'B', 'email' => 'b@example.com', 'signing_order' => 1]);
        app(SendDocumentAction::class)->execute($expiring->load('parties'), $owner);
        Document::query()->whereKey($expiring->id)->update(['expires_at' => now()->subDay()]);
        app(ExpireDocumentsAction::class)->execute();

        $seen = [];

        $contractsPath = (string) config('messaging.contracts_path');

        foreach (OutboxMessage::all() as $message) {
            $schemaPath = "{$contractsPath}/{$message->event_type}.schema.json";
            $this->assertFileExists($schemaPath, "no schema for {$message->event_type}");

            $schema = json_decode((string) file_get_contents($schemaPath));
            $payload = json_decode((string) json_encode($message->payload));

            $validator = new Validator;
            $validator->validate($payload, $schema);

            $this->assertTrue(
                $validator->isValid(),
                "{$message->event_type} payload violates its schema: ".json_encode($validator->getErrors()),
            );

            $seen[$message->event_type] = true;
        }

        // Контракт покрывает весь реестр событий.
        foreach (DomainEventType::cases() as $type) {
            $this->assertArrayHasKey($type->value, $seen, "no event emitted for {$type->value}");
        }
    }
}

<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Enums\DomainEventType;
use Tests\TestCase;

class AsyncApiCatalogTest extends TestCase
{
    /**
     * AsyncAPI-каталог обязан покрывать ровно реестр DomainEventType: каждый тип — отдельным каналом
     * с правильным routing key и сообщением, ссылающимся на существующую JSON Schema. Так каталог не
     * разъезжается с кодом — новый тип события «потребует» строки здесь (как EventContractTest для схем).
     */
    public function test_catalog_covers_every_domain_event_type(): void
    {
        $contractsPath = (string) config('messaging.contracts_path');
        $catalogPath = dirname($contractsPath, 2).'/asyncapi.json';

        $this->assertFileExists($catalogPath, 'asyncapi.json not found next to contracts/');

        $catalog = json_decode((string) file_get_contents($catalogPath), true);
        $this->assertIsArray($catalog, 'asyncapi.json is not valid JSON');
        $this->assertSame('3.0.0', $catalog['asyncapi'] ?? null);

        $channels = $catalog['channels'] ?? [];
        $messages = $catalog['components']['messages'] ?? [];

        // Адреса каналов = ровно множество routing key'ев реестра (без пропусков и лишних).
        $expectedRoutingKeys = array_map(fn (DomainEventType $t) => $t->routingKey(), DomainEventType::cases());
        $actualRoutingKeys = array_values(array_map(fn (array $c) => $c['address'] ?? null, $channels));
        sort($expectedRoutingKeys);
        sort($actualRoutingKeys);
        $this->assertSame($expectedRoutingKeys, $actualRoutingKeys, 'channels must mirror DomainEventType routing keys');

        foreach (DomainEventType::cases() as $type) {
            $channel = $this->channelByAddress($channels, $type->routingKey());
            $this->assertNotNull($channel, "no channel for {$type->routingKey()}");

            // Канал ссылается на сообщение, сообщение — на JSON Schema этого же типа, и файл реально есть.
            $messageRef = array_values($channel['messages'])[0]['$ref'] ?? '';
            $messageKey = basename((string) $messageRef);
            $this->assertArrayHasKey($messageKey, $messages, "message {$messageKey} missing in components");

            $schemaRef = $messages[$messageKey]['payload']['schema']['$ref'] ?? '';
            $this->assertSame("./events/v1/{$type->value}.schema.json", $schemaRef, "wrong schema ref for {$type->value}");
            $this->assertFileExists("{$contractsPath}/{$type->value}.schema.json");
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $channels
     * @return array<string, mixed>|null
     */
    private function channelByAddress(array $channels, string $address): ?array
    {
        foreach ($channels as $channel) {
            if (($channel['address'] ?? null) === $address) {
                return $channel;
            }
        }

        return null;
    }
}

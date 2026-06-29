<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Enums\DomainEventType;
use Tests\TestCase;

class AsyncApiCatalogTest extends TestCase
{
    /**
     * AsyncAPI-каталог не должен разъезжаться с кодом. Каналы помечены `x-producer`:
     * - **backend** (эмитит ядро через outbox) обязаны зеркалить ровно реестр DomainEventType;
     * - **platform** (производят полиглот-сервисы, напр. Go signing-worker для signature.verified)
     *   проверяем мягче — их нет в DomainEventType, но схема обязана существовать.
     * Так новый backend-тип «потребует» строки здесь (как EventContractTest для схем), а platform-события
     * не ломают инвариант про реестр.
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

        // Backend-каналы = ровно множество routing key'ев реестра (без пропусков и лишних).
        $backendAddresses = array_values(array_map(
            fn (array $c) => $c['address'] ?? null,
            array_filter($channels, fn (array $c) => ($c['x-producer'] ?? null) === 'backend')
        ));
        $expectedRoutingKeys = array_map(fn (DomainEventType $t) => $t->routingKey(), DomainEventType::cases());
        sort($expectedRoutingKeys);
        sort($backendAddresses);
        $this->assertSame($expectedRoutingKeys, $backendAddresses, 'backend channels must mirror DomainEventType routing keys');

        foreach (DomainEventType::cases() as $type) {
            $channel = $this->channelByAddress($channels, $type->routingKey());
            $this->assertNotNull($channel, "no channel for {$type->routingKey()}");
            $this->assertSame('backend', $channel['x-producer'] ?? null, "{$type->value} must be x-producer=backend");

            // Канал ссылается на сообщение, сообщение — на JSON Schema этого же типа, и файл реально есть.
            $schemaRef = $this->schemaRefFor($channel, $messages);
            $this->assertSame("./events/v1/{$type->value}.schema.json", $schemaRef, "wrong schema ref for {$type->value}");
            $this->assertFileExists("{$contractsPath}/{$type->value}.schema.json");
        }

        // Platform-каналы (другой producer): хотя бы один, каждый ссылается на существующую схему.
        $platformChannels = array_filter($channels, fn (array $c) => ($c['x-producer'] ?? 'backend') !== 'backend');
        $this->assertNotEmpty($platformChannels, 'expected at least one platform channel (e.g. signature.verified)');

        foreach ($platformChannels as $name => $channel) {
            $schemaRef = $this->schemaRefFor($channel, $messages);
            $this->assertStringStartsWith('./events/v1/', $schemaRef, "schema ref for {$name} must point into events/v1");
            $this->assertFileExists($contractsPath.'/'.basename($schemaRef), "schema file missing for channel {$name}");
        }
    }

    /**
     * Достаёт `$ref` JSON Schema, на которую через сообщение ссылается канал.
     *
     * @param  array<string, mixed>  $channel
     * @param  array<string, array<string, mixed>>  $messages
     */
    private function schemaRefFor(array $channel, array $messages): string
    {
        $messageRef = array_values($channel['messages'])[0]['$ref'] ?? '';
        $messageKey = basename((string) $messageRef);
        $this->assertArrayHasKey($messageKey, $messages, "message {$messageKey} missing in components");

        return (string) ($messages[$messageKey]['payload']['schema']['$ref'] ?? '');
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

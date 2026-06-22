<?php

namespace Tests\Feature;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_openapi_document_describes_auth_endpoints_and_security(): void
    {
        $generator = app(Generator::class);
        $document = $generator(Scramble::getGeneratorConfig('default'));

        $this->assertStringContainsString('DocSign Hub', $document['info']['title']);

        foreach (['/v1/health', '/v1/auth/register', '/v1/auth/login', '/v1/auth/logout', '/v1/me'] as $path) {
            $this->assertArrayHasKey($path, $document['paths']);
        }

        // Bearer-схема объявлена и применяется глобально.
        $this->assertSame('bearer', $document['components']['securitySchemes']['http']['scheme']);
        $this->assertSame([['http' => []]], $document['security']);

        // Публичные endpoint'ы переопределяют глобальную security как открытые.
        $this->assertSame([], $document['paths']['/v1/auth/login']['post']['security']);

        // Защищённые endpoint'ы наследуют глобальный bearer (нет собственного override).
        $this->assertArrayNotHasKey('security', $document['paths']['/v1/me']['get']);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{access: string, refresh: string}
     */
    private function login(): array
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $tokens = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ])->json('tokens');

        return ['access' => $tokens['access_token'], 'refresh' => $tokens['refresh_token']];
    }

    public function test_refresh_issues_new_pair_and_rotates_old_refresh(): void
    {
        ['refresh' => $refresh] = $this->login();

        $new = $this->withToken($refresh)->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonStructure(['tokens' => ['access_token', 'refresh_token']])
            ->json('tokens');

        $this->assertNotSame($refresh, $new['refresh_token']);

        // Старый refresh после ротации сразу предъявлять нельзя — это переиспользование (см. отдельный тест).
        $this->app['auth']->forgetGuards();
        $this->withToken($new['access_token'])->getJson('/api/v1/me')->assertOk();
    }

    public function test_expired_access_is_rejected_but_refresh_still_works(): void
    {
        ['access' => $access, 'refresh' => $refresh] = $this->login();

        $this->travelTo(now()->addMinutes((int) config('sanctum.access_token_expiration') + 1));

        $this->withToken($access)->getJson('/api/v1/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $fresh = $this->withToken($refresh)->postJson('/api/v1/auth/refresh')->assertOk()->json('tokens.access_token');

        $this->app['auth']->forgetGuards();
        $this->withToken($fresh)->getJson('/api/v1/me')->assertOk();

        $this->travelBack();
    }

    public function test_reusing_rotated_refresh_revokes_the_whole_family(): void
    {
        ['refresh' => $refresh] = $this->login();

        // Первая ротация — легальная.
        $rotated = $this->withToken($refresh)->postJson('/api/v1/auth/refresh')->assertOk()->json('tokens.refresh_token');

        // Повторное использование уже ротированного refresh → отказ и отзыв всей семьи.
        $this->app['auth']->forgetGuards();
        $this->withToken($refresh)->postJson('/api/v1/auth/refresh')->assertUnauthorized();

        // Выданный в первой ротации refresh тоже мёртв — семья погашена.
        $this->app['auth']->forgetGuards();
        $this->withToken($rotated)->postJson('/api/v1/auth/refresh')->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_access_token_cannot_be_used_to_refresh(): void
    {
        ['access' => $access] = $this->login();

        $this->withToken($access)->postJson('/api/v1/auth/refresh')->assertForbidden();
    }

    public function test_refresh_token_cannot_access_the_api(): void
    {
        ['refresh' => $refresh] = $this->login();

        $this->withToken($refresh)->getJson('/api/v1/me')->assertForbidden();
    }
}

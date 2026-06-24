<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Подписанная ссылка из письма верификации — как её строит фреймворк.
     */
    private function verificationUrl(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => $hash ?? sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_registration_sends_verification_link(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonPath('user.email_verified_at', null);

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_email_is_verified_via_signed_link(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson($this->verificationUrl($user))
            ->assertOk()
            ->assertJsonPath('message', 'Email verified.');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_verification_link_with_wrong_hash_is_forbidden(): void
    {
        $user = User::factory()->unverified()->create();

        // Подпись валидна, но hash не соответствует email пользователя.
        $this->getJson($this->verificationUrl($user, sha1('someone-else@example.com')))
            ->assertForbidden();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_tampered_signature_is_forbidden(): void
    {
        $user = User::factory()->unverified()->create();

        // Меняем подписанный URL — подпись становится недействительной (middleware signed → 403).
        $this->getJson($this->verificationUrl($user).'tampered')->assertForbidden();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_verifying_already_verified_is_idempotent(): void
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::parse('2026-01-01')]);

        $this->getJson($this->verificationUrl($user))
            ->assertOk()
            ->assertJsonPath('message', 'Email already verified.');
    }

    public function test_resend_sends_link_to_unverified_user(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user, ['access-api']);

        $this->postJson('/api/v1/auth/verification/resend')
            ->assertOk()
            ->assertJsonPath('message', 'Verification link sent.');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_is_noop_for_verified_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access-api']);

        $this->postJson('/api/v1/auth/verification/resend')
            ->assertOk()
            ->assertJsonPath('message', 'Email already verified.');

        Notification::assertNothingSent();
    }

    public function test_resend_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/verification/resend')->assertUnauthorized();
    }

    public function test_resend_is_rate_limited(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->unverified()->create(), ['access-api']);

        // Лимит verification — 6/мин: шесть проходят, седьмой упирается в 429.
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/verification/resend')->assertOk();
        }

        $this->postJson('/api/v1/auth/verification/resend')->assertStatus(429);
    }

    public function test_unverified_user_cannot_create_document(): void
    {
        Sanctum::actingAs(User::factory()->unverified()->create(), ['access-api']);

        $this->postJson('/api/v1/documents', ['title' => 'Blocked draft'])
            ->assertForbidden();
    }

    public function test_verified_user_can_create_document(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access-api']);

        $this->postJson('/api/v1/documents', ['title' => 'Allowed draft'])
            ->assertCreated();
    }
}

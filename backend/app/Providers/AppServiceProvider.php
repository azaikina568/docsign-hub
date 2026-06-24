<?php

namespace App\Providers;

use App\Domain\Documents\Contracts\SigningInvitationNotifier;
use App\Domain\Documents\Events\DocumentSent;
use App\Domain\Documents\Listeners\SendSigningInvitations;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Policies\DocumentPolicy;
use App\Infrastructure\Notifications\MailSigningInvitationNotifier;
use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SigningInvitationNotifier::class, MailSigningInvitationNotifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Лимит по аутентифицированному пользователю, для гостей — по IP.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Подтверждение email: тугой лимит против перебора ссылок и спама resend (по пользователю/IP).
        RateLimiter::for('verification', function (Request $request) {
            return Limit::perMinute(6)->by($request->user()?->id ?: $request->ip());
        });

        // Публичные signing-роуты: защита от перебора токенов — лимит и по IP, и по самому токену.
        RateLimiter::for('signing', function (Request $request) {
            $token = (string) $request->route('token');

            return [
                Limit::perMinute(20)->by('signing-ip:'.$request->ip()),
                Limit::perMinute(10)->by('signing-token:'.sha1($token)),
            ];
        });

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Gate::policy(Document::class, DocumentPolicy::class);

        Event::listen(DocumentSent::class, SendSigningInvitations::class);
    }
}

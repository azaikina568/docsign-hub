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

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Gate::policy(Document::class, DocumentPolicy::class);

        Event::listen(DocumentSent::class, SendSigningInvitations::class);
    }
}

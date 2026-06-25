<?php

namespace App\Providers;

use App\Domain\Documents\Contracts\SigningInvitationNotifier;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Policies\DocumentPolicy;
use App\Domain\Messaging\Contracts\AuditStore;
use App\Domain\Messaging\Contracts\EventPublisher;
use App\Infrastructure\Messaging\MongoAuditStore;
use App\Infrastructure\Messaging\RabbitMqConnection;
use App\Infrastructure\Messaging\RabbitMqEventPublisher;
use App\Infrastructure\Notifications\MailSigningInvitationNotifier;
use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SigningInvitationNotifier::class, MailSigningInvitationNotifier::class);

        // Одно соединение с брокером на процесс; издатель за доменным контрактом (в тестах — фейк).
        $this->app->singleton(RabbitMqConnection::class, fn () => new RabbitMqConnection(config('messaging.rabbitmq')));
        $this->app->bind(EventPublisher::class, fn ($app) => new RabbitMqEventPublisher(
            $app->make(RabbitMqConnection::class),
            (string) config('messaging.rabbitmq.exchange'),
        ));

        // Audit-журнал в MongoDB (append-only); в тестах подменяется фейком.
        $this->app->singleton(AuditStore::class, fn () => new MongoAuditStore(config('messaging.audit')));
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

        // Требования к паролю: минимум буквы и цифры; в проде дополнительно регистр, символ и
        // проверка по утечкам (HaveIBeenPwned). Полный спек политики — plans/SECURITY.md.
        Password::defaults(function () {
            $rule = Password::min(8)->letters()->numbers();

            return $this->app->isProduction() ? $rule->mixedCase()->symbols()->uncompromised() : $rule;
        });

        Gate::policy(Document::class, DocumentPolicy::class);
    }
}

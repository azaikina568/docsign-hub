<?php

namespace App\Domain\Users\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Письмо подтверждения email — в очередь, а не синхронно в HTTP-запросе регистрации. Иначе медленная
 * отправка по SMTP держит запрос открытым (наблюдали ~5с) и при таймауте клиента регистрация выглядит
 * упавшей, хотя пользователь уже создан.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Ссылку ведём на фронт-страницу, а не на API: подпись (signature/expires) генерируется родителем для
     * API-роута и переносится в query как есть — SPA переотправит её в API, бэкенд провалидирует тот же URL.
     *
     * @param  User  $notifiable
     */
    protected function verificationUrl($notifiable): string
    {
        $signedQuery = parse_url(parent::verificationUrl($notifiable), PHP_URL_QUERY) ?: '';

        return sprintf(
            '%s/verify-email/%s/%s?%s',
            rtrim((string) config('app.frontend_url'), '/'),
            $notifiable->getKey(),
            sha1($notifiable->getEmailForVerification()),
            $signedQuery,
        );
    }
}

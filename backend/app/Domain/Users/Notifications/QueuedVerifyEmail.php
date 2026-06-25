<?php

namespace App\Domain\Users\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Письмо подтверждения email — в очередь, а не синхронно в HTTP-запросе регистрации. Иначе медленная
 * отправка по SMTP держит запрос открытым (наблюдали ~5с) и при таймауте клиента регистрация выглядит
 * упавшей, хотя пользователь уже создан. Ссылку/срок генерирует родительский VerifyEmail.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}

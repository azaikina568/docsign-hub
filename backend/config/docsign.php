<?php

return [
    /*
     * Срок жизни signing-токена (в днях). Применяется, если у документа не задан
     * собственный expires_at — чтобы токен никогда не был бессрочным.
     */
    'signing_token_ttl_days' => (int) env('DOCSIGN_SIGNING_TOKEN_TTL_DAYS', 14),

    /*
     * База ссылки для подписания, которая уходит участнику в письме.
     * Реальную страницу подписания отдаёт frontend; сюда подставляется plain-токен.
     */
    'signing_url_base' => env('DOCSIGN_SIGNING_URL_BASE', env('APP_URL', 'http://localhost:8080').'/sign'),
];

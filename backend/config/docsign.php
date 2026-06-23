<?php

return [
    /*
     * Дефолтный срок (в днях) для дедлайна документа при отправке, если владелец не задал
     * expires_at. На этот же момент выставляется срок signing-токенов — токен не бывает бессрочным.
     */
    'signing_token_ttl_days' => (int) env('DOCSIGN_SIGNING_TOKEN_TTL_DAYS', 14),

    /*
     * База ссылки для подписания, которая уходит участнику в письме.
     * Реальную страницу подписания отдаёт frontend; сюда подставляется plain-токен.
     */
    'signing_url_base' => env('DOCSIGN_SIGNING_URL_BASE', env('APP_URL', 'http://localhost:8080').'/sign'),
];

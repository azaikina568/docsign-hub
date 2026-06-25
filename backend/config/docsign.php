<?php

return [
    /*
     * Дефолтный срок (в днях) для дедлайна документа при отправке, если владелец не задал
     * expires_at. На этот же момент выставляется срок signing-токенов — токен не бывает бессрочным.
     */
    'signing_token_ttl_days' => (int) env('DOCSIGN_SIGNING_TOKEN_TTL_DAYS', 14),

    /*
     * База ссылки для подписания, которая уходит участнику в письме: страницу подписания отдаёт
     * frontend (SPA), поэтому ссылка ведёт на FRONTEND_URL, а не на API. Сюда подставляется plain-токен.
     */
    'signing_url_base' => env('DOCSIGN_SIGNING_URL_BASE', env('FRONTEND_URL', 'http://localhost:3000').'/signing'),

    /*
     * Путь к Mermaid-диаграммам, которые отдаёт страница /docs/diagrams.
     * В Docker файл проброшен из корневого docs/ (см. docker-compose), локально берём его же по дефолту.
     */
    'diagrams_path' => env('DOCSIGN_DIAGRAMS_PATH', base_path('../docs/diagrams.md')),
];

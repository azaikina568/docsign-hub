<?php

namespace App\Domain\Documents\Exceptions;

use Symfony\Component\HttpKernel\Exception\GoneHttpException;

/**
 * Окно подписания закрыто: дедлайн документа уже прошёл. Отдаётся как 410.
 */
class SigningWindowClosedException extends GoneHttpException {}

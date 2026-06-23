<?php

namespace App\Domain\Documents\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Подпись запрещена для данного актёра: не та identity, нет валидного токена
 * или участник — наблюдатель. Отдаётся как 403.
 */
class SigningForbiddenException extends AccessDeniedHttpException {}

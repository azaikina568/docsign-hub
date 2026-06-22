<?php

namespace App\Domain\Documents\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Бросается, когда действие невозможно в текущем статусе документа
 * (например, изменить участников уже отправленного документа). Отдаётся как 409.
 */
class DocumentStateException extends ConflictHttpException {}

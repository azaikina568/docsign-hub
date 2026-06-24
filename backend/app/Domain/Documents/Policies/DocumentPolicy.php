<?php

namespace App\Domain\Documents\Policies;

use App\Domain\Documents\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Создавать документы может только пользователь с подтверждённым email.
     * Этого достаточно, чтобы закрыть весь владельческий write-доступ: подтверждённость
     * монотонна (обратного пути нет — смены email/разверификации в системе нет), поэтому
     * любой документ создан уже подтверждённым владельцем, и send/cancel/parties/deadline
     * над ним по определению идут от verified-пользователя.
     * Это же — точка расширения под политику доступа (invite-only / квоты / подписка — OPEN_QUESTIONS Q2).
     */
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function view(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    /**
     * Управление участниками и жизненным циклом (parties, send, cancel).
     */
    public function manage(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    private function owns(User $user, Document $document): bool
    {
        return $document->owner_id === $user->id;
    }
}

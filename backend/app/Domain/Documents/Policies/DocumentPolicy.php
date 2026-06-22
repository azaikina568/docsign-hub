<?php

namespace App\Domain\Documents\Policies;

use App\Domain\Documents\Models\Document;
use App\Models\User;

class DocumentPolicy
{
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

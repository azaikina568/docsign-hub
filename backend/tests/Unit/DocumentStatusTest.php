<?php

namespace Tests\Unit;

use App\Domain\Documents\Enums\DocumentStatus;
use PHPUnit\Framework\TestCase;

class DocumentStatusTest extends TestCase
{
    public function test_allowed_transitions(): void
    {
        $this->assertTrue(DocumentStatus::Draft->canTransitionTo(DocumentStatus::Pending));
        $this->assertTrue(DocumentStatus::Draft->canTransitionTo(DocumentStatus::Cancelled));
        $this->assertTrue(DocumentStatus::Pending->canTransitionTo(DocumentStatus::PartiallySigned));
        $this->assertTrue(DocumentStatus::Pending->canTransitionTo(DocumentStatus::Signed));
        $this->assertTrue(DocumentStatus::PartiallySigned->canTransitionTo(DocumentStatus::Signed));
        $this->assertTrue(DocumentStatus::Pending->canTransitionTo(DocumentStatus::Expired));
    }

    public function test_forbidden_transitions(): void
    {
        $this->assertFalse(DocumentStatus::Draft->canTransitionTo(DocumentStatus::Signed));
        $this->assertFalse(DocumentStatus::Draft->canTransitionTo(DocumentStatus::PartiallySigned));
        $this->assertFalse(DocumentStatus::Signed->canTransitionTo(DocumentStatus::Cancelled));
        $this->assertFalse(DocumentStatus::Cancelled->canTransitionTo(DocumentStatus::Pending));
        $this->assertFalse(DocumentStatus::Expired->canTransitionTo(DocumentStatus::Signed));
    }
}

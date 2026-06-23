<?php

namespace Tests\Feature;

use Tests\TestCase;

class DiagramsPageTest extends TestCase
{
    public function test_diagrams_page_renders_with_mermaid_source(): void
    {
        $this->get('/docs/diagrams')
            ->assertOk()
            ->assertSee('DocSign Hub')
            ->assertSee('```mermaid', false);
    }
}

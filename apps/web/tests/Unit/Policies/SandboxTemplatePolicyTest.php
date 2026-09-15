<?php

namespace Tests\Unit\Policies;

use App\Models\SandboxTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR 0096: "Sandbox Templates werden ausschliesslich von Administratoren
 * angelegt oder freigegeben" -- solange es die administrator-Rolle noch
 * nicht gibt, uebernimmt Reviewer diese Aufgabe (SandboxTemplatePolicy-
 * Klassendoc).
 */
class SandboxTemplatePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reviewer_can_manage_templates(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $template = SandboxTemplate::factory()->create();

        $this->assertTrue($reviewer->can('manage', $template));
    }

    public function test_an_author_cannot_manage_templates(): void
    {
        $author = User::factory()->author()->create();
        $template = SandboxTemplate::factory()->create();

        $this->assertFalse($author->can('manage', $template));
    }

    public function test_a_learner_cannot_view_or_manage_templates(): void
    {
        $learner = User::factory()->create();
        $template = SandboxTemplate::factory()->create();

        $this->assertFalse($learner->can('viewAny', SandboxTemplate::class));
        $this->assertFalse($learner->can('manage', $template));
    }

    public function test_an_author_can_view_templates(): void
    {
        $author = User::factory()->author()->create();

        $this->assertTrue($author->can('viewAny', SandboxTemplate::class));
    }
}

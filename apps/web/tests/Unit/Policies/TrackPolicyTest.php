<?php

namespace Tests\Unit\Policies;

use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR 0100 (CMS-4b): Track ist nicht an einzelne Autor:innen zuweisbar,
 * Verwaltung bleibt Reviewer/Administrator vorbehalten (additiv, siehe
 * UserRole-Klassendoc).
 */
class TrackPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reviewer_can_manage_tracks(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $track = Track::factory()->create();

        $this->assertTrue($reviewer->can('manage', $track));
    }

    public function test_an_administrator_can_manage_tracks(): void
    {
        $administrator = User::factory()->administrator()->create();
        $track = Track::factory()->create();

        $this->assertTrue($administrator->can('manage', $track));
    }

    public function test_an_author_can_view_but_not_manage_tracks(): void
    {
        $author = User::factory()->author()->create();
        $track = Track::factory()->create();

        $this->assertTrue($author->can('viewAny', Track::class));
        $this->assertFalse($author->can('manage', $track));
    }

    public function test_a_learner_cannot_view_or_manage_tracks(): void
    {
        $learner = User::factory()->create();
        $track = Track::factory()->create();

        $this->assertFalse($learner->can('viewAny', Track::class));
        $this->assertFalse($learner->can('manage', $track));
    }
}

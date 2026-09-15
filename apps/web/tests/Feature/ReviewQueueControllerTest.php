<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uebergreifende Review-Queue (ADR 0092): `content_versions` ist bereits
 * generisch ueber alle Aktivitaetstypen (ADR 0071, W3) -- diese Seite
 * liest das erstmals auch so aus, statt je Aktivitaet einzeln.
 */
class ReviewQueueControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_learner_cannot_open_the_queue(): void
    {
        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/de/author/review-queue')->assertForbidden();
    }

    public function test_an_author_cannot_open_the_queue(): void
    {
        $author = User::factory()->author()->create();

        $this->actingAs($author)->get('/de/author/review-queue')->assertForbidden();
    }

    public function test_a_reviewer_sees_an_empty_queue_when_nothing_is_pending(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get('/de/author/review-queue')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Author/ReviewQueue')
                ->has('items', 0)
            );
    }

    public function test_a_reviewer_sees_pending_items_across_types_with_the_right_edit_link(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $author = User::factory()->author()->create();

        $lesson = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0', 'title' => ['de' => 'Fundamente']]);
        ContentVersion::create([
            'activity_id' => $lesson->id, 'status' => 'review', 'payload' => [],
            'is_current' => false, 'created_by' => $author->id,
        ]);

        $achievement = Activity::factory()->create(['type' => 'achievement', 'key' => 'catalog']);
        ContentVersion::create([
            'activity_id' => $achievement->id, 'status' => 'review', 'payload' => ['slug' => 'new-badge'],
            'is_current' => false, 'created_by' => $author->id,
        ]);

        $this->actingAs($reviewer)
            ->get('/de/author/review-queue')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items', 2)
                ->where('items', function ($items) use ($author) {
                    $lessonItem = collect($items)->firstWhere('activity_type', 'lesson');
                    $achievementItem = collect($items)->firstWhere('activity_type', 'achievement');

                    return $lessonItem['title'] === 'Fundamente'
                        && $lessonItem['edit_url'] === '/de/author/lessons/1.0/edit'
                        && $lessonItem['author_name'] === $author->name
                        && $achievementItem['edit_url'] === '/de/author/achievements/new-badge/edit';
                })
            );
    }

    public function test_a_pending_node_draft_links_to_the_studio_node_editor(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $author = User::factory()->author()->create();
        $node = Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        ContentVersion::create([
            'activity_id' => $node->id, 'status' => 'review', 'payload' => [],
            'is_current' => false, 'created_by' => $author->id,
        ]);

        $this->actingAs($reviewer)
            ->get('/de/author/review-queue')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('items.0.edit_url', '/de/studio/nodes/test-node'));
    }

    public function test_the_queue_shows_only_the_latest_pending_version_per_activity(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $author = User::factory()->author()->create();
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);

        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => [],
            'is_current' => false, 'created_by' => $author->id, 'created_at' => now()->subHour(),
        ]);
        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => [],
            'is_current' => false, 'created_by' => $author->id, 'created_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->get('/de/author/review-queue')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items', 1));
    }

    public function test_a_draft_that_was_never_submitted_does_not_appear(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $author = User::factory()->author()->create();
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'draft', 'payload' => [],
            'is_current' => false, 'created_by' => $author->id,
        ]);

        $this->actingAs($reviewer)
            ->get('/de/author/review-queue')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items', 0));
    }
}

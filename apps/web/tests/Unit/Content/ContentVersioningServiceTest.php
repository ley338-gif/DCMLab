<?php

namespace Tests\Unit\Content;

use App\Content\ContentVersioningService;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * ADR 0071, W3-DoD: "Eine veroeffentlichte Version laesst sich
 * zuruecksetzen." Das tatsaechliche Zurueckschreiben nach content/ ist
 * bewusst nicht Teil dieses Service (siehe ADR 0075) -- hier geht es nur um
 * die Versions-Buchfuehrung.
 */
class ContentVersioningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_activity_without_any_version_history_counts_as_published(): void
    {
        $activity = Activity::factory()->create();

        $this->assertTrue((new ContentVersioningService)->isPublished($activity));
    }

    public function test_the_full_lifecycle_from_draft_to_published(): void
    {
        $service = new ContentVersioningService;
        $activity = Activity::factory()->create();
        $author = User::factory()->author()->create();
        $reviewer = User::factory()->reviewer()->create();

        $draft = $service->createDraft($activity, ['title' => 'Entwurf'], $author);
        $this->assertSame('draft', $draft->status);
        $this->assertFalse($draft->is_current);
        $this->assertFalse($service->isPublished($activity));

        $inReview = $service->submitForReview($draft);
        $this->assertSame('review', $inReview->status);

        $published = $service->publish($inReview, $reviewer);
        $this->assertSame('published', $published->status);
        $this->assertTrue($published->is_current);
        $this->assertSame($reviewer->id, $published->reviewed_by);
        $this->assertNotNull($published->published_at);
        $this->assertTrue($service->isPublished($activity));
    }

    public function test_submitting_a_non_draft_for_review_fails(): void
    {
        $service = new ContentVersioningService;
        $activity = Activity::factory()->create();
        $author = User::factory()->author()->create();
        $draft = $service->createDraft($activity, [], $author);
        $inReview = $service->submitForReview($draft);

        $this->expectException(RuntimeException::class);
        $service->submitForReview($inReview);
    }

    public function test_publishing_a_draft_that_is_not_in_review_fails(): void
    {
        $service = new ContentVersioningService;
        $activity = Activity::factory()->create();
        $author = User::factory()->author()->create();
        $reviewer = User::factory()->reviewer()->create();
        $draft = $service->createDraft($activity, [], $author);

        $this->expectException(RuntimeException::class);
        $service->publish($draft, $reviewer);
    }

    public function test_publishing_a_new_version_supersedes_the_previous_current_one(): void
    {
        $service = new ContentVersioningService;
        $activity = Activity::factory()->create();
        $author = User::factory()->author()->create();
        $reviewer = User::factory()->reviewer()->create();

        $first = $service->publish($service->submitForReview($service->createDraft($activity, ['v' => 1], $author)), $reviewer);
        $second = $service->publish($service->submitForReview($service->createDraft($activity, ['v' => 2], $author)), $reviewer);

        $this->assertFalse($first->refresh()->is_current);
        $this->assertTrue($second->refresh()->is_current);
        $this->assertSame(2, $activity->contentVersions()->where('status', 'published')->count());
    }

    /**
     * ADR 0102 (CMS-5b): ein Rollback erzeugt eine NEUE Version mit
     * demselben payload, statt eine bestehende, bereits veroeffentlichte
     * Version erneut zu mutieren -- veroeffentlichte Versionen bleiben
     * unveraendert (siehe Klassendoc).
     */
    public function test_rollback_creates_a_new_version_with_the_previous_payload(): void
    {
        $service = new ContentVersioningService;
        $activity = Activity::factory()->create();
        $author = User::factory()->author()->create();
        $reviewer = User::factory()->reviewer()->create();
        $secondReviewer = User::factory()->reviewer()->create();

        $first = $service->publish($service->submitForReview($service->createDraft($activity, ['v' => 1], $author)), $reviewer);
        $second = $service->publish($service->submitForReview($service->createDraft($activity, ['v' => 2], $author)), $reviewer);

        $restored = $service->rollback($activity, $secondReviewer);

        $this->assertNotSame($first->id, $restored->id);
        $this->assertTrue($restored->is_current);
        $this->assertSame('published', $restored->status);
        $this->assertSame(['v' => 1], $restored->payload);
        $this->assertSame($author->id, $restored->created_by, 'Urheberschaft der wiederhergestellten Fassung bleibt erhalten.');
        $this->assertSame($secondReviewer->id, $restored->reviewed_by);

        // Beide vorherigen Versionen bleiben unveraendert -- nur is_current
        // bewegt sich.
        $this->assertFalse($second->refresh()->is_current);
        $this->assertSame(['v' => 1], $first->refresh()->payload);
        $this->assertSame(['v' => 2], $second->payload);
        $this->assertSame(3, $activity->contentVersions()->where('status', 'published')->count());
    }

    public function test_rollback_without_a_previous_published_version_fails(): void
    {
        $service = new ContentVersioningService;
        $activity = Activity::factory()->create();
        $author = User::factory()->author()->create();
        $reviewer = User::factory()->reviewer()->create();

        $service->publish($service->submitForReview($service->createDraft($activity, [], $author)), $reviewer);

        $this->expectException(RuntimeException::class);
        $service->rollback($activity, $reviewer);
    }

    public function test_rollback_without_any_version_history_fails(): void
    {
        $activity = Activity::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        $this->expectException(RuntimeException::class);
        (new ContentVersioningService)->rollback($activity, $reviewer);
    }
}

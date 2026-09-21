<?php

namespace Tests\Unit\Content;

use App\Content\ContentVersioningService;
use App\Models\Activity;
use App\Models\ContentVersion;
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

    /**
     * Betreiber-Review nach #178: `storeDraft()` in jedem der fuenf Editoren
     * ruft `createDraft()` ohne jede Pruefung auf vorhandene Entwuerfe auf --
     * vor diesem Fix haetten zwei Speichervorgaenge zwei parallele
     * `draft`-Versionen derselben Aktivitaet hinterlassen.
     */
    public function test_creating_a_new_draft_supersedes_an_older_draft_of_the_same_activity(): void
    {
        $service = new ContentVersioningService;
        $activity = Activity::factory()->create();
        $author = User::factory()->author()->create();

        $older = $service->createDraft($activity, ['v' => 1], $author);
        $newer = $service->createDraft($activity, ['v' => 2], $author);

        $this->assertSame('superseded', $older->fresh()->status);
        $this->assertSame('draft', $newer->fresh()->status);
        $this->assertSame(['v' => 1], $older->fresh()->payload, 'die ueberholte Version bleibt unveraendert stehen, nur ihr Status wechselt.');
    }

    /**
     * Genau der Fall, der PR #178 (Lesson 4.1) tatsaechlich passiert ist:
     * ein Entwurf wurde bereits zur Review eingereicht (ContentVersion #22),
     * dann entstand fuer dieselbe Aktivitaet ein neuer, korrigierter Entwurf
     * (#23) -- #22 muss dabei ungueltig werden, nicht nur unauffaellig in
     * der Review-Queue verschwinden.
     */
    public function test_creating_a_new_draft_supersedes_an_older_review_version_of_the_same_activity(): void
    {
        $service = new ContentVersioningService;
        $activity = Activity::factory()->create();
        $author = User::factory()->author()->create();

        $staleReview = $service->submitForReview($service->createDraft($activity, ['v' => 'stale'], $author));
        $this->assertSame('review', $staleReview->status);

        $correctedDraft = $service->createDraft($activity, ['v' => 'corrected'], $author);

        $this->assertSame('superseded', $staleReview->fresh()->status);
        $this->assertSame('draft', $correctedDraft->fresh()->status);
    }

    public function test_creating_a_new_draft_does_not_touch_published_versions(): void
    {
        $service = new ContentVersioningService;
        $activity = Activity::factory()->create();
        $author = User::factory()->author()->create();
        $reviewer = User::factory()->reviewer()->create();

        $published = $service->publish($service->submitForReview($service->createDraft($activity, ['v' => 1], $author)), $reviewer);
        $service->createDraft($activity, ['v' => 2], $author);

        $this->assertSame('published', $published->fresh()->status);
        $this->assertTrue($published->fresh()->is_current);
    }

    public function test_creating_a_new_draft_does_not_touch_versions_of_other_activities(): void
    {
        $service = new ContentVersioningService;
        $activityA = Activity::factory()->create();
        $activityB = Activity::factory()->create();
        $author = User::factory()->author()->create();

        $unrelatedDraft = $service->createDraft($activityB, ['v' => 'other-activity'], $author);
        $service->createDraft($activityA, ['v' => 1], $author);

        $this->assertSame('draft', $unrelatedDraft->fresh()->status, 'ein Entwurf einer anderen Aktivitaet darf nie superseded werden.');
    }

    /**
     * Spiegelbildlich zu oben: Veroeffentlichen raeumt ebenfalls auf --
     * jede andere parallele draft-/review-Version derselben Aktivitaet wird
     * ungueltig, genau der Fix, der ContentVersion #22 (Lesson 4.1) bei der
     * Veroeffentlichung von #23 automatisch bereinigen soll.
     */
    public function test_publishing_a_version_supersedes_all_other_pending_versions_of_the_same_activity(): void
    {
        $service = new ContentVersioningService;
        $activity = Activity::factory()->create();
        $author = User::factory()->author()->create();
        $reviewer = User::factory()->reviewer()->create();

        // Zwei Entwuerfe koennen trotz der createDraft()-Sperre parallel
        // existieren, wenn der aeltere schon vor diesem Fix (oder per
        // direktem Model-Insert, wie ein Alt-Datensatz) angelegt wurde --
        // publish() darf sich deshalb nicht allein auf createDraft() verlassen.
        $staleReview = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => ['v' => 'stale'],
            'is_current' => false, 'created_by' => $author->id,
        ]);
        $staleDraft = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'draft', 'payload' => ['v' => 'stale-draft'],
            'is_current' => false, 'created_by' => $author->id,
        ]);
        $theOneToPublish = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => ['v' => 'corrected'],
            'is_current' => false, 'created_by' => $author->id,
        ]);

        $published = $service->publish($theOneToPublish, $reviewer);

        $this->assertSame('published', $published->status);
        $this->assertTrue($published->is_current);
        $this->assertSame('superseded', $staleReview->fresh()->status);
        $this->assertSame('superseded', $staleDraft->fresh()->status);
    }

    public function test_publishing_a_version_does_not_touch_pending_versions_of_other_activities(): void
    {
        $service = new ContentVersioningService;
        $activityA = Activity::factory()->create();
        $activityB = Activity::factory()->create();
        $author = User::factory()->author()->create();
        $reviewer = User::factory()->reviewer()->create();

        $unrelatedReview = $service->submitForReview($service->createDraft($activityB, ['v' => 'other-activity'], $author));
        $service->publish($service->submitForReview($service->createDraft($activityA, ['v' => 1], $author)), $reviewer);

        $this->assertSame('review', $unrelatedReview->fresh()->status);
    }

    /**
     * Requirement 3 (Betreiber-Vorgabe): eine `superseded`-Version darf
     * weder erneut eingereicht noch veroeffentlicht werden. Beide Pruefungen
     * existierten bereits vor diesem Fix (`submitForReview()` verlangt
     * Status `draft`, `publish()` verlangt Status `review`) -- `superseded`
     * erfuellt keine der beiden, die Guards brauchten keine Erweiterung.
     */
    public function test_a_superseded_version_cannot_be_submitted_for_review(): void
    {
        $service = new ContentVersioningService;
        $activity = Activity::factory()->create();
        $author = User::factory()->author()->create();

        $superseded = $service->createDraft($activity, ['v' => 1], $author);
        $service->createDraft($activity, ['v' => 2], $author);
        $this->assertSame('superseded', $superseded->fresh()->status);

        $this->expectException(RuntimeException::class);
        $service->submitForReview($superseded->fresh());
    }

    public function test_a_superseded_version_cannot_be_published(): void
    {
        $service = new ContentVersioningService;
        $activity = Activity::factory()->create();
        $author = User::factory()->author()->create();
        $reviewer = User::factory()->reviewer()->create();

        $superseded = $service->submitForReview($service->createDraft($activity, ['v' => 1], $author));
        $service->createDraft($activity, ['v' => 2], $author);
        $this->assertSame('superseded', $superseded->fresh()->status);

        $this->expectException(RuntimeException::class);
        $service->publish($superseded->fresh(), $reviewer);
    }
}

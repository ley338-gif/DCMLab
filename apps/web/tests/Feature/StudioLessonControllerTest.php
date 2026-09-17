<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Lab;
use App\Models\Lesson;
use App\Models\LessonElement;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Elementsequenz einer Lektion in Studio (ADR 0105/0106, CMS-6b/CMS-6c) --
 * Ansehen offen fuer jede Nicht-Lernende-Rolle, Umsortieren an dieselbe
 * Regel gebunden wie der Lektions-Editor selbst.
 */
class StudioLessonControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_learner_cannot_view_the_element_sequence(): void
    {
        $lesson = Lesson::factory()->create();
        $learner = User::factory()->create();

        $this->actingAs($learner)
            ->get("/de/studio/lessons/{$lesson->lesson_id}")
            ->assertForbidden();
    }

    public function test_an_author_sees_the_element_sequence_in_order(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id]);
        Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $quizActivity = Activity::factory()->create(['type' => 'quiz', 'key' => $lesson->lesson_id, 'title' => ['de' => 'Quiz-Titel']]);

        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $quizActivity->id, 'position' => 0]);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'content', 'activity_id' => null, 'position' => 1]);

        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->get("/de/studio/lessons/{$lesson->lesson_id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Studio/Lesson')
                ->has('elements', 2)
                ->where('elements.0.kind', 'quiz')
                ->where('elements.1.kind', 'content'),
            );
    }

    public function test_can_manage_reflects_the_same_rule_as_the_lesson_editor(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'content', 'position' => 0]);

        $reviewer = User::factory()->reviewer()->create();
        $unassignedAuthor = User::factory()->author()->create();
        $assignedAuthor = User::factory()->author()->create();
        $activity->authorUsers()->attach($assignedAuthor);

        $this->actingAs($reviewer)
            ->get("/de/studio/lessons/{$lesson->lesson_id}")
            ->assertInertia(fn ($page) => $page->where('can_manage', true));

        $this->actingAs($unassignedAuthor)
            ->get("/de/studio/lessons/{$lesson->lesson_id}")
            ->assertInertia(fn ($page) => $page->where('can_manage', false));

        $this->actingAs($assignedAuthor)
            ->get("/de/studio/lessons/{$lesson->lesson_id}")
            ->assertInertia(fn ($page) => $page->where('can_manage', true));
    }

    public function test_a_reviewer_can_reorder_the_elements(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $quizActivity = Activity::factory()->create(['type' => 'quiz', 'key' => '1.0']);

        $content = LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'content', 'position' => 0]);
        $quiz = LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $quizActivity->id, 'position' => 1]);

        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->patch("/de/studio/lessons/{$lesson->lesson_id}/reorder", [
                'order' => [$quiz->id, $content->id],
            ])
            ->assertRedirect();

        $this->assertSame(0, $quiz->fresh()->position);
        $this->assertSame(1, $content->fresh()->position);
    }

    public function test_an_unassigned_author_cannot_reorder(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $content = LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'content', 'position' => 0]);

        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->patch("/de/studio/lessons/{$lesson->lesson_id}/reorder", ['order' => [$content->id]])
            ->assertForbidden();

        $this->assertSame(0, $content->fresh()->position);
    }

    public function test_reordering_rejects_an_element_id_from_another_lesson(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $content = LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'content', 'position' => 0]);

        $otherLesson = Lesson::factory()->create(['track_id' => $track->id]);
        $foreignElement = LessonElement::create(['lesson_id' => $otherLesson->id, 'type' => 'content', 'position' => 0]);

        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->patch("/de/studio/lessons/{$lesson->lesson_id}/reorder", [
                'order' => [$foreignElement->id],
            ])
            ->assertSessionHasErrors('order.0');
    }

    public function test_the_element_sequence_lists_published_labs_not_yet_attached(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id]);
        Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);

        $attachedLab = Lab::factory()->create(['slug' => 'attached-lab', 'status' => 'published']);
        $attachedActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'attached-lab']);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $attachedActivity->id, 'position' => 0]);

        Lab::factory()->create(['slug' => 'available-lab', 'status' => 'published', 'title' => ['de' => 'Verfügbares Lab']]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'available-lab']);

        Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);

        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->get("/de/studio/lessons/{$lesson->lesson_id}")
            ->assertInertia(fn ($page) => $page
                ->has('available_labs', 1)
                ->where('available_labs.0.slug', 'available-lab')
                ->where('available_labs.0.title', 'Verfügbares Lab'),
            );
    }

    public function test_an_assigned_author_can_attach_a_published_lab(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id]);
        $lessonActivity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'content', 'position' => 0]);

        Lab::factory()->create(['slug' => 'c-echo-live', 'status' => 'published']);
        $labActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-live']);

        $author = User::factory()->author()->create();
        $lessonActivity->authorUsers()->attach($author);

        $this->actingAs($author)
            ->post("/de/studio/lessons/{$lesson->lesson_id}/labs", ['lab_slug' => 'c-echo-live'])
            ->assertRedirect();

        $element = LessonElement::query()->where('lesson_id', $lesson->id)->where('activity_id', $labActivity->id)->first();
        $this->assertNotNull($element);
        $this->assertSame('activity', $element->type);
        $this->assertSame(1, $element->position);
    }

    public function test_an_unassigned_author_cannot_attach_a_lab(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id]);
        Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);

        Lab::factory()->create(['slug' => 'c-echo-live', 'status' => 'published']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-live']);

        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->post("/de/studio/lessons/{$lesson->lesson_id}/labs", ['lab_slug' => 'c-echo-live'])
            ->assertForbidden();

        $this->assertSame(0, LessonElement::query()->where('lesson_id', $lesson->id)->count());
    }

    public function test_attaching_a_lab_already_attached_to_the_lesson_is_rejected(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id]);
        Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);

        Lab::factory()->create(['slug' => 'c-echo-live', 'status' => 'published']);
        $labActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-live']);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $labActivity->id, 'position' => 0]);

        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post("/de/studio/lessons/{$lesson->lesson_id}/labs", ['lab_slug' => 'c-echo-live'])
            ->assertSessionHasErrors('lab_slug');

        $this->assertSame(1, LessonElement::query()->where('lesson_id', $lesson->id)->where('activity_id', $labActivity->id)->count());
    }

    public function test_attaching_an_unpublished_lab_is_rejected(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id]);
        Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);

        Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);

        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post("/de/studio/lessons/{$lesson->lesson_id}/labs", ['lab_slug' => 'draft-lab'])
            ->assertSessionHasErrors('lab_slug');

        $this->assertSame(0, LessonElement::query()->where('lesson_id', $lesson->id)->count());
    }

    /**
     * Betreiber-Korrektur: Duplikat-Check, Positionsberechnung und
     * `create()` bildeten zuvor keine atomare Einheit -- zwei gleichzeitige
     * Anfragen fuer dasselbe Lab an derselben Lektion konnten beide den
     * (noch leeren) Duplikat-Check und dasselbe `max(position)` sehen. Ein
     * echtes Nebenlaeufigkeits-Race laesst sich unter der SQLite-Testdatenbank
     * (ein einzelner Schreiber, dateibasierte Sperre) nicht sinnvoll
     * nachstellen -- stattdessen wird hier die tatsaechliche Absicherung
     * direkt nachgewiesen: ein `SELECT ... FROM lessons WHERE id = ?` auf
     * die Lesson selbst (nicht die Route-Bindung, die ueber `lesson_id`
     * laeuft) muss als Teil der Anfrage ausgefuehrt werden -- das ist genau
     * die neue `lockForUpdate()`-Zeilensperre, die zwei ueberlappende
     * Transaktionen fuer dieselbe Lektion serialisiert.
     */
    public function test_attaching_a_lab_locks_the_lesson_row_for_the_duration_of_the_write(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id]);
        Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);

        Lab::factory()->create(['slug' => 'c-echo-live', 'status' => 'published']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-live']);

        $reviewer = User::factory()->reviewer()->create();

        DB::enableQueryLog();

        $this->actingAs($reviewer)
            ->post("/de/studio/lessons/{$lesson->lesson_id}/labs", ['lab_slug' => 'c-echo-live'])
            ->assertRedirect();

        $lockingLessonRead = collect(DB::getQueryLog())->contains(
            fn (array $entry) => str_contains($entry['query'], 'select * from "lessons" where "lessons"."id" = ?'),
        );

        DB::disableQueryLog();

        $this->assertTrue($lockingLessonRead, 'attachLab() muss die Lesson-Zeile explizit per lockForUpdate() lesen.');
    }
}

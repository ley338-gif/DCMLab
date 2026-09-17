<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Themenfeld;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Track-Verwaltung in Studio (ADR 0100, CMS-4b): das erste First-Class-
 * Lerninhalt-Objekt, das ein Studio-Formular ohne YAML/Git anlegen kann.
 */
class StudioTrackControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_learner_cannot_view_the_track_list(): void
    {
        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/de/studio/tracks')->assertForbidden();
    }

    public function test_an_author_can_view_but_not_manage_tracks(): void
    {
        $author = User::factory()->author()->create();
        Track::factory()->create(['title' => ['de' => 'Fundamente']]);

        $this->actingAs($author)
            ->get('/de/studio/tracks')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Studio/Tracks')
                ->where('can_manage', false)
                ->has('tracks', 1)
                ->where('tracks.0.title', 'Fundamente')
            );
    }

    public function test_a_track_without_a_real_title_falls_back_to_title_key_in_the_list(): void
    {
        $author = User::factory()->author()->create();
        Track::factory()->create(['title_key' => 'track.legacy.title', 'title' => null]);

        $this->actingAs($author)
            ->get('/de/studio/tracks')
            ->assertInertia(fn ($page) => $page->where('tracks.0.title', 'track.legacy.title'));
    }

    public function test_an_author_cannot_create_a_track(): void
    {
        $themenfeld = Themenfeld::factory()->create();
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->post('/de/studio/tracks', [
                'slug' => 'neuer-track', 'title' => 'Neuer Track',
                'themenfeld_id' => $themenfeld->id, 'level' => 'einsteiger',
                'hours' => 2, 'order' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, Track::query()->count());
    }

    public function test_a_reviewer_can_create_a_track_as_a_draft(): void
    {
        $themenfeld = Themenfeld::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post('/de/studio/tracks', [
                'slug' => 'netzwerksicherheit',
                'title' => 'Netzwerksicherheit',
                'teaser' => 'Grundlagen sicherer DICOM-Netze.',
                'themenfeld_id' => $themenfeld->id,
                'level' => 'aufbau',
                'hours' => 4,
                'order' => 2,
            ])
            ->assertRedirect();

        $track = Track::query()->where('slug', 'netzwerksicherheit')->first();
        $this->assertNotNull($track);
        $this->assertSame('draft', $track->status);
        $this->assertSame('Netzwerksicherheit', $track->title['de']);
        $this->assertSame('Grundlagen sicherer DICOM-Netze.', $track->teaser['de']);
        $this->assertSame($themenfeld->id, $track->themenfeld_id);
    }

    public function test_a_duplicate_slug_is_rejected(): void
    {
        Track::factory()->create(['slug' => 'fundamente']);
        $themenfeld = Themenfeld::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post('/de/studio/tracks', [
                'slug' => 'fundamente', 'title' => 'Duplikat',
                'themenfeld_id' => $themenfeld->id, 'level' => 'einsteiger',
                'hours' => 1, 'order' => 1,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_a_reviewer_can_update_track_metadata(): void
    {
        $themenfeld = Themenfeld::factory()->create();
        $track = Track::factory()->create(['themenfeld_id' => $themenfeld->id]);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->patch("/de/studio/tracks/{$track->slug}", [
                'title' => 'Neuer Titel', 'teaser' => null,
                'themenfeld_id' => $themenfeld->id, 'level' => 'fortgeschritten',
                'hours' => 9, 'order' => 5,
            ])
            ->assertRedirect();

        $track->refresh();
        $this->assertSame('Neuer Titel', $track->title['de']);
        $this->assertSame('fortgeschritten', $track->level);
        $this->assertSame(9, $track->hours);
    }

    public function test_the_full_status_lifecycle(): void
    {
        $track = Track::factory()->create(['status' => 'draft']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)->post("/de/studio/tracks/{$track->slug}/publish")->assertRedirect();
        $this->assertSame('published', $track->fresh()->status);

        $this->actingAs($reviewer)->post("/de/studio/tracks/{$track->slug}/unpublish")->assertRedirect();
        $this->assertSame('draft', $track->fresh()->status);

        $this->actingAs($reviewer)->post("/de/studio/tracks/{$track->slug}/archive")->assertRedirect();
        $this->assertSame('archived', $track->fresh()->status);

        $this->actingAs($reviewer)->post("/de/studio/tracks/{$track->slug}/restore")->assertRedirect();
        $this->assertSame('draft', $track->fresh()->status);
    }

    public function test_an_author_cannot_change_a_tracks_status(): void
    {
        $track = Track::factory()->create(['status' => 'draft']);
        $author = User::factory()->author()->create();

        $this->actingAs($author)->post("/de/studio/tracks/{$track->slug}/publish")->assertForbidden();
        $this->assertSame('draft', $track->fresh()->status);
    }

    public function test_an_author_cannot_move_a_lesson(): void
    {
        $sourceTrack = Track::factory()->create();
        $targetTrack = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $sourceTrack->id]);
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->post("/de/studio/tracks/{$targetTrack->slug}/lessons", ['lesson_id' => $lesson->lesson_id])
            ->assertForbidden();

        $this->assertSame($sourceTrack->id, $lesson->fresh()->track_id);
    }

    public function test_a_reviewer_can_move_a_lesson_into_this_track(): void
    {
        $sourceTrack = Track::factory()->create();
        $targetTrack = Track::factory()->create();
        Lesson::factory()->create(['track_id' => $targetTrack->id, 'order' => 0]);
        $lesson = Lesson::factory()->create(['track_id' => $sourceTrack->id, 'order' => 0]);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post("/de/studio/tracks/{$targetTrack->slug}/lessons", ['lesson_id' => $lesson->lesson_id])
            ->assertRedirect();

        $lesson->refresh();
        $this->assertSame($targetTrack->id, $lesson->track_id);
        // Ans Ende der Ziel-Track angehaengt, nicht die vorhandene Lektion
        // (order 0) ueberschrieben.
        $this->assertSame(1, $lesson->order);
    }

    public function test_moving_an_unknown_lesson_is_rejected(): void
    {
        $track = Track::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post("/de/studio/tracks/{$track->slug}/lessons", ['lesson_id' => 'nicht-vorhanden'])
            ->assertSessionHasErrors('lesson_id');
    }

    /**
     * Betreiber-Vorgabe: die Track wird VOR der Lesson gesperrt, in jedem
     * Aufruf in derselben Reihenfolge -- verhindert eine Verklemmung
     * zwischen zwei gleichzeitigen Verschiebungen, die sich
     * ueberschneidende Zeilen sonst in umgekehrter Reihenfolge sperren
     * koennten. Nachgewiesen ueber den Query-Log statt echter
     * Nebenlaeufigkeit (SQLite-Testdatenbank hat keine echte Zeilensperre,
     * derselbe Grund wie beim analogen Nachweis fuer
     * StudioLessonElementsController::attachLab()).
     */
    public function test_moving_a_lesson_locks_the_track_before_the_lesson(): void
    {
        $targetTrack = Track::factory()->create();
        $lesson = Lesson::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        DB::enableQueryLog();

        $this->actingAs($reviewer)
            ->post("/de/studio/tracks/{$targetTrack->slug}/lessons", ['lesson_id' => $lesson->lesson_id])
            ->assertRedirect();

        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $trackLockIndex = $queries->search(fn (string $query) => str_contains($query, 'select * from "tracks" where "tracks"."id" = ?'));
        $lessonLockIndex = $queries->search(fn (string $query) => str_contains($query, 'select * from "lessons" where "lesson_id" = ?'));

        $this->assertNotFalse($trackLockIndex, 'moveLesson() muss die Track-Zeile per lockForUpdate() lesen.');
        $this->assertNotFalse($lessonLockIndex, 'moveLesson() muss die Lesson-Zeile per lockForUpdate() lesen.');
        $this->assertLessThan($lessonLockIndex, $trackLockIndex, 'Die Track muss vor der Lesson gesperrt werden.');
    }

    public function test_an_author_cannot_reorder_lessons(): void
    {
        $track = Track::factory()->create();
        $first = Lesson::factory()->create(['track_id' => $track->id, 'order' => 0]);
        $second = Lesson::factory()->create(['track_id' => $track->id, 'order' => 1]);
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->patch("/de/studio/tracks/{$track->slug}/lessons/reorder", [
                'order' => [$second->lesson_id, $first->lesson_id],
            ])
            ->assertForbidden();

        $this->assertSame(0, $first->fresh()->order);
        $this->assertSame(1, $second->fresh()->order);
    }

    public function test_a_reviewer_can_reorder_the_tracks_lessons(): void
    {
        $track = Track::factory()->create();
        $first = Lesson::factory()->create(['track_id' => $track->id, 'order' => 0]);
        $second = Lesson::factory()->create(['track_id' => $track->id, 'order' => 1]);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->patch("/de/studio/tracks/{$track->slug}/lessons/reorder", [
                'order' => [$second->lesson_id, $first->lesson_id],
            ])
            ->assertRedirect();

        $this->assertSame(0, $second->fresh()->order);
        $this->assertSame(1, $first->fresh()->order);
    }

    /**
     * Betreiber-Vorgabe: die Permutation wird ERST NACH dem Sperren der
     * Track validiert, gegen den dann aktuellen Bestand -- eine Lektion,
     * die zwischen Anfrageeingang und Sperrerwerb per moveLesson() aus der
     * Track herausverschoben wurde, darf in der erwarteten Permutation
     * nicht mehr vorkommen.
     */
    public function test_reordering_rejects_a_lesson_id_no_longer_in_this_track(): void
    {
        $track = Track::factory()->create();
        $otherTrack = Track::factory()->create();
        $stayingLesson = Lesson::factory()->create(['track_id' => $track->id, 'order' => 0]);
        $movedAwayLesson = Lesson::factory()->create(['track_id' => $otherTrack->id, 'order' => 0]);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->patch("/de/studio/tracks/{$track->slug}/lessons/reorder", [
                'order' => [$movedAwayLesson->lesson_id, $stayingLesson->lesson_id],
            ])
            ->assertSessionHasErrors('order.0');
    }

    /**
     * Betreiber-Korrektur: eine Zeilensperre nur auf die Track reichte
     * nicht -- `moveLesson()` sperrt beim Verschieben einer Lesson nur die
     * ZIEL-Track, nie die QUELL-Track der schon dort befindlichen Lessons.
     * Ohne eigene Sperren auf den Lesson-Zeilen selbst konnte eine
     * gleichzeitige `moveLesson()` eine Lesson aus dieser Track
     * herausverschieben, NACHDEM sie hier schon in die Permutation
     * aufgenommen wurde, und die anschliessende `order`-Zuweisung traf
     * dann eine laengst fremde Lesson. Ein echtes Race laesst sich unter
     * der SQLite-Testdatenbank nicht sinnvoll nachstellen (derselbe Grund
     * wie bei den analogen Lock-Nachweisen fuer `attachLab()`/
     * `moveLesson()`) -- stattdessen wird hier die tatsaechliche
     * Absicherung direkt nachgewiesen: jede zur Track gehoerende
     * Lesson-Zeile muss gelesen UND gesperrt werden, nicht nur die Track.
     */
    public function test_reordering_locks_every_lesson_row_belonging_to_the_track(): void
    {
        $track = Track::factory()->create();
        $first = Lesson::factory()->create(['track_id' => $track->id, 'order' => 0]);
        $second = Lesson::factory()->create(['track_id' => $track->id, 'order' => 1]);
        $reviewer = User::factory()->reviewer()->create();

        DB::enableQueryLog();

        $this->actingAs($reviewer)
            ->patch("/de/studio/tracks/{$track->slug}/lessons/reorder", [
                'order' => [$second->lesson_id, $first->lesson_id],
            ])
            ->assertRedirect();

        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $lessonLockIndex = $queries->search(
            fn (string $query) => str_contains($query, 'select "id", "lesson_id" from "lessons" where "track_id" = ?'),
        );

        $this->assertNotFalse($lessonLockIndex, 'reorderLessons() muss alle Lesson-Zeilen dieser Track per lockForUpdate() lesen, nicht nur die Track selbst.');
    }

    /**
     * Zweiter Teil derselben Betreiber-Korrektur: selbst wenn die Sperre
     * oben je fehlschluege, darf das eigentliche `update()` niemals eine
     * Lesson treffen, die nicht mehr zur gesperrten Track gehoert -- jedes
     * Update ist deshalb zusaetzlich auf `track_id` eingeschraenkt. Direkt
     * ueber den Query-Log nachgewiesen, damit ein kuenftiges "Vereinfachen"
     * dieser WHERE-Klausel (genau der urspruengliche Fehler) sofort
     * auffaellt.
     */
    public function test_reordering_scopes_each_update_to_the_locked_track(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'order' => 0]);
        $reviewer = User::factory()->reviewer()->create();

        DB::enableQueryLog();

        $this->actingAs($reviewer)
            ->patch("/de/studio/tracks/{$track->slug}/lessons/reorder", [
                'order' => [$lesson->lesson_id],
            ])
            ->assertRedirect();

        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $scopedUpdateIndex = $queries->search(
            fn (string $query) => str_contains($query, 'update "lessons" set "order" = ?, "updated_at" = ? where "lessons"."id" = ? and "track_id" = ?'),
        );

        $this->assertNotFalse($scopedUpdateIndex, 'reorderLessons() muss jedes Lesson-Update zusaetzlich auf track_id = die gesperrte Track einschraenken.');
    }
}

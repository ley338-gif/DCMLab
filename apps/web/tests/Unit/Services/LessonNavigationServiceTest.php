<?php

namespace Tests\Unit\Services;

use App\Models\Activity;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Track;
use App\Models\User;
use App\Services\LessonNavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sidebar-Daten fuer die Lesson-Ansicht: Status je Lektion im aktuellen
 * Track, Zaehler fuer die uebrigen Tracks, echter Gesamtfortschritt.
 */
class LessonNavigationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_lesson_status_within_the_current_track(): void
    {
        $track = Track::factory()->create(['order' => 1, 'status' => 'published']);
        $done = Lesson::factory()->create(['track_id' => $track->id, 'order' => 0, 'lesson_id' => '1.0']);
        $current = Lesson::factory()->create(['track_id' => $track->id, 'order' => 1, 'lesson_id' => '1.1']);
        $todo = Lesson::factory()->create(['track_id' => $track->id, 'order' => 2, 'lesson_id' => '1.2']);

        $user = User::factory()->create();
        LessonProgress::create([
            'user_id' => $user->id,
            'lesson_id' => $done->id,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $sidebar = (new LessonNavigationService)->sidebarFor($user, $current);

        $statuses = collect($sidebar['current_track']['lessons'])->pluck('status', 'lesson_id');

        $this->assertSame('completed', $statuses['1.0']);
        $this->assertSame('current', $statuses['1.1']);
        $this->assertSame('todo', $statuses['1.2']);
        $this->assertSame($track->slug, $sidebar['current_track']['slug']);
    }

    public function test_it_summarizes_other_published_tracks_without_full_lesson_lists(): void
    {
        $currentTrack = Track::factory()->create(['order' => 1, 'status' => 'published']);
        $lesson = Lesson::factory()->create(['track_id' => $currentTrack->id, 'order' => 0]);

        $otherTrack = Track::factory()->create(['order' => 2, 'status' => 'published']);
        Lesson::factory()->count(3)->create(['track_id' => $otherTrack->id]);

        $draftTrack = Track::factory()->create(['order' => 3, 'status' => 'draft']);
        Lesson::factory()->create(['track_id' => $draftTrack->id]);

        $user = User::factory()->create();

        $sidebar = (new LessonNavigationService)->sidebarFor($user, $lesson);

        $this->assertCount(1, $sidebar['other_tracks']);
        $this->assertSame($otherTrack->slug, $sidebar['other_tracks'][0]['slug']);
        $this->assertSame(3, $sidebar['other_tracks'][0]['lessons_count']);
        $this->assertSame(0, $sidebar['other_tracks'][0]['completed_lessons_count']);
    }

    public function test_it_computes_real_overall_progress_across_published_tracks(): void
    {
        $trackA = Track::factory()->create(['status' => 'published']);
        $lessonA1 = Lesson::factory()->create(['track_id' => $trackA->id, 'order' => 0]);
        Lesson::factory()->create(['track_id' => $trackA->id, 'order' => 1]);

        $trackB = Track::factory()->create(['status' => 'published']);
        Lesson::factory()->create(['track_id' => $trackB->id]);

        $draftTrack = Track::factory()->create(['status' => 'draft']);
        Lesson::factory()->create(['track_id' => $draftTrack->id]);

        $user = User::factory()->create();
        LessonProgress::create([
            'user_id' => $user->id,
            'lesson_id' => $lessonA1->id,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $sidebar = (new LessonNavigationService)->sidebarFor($user, $lessonA1);

        $this->assertSame(1, $sidebar['overall']['completed']);
        $this->assertSame(3, $sidebar['overall']['total']);
    }

    /**
     * Published Content Boundary Hardening: eine Draft-/Review-/archivierte
     * Geschwister-Lektion darf im aktuellen Track weder Titel noch einen
     * echten Navigationslink preisgeben.
     */
    public function test_it_excludes_draft_siblings_from_the_current_track_for_a_learner(): void
    {
        $track = Track::factory()->create(['order' => 1, 'status' => 'published']);
        $current = Lesson::factory()->create(['track_id' => $track->id, 'order' => 0, 'lesson_id' => '1.0']);
        Lesson::factory()->create(['track_id' => $track->id, 'order' => 1, 'lesson_id' => '1.1', 'status' => 'draft']);

        $user = User::factory()->create();

        $sidebar = (new LessonNavigationService)->sidebarFor($user, $current);

        $this->assertSame(['1.0'], collect($sidebar['current_track']['lessons'])->pluck('lesson_id')->all());
    }

    /**
     * Gegenprobe: ein zugewiesener Reviewer sieht ueber LessonPolicy::view()
     * seine eigene sichtbare Draft-Geschwister-Lektion weiterhin.
     */
    public function test_an_authorized_reviewer_still_sees_a_draft_sibling(): void
    {
        $track = Track::factory()->create(['order' => 1, 'status' => 'published']);
        $current = Lesson::factory()->create(['track_id' => $track->id, 'order' => 0, 'lesson_id' => '1.0']);
        Lesson::factory()->create(['track_id' => $track->id, 'order' => 1, 'lesson_id' => '1.1', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.1']);

        $reviewer = User::factory()->reviewer()->create();

        $sidebar = (new LessonNavigationService)->sidebarFor($reviewer, $current);

        $this->assertSame(['1.0', '1.1'], collect($sidebar['current_track']['lessons'])->pluck('lesson_id')->all());
    }

    /**
     * Fortschrittszaehler der uebrigen Tracks: eine Draft-Lesson darf den
     * Nenner nicht aufblaehen (analog DashboardController::index()).
     */
    public function test_other_track_counters_exclude_draft_lessons(): void
    {
        $currentTrack = Track::factory()->create(['order' => 1, 'status' => 'published']);
        $lesson = Lesson::factory()->create(['track_id' => $currentTrack->id, 'order' => 0]);

        $otherTrack = Track::factory()->create(['order' => 2, 'status' => 'published']);
        Lesson::factory()->count(2)->create(['track_id' => $otherTrack->id]);
        Lesson::factory()->create(['track_id' => $otherTrack->id, 'status' => 'draft']);

        $user = User::factory()->create();

        $sidebar = (new LessonNavigationService)->sidebarFor($user, $lesson);

        $this->assertSame(2, $sidebar['other_tracks'][0]['lessons_count']);
    }

    /**
     * Historische Progress-Datensaetze von Inhalten, die spaeter wieder auf
     * Draft gesetzt wurden, duerfen den globalen Gesamtfortschritt nicht
     * verfaelschen -- weder als zusaetzlicher Nenner noch als zusaetzlicher
     * Zaehler.
     */
    public function test_overall_progress_ignores_draft_lessons_and_historical_progress_on_a_reverted_lesson(): void
    {
        $track = Track::factory()->create(['status' => 'published']);
        $lessonA = Lesson::factory()->create(['track_id' => $track->id, 'order' => 0]);
        $lessonB = Lesson::factory()->create(['track_id' => $track->id, 'order' => 1]);

        $user = User::factory()->create();
        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $lessonA->id,
            'status' => 'completed', 'started_at' => now(), 'completed_at' => now(),
        ]);
        $lessonA->update(['status' => 'draft']);

        $sidebar = (new LessonNavigationService)->sidebarFor($user, $lessonB);

        $this->assertSame(0, $sidebar['overall']['completed']);
        $this->assertSame(1, $sidebar['overall']['total']);
    }
}

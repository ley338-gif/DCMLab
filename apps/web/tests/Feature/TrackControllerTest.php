<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Themenfeld;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_track_page_is_publicly_visible(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente', 'status' => 'published']);
        Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0', 'order' => 0]);
        Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.1', 'order' => 1]);

        $response = $this->get('/de/tracks/fundamente');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tracks/Show')
            ->where('track.slug', 'fundamente')
            ->where('lessons.0.lesson_id', '1.0')
            ->where('lessons.1.lesson_id', '1.1'),
        );
    }

    /**
     * Abschnitt 13: ein Track ohne themenfeld_id (Bestand vor der
     * Themenfeld-Migration, oder ueber Track::factory() ohne den Wert
     * erzeugt) faellt auf "dicom" zurueck -- denselben Fallback wie
     * NodeController::themenfeldSlug().
     */
    public function test_a_track_without_a_themenfeld_falls_back_to_dicom_on_the_show_page(): void
    {
        Track::factory()->create(['slug' => 'fundamente', 'status' => 'published']);

        $this->get('/de/tracks/fundamente')
            ->assertInertia(fn ($page) => $page->where('track.themenfeld', 'dicom'));
    }

    public function test_the_track_index_groups_tracks_by_themenfeld_ordered_by_themenfeld_then_track_order(): void
    {
        $datenschutz = Themenfeld::factory()->create(['slug' => 'datenschutz', 'order' => 2]);
        $dicom = Themenfeld::factory()->create(['slug' => 'dicom', 'order' => 1]);

        Track::factory()->create(['slug' => 'grundlagen', 'themenfeld_id' => $datenschutz->id, 'order' => 1, 'status' => 'published']);
        Track::factory()->create(['slug' => 'services', 'themenfeld_id' => $dicom->id, 'order' => 2, 'status' => 'published']);
        Track::factory()->create(['slug' => 'fundamente', 'themenfeld_id' => $dicom->id, 'order' => 1, 'status' => 'published']);

        $this->get('/de/tracks')
            ->assertInertia(fn ($page) => $page
                ->component('Tracks/Index')
                ->where('tracks.0.slug', 'fundamente')
                ->where('tracks.0.themenfeld', 'dicom')
                ->where('tracks.1.slug', 'services')
                ->where('tracks.1.themenfeld', 'dicom')
                ->where('tracks.2.slug', 'grundlagen')
                ->where('tracks.2.themenfeld', 'datenschutz'),
            );
    }

    /**
     * ADR 0100 (CMS-4a): ein in Studio angelegter Track hat einen echten
     * `title`-Wert statt eines `title_key`-Verweises in lang/de.json.
     */
    public function test_a_track_with_a_real_title_uses_it_instead_of_title_key(): void
    {
        $track = Track::factory()->create([
            'slug' => 'fundamente',
            'status' => 'published',
            'title' => ['de' => 'Fundamente der DICOM-Kommunikation'],
        ]);

        $this->get('/de/tracks')
            ->assertInertia(fn ($page) => $page->where('tracks.0.title.de', 'Fundamente der DICOM-Kommunikation'));

        $this->get("/de/tracks/{$track->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('track.title.de', 'Fundamente der DICOM-Kommunikation'));
    }

    /**
     * ADR 0100 (CMS-4b): archiviert heisst fuer Lernende nicht mehr
     * sichtbar, anders als "draft" (bleibt als "Bald verfügbar" gelistet).
     */
    public function test_an_archived_track_is_hidden_from_the_index_and_returns_404_on_show(): void
    {
        Track::factory()->create(['slug' => 'archiviert', 'status' => 'archived']);
        Track::factory()->create(['slug' => 'fundamente', 'status' => 'published']);

        $this->get('/de/tracks')
            ->assertInertia(fn ($page) => $page
                ->has('tracks', 1)
                ->where('tracks.0.slug', 'fundamente'));

        $this->get('/de/tracks/archiviert')->assertNotFound();
    }

    public function test_it_lists_lessons_ordered_and_marks_completion_per_user(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $first = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0', 'order' => 0]);
        $second = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.1', 'order' => 1]);

        $user = User::factory()->create();
        LessonProgress::create([
            'user_id' => $user->id,
            'lesson_id' => $first->id,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/de/tracks/fundamente');

        $response->assertInertia(fn ($page) => $page
            ->where('lessons.0.lesson_id', '1.0')
            ->where('lessons.0.completed', true)
            ->where('lessons.1.lesson_id', '1.1')
            ->where('lessons.1.completed', false),
        );
    }

    public function test_another_users_completion_does_not_leak_into_this_users_view(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0', 'order' => 0]);

        $otherUser = User::factory()->create();
        LessonProgress::create([
            'user_id' => $otherUser->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get('/de/tracks/fundamente')
            ->assertInertia(fn ($page) => $page->where('lessons.0.completed', false));
    }
}

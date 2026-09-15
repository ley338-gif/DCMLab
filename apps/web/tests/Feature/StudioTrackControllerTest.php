<?php

namespace Tests\Feature;

use App\Models\Themenfeld;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

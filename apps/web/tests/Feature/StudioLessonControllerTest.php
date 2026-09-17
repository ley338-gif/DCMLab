<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lesson-Ressourcenliste in Studio (Studio-Lessons-Umbau) -- nur die Liste,
 * der Editor selbst bleibt LessonEditorControllerTest's Sache.
 */
class StudioLessonControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_learner_cannot_view_the_lesson_list(): void
    {
        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/de/studio/lessons')->assertForbidden();
    }

    public function test_an_author_sees_the_full_lesson_list(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente', 'title' => ['de' => 'Fundamente']]);
        $lesson = Lesson::factory()->create([
            'lesson_id' => '1.0',
            'track_id' => $track->id,
            'level' => 'einsteiger',
            'status' => 'published',
            'title' => ['de' => 'Erste Lektion'],
        ]);
        Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);

        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->get('/de/studio/lessons')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Studio/Lessons/Index')
                ->has('lessons', 1)
                ->where('lessons.0.lesson_id', '1.0')
                ->where('lessons.0.title', 'Erste Lektion')
                ->where('lessons.0.track.slug', 'fundamente')
                ->where('lessons.0.level', 'einsteiger')
                ->where('lessons.0.status', 'published')
                ->where('lessons.0.pending_version_status', null)
                ->has('tracks', 1)
                ->where('tracks.0.slug', 'fundamente'),
            );
    }

    public function test_the_pending_version_status_reflects_a_draft(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        ContentVersion::create([
            'activity_id' => $activity->id,
            'status' => 'draft',
            'payload' => [],
            'is_current' => false,
            'created_by' => User::factory()->author()->create()->id,
        ]);

        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get('/de/studio/lessons')
            ->assertInertia(fn ($page) => $page
                ->where('lessons.0.pending_version_status', 'draft'),
            );
    }

    public function test_the_pending_version_status_reflects_a_review(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        ContentVersion::create([
            'activity_id' => $activity->id,
            'status' => 'review',
            'payload' => [],
            'is_current' => false,
            'created_by' => User::factory()->author()->create()->id,
        ]);

        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get('/de/studio/lessons')
            ->assertInertia(fn ($page) => $page
                ->where('lessons.0.pending_version_status', 'review'),
            );
    }

    public function test_a_published_version_does_not_count_as_pending(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        ContentVersion::create([
            'activity_id' => $activity->id,
            'status' => 'published',
            'payload' => [],
            'is_current' => true,
            'created_by' => User::factory()->author()->create()->id,
            'published_at' => now(),
        ]);

        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get('/de/studio/lessons')
            ->assertInertia(fn ($page) => $page
                ->where('lessons.0.pending_version_status', null),
            );
    }
}

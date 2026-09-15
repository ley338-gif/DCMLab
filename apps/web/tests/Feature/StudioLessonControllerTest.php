<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Lesson;
use App\Models\LessonElement;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sichtbarkeit der Elementsequenz in Studio (ADR 0105, CMS-6b) -- rein
 * lesend, noch ohne Drag & Drop (CMS-6c).
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
}

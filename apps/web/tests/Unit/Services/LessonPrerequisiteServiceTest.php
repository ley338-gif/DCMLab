<?php

namespace Tests\Unit\Services;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Track;
use App\Models\User;
use App\Services\LessonPrerequisiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR 0071, W5: requires wird durchgesetzt -- aber weich (siehe
 * docs/content-schema.md Abschnitt 2, "Quereinstieg"). Diese Klasse
 * berechnet nur, was offen ist; sie blockiert nichts.
 */
class LessonPrerequisiteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lesson_without_requires_has_nothing_unmet(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'requires' => []]);
        $user = User::factory()->create();

        $unmet = (new LessonPrerequisiteService)->unmetFor($user, $lesson);

        $this->assertSame([], $unmet);
    }

    public function test_an_uncompleted_requirement_is_reported_with_its_title(): void
    {
        $track = Track::factory()->create();
        $required = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0', 'title' => ['de' => 'Grundlagen']]);
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.1', 'requires' => ['1.0']]);
        $user = User::factory()->create();

        $unmet = (new LessonPrerequisiteService)->unmetFor($user, $lesson);

        $this->assertSame([['lesson_id' => '1.0', 'title' => 'Grundlagen']], $unmet);
    }

    public function test_a_completed_requirement_is_not_reported(): void
    {
        $track = Track::factory()->create();
        $required = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0']);
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.1', 'requires' => ['1.0']]);
        $user = User::factory()->create();

        LessonProgress::create([
            'user_id' => $user->id,
            'lesson_id' => $required->id,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $unmet = (new LessonPrerequisiteService)->unmetFor($user, $lesson);

        $this->assertSame([], $unmet);
    }

    public function test_unmet_for_many_only_returns_lessons_with_at_least_one_open_requirement(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0']);
        $blocked = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.1', 'requires' => ['1.0']]);
        $unblocked = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.2', 'requires' => []]);
        $user = User::factory()->create();

        $result = (new LessonPrerequisiteService)->unmetForMany($user, collect([$blocked, $unblocked]));

        $this->assertSame(['1.1'], array_keys($result));
    }
}

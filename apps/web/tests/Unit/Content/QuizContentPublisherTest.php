<?php

namespace Tests\Unit\Content;

use App\Content\QuizContentPublisher;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR 0104 (CMS-6a): wendet einen Quiz-Entwurf direkt auf die DB an -- kein
 * Datei-Schreibvorgang.
 */
class QuizContentPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_question_metadata_and_regenerates_the_quiz_section_in_the_body(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create([
            'track_id' => $track->id,
            'quiz' => [['id' => 'q1', 'type' => 'single', 'answer' => 0]],
            'body' => "Prosa.\n\n## Quiz\n\n**q1 — Alte Frage?**\n1. Alt A\n2. Alt B",
        ]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);

        (new QuizContentPublisher)->publish($activity, [
            'quiz' => [
                ['id' => 'q1', 'type' => 'single', 'answer' => 1, 'question' => 'Neue Frage?', 'options' => ['Neu A', 'Neu B']],
                ['id' => 'q2', 'type' => 'input', 'answer' => 'Text', 'question' => 'Zweite Frage?', 'options' => []],
            ],
        ]);

        $lesson->refresh();
        $this->assertSame([
            ['id' => 'q1', 'type' => 'single', 'answer' => 1],
            ['id' => 'q2', 'type' => 'input', 'answer' => 'Text'],
        ], $lesson->quiz);
        $this->assertStringContainsString('Prosa.', $lesson->body);
        $this->assertStringContainsString('Neue Frage?', $lesson->body);
        $this->assertStringContainsString('Zweite Frage?', $lesson->body);
        $this->assertStringNotContainsString('Alte Frage', $lesson->body);
    }

    public function test_it_updates_the_activity_source_hash(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'body' => 'Prosa.']);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id, 'source_hash' => 'alt']);

        (new QuizContentPublisher)->publish($activity, [
            'quiz' => [['id' => 'q1', 'type' => 'single', 'answer' => 0, 'question' => 'F?', 'options' => ['A', 'B']]],
        ]);

        $this->assertNotSame('alt', $activity->refresh()->source_hash);
    }
}

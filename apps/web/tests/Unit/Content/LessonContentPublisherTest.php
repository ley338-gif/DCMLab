<?php

namespace Tests\Unit\Content;

use App\Content\LessonContentPublisher;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR 0102 (CMS-5b): wendet einen Lektionsfeld-Entwurf direkt auf die DB an
 * -- kein Datei-Schreibvorgang, kein content:sync.
 */
class LessonContentPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_every_field_directly_to_the_lesson_row(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create([
            'track_id' => $track->id,
            'title' => ['de' => 'Alt'],
            'teaser' => ['de' => 'Alt'],
            'objectives' => ['Altes Ziel'],
            'body' => 'Alte Prosa ohne Quiz.',
        ]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);

        (new LessonContentPublisher)->publish($activity, [
            'title' => 'Neu',
            'teaser' => 'Neuer Teaser',
            'level' => 'fortgeschritten',
            'duration_minutes' => 20,
            'tools' => ['dcmftest'],
            'requires' => ['1.0'],
            'glossary_terms' => ['dicom'],
            'objectives' => ['Ziel eins', 'Ziel zwei'],
            'sandbox' => ['required' => true, 'dataset' => 'ct-head-01', 'note' => null],
            'lab' => ['node' => 'silent-ct', 'optional' => true],
            'body' => 'Neue Prosa.',
        ]);

        $lesson->refresh();
        $this->assertSame('Neu', $lesson->title['de']);
        $this->assertSame('Neuer Teaser', $lesson->teaser['de']);
        $this->assertSame('fortgeschritten', $lesson->level);
        $this->assertSame(20, $lesson->duration_minutes);
        $this->assertSame(['dcmftest'], $lesson->tools);
        $this->assertSame(['1.0'], $lesson->requires);
        $this->assertSame(['dicom'], $lesson->glossary_terms);
        $this->assertSame(['Ziel eins', 'Ziel zwei'], $lesson->objectives);
        $this->assertSame(2, $lesson->objectives_count);
        $this->assertTrue($lesson->sandbox['required']);
        $this->assertSame('silent-ct', $lesson->lab['node']);
        $this->assertSame('Neue Prosa.', $lesson->body);
    }

    public function test_it_preserves_an_existing_quiz_section_in_the_body(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create([
            'track_id' => $track->id,
            'body' => "Alte Prosa.\n\n## Quiz\n\n**q1 — Frage?**\n1. A\n2. B\n\n---\n\n**Als Nächstes:** weiter.",
        ]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);

        (new LessonContentPublisher)->publish($activity, [
            'title' => 'Neu', 'teaser' => 'Neu', 'level' => 'einsteiger', 'duration_minutes' => 5,
            'objectives' => ['Ziel'], 'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
            'lab' => ['node' => null, 'optional' => true], 'body' => 'Neue Prosa.',
        ]);

        $lesson->refresh();
        $this->assertStringContainsString('Neue Prosa.', $lesson->body);
        $this->assertStringContainsString('q1 — Frage?', $lesson->body);
        $this->assertStringContainsString('Als Nächstes', $lesson->body);
        $this->assertStringNotContainsString('Alte Prosa.', $lesson->body);
    }

    public function test_it_keeps_the_activity_row_title_and_teaser_in_sync(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id]);
        $activity = Activity::factory()->create([
            'type' => 'lesson',
            'key' => $lesson->lesson_id,
            'title' => ['de' => 'Alt'],
            'teaser' => ['de' => 'Alt'],
            'source_hash' => 'alt',
        ]);

        (new LessonContentPublisher)->publish($activity, [
            'title' => 'Neuer Aktivitaetstitel', 'teaser' => 'Neuer Aktivitaetsteaser',
            'level' => 'einsteiger', 'duration_minutes' => 5, 'objectives' => ['Ziel'],
            'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
            'lab' => ['node' => null, 'optional' => true], 'body' => 'Prosa.',
        ]);

        $activity->refresh();
        $this->assertSame('Neuer Aktivitaetstitel', $activity->title['de']);
        $this->assertSame('Neuer Aktivitaetsteaser', $activity->teaser['de']);
        $this->assertNotSame('alt', $activity->source_hash);
    }
}

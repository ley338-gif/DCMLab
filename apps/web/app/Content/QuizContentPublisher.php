<?php

namespace App\Content;

use App\Models\Activity;
use App\Models\Lesson;

/**
 * Wendet einen freigegebenen Quiz-Entwurf direkt auf die DB an (ADR 0104,
 * CMS-6a) -- Gegenstueck zu `LessonContentPublisher` fuer den `quiz`-
 * Schluessel desselben Payload-Diskriminators (siehe
 * `ActivityContentApplier`). Der Fragetext/die Optionen stecken bereits im
 * `body`-Feld (seit ADR 0101, Teil des vollstaendigen Markdowns); nur die
 * Metadaten (`id`/`type`/`answer`) brauchen einen eigenen Speicherort
 * (`lessons.quiz`, ADR 0104).
 *
 * `LessonQuizGenerator::regenerateBody()` ist reine Text-in-Text-out-Logik
 * (kein Datei-I/O) -- dieselbe Funktion, die bisher gegen Dateitext lief,
 * wendet sich hier unveraendert auf den DB-Spaltenwert an.
 */
final class QuizContentPublisher
{
    /**
     * @param  array{quiz: list<array{id: string, type: string, answer: mixed, question: string, options: list<string>}>}  $payload
     */
    public function publish(Activity $activity, array $payload): void
    {
        $lesson = Lesson::where('lesson_id', $activity->key)->firstOrFail();
        $questions = $payload['quiz'];

        $body = LessonQuizGenerator::regenerateBody((string) ($lesson->body ?? ''), $questions);

        $lesson->update([
            'quiz' => array_map(fn (array $question): array => [
                'id' => $question['id'],
                'type' => $question['type'],
                'answer' => $question['answer'],
            ], $questions),
            'body' => $body,
        ]);

        $activity->update([
            'source_hash' => hash('sha256', json_encode(['quiz' => $questions, 'body' => $body], JSON_THROW_ON_ERROR)),
        ]);
    }
}

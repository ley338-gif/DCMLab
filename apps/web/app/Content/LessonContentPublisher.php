<?php

namespace App\Content;

use App\Models\Activity;
use App\Models\Lesson;

/**
 * Wendet einen freigegebenen Lektions-Entwurf direkt auf die DB an (ADR
 * 0102, CMS-5b) -- Gegenstueck zu `ContentWriter` fuer Aktivitaetstypen, die
 * ihren Fliesstext bereits vollstaendig in der DB fuehren (seit ADR 0101).
 * Anders als `ContentWriter` gibt es keine Datei zu schreiben, kein
 * `content:sync` und keine Cache-Invalidierung -- die Werte landen direkt in
 * den Spalten, die `LessonController::show()` ohnehin schon bevorzugt liest.
 *
 * Nur fuer Lektionsfeld-Entwuerfe (kein `quiz`-Schluessel, siehe
 * ActivityContentApplier) -- Quiz-Entwuerfe bleiben bis CMS-6 datei-gefuehrt
 * (ADR 0097), weil Fragen-Metadaten noch keine eigene DB-Spalte haben.
 */
final class LessonContentPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(Activity $activity, array $payload): void
    {
        $lesson = Lesson::where('lesson_id', $activity->key)->firstOrFail();

        // Ein bestehender Quiz-Abschnitt (und die Fussnote danach) gehoert
        // nicht diesem Editor -- wie zuvor bei LessonActivity::serialize()
        // bleibt er unangetastet erhalten, nur die Prosa davor wird ersetzt.
        $split = QuizContent::splitBody((string) ($lesson->body ?? ''));
        $newBefore = rtrim((string) $payload['body'], "\r\n");
        $body = $split['quiz_raw'] !== ''
            ? $newBefore."\n\n".$split['quiz_raw']."\n\n".$split['after']
            : $newBefore;

        $lesson->update([
            'title' => ['de' => $payload['title']],
            'teaser' => ['de' => $payload['teaser']],
            'level' => $payload['level'],
            'duration_minutes' => $payload['duration_minutes'],
            'tools' => $payload['tools'] ?? [],
            'requires' => $payload['requires'] ?? [],
            'glossary_terms' => $payload['glossary_terms'] ?? [],
            'objectives' => $payload['objectives'],
            'objectives_count' => count($payload['objectives']),
            'sandbox' => $payload['sandbox'],
            'lab' => $payload['lab'],
            'body' => $body,
        ]);

        // Haelt den activities-Verzeichniseintrag (Autoren-Panel,
        // Review-Queue) konsistent mit der Lektion -- vorher Aufgabe von
        // content:sync, das dieser Pfad jetzt bewusst nicht mehr aufruft.
        $activity->update([
            'title' => $lesson->title,
            'teaser' => $lesson->teaser,
            'source_hash' => hash('sha256', json_encode(['payload' => $payload, 'body' => $body], JSON_THROW_ON_ERROR)),
        ]);
    }
}

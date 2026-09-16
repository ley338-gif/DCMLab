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
 * ActivityContentApplier) -- Quiz-Entwuerfe bleiben bei `QuizContentPublisher`
 * (ADR 0104), weil Fragen-Metadaten weiterhin nur `lessons.quiz` betreffen.
 *
 * Seit CMS-7d.3 (ADR 0118) schreibt dieser Publisher `rich_content`, nicht
 * mehr `body` -- der Aufrufer (`ContentPublishingService`) hat das Payload
 * vorher immer schon normalisiert (`LessonPayloadNormalizer`), `body` wird
 * hier bewusst NICHT mehr aus dem Entwurf neu erzeugt (Betreiber-Vorgabe:
 * "keine zwei schreibenden Sources of Truth"). Die Spalte bleibt unangetastet
 * stehen -- Legacy-Fallback fuer eine noch nicht migrierte Lektion, siehe
 * `LessonController::show()`.
 */
final class LessonContentPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(Activity $activity, array $payload): void
    {
        $lesson = Lesson::where('lesson_id', $activity->key)->firstOrFail();

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
            'related_node' => $payload['related_node'],
            'rich_content' => $payload['rich_content'],
        ]);

        // Haelt den activities-Verzeichniseintrag (Autoren-Panel,
        // Review-Queue) konsistent mit der Lektion -- vorher Aufgabe von
        // content:sync, das dieser Pfad jetzt bewusst nicht mehr aufruft.
        $activity->update([
            'title' => $lesson->title,
            'teaser' => $lesson->teaser,
            'source_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);
    }
}

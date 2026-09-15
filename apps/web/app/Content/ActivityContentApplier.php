<?php

namespace App\Content;

use App\Activities\ActivityRegistry;
use App\Activities\ActivityType;
use App\Models\Activity;

/**
 * Entscheidet, WIE ein freigegebener Entwurf tatsaechlich wirksam wird (ADR
 * 0102, CMS-5b) -- ersetzt den bisherigen, immer-datei-schreibenden Aufruf
 * von `ContentWriter::write()` in `ContentVersionController::publish()`.
 *
 * Eine Lektionsfeld-Aenderung (kein `quiz`-Schluessel im Payload) geht seit
 * ADR 0101 direkt in die DB (`LessonContentPublisher`) -- `content/` wird
 * dabei nicht mehr angefasst. Jeder andere Fall (Quiz-Entwurf -- weiterhin
 * an seine Lektion gebunden, ADR 0097 --, Pruefung, Achievement, Node) nutzt
 * weiterhin `ContentWriter`, bis auch deren Fliesstext DB-gefuehrt ist
 * (CMS-6/CMS-8).
 */
final readonly class ActivityContentApplier
{
    public function __construct(
        private ActivityRegistry $registry,
        private ContentWriter $writer,
        private LessonContentPublisher $lessonPublisher,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return list<ContentIssue> Nicht leer, wenn NICHTS uebernommen wurde.
     */
    public function apply(Activity $activityModel, array $payload): array
    {
        $activity = $this->registry->resolve($activityModel);
        $issues = $activity->validate($payload);

        if ($issues !== []) {
            return $issues;
        }

        if ($activityModel->type === ActivityType::Lesson->value && ! isset($payload['quiz'])) {
            $this->lessonPublisher->publish($activityModel, $payload);

            return [];
        }

        return $this->writer->write($activity, $payload);
    }
}

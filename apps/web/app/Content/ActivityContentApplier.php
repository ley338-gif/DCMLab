<?php

namespace App\Content;

use App\Activities\ActivityRegistry;
use App\Activities\ActivityType;
use App\Models\Activity;

/**
 * Entscheidet, WIE ein freigegebener Entwurf tatsaechlich wirksam wird (ADR
 * 0102/0104, CMS-5b/CMS-6a) -- ersetzt den bisherigen, immer-datei-
 * schreibenden Aufruf von `ContentWriter::write()` in
 * `ContentVersionController::publish()`.
 *
 * Eine Lektionsfeld-Aenderung (kein `quiz`-Schluessel im Payload) geht seit
 * ADR 0101/0102 direkt in die DB (`LessonContentPublisher`), ein
 * Quiz-Entwurf (`quiz`-Schluessel -- weiterhin an seine Lektion gebunden,
 * ADR 0097) seit ADR 0104 ueber `QuizContentPublisher`, ein Node-Entwurf seit
 * ADR 0108 (CMS-6d Teil 2) ueber `NodeContentPublisher` -- `content/` wird in
 * allen drei Faellen nicht mehr angefasst. Jeder andere Fall (Pruefung,
 * Achievement) nutzt weiterhin `ContentWriter`, bis auch deren Fliesstext
 * DB-gefuehrt ist (CMS-8).
 */
final readonly class ActivityContentApplier
{
    public function __construct(
        private ActivityRegistry $registry,
        private ContentWriter $writer,
        private LessonContentPublisher $lessonPublisher,
        private QuizContentPublisher $quizPublisher,
        private NodeContentPublisher $nodePublisher,
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

        if ($activityModel->type === ActivityType::Lesson->value) {
            if (isset($payload['quiz'])) {
                $this->quizPublisher->publish($activityModel, $payload);
            } else {
                $this->lessonPublisher->publish($activityModel, $payload);
            }

            return [];
        }

        if ($activityModel->type === ActivityType::Node->value) {
            $this->nodePublisher->publish($activityModel, $payload);

            return [];
        }

        return $this->writer->write($activity, $payload);
    }
}

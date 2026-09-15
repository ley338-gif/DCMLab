<?php

namespace App\Activities;

use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\User;

/**
 * Schreibt `activity_progress` fort, wenn ein Nutzer eine Aktivitaet
 * abschliesst (ADR 0076, Vorstufe zu W4). Nutzt bewusst
 * ActivityContract::result() statt eigener Punkte-/Skill-Logik, damit es
 * nie zwei Quellen fuer "was bedeutet Abschluss bei diesem Typ" gibt --
 * dieselbe Methode, die schon `ActivityRegistryTest` und die einzelnen
 * *ActivityTest-Klassen decken.
 *
 * Ruft `Activity::query()->where('type', ...)->where('key', ...)` auf
 * statt eine Aktivitaet direkt durchzureichen, weil die aufrufenden
 * Controller (NodeController, LessonController, ExamAttemptService) heute
 * `Lesson`/`Node`/`Track`-Modelle kennen, nicht `Activity` -- und weil ein
 * fehlender Eintrag (content:sync noch nicht gelaufen, z. B. in vielen
 * bestehenden Tests) kein Fehler sein darf, nur ein Verzicht auf das
 * Schreiben.
 */
final class ActivityProgressRecorder
{
    public function __construct(
        private readonly ActivityRegistry $registry,
    ) {}

    public function record(string $type, string $key, User $user): void
    {
        $activity = Activity::query()->where('type', $type)->where('key', $key)->first();

        if ($activity === null) {
            return;
        }

        $result = $this->registry->resolve($activity)->result($user);

        if ($result === null) {
            return;
        }

        ActivityProgress::updateOrCreate(
            ['user_id' => $user->id, 'activity_id' => $activity->id],
            [
                'completed' => $result->completed,
                'score' => $result->score,
                'max_score' => $result->maxScore,
                'skills' => $result->skills,
                'completed_at' => $result->completedAt,
            ],
        );
    }
}

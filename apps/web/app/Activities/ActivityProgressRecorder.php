<?php

namespace App\Activities;

use App\Achievements\AchievementUnlockEvaluator;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\User;

/**
 * Schreibt `activity_progress` fort, wenn ein Nutzer eine Aktivitaet
 * abschliesst (ADR 0076, Vorstufe zu W4), und wertet danach deklarative
 * Achievement-Kriterien dagegen aus (ADR 0077, W4). Nutzt bewusst
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
 *
 * `persistProgress()`/`evaluateAchievements()` (CMS-8d-Korrektur): in
 * `record()` unveraendert nacheinander aufgerufen, aber als zwei oeffentliche
 * Methoden auch einzeln nutzbar -- noetig, weil `AchievementService::
 * unlock()` einen Unique-Constraint-Verstoss zwar per PHP-`try`/`catch`
 * abfaengt, das unter PostgreSQL eine bereits laufende Transaktion aber
 * NICHT rettet: ohne expliziten `SAVEPOINT` bleibt sie nach einem
 * Constraint-Fehler bis zum Ende "aborted", selbst wenn der PHP-Fehler
 * lokal behandelt wurde -- der naechste COMMIT wuerde dann auch bereits
 * erfolgreich gespeicherte Aenderungen (z. B. einen `LabAttempt::save()`
 * kurz zuvor) verwerfen. Ein Aufrufer mit einer eigenen, schreibenden
 * `DB::transaction()` (insbesondere mit `lockForUpdate()`, wie
 * `LabController::exec()`) ruft deshalb NUR `persistProgress()` innerhalb
 * dieser Transaktion auf und `evaluateAchievements()` erst danach, nach dem
 * Commit.
 */
final class ActivityProgressRecorder
{
    public function __construct(
        private readonly ActivityRegistry $registry,
        private readonly AchievementUnlockEvaluator $achievements,
    ) {}

    /**
     * @return list<array<string, mixed>> neu freigeschaltete Achievements, fertig fuers Frontend
     */
    public function record(string $type, string $key, User $user): array
    {
        $context = $this->persistProgress($type, $key, $user);

        if ($context === null) {
            return [];
        }

        return $this->evaluateAchievements($context);
    }

    /**
     * Nur der `activity_progress`-Schreibpfad, bewusst OHNE die
     * Achievement-Auswertung darunter -- siehe Klassendoc.
     */
    public function persistProgress(string $type, string $key, User $user): ?ActivityProgressContext
    {
        $activity = Activity::query()->where('type', $type)->where('key', $key)->first();

        if ($activity === null) {
            return null;
        }

        $result = $this->registry->resolve($activity)->result($user);

        if ($result === null) {
            return null;
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

        return new ActivityProgressContext($activity, $user, $result);
    }

    /**
     * @return list<array<string, mixed>> neu freigeschaltete Achievements, fertig fuers Frontend
     */
    public function evaluateAchievements(ActivityProgressContext $context): array
    {
        return $this->achievements->evaluate($context->activity, $context->user, $context->result);
    }
}

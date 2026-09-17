<?php

namespace App\Activities;

use App\Models\Activity;
use App\Models\User;

/**
 * Trägt das Ergebnis eines `ActivityProgressRecorder::persistProgress()`-
 * Aufrufs zu einem späteren `ActivityProgressRecorder::evaluateAchievements()`
 * -- siehe `ActivityProgressRecorder`-Klassendoc: unter PostgreSQL bleibt
 * eine Transaktion nach einem Unique-Constraint-Fehler ohne SAVEPOINT
 * "aborted", ein reines PHP-`try`/`catch` (`AchievementService::unlock()`)
 * repariert das nicht. Ein Aufrufer mit einer eigenen, schreibenden
 * `DB::transaction()` (z. B. mit `lockForUpdate()`, wie
 * `LabController::exec()`) muss die Achievement-Auswertung deshalb erst
 * NACH dem Commit dieser Transaktion anstoßen.
 */
final readonly class ActivityProgressContext
{
    public function __construct(
        public Activity $activity,
        public User $user,
        public ActivityResult $result,
    ) {}
}

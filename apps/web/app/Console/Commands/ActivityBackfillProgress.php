<?php

namespace App\Console\Commands;

use App\Activities\ActivityProgressRecorder;
use App\Models\ExamAttempt;
use App\Models\LessonProgress;
use App\Models\NodeAttempt;
use Illuminate\Console\Command;

/**
 * Fuellt `activity_progress` aus dem bestehenden Bestand
 * (`node_attempts`, `lesson_progress`, `exam_attempts`) fuer alle Nutzer, die
 * vor dieser Aenderung schon gespielt haben (ADR 0076). Rueckwaerts
 * lauffaehig im Sinn von ADR 0071 Abschnitt 7 ("W0 und W4 enthalten
 * Datenmigrationen ... rueckwaerts lauffaehig"): reine Additivmigration
 * ueber `updateOrCreate`, `activity_progress` ist eine neue Tabelle, ein
 * Zuruecksetzen ist ein einfaches `php artisan migrate:rollback` auf ihre
 * Migration, ohne dass ein Bestandssystem etwas davon liest.
 *
 * Idempotent: erneutes Ausfuehren ueberschreibt bestehende Zeilen nur mit
 * demselben, aus ActivityContract::result() berechneten Stand.
 */
class ActivityBackfillProgress extends Command
{
    protected $signature = 'activity:backfill-progress';

    protected $description = 'Fuellt activity_progress aus node_attempts/lesson_progress/exam_attempts (ADR 0076)';

    public function handle(ActivityProgressRecorder $recorder): int
    {
        $nodes = 0;
        NodeAttempt::query()->where('status', 'solved')->with(['user', 'node'])->chunkById(200, function ($attempts) use ($recorder, &$nodes) {
            foreach ($attempts as $attempt) {
                $recorder->record('node', $attempt->node->slug, $attempt->user);
                $nodes++;
            }
        });

        $lessons = 0;
        LessonProgress::query()->with(['user', 'lesson'])->chunkById(200, function ($progresses) use ($recorder, &$lessons) {
            foreach ($progresses as $progress) {
                $recorder->record('lesson', $progress->lesson->lesson_id, $progress->user);
                $lessons++;
            }
        });

        $exams = 0;
        ExamAttempt::query()->where('status', 'completed')->with(['user', 'track'])->chunkById(200, function ($attempts) use ($recorder, &$exams) {
            foreach ($attempts as $attempt) {
                $recorder->record('exam', $attempt->track->slug, $attempt->user);
                $exams++;
            }
        });

        $this->info("activity:backfill-progress — {$nodes} Node-Ergebnisse, {$lessons} Lektions-Fortschritte, {$exams} Pruefungsergebnisse uebertragen.");

        return self::SUCCESS;
    }
}

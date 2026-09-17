<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\Lesson;
use App\Models\LessonElement;
use App\Models\LessonProgress;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Verdichtet echte Fortschritts-/Lab-Daten zu den drei "Was jetzt?"-Bloecken
 * der Dashboard-Homebase (Weiterlernen, Deine Labs, Empfohlen als Naechstes)
 * -- bewusst ein eigener Service statt Controller-Logik, nach demselben
 * Zuschnitt wie ProfileService/ExamAttemptService/AchievementService. Kein
 * KI-Recommendation-System: alle Regeln sind deterministisch und rechnen
 * ausschliesslich gegen bereits vorhandene Tabellen (LessonProgress,
 * ActivityProgress/LabAttempt) -- keine neue Datenquelle, kein Mocking.
 */
class DashboardHomeService
{
    /**
     * "Weiterlernen": die zuletzt beruehrte Lektion, oder -- falls diese
     * bereits abgeschlossen ist -- die naechste offene Lektion desselben
     * Tracks. Liefert null, wenn der Nutzer noch nie eine Lektion begonnen
     * hat ODER der zuletzt beruehrte Track bereits vollstaendig
     * abgeschlossen ist (beides zeigt die Oberflaeche als denselben
     * Empty-State "Starte deinen ersten Track").
     *
     * @return array{track_slug: string, track_title_key: string, track_title: array<string,string>|null, lesson_id: string, lesson_title: string, lesson_order: int, position: int, lessons_count: int, completed_lessons_count: int}|null
     */
    public function continueLearning(User $user, ?LessonProgress $mostRecentProgress): ?array
    {
        if ($mostRecentProgress === null) {
            return null;
        }

        $lesson = $mostRecentProgress->lesson;
        $track = $lesson->track;

        $targetLesson = $mostRecentProgress->status === 'completed'
            ? $this->nextIncompleteLesson($user, $track, $lesson->order)
            : $lesson;

        if ($targetLesson === null) {
            return null;
        }

        $lessonsCount = $track->lessons()->count();
        $completedCount = $track->lessons()
            ->whereHas('progress', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('status', 'completed'),
            )
            ->count();

        return [
            'track_slug' => $track->slug,
            'track_title_key' => $track->title_key,
            'track_title' => $track->title,
            'lesson_id' => $targetLesson->lesson_id,
            'lesson_title' => $targetLesson->title['de'] ?? $targetLesson->lesson_id,
            'lesson_order' => $targetLesson->order,
            'position' => $targetLesson->order + 1,
            'lessons_count' => $lessonsCount,
            'completed_lessons_count' => $completedCount,
        ];
    }

    /**
     * @return list<array{slug: string, title: string, difficulty: string, estimated_minutes: int, status: string, lesson_id: string|null, track_slug: string|null}>
     */
    public function labsOverview(User $user): array
    {
        $labs = Lab::query()->where('status', 'published')->orderBy('id')->get();

        if ($labs->isEmpty()) {
            return [];
        }

        $activitiesBySlug = Activity::query()
            ->where('type', 'lab')
            ->whereIn('key', $labs->pluck('slug'))
            ->get()
            ->keyBy('key');

        $activityIds = $activitiesBySlug->pluck('id');

        $elementByActivityId = LessonElement::query()
            ->where('type', 'activity')
            ->whereIn('activity_id', $activityIds)
            ->with('lesson:id,lesson_id,track_id')
            ->get()
            ->keyBy('activity_id');

        $attemptsByActivityId = LabAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('activity_id', $activityIds)
            ->get()
            ->keyBy('activity_id');

        $trackSlugById = Track::query()->pluck('slug', 'id');

        $result = [];

        foreach ($labs as $lab) {
            $activity = $activitiesBySlug->get($lab->slug);

            if ($activity === null) {
                continue;
            }

            $lesson = $elementByActivityId->get($activity->id)?->lesson;
            $attempt = $attemptsByActivityId->get($activity->id);
            $trackSlug = $lesson === null ? null : $trackSlugById->get($lesson->track_id);

            $result[] = [
                'slug' => $lab->slug,
                'title' => $lab->title['de'] ?? $lab->slug,
                'difficulty' => $lab->difficulty,
                'estimated_minutes' => $lab->estimated_minutes,
                // LabAttempt kennt nur started|solved (siehe LabController)
                // -- fehlender Attempt heisst schlicht "noch nicht
                // begonnen", kein eigener DB-Zustand.
                'status' => match ($attempt?->status) {
                    'solved' => 'solved',
                    'started' => 'in_progress',
                    default => 'not_started',
                },
                'lesson_id' => $lesson?->lesson_id,
                'track_slug' => is_string($trackSlug) ? $trackSlug : null,
            ];
        }

        return $result;
    }

    /**
     * Deterministische Empfehlung, keine KI: die erste zutreffende Regel
     * gewinnt. Bewusst kein Duplikat von "Weiterlernen" -- schlaegt
     * entweder ein zum aktuellen Fortschritt passendes Lab vor, den
     * naechsten Schritt danach, den naechsten unbegonnenen Track, oder
     * (ganz am Anfang) einen Einstiegstrack.
     *
     * Der `reason_code` beschreibt nur den WARUM-Grund, keinen fertigen Satz
     * -- die Uebersetzung (inkl. Platzhalter wie dem Tracknamen) passiert
     * wie ueberall sonst in der Vue-Schicht per `trans()`, nicht hier
     * (sonst muesste dieser Service deutsche Saetze zusammenbauen, die an
     * `lang/de.json` vorbeilaufen).
     *
     * `title`/`title_key` folgen bewusst demselben Muster wie ueberall sonst
     * (siehe Tracks/Index.vue `trackTitle()`): fuer Lab/Lesson ist `title`
     * bereits ein echter, aufgeloester Anzeigetext; fuer einen Track kann er
     * fehlen (per `content:sync` verwaltete Tracks haben nur `title_key`,
     * einen `lang/de.json`-Schluessel) -- dann muss die Vue-Seite wie
     * ueberall sonst per `trans(title_key)` aufloesen, nicht dieser Service.
     *
     * @param  Collection<int, Track>  $trackModels  bereits geladen mit lessons_count/completed_lessons_count (siehe DashboardController)
     * @param  array{track_slug: string, track_title_key: string, track_title: array<string,string>|null, lesson_id: string, lesson_title: string, lesson_order: int, position: int, lessons_count: int, completed_lessons_count: int}|null  $continueLearning
     * @param  list<array{slug: string, title: string, difficulty: string, estimated_minutes: int, status: string, lesson_id: string|null, track_slug: string|null}>  $labsOverview
     * @return array{type: 'lab'|'lesson'|'track', reason_code: 'fits_current_track'|'prerequisites_met'|'track_completed'|'beginner_recommendation', title: ?string, title_key: ?string, slug: ?string, lesson_id: ?string, completed_track_title: ?string, completed_track_title_key: ?string}|null
     */
    public function recommendedNext(User $user, Collection $trackModels, ?array $continueLearning, array $labsOverview): ?array
    {
        $currentTrackSlug = $continueLearning['track_slug'] ?? null;

        // Regel 1: ein Lab, dessen Lektion bereits abgeschlossen ist und das
        // selbst noch nicht geloest wurde -- bevorzugt im aktuellen Track.
        $labCandidates = collect($labsOverview)
            ->filter(fn (array $lab) => $lab['status'] !== 'solved' && $lab['lesson_id'] !== null)
            ->filter(fn (array $lab) => LessonProgress::query()
                ->where('user_id', $user->id)
                ->whereHas('lesson', fn ($q) => $q->where('lesson_id', $lab['lesson_id']))
                ->where('status', 'completed')
                ->exists(),
            );

        $labCandidate = ($currentTrackSlug !== null ? $labCandidates->firstWhere('track_slug', $currentTrackSlug) : null)
            ?? $labCandidates->first();

        if ($labCandidate !== null) {
            return [
                'type' => 'lab',
                'reason_code' => $currentTrackSlug !== null && $labCandidate['track_slug'] === $currentTrackSlug
                    ? 'fits_current_track'
                    : 'prerequisites_met',
                'title' => $labCandidate['title'],
                'title_key' => null,
                'slug' => $labCandidate['slug'],
                'lesson_id' => null,
                'completed_track_title' => null,
                'completed_track_title_key' => null,
            ];
        }

        // Regel 2: ein Schritt weiter als "Weiterlernen" im selben Track.
        if ($continueLearning !== null) {
            $track = $trackModels->firstWhere('slug', $currentTrackSlug);

            if ($track !== null) {
                $nextLesson = $this->nextIncompleteLesson($user, $track, $continueLearning['lesson_order']);

                if ($nextLesson !== null) {
                    return [
                        'type' => 'lesson',
                        'reason_code' => 'fits_current_track',
                        'title' => $nextLesson->title['de'] ?? $nextLesson->lesson_id,
                        'title_key' => null,
                        'slug' => null,
                        'lesson_id' => $nextLesson->lesson_id,
                        'completed_track_title' => null,
                        'completed_track_title_key' => null,
                    ];
                }
            }
        }

        // Regel 3: der aktuelle Track ist fertig (oder es gab nie einen) --
        // der naechste, noch nicht begonnene, veroeffentlichte Track.
        $nextTrack = $trackModels->first(fn (Track $track) => $track->completed_lessons_count === 0
            && $track->lessons_count > 0
            && $track->slug !== $currentTrackSlug,
        );

        if ($nextTrack === null) {
            return null;
        }

        return [
            'type' => 'track',
            'reason_code' => $continueLearning !== null ? 'track_completed' : 'beginner_recommendation',
            'title' => $nextTrack->title['de'] ?? null,
            'title_key' => $nextTrack->title !== null ? null : $nextTrack->title_key,
            'slug' => $nextTrack->slug,
            'lesson_id' => null,
            'completed_track_title' => $continueLearning['track_title']['de'] ?? null,
            'completed_track_title_key' => isset($continueLearning['track_title']['de']) ? null : ($continueLearning['track_title_key'] ?? null),
        ];
    }

    /**
     * Naechste veroeffentlichte, fuer diesen Nutzer noch offene Lektion
     * nach `$afterOrder` im selben Track -- "offen" meint hier nur "noch
     * nicht abgeschlossen" (dieselbe weiche Semantik wie
     * LessonPrerequisiteService, kein Zugriffsschutz).
     */
    private function nextIncompleteLesson(User $user, Track $track, int $afterOrder): ?Lesson
    {
        return $track->lessons()
            ->where('order', '>', $afterOrder)
            ->where('status', 'published')
            ->whereDoesntHave('progress', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('status', 'completed'),
            )
            ->orderBy('order')
            ->first();
    }
}

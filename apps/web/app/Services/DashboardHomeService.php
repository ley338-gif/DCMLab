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
use Illuminate\Support\Facades\Gate;

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

        // Published Content Boundary Hardening: dieselbe Fortschrittszaehler-
        // Regel wie ueberall sonst (Dashboard-Trackliste, Sidebar) -- eine
        // Draft-Lesson darf weder mitgezaehlt noch, ueber historischen
        // Fortschritt, faelschlich als erledigt gezaehlt werden.
        $lessonsCount = $track->lessons()->where('status', 'published')->count();
        $completedCount = $track->lessons()
            ->where('status', 'published')
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
     * `$user === null` (Gast auf der oeffentlichen /de/labs-Uebersicht, siehe
     * LabController::index()) liefert dieselben Labs, aber jedes ohne
     * Attempt -- also immer `status: 'not_started'`, wie bei
     * NodeController::index() fuer Gaeste.
     *
     * @return list<array{slug: string, title: string, scenario_title: string, difficulty: string, points: int, estimated_minutes: int, status: string, lesson_id: string|null, track_slug: string|null}>
     */
    public function labsOverview(?User $user): array
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

        // Published Content Boundary Hardening: `status` muss mitgeladen
        // werden -- visibleLesson() braucht die Spalte fuer
        // LessonPolicy::view()/isPublished(), der bisherige partielle
        // Select hatte nur id/lesson_id/track_id.
        $elementByActivityId = LessonElement::query()
            ->where('type', 'activity')
            ->whereIn('activity_id', $activityIds)
            ->with('lesson:id,lesson_id,track_id,status')
            ->get()
            ->keyBy('activity_id');

        $attemptsByActivityId = $user === null
            ? collect()
            : LabAttempt::query()
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

            // Published Content Boundary Hardening: eine fuer diesen
            // Betrachter nicht sichtbare Lesson wird komplett wie "keine
            // Verknuepfung" behandelt (lesson_id UND track_slug null) --
            // kein Platzhalter, ein Lab kann auch eigenstaendig ohne
            // Lesson-Bezug im Katalog stehen.
            $lesson = $this->visibleLesson($elementByActivityId->get($activity->id)?->lesson, $user);
            $attempt = $attemptsByActivityId->get($activity->id);
            $trackSlug = $lesson === null ? null : $trackSlugById->get($lesson->track_id);

            $result[] = [
                'slug' => $lab->slug,
                'title' => $lab->title['de'] ?? $lab->slug,
                'scenario_title' => $lab->scenario_title['de'] ?? '',
                'difficulty' => $lab->difficulty,
                'points' => $lab->points,
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
     * Der fachlich passende Rueckweg von einem Lab (PR #148, Prioritaet 1:
     * "Labs/Show darf kein Dead-End mehr sein") -- dieselbe Lesson-/Track-
     * Aufloesung wie `labsOverview()` (ueber `LessonElement` mit
     * `type=activity`), hier fuer genau EIN Lab statt gebuendelt ueber
     * alle. `LabController::show()` berechnet das immer mit, zeigt es aber
     * nur im Abschluss-Bereich eines geloesten Attempts.
     *
     * Nur zwei tatsaechlich erreichbare Faelle im heutigen Schema: eine
     * Lesson ist bekannt (dann zwangslaeufig auch deren Track, aber die
     * Lesson selbst ist der naeherliegende Rueckweg), oder gar keine (ein
     * frei im Katalog stehendes Lab, siehe PR #147) -- ein Track ganz ohne
     * Lesson kann `labsOverview()`s eigene Aufloesung nicht liefern (der
     * Track-Slug kommt dort selbst nur ueber `$lesson->track_id`). Der
     * Katalog-Fallback deckt diesen Fall wie vorgegeben ab.
     *
     * Published Content Boundary Hardening: `$user` ist der anfragende
     * Nutzer (vom Aufrufer uebergeben, kein `Auth::user()` innerhalb des
     * Service) -- `LabController::show()` ist ausschliesslich fuer
     * angemeldete Nutzer erreichbar (Route hinter `auth`-Middleware), daher
     * hier bewusst nicht-nullable statt der `?User` von `labsOverview()`.
     *
     * @return array{type: 'lesson'|'labs_index', lesson_id: string|null, lesson_title: string|null}
     */
    public function nextStepAfterLab(Activity $activity, User $user): array
    {
        $lesson = LessonElement::query()
            ->where('type', 'activity')
            ->where('activity_id', $activity->id)
            ->with('lesson')
            ->first()
            ?->lesson;

        // Eine fuer diesen Nutzer nicht sichtbare verknuepfte Lesson faellt
        // auf denselben Katalog-Fallback zurueck wie ein Lab ganz ohne
        // Lesson-Bezug -- kein Draft-Titel/-Link nach einem geloesten Lab.
        $lesson = $this->visibleLesson($lesson, $user);

        if ($lesson !== null) {
            return [
                'type' => 'lesson',
                'lesson_id' => $lesson->lesson_id,
                'lesson_title' => $lesson->title['de'] ?? $lesson->lesson_id,
            ];
        }

        return [
            'type' => 'labs_index',
            'lesson_id' => null,
            'lesson_title' => null,
        ];
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
     * @param  list<array{slug: string, title: string, scenario_title: string, difficulty: string, points: int, estimated_minutes: int, status: string, lesson_id: string|null, track_slug: string|null}>  $labsOverview
     * @return array{type: 'lab'|'lesson'|'track', reason_code: 'fits_current_track'|'prerequisites_met'|'track_completed'|'beginner_recommendation', title: ?string, title_key: ?string, slug: ?string, lesson_id: ?string, completed_track_title: ?string, completed_track_title_key: ?string}|null
     */
    public function recommendedNext(User $user, Collection $trackModels, ?array $continueLearning, array $labsOverview): ?array
    {
        $currentTrackSlug = $continueLearning['track_slug'] ?? null;

        // Regel 1: ein Lab, dessen Lektion bereits abgeschlossen ist und das
        // selbst noch nicht geloest wurde -- bevorzugt im aktuellen Track.
        // Published Content Boundary Hardening: `lesson_id !== null` allein
        // reicht bereits als Sichtbarkeitsfilter, weil `$labsOverview` von
        // `labsOverview($user)` mit demselben `$user` stammt (siehe
        // DashboardController::index()) und `visibleLesson()` dort jede
        // fuer diesen Nutzer unsichtbare Verknuepfung bereits auf `null`
        // gesetzt hat -- historischer Fortschritt auf einer inzwischen
        // unveroeffentlichten Lesson kann eine solche Zeile also gar nicht
        // erst erreichen. Keine zweite Policy-Pruefung hier, um keine
        // zusaetzliche Abfrage pro Kandidat zu erzeugen.
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
     * Published Content Boundary Hardening: zentrale Sichtbarkeitsregel
     * fuer jede Lab-zu-Lesson-Verknuepfung (Katalog, Empfehlung, Abschluss-
     * Rueckweg) -- eine Stelle statt dreimal dieselbe Regel unterschiedlich
     * erfunden, analog LearnerViewBuilder::toolbarData()/
     * LessonNavigationService. Fuer einen Gast (`$user === null`) gilt
     * ausschliesslich der Veroeffentlichungsstatus -- LessonPolicy::view()
     * erwartet einen echten Nutzer und wird fuer Gaeste nie aufgerufen. Fuer
     * einen angemeldeten Nutzer entscheidet `Gate::forUser($user)`
     * (nutzergebunden, nicht der ambiente globale Gate-Kontext) ueber
     * dieselbe Policy wie ueberall sonst -- ein normaler Lernender sieht
     * damit nur Veroeffentlichtes, ein zugewiesener Autor/Reviewer/
     * Administrator zusaetzlich seine eigenen sichtbaren Entwuerfe.
     */
    private function visibleLesson(?Lesson $lesson, ?User $user): ?Lesson
    {
        if ($lesson === null) {
            return null;
        }

        if ($user === null) {
            return $lesson->isPublished() ? $lesson : null;
        }

        return Gate::forUser($user)->allows('view', $lesson) ? $lesson : null;
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

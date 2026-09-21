<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Liefert die Navigationsdaten fuer die linke Sidebar der Lesson-Ansicht:
 * den aktuellen Track mit Lektionsstatus, die uebrigen veroeffentlichten
 * Tracks nur als Zaehler (kein Full-Lesson-Payload je Seitenaufruf) und den
 * Gesamtfortschritt ueber alle veroeffentlichten Tracks -- dieselbe
 * Aggregations-Idee wie DashboardController::index, hier aber je Lektion
 * statt je Track gebraucht.
 */
final class LessonNavigationService
{
    /**
     * @return array{
     *     current_track: array{slug: string, title_key: string, lessons: array<int, array{lesson_id: string, title: string, order: int, status: string}>},
     *     other_tracks: array<int, array{slug: string, title_key: string, lessons_count: int, completed_lessons_count: int}>,
     *     overall: array{completed: int, total: int},
     * }
     */
    public function sidebarFor(User $user, Lesson $lesson): array
    {
        $currentTrack = $lesson->track;

        $completedLessonIds = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->pluck('lesson_id')
            ->all();

        // Published Content Boundary Hardening: eine Draft-/Review-/
        // archivierte Geschwister-Lektion darf hier weder Titel noch einen
        // echten Navigationslink preisgeben -- ein zugewiesener Autor/
        // Reviewer/Administrator sieht ueber LessonPolicy::view() trotzdem
        // seine eigenen sichtbaren Entwuerfe (die aktuell betrachtete
        // Lektion `$lesson` ist dabei immer sichtbar, sonst waere der
        // Aufrufer schon vorher an LessonPolicy::view() gescheitert).
        $currentTrackLessons = $currentTrack->lessons()
            ->get()
            ->filter(fn (Lesson $trackLesson) => Gate::forUser($user)->allows('view', $trackLesson))
            ->map(fn (Lesson $trackLesson) => [
                'lesson_id' => $trackLesson->lesson_id,
                'title' => $trackLesson->title['de'] ?? $trackLesson->lesson_id,
                'order' => $trackLesson->order,
                'status' => match (true) {
                    in_array($trackLesson->id, $completedLessonIds, true) => 'completed',
                    $trackLesson->id === $lesson->id => 'current',
                    default => 'todo',
                },
            ])
            ->values()
            ->all();

        // Dieselbe Regel fuer die Fortschrittszaehler der uebrigen Tracks:
        // eine Draft-Lesson darf weder den Nenner (lessons_count) noch den
        // Zaehler (completed_lessons_count) beeinflussen.
        $otherTracks = Track::query()
            ->where('status', 'published')
            ->where('id', '!=', $currentTrack->id)
            ->withCount(['lessons' => fn ($query) => $query->where('status', 'published')])
            ->withCount(['lessons as completed_lessons_count' => fn ($query) => $query
                ->where('status', 'published')
                ->whereHas('progress', fn ($progressQuery) => $progressQuery
                    ->where('user_id', $user->id)
                    ->where('status', 'completed'),
                ),
            ])
            ->orderBy('order')
            ->get()
            ->map(fn (Track $track) => [
                'slug' => $track->slug,
                'title_key' => $track->title_key,
                'lessons_count' => $track->lessons_count,
                'completed_lessons_count' => $track->completed_lessons_count,
            ])
            ->values()
            ->all();

        $totalLessons = Lesson::query()
            ->where('status', 'published')
            ->whereHas('track', fn ($query) => $query->where('status', 'published'))
            ->count();

        // `$completedLessonIds` oben ist bewusst status-unabhaengig (dient
        // nur der Anzeige innerhalb des bereits gefilterten aktuellen
        // Tracks) -- der globale Gesamtfortschritt braucht dagegen einen
        // eigenen, symmetrisch zu `$totalLessons` gefilterten Zaehler,
        // sonst wuerde historischer Fortschritt auf einer inzwischen
        // unveroeffentlichten Lesson den Prozentwert verfaelschen.
        $overallCompleted = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereHas('lesson', fn ($query) => $query
                ->where('status', 'published')
                ->whereHas('track', fn ($trackQuery) => $trackQuery->where('status', 'published')),
            )
            ->count();

        return [
            'current_track' => [
                'slug' => $currentTrack->slug,
                'title_key' => $currentTrack->title_key,
                'lessons' => $currentTrackLessons,
            ],
            'other_tracks' => $otherTracks,
            'overall' => [
                'completed' => $overallCompleted,
                'total' => $totalLessons,
            ],
        ];
    }
}

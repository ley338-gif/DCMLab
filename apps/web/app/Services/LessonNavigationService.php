<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Track;
use App\Models\User;

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

        $currentTrackLessons = $currentTrack->lessons()
            ->get()
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

        $otherTracks = Track::query()
            ->where('status', 'published')
            ->where('id', '!=', $currentTrack->id)
            ->withCount('lessons')
            ->withCount(['lessons as completed_lessons_count' => fn ($query) => $query
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
            ->whereHas('track', fn ($query) => $query->where('status', 'published'))
            ->count();

        return [
            'current_track' => [
                'slug' => $currentTrack->slug,
                'title_key' => $currentTrack->title_key,
                'lessons' => $currentTrackLessons,
            ],
            'other_tracks' => $otherTracks,
            'overall' => [
                'completed' => count($completedLessonIds),
                'total' => $totalLessons,
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Content\ContentRepository;
use App\Models\ExamAttempt;
use App\Models\Track;
use App\Models\TrackBadge;
use Inertia\Inertia;
use Inertia\Response;

class TrackController extends Controller
{
    /**
     * Zeigt die Trackuebersicht -- die Startseite fuer angemeldete wie
     * unangemeldete Besucher (Abschnitt 10, P2).
     */
    public function index(): Response
    {
        $tracks = Track::query()
            ->withCount('lessons')
            ->orderBy('order')
            ->get()
            ->map(fn (Track $track) => [
                'slug' => $track->slug,
                'title_key' => $track->title_key,
                'level' => $track->level,
                'hours' => $track->hours,
                'status' => $track->status,
                'lessons_count' => $track->lessons_count,
            ]);

        return Inertia::render('Tracks/Index', [
            'tracks' => $tracks,
        ]);
    }

    /**
     * Zeigt die Lektionsliste eines Tracks.
     */
    public function show(Track $track, ContentRepository $content): Response
    {
        $lessons = $track->lessons()
            ->withCount(['progress as completed' => fn ($query) => $query
                ->where('user_id', auth()->id())
                ->where('status', 'completed'),
            ])
            ->get()
            ->map(fn ($lesson) => [
                'lesson_id' => $lesson->lesson_id,
                'title' => $lesson->title['de'] ?? $lesson->lesson_id,
                'teaser' => $lesson->teaser['de'] ?? '',
                'duration_minutes' => $lesson->duration_minutes,
                'level' => $lesson->level,
                'status' => $lesson->status,
                'completed' => (bool) $lesson->getAttribute('completed'),
            ]);

        $examAvailable = array_key_exists($track->slug, $content->exams());
        $allLessonsCompleted = $lessons->isNotEmpty() && $lessons->every(fn ($lesson) => $lesson['completed']);

        $inProgressAttempt = auth()->check()
            ? ExamAttempt::query()
                ->where('user_id', auth()->id())
                ->where('track_id', $track->id)
                ->where('status', 'in_progress')
                ->first()
            : null;

        $hasPassed = auth()->check()
            ? TrackBadge::query()->where('user_id', auth()->id())->where('track_id', $track->id)->exists()
            : false;

        return Inertia::render('Tracks/Show', [
            'track' => [
                'slug' => $track->slug,
                'title_key' => $track->title_key,
            ],
            'lessons' => $lessons,
            'exam' => [
                'available' => $examAvailable,
                'all_lessons_completed' => $allLessonsCompleted,
                'in_progress_attempt_id' => $inProgressAttempt?->id,
                'passed' => $hasPassed,
            ],
        ]);
    }
}

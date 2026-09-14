<?php

namespace App\Http\Controllers;

use App\Models\Track;
use App\Services\ExamAttemptService;
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
    public function show(Track $track, ExamAttemptService $exams): Response
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

        $status = $exams->statusForTracks(auth()->user(), [$track])[$track->id];

        return Inertia::render('Tracks/Show', [
            'track' => [
                'slug' => $track->slug,
                'title_key' => $track->title_key,
            ],
            'lessons' => $lessons,
            'exam' => [
                'available' => $status['exam_defined'],
                'all_lessons_completed' => $status['all_lessons_completed'],
                'in_progress_attempt_id' => $status['in_progress_attempt_id'],
                'passed' => $status['passed'],
            ],
        ]);
    }
}

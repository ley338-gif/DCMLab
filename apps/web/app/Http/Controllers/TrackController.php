<?php

namespace App\Http\Controllers;

use App\Models\Track;
use App\Services\ExamAttemptService;
use App\Services\LessonPrerequisiteService;
use Illuminate\Support\Facades\Auth;
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
            ->with('themenfeld')
            ->get()
            ->sortBy(fn (Track $track) => sprintf(
                '%02d-%s-%02d-%s',
                $this->themenfeldOrder($track),
                $this->themenfeldSlug($track),
                $track->order,
                $track->slug,
            ))
            ->values()
            ->map(fn (Track $track) => [
                'slug' => $track->slug,
                'title_key' => $track->title_key,
                'level' => $track->level,
                'hours' => $track->hours,
                'status' => $track->status,
                'lessons_count' => $track->lessons_count,
                'themenfeld' => $this->themenfeldSlug($track),
            ]);

        return Inertia::render('Tracks/Index', [
            'tracks' => $tracks,
        ]);
    }

    /**
     * Zeigt die Lektionsliste eines Tracks.
     */
    public function show(Track $track, ExamAttemptService $exams, LessonPrerequisiteService $prerequisites): Response
    {
        $trackLessons = $track->lessons()
            ->withCount(['progress as completed' => fn ($query) => $query
                ->where('user_id', auth()->id())
                ->where('status', 'completed'),
            ])
            // Fuer die optionale ✓/●/○-Anzeige (Abschnitt 3) reicht der echte
            // LessonProgress-Status (started|completed) -- kein Platzhalter.
            ->with(['progress' => fn ($query) => $query->where('user_id', auth()->id())])
            ->get();

        $unmetByLessonId = Auth::user() !== null
            ? $prerequisites->unmetForMany(Auth::user(), $trackLessons)
            : [];

        $lessons = $trackLessons->map(fn ($lesson) => [
            'lesson_id' => $lesson->lesson_id,
            'title' => $lesson->title['de'] ?? $lesson->lesson_id,
            'teaser' => $lesson->teaser['de'] ?? '',
            'duration_minutes' => $lesson->duration_minutes,
            'level' => $lesson->level,
            'status' => $lesson->status,
            'completed' => (bool) $lesson->getAttribute('completed'),
            'progress_status' => $lesson->progress->first()?->status,
            // requires ist eine fachliche Empfehlung, kein Zugriffsschutz
            // (docs/content-schema.md Abschnitt 2) -- nur fuer die Anzeige.
            'unmet_requires' => $unmetByLessonId[$lesson->lesson_id] ?? [],
        ]);

        $status = $exams->statusForTracks(auth()->user(), [$track])[$track->id];

        return Inertia::render('Tracks/Show', [
            'track' => [
                'slug' => $track->slug,
                'title_key' => $track->title_key,
                'themenfeld' => $this->themenfeldSlug($track),
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

    /**
     * Themenfeld-Slug eines Tracks, mit "dicom" als Fallback (Abschnitt 13):
     * `themenfeld_id` ist nullable (siehe Migration), praktisch aber immer
     * gesetzt, sobald content:sync gelaufen ist -- derselbe Fallback wie in
     * NodeController::themenfeldSlug().
     */
    private function themenfeldSlug(Track $track): string
    {
        return $track->themenfeld_id === null ? 'dicom' : $track->themenfeld->slug;
    }

    /**
     * @see self::themenfeldSlug() -- 1 ist dicoms tatsaechlicher Wert in
     * themenfelder.yml, deshalb derselbe Fallback wie dort.
     */
    private function themenfeldOrder(Track $track): int
    {
        return $track->themenfeld_id === null ? 1 : $track->themenfeld->order;
    }
}

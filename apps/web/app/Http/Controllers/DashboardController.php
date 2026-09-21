<?php

namespace App\Http\Controllers;

use App\Models\LessonProgress;
use App\Models\QuizReview;
use App\Models\Track;
use App\Services\AchievementService;
use App\Services\DashboardHomeService;
use App\Services\ExamAttemptService;
use App\Services\ProfileService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Zeigt die persoenliche Homebase: allen voran "Weiterlernen" (die
     * wichtigste Frage "was jetzt?"), dann Tracks, Labs, eine deterministische
     * Empfehlung und -- weiter hinten, nicht mehr oben -- Punkte/Rang/
     * Achievements. Alles aus echten Fortschrittsdaten, nichts geschaetzt
     * oder erfunden (Homebase-Umbau).
     */
    public function index(
        ProfileService $profiles,
        ExamAttemptService $exams,
        AchievementService $achievementService,
        DashboardHomeService $home,
    ): Response {
        $user = Auth::user();
        $profile = $profiles->profileFor($user);

        $trackModels = Track::query()
            ->where('status', 'published')
            // Published Content Boundary Hardening: eine Draft-Lesson darf
            // weder den Nenner noch den Zaehler der Track-Fortschrittsanzeige
            // beeinflussen (analog LessonNavigationService::sidebarFor()).
            ->withCount(['lessons' => fn ($query) => $query->where('status', 'published')])
            ->withCount(['lessons as completed_lessons_count' => fn ($query) => $query
                ->where('status', 'published')
                ->whereHas('progress', fn ($progressQuery) => $progressQuery
                    ->where('user_id', $user->id)
                    ->where('status', 'completed'),
                ),
            ])
            ->orderBy('order')
            ->get();

        $examStatus = $exams->statusForTracks($user, $trackModels);

        $tracks = $trackModels->map(fn (Track $track) => [
            'slug' => $track->slug,
            'title_key' => $track->title_key,
            'title' => $track->title,
            'lessons_count' => $track->lessons_count,
            'completed_lessons_count' => $track->completed_lessons_count,
            'exam' => [
                'available' => $examStatus[$track->id]['exam_defined'] && $examStatus[$track->id]['all_lessons_completed'],
                'defined' => $examStatus[$track->id]['exam_defined'],
                'passed' => $examStatus[$track->id]['passed'],
                'passed_at' => $examStatus[$track->id]['passed_at'],
            ],
        ]);

        // Published Content Boundary Hardening: Aktivitaetsverlauf und
        // "Weiterlernen" (ueber $recentProgress->first(), siehe unten)
        // duerfen keine inzwischen unveroeffentlichte Lesson mehr zeigen --
        // vorher haette hier ein echter Titel samt aktivem Link auf eine
        // Draft-/Review-/archivierte Lektion erscheinen koennen, sobald sie
        // die zuletzt beruehrte war.
        $recentProgress = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereHas('lesson', fn ($query) => $query->where('status', 'published'))
            ->with('lesson.track')
            ->orderByDesc('started_at')
            ->limit(5)
            ->get();

        $recentLessons = $recentProgress->map(fn (LessonProgress $progress) => [
            'lesson_id' => $progress->lesson->lesson_id,
            'title' => $progress->lesson->title['de'] ?? $progress->lesson->lesson_id,
            'status' => $progress->status,
            'started_at' => $progress->started_at->toIso8601String(),
            'completed_at' => $progress->completed_at?->toIso8601String(),
            'track_slug' => $progress->lesson->track->slug,
            'track_title_key' => $progress->lesson->track->title_key,
        ]);

        $dueReviewsCount = QuizReview::query()
            ->where('user_id', $user->id)
            ->where('due_at', '<=', now())
            ->count();

        $continueLearning = $home->continueLearning($user, $recentProgress->first());
        $labs = $home->labsOverview($user);

        return Inertia::render('Dashboard', [
            'profile' => [
                'points' => $profile->points,
                'rank' => $profile->rank,
                'skill_vector' => $profile->skill_vector,
            ],
            'tracks' => $tracks,
            'recent_lessons' => $recentLessons,
            'achievements' => $achievementService->listForUser($user)->values(),
            'due_reviews_count' => $dueReviewsCount,
            'continue_learning' => $continueLearning,
            'labs' => $labs,
            'recommended' => $home->recommendedNext($user, $trackModels, $continueLearning, $labs),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\LessonProgress;
use App\Models\QuizReview;
use App\Models\Track;
use App\Services\AchievementService;
use App\Services\ExamAttemptService;
use App\Services\ProfileService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Zeigt den eigenen Fortschritt: Punkte, Rang, Lektionen je Track,
     * zuletzt abgeschlossene Lektion und Achievements -- alles aus echten
     * Fortschrittsdaten, nichts geschaetzt oder erfunden.
     */
    public function index(ProfileService $profiles, ExamAttemptService $exams, AchievementService $achievementService): Response
    {
        $user = Auth::user();
        $profile = $profiles->profileFor($user);

        $trackModels = Track::query()
            ->where('status', 'published')
            ->withCount('lessons')
            ->withCount(['lessons as completed_lessons_count' => fn ($query) => $query
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
            'lessons_count' => $track->lessons_count,
            'completed_lessons_count' => $track->completed_lessons_count,
            'exam' => [
                'available' => $examStatus[$track->id]['exam_defined'] && $examStatus[$track->id]['all_lessons_completed'],
                'defined' => $examStatus[$track->id]['exam_defined'],
                'passed' => $examStatus[$track->id]['passed'],
                'passed_at' => $examStatus[$track->id]['passed_at'],
            ],
        ]);

        $recentLessons = LessonProgress::query()
            ->where('user_id', $user->id)
            ->with('lesson.track')
            ->orderByDesc('started_at')
            ->limit(5)
            ->get()
            ->map(fn (LessonProgress $progress) => [
                'lesson_id' => $progress->lesson->lesson_id,
                'title' => $progress->lesson->title['de'] ?? $progress->lesson->lesson_id,
                'status' => $progress->status,
                'started_at' => $progress->started_at->toIso8601String(),
                'completed_at' => $progress->completed_at?->toIso8601String(),
            ]);

        // "Pionier" (frueher "First Blood"): globaler Wettlauf um die
        // Erstloesung einer Node, unveraendertes Altsystem aus ADR 0009 --
        // bewusst getrennt von den neuen, generischen Achievements unten,
        // siehe docs/achievements.md.
        $pioneerAchievements = Achievement::query()
            ->where('user_id', $user->id)
            ->with('node')
            ->orderByDesc('awarded_at')
            ->get()
            ->map(fn (Achievement $achievement) => [
                'type' => $achievement->type,
                'node_title' => $achievement->node?->title['de'] ?? null,
                'awarded_at' => $achievement->awarded_at->toIso8601String(),
            ]);

        $dueReviewsCount = QuizReview::query()
            ->where('user_id', $user->id)
            ->where('due_at', '<=', now())
            ->count();

        return Inertia::render('Dashboard', [
            'profile' => [
                'points' => $profile->points,
                'rank' => $profile->rank,
                'skill_vector' => $profile->skill_vector,
            ],
            'tracks' => $tracks,
            'recent_lessons' => $recentLessons,
            'pioneer_achievements' => $pioneerAchievements,
            'achievements' => $achievementService->listForUser($user)->values(),
            'due_reviews_count' => $dueReviewsCount,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Activities\ActivityProgressRecorder;
use App\Content\ContentRepository;
use App\Content\MarkdownRenderer;
use App\Content\QuizContent;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Node;
use App\Models\QuizReview;
use App\Models\User;
use App\Services\LessonNavigationService;
use App\Services\LessonPrerequisiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    /**
     * Zeigt eine Lektion: gerenderte Werkzeugleiste (Abschnitt 4.4), Prosa
     * mit aufgeloesten Glossar-Begriffen, Fortschritt fuer den Nutzer.
     */
    public function show(
        Lesson $lesson,
        ContentRepository $content,
        LessonNavigationService $navigation,
        LessonPrerequisiteService $prerequisites,
    ): Response {
        $lessonContent = $content->lessons()[$lesson->lesson_id] ?? null;

        // ADR 0101 (CMS-5a): body/objectives/title/teaser bevorzugt aus der
        // DB (von content:sync gefuellt) -- ContentRepository bleibt nur
        // noch Fallback fuer eine Lektion, deren naechster Sync-Lauf noch
        // aussteht, sowie fuer das weiterhin datei-gefuehrte Quiz-Meta.
        $body = $lesson->body ?? $lessonContent['body'] ?? null;

        abort_unless($body !== null, 404);

        $tools = $content->tools();
        $datasets = $content->datasets();

        $renderer = new MarkdownRenderer($content->glossary());

        // Der "## Quiz"-Abschnitt wird nicht als Prosa mitgerendert (das
        // waere die alte, nicht-interaktive Darstellung) -- er wird
        // herausgetrennt und stattdessen strukturiert an eine eigene
        // Vue-Komponente uebergeben (Abschnitt 4.7).
        $split = QuizContent::splitBody($body);
        $bodyHtml = $renderer->render($split['before']);
        $bodyAfterQuizHtml = trim($split['after']) !== '' ? $renderer->render($split['after']) : null;

        $quizMeta = $lessonContent['meta']['quiz'] ?? [];
        $questions = QuizContent::parseQuestions($split['quiz_raw'], $quizMeta, $renderer);

        $userId = Auth::id();
        $progress = LessonProgress::firstOrNew(['user_id' => $userId, 'lesson_id' => $lesson->id]);
        $isReturningVisit = $progress->exists;

        if (! $progress->exists) {
            $progress->status = 'started';
            $progress->started_at = now();
            $progress->save();
        }

        $reviewsByQuestion = QuizReview::query()
            ->where('user_id', $userId)
            ->where('lesson_id', $lesson->id)
            ->get()
            ->keyBy('question_id');

        $quiz = collect($questions)->map(fn (array $question) => [
            ...$question,
            'last_result' => $reviewsByQuestion[$question['id']]->last_result ?? null,
        ])->values();

        $trackLessons = $lesson->track->lessons()->get();
        $positionInTrack = $trackLessons->search(fn (Lesson $candidate) => $candidate->id === $lesson->id);
        $previousLesson = $positionInTrack > 0 ? $trackLessons->get($positionInTrack - 1) : null;
        $nextLesson = $positionInTrack !== false ? $trackLessons->get($positionInTrack + 1) : null;

        return Inertia::render('Lessons/Show', [
            'lesson' => [
                'lesson_id' => $lesson->lesson_id,
                'title' => $lesson->title['de'] ?? $lessonContent['frontmatter']['title'] ?? $lesson->lesson_id,
                'teaser' => $lesson->teaser['de'] ?? $lessonContent['frontmatter']['teaser'] ?? '',
                'objectives' => $lesson->objectives ?? $lessonContent['frontmatter']['objectives'] ?? [],
                'duration_minutes' => $lesson->duration_minutes,
                'level' => $lesson->level,
                'body_html' => $bodyHtml,
                'body_after_quiz_html' => $bodyAfterQuizHtml,
                'position_in_track' => $positionInTrack !== false ? $positionInTrack + 1 : null,
                'track_lessons_count' => $trackLessons->count(),
                'prev' => $previousLesson !== null ? [
                    'lesson_id' => $previousLesson->lesson_id,
                    'title' => $previousLesson->title['de'] ?? $previousLesson->lesson_id,
                ] : null,
                'next' => $nextLesson !== null ? [
                    'lesson_id' => $nextLesson->lesson_id,
                    'title' => $nextLesson->title['de'] ?? $nextLesson->lesson_id,
                ] : null,
            ],
            'track' => [
                'slug' => $lesson->track->slug,
                'title_key' => $lesson->track->title_key,
            ],
            'quiz' => $quiz,
            'toolbar' => $this->toolbarData($lesson, $tools, $datasets, Auth::user(), $prerequisites),
            'progress' => [
                'status' => $progress->status,
                'is_returning_visit' => $isReturningVisit,
            ],
            'sidebar' => $navigation->sidebarFor(Auth::user(), $lesson),
        ]);
    }

    public function complete(Request $request, Lesson $lesson, ActivityProgressRecorder $progressRecorder): RedirectResponse
    {
        $this->setStatus($request, $lesson, 'completed', $progressRecorder);

        return back();
    }

    public function reopen(Request $request, Lesson $lesson, ActivityProgressRecorder $progressRecorder): RedirectResponse
    {
        $this->setStatus($request, $lesson, 'started', $progressRecorder);

        return back();
    }

    private function setStatus(Request $request, Lesson $lesson, string $status, ActivityProgressRecorder $progressRecorder): void
    {
        $progress = LessonProgress::firstOrNew(['user_id' => $request->user()->id, 'lesson_id' => $lesson->id]);
        $progress->status = $status;
        $progress->started_at ??= now();
        $progress->completed_at = $status === 'completed' ? now() : null;
        $progress->save();

        $progressRecorder->record('lesson', $lesson->lesson_id, $request->user());
    }

    /**
     * @param  array<string, array<string, mixed>>  $tools
     * @param  array<string, array<string, mixed>>  $datasets
     * @return array<string, mixed>
     */
    private function toolbarData(Lesson $lesson, array $tools, array $datasets, ?User $user, LessonPrerequisiteService $prerequisites): array
    {
        // "NEU" (Abschnitt 4.4): kein frueherer Eintrag im selben Track hat
        // dieses Werkzeug schon vorgestellt.
        $priorTools = Lesson::query()
            ->where('track_id', $lesson->track_id)
            ->where('order', '<', $lesson->order)
            ->pluck('tools')
            ->flatten()
            ->unique()
            ->all();

        $toolbarTools = collect($lesson->tools)->map(function (string $slug) use ($tools, $priorTools) {
            $definition = $tools[$slug] ?? null;

            return [
                'slug' => $slug,
                'name' => $definition['name'] ?? $slug,
                'purpose' => $definition['purpose'] ?? null,
                'example' => $definition['example'] ?? null,
                'is_new' => ! in_array($slug, $priorTools, true),
            ];
        })->values();

        $needsSandbox = collect($lesson->tools)
            ->contains(fn (string $slug) => (bool) ($tools[$slug]['needs_sandbox'] ?? false));

        $datasetSlug = $lesson->sandbox['dataset'] ?? null;
        $dataset = $datasetSlug !== null ? ($datasets[$datasetSlug] ?? null) : null;

        // requires ist eine fachliche Empfehlung, kein Zugriffsschutz
        // (docs/content-schema.md Abschnitt 2, "Quereinstieg") -- unmetIds
        // steuert nur die Anzeige ("gesperrt, mit Hinweis"), nie den Zugriff.
        $unmetIds = $user !== null ? array_column($prerequisites->unmetFor($user, $lesson), 'lesson_id') : [];

        $requiresLessons = collect($lesson->requires)
            ->map(fn (string $requiredId) => Lesson::where('lesson_id', $requiredId)->first())
            ->filter()
            ->map(fn (Lesson $required) => [
                'lesson_id' => $required->lesson_id,
                'title' => $required->title['de'] ?? $required->lesson_id,
                'completed' => ! in_array($required->lesson_id, $unmetIds, true),
            ])
            ->values();

        $labNode = null;
        $nodeSlug = $lesson->lab['node'] ?? null;

        if ($nodeSlug !== null) {
            $node = Node::where('slug', $nodeSlug)->first();

            if ($node !== null) {
                $labNode = [
                    'slug' => $node->slug,
                    'title' => $node->title['de'] ?? $node->slug,
                    'difficulty' => $node->difficulty,
                    'points' => $node->points,
                ];
            }
        }

        return [
            'tools' => $toolbarTools,
            'needs_sandbox' => $needsSandbox,
            'dataset' => $dataset !== null ? [
                'note' => $lesson->sandbox['note'] ?? $dataset['note'] ?? null,
                'file_count' => $dataset['file_count'] ?? null,
            ] : null,
            'requires' => $requiresLessons,
            'prerequisites_met' => $unmetIds === [],
            'lab_node' => $labNode,
            'lab_optional' => (bool) ($lesson->lab['optional'] ?? false),
        ];
    }
}

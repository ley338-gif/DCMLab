<?php

namespace App\Http\Controllers;

use App\Activities\ActivityRegistry;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Content\MarkdownRenderer;
use App\Content\QuizContent;
use App\Models\Activity;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Der erste Autoren-Editor (ADR 0071/0080, W6): eine Lektion mit
 * Kommandozeile anzulegen war nie das Problem, Quiz-Fragen ohne sie zu
 * pflegen dagegen unmoeglich -- deshalb zuerst dieser Editor
 * (`dcm-lab-lms-agent-prompt.md` Abschnitt 5, W6.1: "Größter
 * Einzelgewinn"). Live-Befunde kommen aus `LessonActivity::validate()`
 * (demselben Regelsatz wie `content:validate`), Freigabe schreibt ueber
 * `ContentWriter` tatsaechlich nach `content/`.
 */
class QuizEditorController extends Controller
{
    public function edit(Lesson $lesson, ContentRepository $content): Response
    {
        $activity = $this->activityFor($lesson);
        Gate::authorize('update', $activity);

        $pendingVersion = $activity->contentVersions()
            ->whereIn('status', ['draft', 'review'])
            ->latest()
            ->first();

        return Inertia::render('Author/QuizEditor', [
            'lesson' => [
                'lesson_id' => $lesson->lesson_id,
                'title' => $lesson->title['de'] ?? $lesson->lesson_id,
            ],
            'questions' => $pendingVersion?->payload['quiz'] ?? $this->currentQuestions($lesson, $content),
            'pending_version' => $pendingVersion === null ? null : [
                'id' => $pendingVersion->id,
                'status' => $pendingVersion->status,
            ],
            'can_publish' => Gate::allows('publish', $activity),
        ]);
    }

    public function validateDraft(Request $request, Lesson $lesson): JsonResponse
    {
        $activity = $this->activityFor($lesson);
        Gate::authorize('update', $activity);

        $questions = $this->validatedQuestions($request);
        $issues = app(ActivityRegistry::class)->resolve($activity)->validate(['quiz' => $questions]);

        return response()->json([
            'issues' => array_map(fn ($issue) => (string) $issue, $issues),
        ]);
    }

    public function storeDraft(Request $request, Lesson $lesson, ContentVersioningService $versions): RedirectResponse
    {
        $activity = $this->activityFor($lesson);
        Gate::authorize('update', $activity);

        $questions = $this->validatedQuestions($request);
        $versions->createDraft($activity, ['quiz' => $questions], $request->user());

        return back()->with('status', 'Entwurf gespeichert.');
    }

    private function activityFor(Lesson $lesson): Activity
    {
        return Activity::query()->where('type', 'lesson')->where('key', $lesson->lesson_id)->firstOrFail();
    }

    /**
     * @return list<array{id: string, type: string, answer: mixed, question: string, options: list<string>}>
     */
    private function validatedQuestions(Request $request): array
    {
        $data = $request->validate([
            'questions' => 'present|array',
            'questions.*.id' => 'required|string|regex:/^q\d+$/',
            'questions.*.type' => 'required|string|in:single,multi,input',
            'questions.*.answer' => 'required',
            'questions.*.question' => 'required|string',
            'questions.*.options' => 'array',
            'questions.*.options.*' => 'string',
        ]);

        return array_values(array_map(fn (array $question): array => [
            'id' => (string) $question['id'],
            'type' => (string) $question['type'],
            'answer' => $question['answer'],
            'question' => (string) $question['question'],
            'options' => array_values($question['options'] ?? []),
        ], $data['questions']));
    }

    /**
     * Aktueller Bestand, gelesen wie `QuizSection` es fuer Lernende tut, nur
     * mit Rohtext statt gerendertem HTML -- Optionen/Fragetext ohne
     * Markdown-Auszeichnung (der ueberwiegende Fall im echten Bestand)
     * kommen so unveraendert zurueck; mit Auszeichnung geht nur die
     * Formatierung selbst verloren, nicht der Text.
     *
     * Seit ADR 0104 (CMS-6a) bevorzugt aus der DB (`lessons.quiz`/`body`,
     * von content:sync befuellt) -- eine Quiz-Freigabe schreibt seitdem
     * nicht mehr nach `content/`, ein Datei-Ist-Zustand waere fuer eine
     * bereits so veroeffentlichte Lektion veraltet (derselbe Fund wie ADR
     * 0103 fuer den Lektions-Editor). `content/` bleibt Fallback fuer eine
     * noch nicht synchronisierte Lektion.
     *
     * @return list<array{id: string, type: string, answer: mixed, question: string, options: list<string>}>
     */
    private function currentQuestions(Lesson $lesson, ContentRepository $content): array
    {
        $renderer = new MarkdownRenderer($content->glossary());

        if ($lesson->body !== null) {
            /** @var list<array<string, mixed>> $quizMeta */
            $quizMeta = $lesson->quiz ?? [];
            $split = QuizContent::splitBody($lesson->body);

            return $this->mergeQuestionsWithMeta($quizMeta, QuizContent::parseQuestions($split['quiz_raw'], $quizMeta, $renderer));
        }

        $entry = $content->lessons()[$lesson->lesson_id] ?? null;

        if ($entry === null) {
            return [];
        }

        /** @var list<array<string, mixed>> $quizMeta */
        $quizMeta = $entry['meta']['quiz'] ?? [];
        $split = QuizContent::splitBody($entry['body'] ?? '');

        return $this->mergeQuestionsWithMeta($quizMeta, QuizContent::parseQuestions($split['quiz_raw'], $quizMeta, $renderer));
    }

    /**
     * @param  list<array<string, mixed>>  $quizMeta
     * @param  array<int, array{id: string, type: string, question_html: string, options_html: array<int, string>}>  $parsed
     * @return list<array{id: string, type: string, answer: mixed, question: string, options: list<string>}>
     */
    private function mergeQuestionsWithMeta(array $quizMeta, array $parsed): array
    {
        $parsedById = [];
        foreach ($parsed as $parsedQuestion) {
            $parsedById[$parsedQuestion['id']] = $parsedQuestion;
        }

        $questions = [];

        foreach ($quizMeta as $meta) {
            $rendered = $parsedById[(string) $meta['id']] ?? null;

            $questions[] = [
                'id' => (string) $meta['id'],
                'type' => (string) $meta['type'],
                'answer' => $meta['answer'],
                'question' => $rendered !== null ? trim(strip_tags($rendered['question_html'])) : '',
                'options' => $rendered !== null ? array_values(array_map(fn (string $o) => trim(strip_tags($o)), $rendered['options_html'])) : [],
            ];
        }

        return $questions;
    }
}

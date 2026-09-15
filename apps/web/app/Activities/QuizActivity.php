<?php

namespace App\Activities;

use App\Content\ContentRepository;
use App\Content\MarkdownRenderer;
use App\Content\QuizContent;
use App\Models\Lesson;
use App\Models\QuizReview;
use App\Models\User;

/**
 * Aktivitaetsvertrag fuer das in eine Lektion eingebettete Quiz (ADR 0072).
 * Anders als Lektion/Node/Pruefung hat ein Quiz keinen eigenen Verzeichnis-
 * eintrag in `activities` -- es ist keine eigenstaendig sortier- oder
 * platzierbare Einheit, sondern ein Ausschnitt seiner Lektion (`meta.yml`s
 * `quiz:`-Block plus `## Quiz` in `de.md`). Instanzen entstehen deshalb ueber
 * `new QuizActivity($lesson, ...)`, nicht ueber die ActivityRegistry.
 *
 * Spaced Repetition kennt keinen einmaligen "abgeschlossen"-Zustand -- jede
 * Karte kommt nach ihrem Intervall zurueck (QuizSchedulerService). `result()`
 * liefert deshalb immer `null`, siehe `supports()->tracksCompletion`.
 */
final readonly class QuizActivity implements ActivityContract
{
    public function __construct(
        private Lesson $lesson,
        private ContentRepository $content,
    ) {}

    public function activityType(): string
    {
        return ActivityType::Quiz->value;
    }

    public function key(): string
    {
        return $this->lesson->lesson_id;
    }

    public function supports(): ActivitySupports
    {
        return new ActivitySupports(
            isGraded: true,
            tracksCompletion: false,
            needsContainer: false,
            authorable: true,
            freelyPlaceable: false,
            // versionable bleibt false: dieser Vertrag hat kein eigenes
            // Dateiziel, serialize($draft) ignoriert $draft immer (siehe
            // oben) -- ein Quiz-Entwurf haengt am content_versions-Datensatz
            // der LEKTION (LessonActivity::serialize($draft['quiz'])), nicht
            // an einer eigenen Instanz. Siehe docs/studio-architecture-plan.md
            // Abschnitt 2.3 fuer die geplante Aufloesung dieser Kopplung.
        );
    }

    public function learnerView(User $user): array
    {
        $questions = $this->questions();

        $dueCount = QuizReview::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $this->lesson->id)
            ->where('due_at', '<=', now())
            ->count();

        return [
            'type' => $this->activityType(),
            'lesson_id' => $this->lesson->lesson_id,
            'question_count' => count($questions),
            'due_count' => $dueCount,
        ];
    }

    public function authorView(): array
    {
        return [
            ['key' => 'questions', 'type' => 'json', 'label' => 'Fragen (id, type, answer)', 'required' => true],
        ];
    }

    public function validate(?array $draft = null): array
    {
        // Bewusst leer, siehe LessonActivity::validate().
        return [];
    }

    public function serialize(?array $draft = null): array
    {
        // Kein eigenes Dateiziel -- das Quiz ist Teil des Lektions-Markdowns
        // und -meta.yml, siehe LessonActivity::serialize().
        return [];
    }

    public function deserialize(): array
    {
        $quizMeta = $this->quizMeta();

        return [
            'lesson_id' => $this->lesson->lesson_id,
            'questions' => $quizMeta,
            'quiz_raw' => $this->splitBody()['quiz_raw'],
        ];
    }

    public function result(User $user): ?ActivityResult
    {
        return null;
    }

    /**
     * Seit ADR 0104 (CMS-6a) bevorzugt aus der DB (von content:sync aus
     * meta.yml befuellt) -- content/ bleibt Fallback fuer eine Lektion,
     * deren naechster Sync-Lauf noch aussteht (wie schon bei
     * LessonActivity::validate(), ADR 0101).
     *
     * @return array<int, array<string, mixed>>
     */
    private function quizMeta(): array
    {
        if ($this->lesson->quiz !== null) {
            return $this->lesson->quiz;
        }

        $entry = $this->content->lessons()[$this->lesson->lesson_id] ?? null;

        return $entry['meta']['quiz'] ?? [];
    }

    /**
     * @return array{before: string, quiz_raw: string, after: string}
     */
    private function splitBody(): array
    {
        if ($this->lesson->body !== null) {
            return QuizContent::splitBody($this->lesson->body);
        }

        $entry = $this->content->lessons()[$this->lesson->lesson_id] ?? null;

        return QuizContent::splitBody($entry['body'] ?? '');
    }

    /**
     * @return array<int, array{id: string, type: string, question_html: string, options_html: array<int, string>}>
     */
    private function questions(): array
    {
        $renderer = new MarkdownRenderer($this->content->glossary());

        return QuizContent::parseQuestions($this->splitBody()['quiz_raw'], $this->quizMeta(), $renderer);
    }
}

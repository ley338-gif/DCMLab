<?php

namespace App\Content;

use App\Content\RichContent\RichContentRenderer;
use App\Models\Activity;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Node;
use App\Models\QuizReview;
use App\Models\User;
use App\Services\LessonNavigationService;
use App\Services\LessonPrerequisiteService;
use Illuminate\Support\Facades\Log;

/**
 * Baut die Inertia-Props fuer die Lernenden-Seiten (`Lessons/Show`,
 * `Nodes/Show`) -- sowohl fuer den echten, veroeffentlichten Aufruf
 * (`LessonController::show()`) als auch fuer die Draft-Vorschau eines
 * Autors (CMS-7d.3 Phase 6, ADR 0118): "echte Learner View ... dieselben
 * Vue-Komponenten wie /lessons/... oder /nodes/..., kein zweiter HTML-/
 * Markdown-Previewrenderer" (Betreiber-Vorgabe).
 *
 * `lessonProps()` nimmt bewusst ein `Lesson`-Objekt entgegen, kein Payload-
 * Array: der Live-Aufruf uebergibt die echte, aus der DB geladene Lektion;
 * die Vorschau uebergibt eine in-memory (nie gespeicherte) Kopie, deren
 * entwurfsbetroffene Felder (title/teaser/objectives/level/duration_minutes/
 * tools/requires/glossary_terms/sandbox/related_node/rich_content) auf die
 * Entwurfswerte gesetzt sind -- `body`, Track-Zugehoerigkeit, Quiz und
 * `lesson_elements` bleiben unveraendert, weil der Rich-Content-Cutover
 * (ADR 0118) diese Bereiche nicht anfasst (Quiz bleibt Sache des separaten
 * Quiz-Editors). `$trackProgress=false` liest `lesson_progress` nur lesend,
 * ohne eine neue Zeile anzulegen -- ein Autor, der einen Entwurf ansieht,
 * erzeugt dabei keinen echten Lernfortschritt fuer sich selbst.
 */
final readonly class LearnerViewBuilder
{
    public function __construct(
        private ContentRepository $content,
        private LessonNavigationService $navigation,
        private LessonPrerequisiteService $prerequisites,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function lessonProps(Lesson $lesson, User $user, bool $trackProgress): array
    {
        $lessonContent = $this->content->lessons()[$lesson->lesson_id] ?? null;
        $body = $lesson->body ?? $lessonContent['body'] ?? null;

        // CMS-7d.4 (Betreiber-Review): eine reine Rich-Content-Ressource
        // (rich_content gesetzt, body zufaellig null) ist genauso gueltig
        // -- der Existenzcheck darf sich nicht mehr allein auf die
        // (praktisch immer wahre, aber nicht erzwungene) Konvention
        // verlassen, dass `body` nie null ist.
        abort_unless($lesson->rich_content !== null || $body !== null, 404);

        $tools = $this->content->tools();
        $datasets = $this->content->datasets();
        $renderer = new MarkdownRenderer($this->content->glossary());

        // quiz_raw kommt unabhaengig von rich_content immer aus body (Quiz
        // bleibt markdown-gefuehrt, siehe ADR 0118-Kontext) -- ein leerer
        // String fuer eine reine Rich-Content-Lesson ohne body ergibt
        // korrekt "kein Quiz", statt splitBody() mit null abstuerzen zu
        // lassen.
        $split = QuizContent::splitBody($body ?? '');

        if ($lesson->rich_content === null) {
            // CMS-7d.4 (Phase 3): messbares Signal fuer den verbleibenden
            // Markdown-Fallback -- Ziel ist, dass dieser Log-Eintrag im
            // produktiven Bestand nie feuert (siehe rich-content:coverage).
            Log::warning('learner_view.legacy_body_fallback', ['activity_type' => 'lesson', 'lesson_id' => $lesson->lesson_id]);
        }

        $contentHtml = $lesson->rich_content !== null
            ? (new RichContentRenderer($this->content->glossary()))->render($lesson->rich_content)
            : $renderer->render(trim($split['before']."\n\n".$split['after']));

        $quizMeta = $lesson->quiz ?? $lessonContent['meta']['quiz'] ?? [];
        $questions = QuizContent::parseQuestions($split['quiz_raw'], $quizMeta, $renderer);

        if ($trackProgress) {
            $progress = LessonProgress::firstOrNew(['user_id' => $user->id, 'lesson_id' => $lesson->id]);
            $isReturningVisit = $progress->exists;

            if (! $progress->exists) {
                $progress->status = 'started';
                $progress->started_at = now();
                $progress->save();
            }

            $progressStatus = $progress->status;
        } else {
            // Vorschau (CMS-7d.3 Phase 6): rein lesend -- das blosse Ansehen
            // eines Entwurfs darf keinen echten Lernfortschritt fuer den
            // Autor anlegen.
            $progress = LessonProgress::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)->first();
            $isReturningVisit = $progress !== null;
            $progressStatus = $progress->status ?? 'not_started';
        }

        $reviewsByQuestion = QuizReview::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->get()
            ->keyBy('question_id');

        $quiz = array_values(collect($questions)->map(fn (array $question) => [
            ...$question,
            'last_result' => $reviewsByQuestion[$question['id']]->last_result ?? null,
        ])->all());

        $trackLessons = $lesson->track->lessons()->get();
        $positionInTrack = $trackLessons->search(fn (Lesson $candidate) => $candidate->id === $lesson->id);
        $previousLesson = $positionInTrack !== false && $positionInTrack > 0 ? $trackLessons->get($positionInTrack - 1) : null;
        $nextLesson = $positionInTrack !== false ? $trackLessons->get($positionInTrack + 1) : null;

        $toolbar = $this->toolbarData($lesson, $tools, $datasets, $user);

        return [
            'lesson' => [
                'lesson_id' => $lesson->lesson_id,
                'title' => $lesson->title['de'] ?? $lessonContent['frontmatter']['title'] ?? $lesson->lesson_id,
                'teaser' => $lesson->teaser['de'] ?? $lessonContent['frontmatter']['teaser'] ?? '',
                'objectives' => $lesson->objectives ?? $lessonContent['frontmatter']['objectives'] ?? [],
                'duration_minutes' => $lesson->duration_minutes,
                'level' => $lesson->level,
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
            'elements' => $this->elementsFor($lesson, $user, $contentHtml, $quiz, $toolbar),
            'toolbar' => [
                'tools' => $toolbar['tools'],
                'requires' => $toolbar['requires'],
                'prerequisites_met' => $toolbar['prerequisites_met'],
                'related_node_optional' => $toolbar['related_node_optional'],
            ],
            'progress' => [
                'status' => $progressStatus,
                'is_returning_visit' => $isReturningVisit,
            ],
            'sidebar' => $this->navigation->sidebarFor($user, $lesson),
        ];
    }

    /**
     * Vorschau-Props fuer eine Node (nur Draft-Vorschau, CMS-7d.3 Phase 6):
     * Briefing/Hinweise/Write-up werden VOLLSTAENDIG aus dem normalisierten
     * Entwurfspayload gerendert (kein Punkt-/Klick-Gating wie beim echten
     * Lernenden -- `hints[].used=true` und ein gesetztes `write_up_html`
     * nutzen absichtlich denselben "bereits aufgedeckt"-Zweig, den
     * `Nodes/Show.vue` fuer einen echten geloesten Lauf zeigt). Sandbox/
     * Engine bleiben unangetastet (Betreiber-Vorgabe: kein echter
     * `NodeAttempt`) -- `preview: true` laesst `Nodes/Show.vue` den
     * interaktiven Teil durch einen Platzhalter ersetzen.
     *
     * @param  array<string, mixed>  $payload  normalisiertes Node-Entwurfspayload (LessonPayloadNormalizer-Pendant: NodePayloadNormalizer)
     * @return array<string, mixed>
     */
    public function nodePreviewProps(Node $node, array $payload): array
    {
        $glossary = $this->content->glossary();
        $richRenderer = new RichContentRenderer($glossary);
        $richContent = is_array($payload['rich_content'] ?? null) ? $payload['rich_content'] : [];
        $richContentHints = is_array($richContent['hints'] ?? null) ? $richContent['hints'] : [];
        $briefing = is_array($richContent['briefing'] ?? null) ? $richContent['briefing'] : self::emptyDocument();
        $writeUp = is_array($richContent['write_up'] ?? null) ? $richContent['write_up'] : self::emptyDocument();
        /** @var list<array<string, mixed>> $hintDefinitions */
        $hintDefinitions = is_array($payload['hints'] ?? null) ? array_values($payload['hints']) : [];

        $hints = collect($hintDefinitions)->map(fn (array $hint) => [
            'id' => (string) $hint['id'],
            'cost' => (int) $hint['cost'],
            'used' => true,
            'text_html' => isset($richContentHints[$hint['id']]) && is_array($richContentHints[$hint['id']])
                ? $richRenderer->render($richContentHints[$hint['id']])
                : '',
        ])->values();

        return [
            'node' => [
                'slug' => $node->slug,
                'title' => $payload['title'] ?? $node->title['de'] ?? $node->slug,
                'scenario_title' => $payload['scenario_title'] ?? $node->scenario_title['de'] ?? '',
                'difficulty' => $payload['difficulty'] ?? $node->difficulty,
                'points' => $payload['points'] ?? $node->points,
                'category' => $payload['category'] ?? $node->category,
                'interaction' => $payload['interaction'] ?? $node->interaction,
                'estimated_minutes' => $payload['estimated_minutes'] ?? $node->estimated_minutes,
            ],
            'prev' => null,
            'next' => null,
            'briefing_html' => $richRenderer->render($briefing),
            'hints' => $hints,
            'write_up_html' => $richRenderer->render($writeUp),
            'templates' => [],
            'placeholders' => [],
            'state' => [
                'node_slug' => $node->slug,
                'hosts' => [],
                'scenario' => null,
                'hints_used' => $hints->pluck('id')->all(),
                'write_up_seen' => true,
                'solved' => false,
                'points' => 0,
                'stuck' => false,
            ],
            'attempt' => ['status' => 'preview'],
            'preview' => true,
        ];
    }

    /**
     * Leeres RichContentDocument -- Fallback fuer ein Draft-Payload, dessen
     * `rich_content.briefing`/`.write_up` (noch) fehlt, damit `render()`
     * trotzdem etwas Sinnvolles bekommt statt eines Typfehlers.
     *
     * @return array<string, mixed>
     */
    private static function emptyDocument(): array
    {
        return ['type' => 'doc', 'version' => 1, 'content' => []];
    }

    /**
     * Baut die renderbare Form von `lesson_elements` (ADR 0105, CMS-6b) --
     * aus `LessonController::elementsFor()` hierher verschoben (CMS-7d.3
     * Phase 6), damit Live- und Vorschau-Aufruf denselben Weg nehmen.
     *
     * @param  list<array{id: string, type: string, question_html: string, options_html: array<int, string>, last_result: string|null}>  $quiz
     * @param  array<string, mixed>  $toolbar
     * @return list<array<string, mixed>>
     */
    private function elementsFor(Lesson $lesson, User $user, string $contentHtml, array $quiz, array $toolbar): array
    {
        $stored = $lesson->elements()->with('activity')->get();

        if ($stored->isEmpty()) {
            $fallback = [$this->contentElement($contentHtml)];

            if ($toolbar['needs_sandbox']) {
                $fallback[] = $this->sandboxElement($toolbar);
            }

            if ($toolbar['related_node'] !== null) {
                $fallback[] = $this->relatedNodeElement($toolbar);
            }

            if ($quiz !== []) {
                $fallback[] = $this->quizElement($quiz);
            }

            return $fallback;
        }

        $elements = [];

        foreach ($stored as $element) {
            if ($element->type === 'content') {
                $elements[] = $this->contentElement($contentHtml);

                continue;
            }

            $rendered = match ($element->activity?->type) {
                'sandbox' => $this->sandboxElement($toolbar),
                'node' => $this->relatedNodeElement($toolbar),
                'quiz' => $this->quizElement($quiz),
                'lab' => $this->labCardElement($element->activity, $user),
                default => null,
            };

            if ($rendered !== null) {
                $elements[] = $rendered;
            }
        }

        return $elements;
    }

    /**
     * @return array<string, mixed>
     */
    private function contentElement(string $contentHtml): array
    {
        return ['type' => 'content', 'body_html' => $contentHtml];
    }

    /**
     * @param  array<string, mixed>  $toolbar
     * @return array<string, mixed>
     */
    private function sandboxElement(array $toolbar): array
    {
        return ['type' => 'sandbox', 'dataset' => $toolbar['dataset']];
    }

    /**
     * @param  array<string, mixed>  $toolbar
     * @return array<string, mixed>
     */
    private function relatedNodeElement(array $toolbar): array
    {
        return ['type' => 'related_node', 'related_node' => $toolbar['related_node']];
    }

    /**
     * @param  list<array{id: string, type: string, question_html: string, options_html: array<int, string>, last_result: string|null}>  $quiz
     * @return array<string, mixed>
     */
    private function quizElement(array $quiz): array
    {
        return ['type' => 'quiz', 'questions' => $quiz];
    }

    /**
     * Lab (CMS-8a, Abschnitt H): innerhalb einer Lesson wird NUR eine
     * schlanke Launch-/Status-Karte gezeigt, nie das Terminal -- das lebt
     * ausschliesslich auf der eigenstaendigen Lab-Route. Kein Analogon zu
     * `toolbar['related_node']`, weil ein Lab (anders als die Node-Referenz)
     * kein eigenes Lesson-Spaltenfeld hat: es existiert nur ueber einen
     * echten `lesson_elements`-Eintrag.
     *
     * @return array<string, mixed>
     */
    private function labCardElement(Activity $activity, User $user): array
    {
        $lab = Lab::where('slug', $activity->key)->first();

        if ($lab === null) {
            // Inkonsistenter Zustand (Activity zeigt auf kein existierendes
            // Lab mehr) -- das Element bleibt trotzdem im Ergebnis (kein
            // stilles default => null wie bei einem echten unbekannten Typ),
            // damit ein verwaister Verweis sichtbar/debugbar bleibt.
            Log::warning('learner_view.lab_reference_missing', ['activity_key' => $activity->key]);

            return ['type' => 'lab', 'lab' => null];
        }

        $attempt = LabAttempt::query()
            ->where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->first();

        return [
            'type' => 'lab',
            'lab' => [
                'slug' => $lab->slug,
                'title' => $lab->title['de'] ?? $lab->slug,
                'estimated_minutes' => $lab->estimated_minutes,
                'status' => $attempt === null ? 'not_started' : $attempt->status,
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $tools
     * @param  array<string, array<string, mixed>>  $datasets
     * @return array<string, mixed>
     */
    private function toolbarData(Lesson $lesson, array $tools, array $datasets, User $user): array
    {
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

        $unmetIds = array_column($this->prerequisites->unmetFor($user, $lesson), 'lesson_id');

        $requiresLessons = collect($lesson->requires)
            ->map(fn (string $requiredId) => Lesson::where('lesson_id', $requiredId)->first())
            ->filter()
            ->map(fn (Lesson $required) => [
                'lesson_id' => $required->lesson_id,
                'title' => $required->title['de'] ?? $required->lesson_id,
                'completed' => ! in_array($required->lesson_id, $unmetIds, true),
            ])
            ->values();

        $relatedNode = null;
        $nodeSlug = $lesson->related_node['node'] ?? null;

        if ($nodeSlug !== null) {
            $node = Node::where('slug', $nodeSlug)->first();

            if ($node !== null) {
                $relatedNode = [
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
            'related_node' => $relatedNode,
            'related_node_optional' => (bool) ($lesson->related_node['optional'] ?? false),
        ];
    }
}

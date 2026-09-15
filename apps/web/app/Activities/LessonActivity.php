<?php

namespace App\Activities;

use App\Content\ContentIssue;
use App\Content\ContentRepository;
use App\Content\ContentValidator;
use App\Content\FrontMatter;
use App\Content\LessonMetaGenerator;
use App\Content\LessonQuizGenerator;
use App\Content\QuizContent;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Symfony\Component\Yaml\Yaml;

/**
 * Aktivitaetsvertrag fuer eine Lektion (ADR 0072). Der Abschlusszustand
 * kommt weiterhin aus `lesson_progress` -- W0 stellt nur den Lesezugriff
 * hinter den Vertrag, `activity_progress` wird erst in einer spaeteren Phase
 * parallel mitgeschrieben (siehe ADR 0073).
 */
final readonly class LessonActivity implements ActivityContract
{
    public function __construct(
        private Lesson $lesson,
        private ContentRepository $content,
    ) {}

    public function activityType(): string
    {
        return ActivityType::Lesson->value;
    }

    public function key(): string
    {
        return $this->lesson->lesson_id;
    }

    public function supports(): ActivitySupports
    {
        return new ActivitySupports(
            isGraded: false,
            tracksCompletion: true,
            needsContainer: false,
            authorable: true,
            freelyPlaceable: false,
        );
    }

    public function learnerView(User $user): array
    {
        $progress = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $this->lesson->id)
            ->first();

        return [
            'type' => $this->activityType(),
            'lesson_id' => $this->lesson->lesson_id,
            'title' => $this->lesson->title['de'] ?? '',
            'teaser' => $this->lesson->teaser['de'] ?? '',
            'level' => $this->lesson->level,
            'duration_minutes' => $this->lesson->duration_minutes,
            'status' => $progress === null ? 'not_started' : $progress->status,
        ];
    }

    public function authorView(): array
    {
        return [
            ['key' => 'title', 'type' => 'text', 'label' => 'Titel', 'required' => true],
            ['key' => 'teaser', 'type' => 'text', 'label' => 'Teaser', 'required' => true],
            ['key' => 'level', 'type' => 'text', 'label' => 'Niveau', 'required' => true],
            ['key' => 'duration_minutes', 'type' => 'number', 'label' => 'Dauer (Minuten)', 'required' => true],
            ['key' => 'tools', 'type' => 'list', 'label' => 'Werkzeuge', 'required' => false],
            ['key' => 'requires', 'type' => 'list', 'label' => 'Voraussetzungen', 'required' => false],
            ['key' => 'glossary_terms', 'type' => 'list', 'label' => 'Glossarbegriffe', 'required' => false],
            ['key' => 'body', 'type' => 'richtext', 'label' => 'Lektionstext', 'required' => true],
        ];
    }

    public function validate(?array $draft = null): array
    {
        // Derselbe Regelsatz wie `content:validate` (ADR 0071/0073, W1),
        // eingegrenzt auf Befunde, die zu dieser Lektion gehoeren -- keine
        // zweite, eigene Pruefung. Mit $draft wird nicht der Ist-Zustand
        // geprueft, sondern das, was serialize($draft) erzeugen wuerde
        // (ADR 0080, W6) -- derselbe Weg wie ContentRepository::lessons()
        // baut den Eintrag, damit ContentValidator keinen Unterschied sieht.
        $lessons = $this->content->lessons();

        if ($draft !== null) {
            $lessons[$this->lesson->lesson_id] = $this->syntheticEntry($draft);
        }

        $prefix = "lessons/{$this->lesson->lesson_id}/";

        return array_values(array_filter(
            (new ContentValidator)->validate(
                themenfelder: $this->content->themenfelder(),
                tracks: $this->content->tracks(),
                achievements: $this->content->achievements(),
                lessons: $lessons,
                nodes: $this->content->nodes(),
                exams: $this->content->exams(),
                tools: $this->content->tools(),
                toolsRaw: $this->content->toolsRaw(),
                glossary: $this->content->glossary(),
                datasets: $this->content->datasets(),
                skills: $this->content->skills(),
            ),
            fn (ContentIssue $issue): bool => str_starts_with($issue->file, $prefix),
        ));
    }

    public function serialize(?array $draft = null): array
    {
        $entry = $this->contentEntry();

        if ($entry === null) {
            return [];
        }

        $metaRaw = $entry['meta_raw'];
        $mdRaw = $entry['md_raw'];

        if ($draft === null) {
            return [
                ['path' => "lessons/{$this->lesson->lesson_id}/meta.yml", 'contents' => $metaRaw],
                ['path' => "lessons/{$this->lesson->lesson_id}/de.md", 'contents' => $mdRaw],
            ];
        }

        // Skalar-/Listenfelder in meta.yml und Titel/Teaser in der
        // Frontmatter (ADR 0080/0081, W6.1/W6.2); sandbox/lab (verschachtelte
        // Bloecke) und objectives (mehrzeilige Liste, ADR 0089) folgen
        // demselben chirurgischen Muster, je ihrem eigenen Block-Ersatz.
        $metaRaw = LessonMetaGenerator::regenerateMeta($metaRaw, $draft);
        $mdRaw = LessonMetaGenerator::regenerateFrontMatter($mdRaw, $draft);

        if (isset($draft['sandbox'])) {
            $metaRaw = LessonMetaGenerator::regenerateSandbox($metaRaw, $draft['sandbox']);
        }

        if (isset($draft['lab'])) {
            $metaRaw = LessonMetaGenerator::regenerateLab($metaRaw, $draft['lab']);
        }

        if (isset($draft['objectives'])) {
            // objectives_count (meta.yml) muss immer zur tatsaechlichen
            // Listenlaenge passen (ContentValidator::checkLessonStructure())
            // -- nie eigenstaendig aus dem Entwurf uebernehmen, sondern hier
            // ableiten, damit beide nie auseinanderlaufen koennen.
            $metaRaw = LessonMetaGenerator::regenerateMeta($metaRaw, [
                'objectives_count' => count($draft['objectives']),
            ]);
        }

        if (isset($draft['quiz'])) {
            $metaRaw = LessonQuizGenerator::regenerateMeta($metaRaw, $draft['quiz']);
        }

        if (isset($draft['quiz']) || array_key_exists('body', $draft)) {
            $frontMatter = FrontMatter::parse($mdRaw);
            $body = $frontMatter['body'];

            if (array_key_exists('body', $draft)) {
                // Nur die Prosa vor dem Quiz-Abschnitt wird vom Lektions-
                // Editor bearbeitet -- ein bestehender Quiz-Abschnitt und
                // die Fussnote danach ("Als Naechstes: ...") bleiben
                // unangetastet erhalten, bis der Entwurf sie selbst aendert.
                $split = QuizContent::splitBody($body);
                $newBefore = rtrim((string) $draft['body'], "\r\n");
                $body = $split['quiz_raw'] !== ''
                    ? $newBefore."\n\n".$split['quiz_raw']."\n\n".$split['after']
                    : $newBefore;
            }

            if (isset($draft['quiz'])) {
                $body = LessonQuizGenerator::regenerateBody($body, $draft['quiz']);
            }

            $mdRaw = $this->withNewBody($mdRaw, $body);
        }

        return [
            ['path' => "lessons/{$this->lesson->lesson_id}/meta.yml", 'contents' => $metaRaw],
            ['path' => "lessons/{$this->lesson->lesson_id}/de.md", 'contents' => $mdRaw],
        ];
    }

    /**
     * Ersetzt nur den Body eines de.md, die Frontmatter (Titel, Teaser,
     * Lernziele) bleibt Zeile fuer Zeile unangetastet.
     */
    private function withNewBody(string $mdRaw, string $newBody): string
    {
        $frontMatter = FrontMatter::parse($mdRaw);
        $lines = preg_split('/\R/', $mdRaw) ?: [];
        $frontMatterLines = array_slice($lines, 0, max(0, $frontMatter['bodyStartLine'] - 1));

        return implode("\n", $frontMatterLines)."\n".$newBody;
    }

    /**
     * Baut denselben Eintrag, den ContentRepository::lessons() fuer diese
     * Lektion liefern wuerde, aber aus serialize($draft) statt von der
     * Platte -- damit validate($draft) den Entwurf pruefen kann, bevor er
     * geschrieben wird.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function syntheticEntry(array $draft): array
    {
        $files = $this->serialize($draft);
        $metaRaw = $files[0]['contents'] ?? '';
        $mdRaw = $files[1]['contents'] ?? '';

        $meta = Yaml::parse($metaRaw) ?? [];
        $frontMatter = FrontMatter::parse($mdRaw);

        return [
            'id' => $this->lesson->lesson_id,
            'meta' => $meta,
            'meta_file' => "lessons/{$this->lesson->lesson_id}/meta.yml",
            'meta_raw' => $metaRaw,
            'md_file' => "lessons/{$this->lesson->lesson_id}/de.md",
            'md_raw' => $mdRaw,
            'frontmatter' => $frontMatter['attributes'],
            'body' => $frontMatter['body'],
            'body_start_line' => $frontMatter['bodyStartLine'],
        ];
    }

    public function deserialize(): array
    {
        $entry = $this->contentEntry();

        return [
            'lesson_id' => $this->lesson->lesson_id,
            'track' => $entry['meta']['track'] ?? null,
            'title' => $this->lesson->title['de'] ?? '',
            'teaser' => $this->lesson->teaser['de'] ?? '',
            'level' => $this->lesson->level,
            'duration_minutes' => $this->lesson->duration_minutes,
            'tools' => $this->lesson->tools,
            'requires' => $this->lesson->requires,
            'glossary_terms' => $this->lesson->glossary_terms,
            'sandbox' => $this->lesson->sandbox,
            'lab' => $this->lesson->lab,
            'body' => $entry['body'] ?? null,
        ];
    }

    public function result(User $user): ?ActivityResult
    {
        $progress = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $this->lesson->id)
            ->first();

        if ($progress === null) {
            return null;
        }

        return new ActivityResult(
            completed: $progress->status === 'completed',
            completedAt: $progress->completed_at,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contentEntry(): ?array
    {
        return $this->content->lessons()[$this->lesson->lesson_id] ?? null;
    }
}

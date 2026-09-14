<?php

namespace App\Activities;

use App\Content\ContentIssue;
use App\Content\ContentRepository;
use App\Content\ContentValidator;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

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

    public function validate(): array
    {
        // Derselbe Regelsatz wie `content:validate` (ADR 0071/0073, W1),
        // eingegrenzt auf Befunde, die zu dieser Lektion gehoeren -- keine
        // zweite, eigene Pruefung.
        $prefix = "lessons/{$this->lesson->lesson_id}/";

        return array_values(array_filter(
            (new ContentValidator)->validate(
                themenfelder: $this->content->themenfelder(),
                tracks: $this->content->tracks(),
                achievements: $this->content->achievements(),
                lessons: $this->content->lessons(),
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

    public function serialize(): array
    {
        $entry = $this->contentEntry();

        if ($entry === null) {
            return [];
        }

        return [
            ['path' => "lessons/{$this->lesson->lesson_id}/meta.yml", 'contents' => $entry['meta_raw']],
            ['path' => "lessons/{$this->lesson->lesson_id}/de.md", 'contents' => $entry['md_raw']],
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

<?php

namespace App\Activities;

use App\Content\ContentIssue;
use App\Content\ContentRepository;
use App\Content\ContentValidator;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\User;

/**
 * Aktivitaetsvertrag fuer eine Node (ADR 0072). Der laufende
 * Versuchszustand (Engine-Sitzung, genutzte Hints) bleibt in `node_attempts`
 * -- nur das Ergebnis (geloest, Punktzahl, belegte Skills) ist Teil des
 * Vertrags.
 */
final readonly class NodeActivity implements ActivityContract
{
    public function __construct(
        private Node $node,
        private ContentRepository $content,
    ) {}

    public function activityType(): string
    {
        return ActivityType::Node->value;
    }

    public function key(): string
    {
        return $this->node->slug;
    }

    public function supports(): ActivitySupports
    {
        return new ActivitySupports(
            isGraded: true,
            tracksCompletion: true,
            needsContainer: true,
            authorable: true,
            freelyPlaceable: true,
        );
    }

    public function learnerView(User $user): array
    {
        $attempt = $this->attemptFor($user);

        return [
            'type' => $this->activityType(),
            'slug' => $this->node->slug,
            'title' => $this->node->title['de'] ?? '',
            'difficulty' => $this->node->difficulty,
            'points' => $this->node->points,
            'category' => $this->node->category,
            'status' => $attempt === null ? 'not_started' : $attempt->status,
        ];
    }

    public function authorView(): array
    {
        return [
            ['key' => 'title', 'type' => 'text', 'label' => 'Titel', 'required' => true],
            ['key' => 'difficulty', 'type' => 'text', 'label' => 'Schwierigkeit', 'required' => true],
            ['key' => 'points', 'type' => 'number', 'label' => 'Punkte', 'required' => true],
            ['key' => 'category', 'type' => 'text', 'label' => 'Kategorie', 'required' => true],
            ['key' => 'skills', 'type' => 'list', 'label' => 'Skill-Kategorien', 'required' => false],
            ['key' => 'related_lessons', 'type' => 'list', 'label' => 'Verwandte Lektionen', 'required' => false],
        ];
    }

    public function validate(): array
    {
        $prefix = "nodes/{$this->node->slug}/";

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
            ['path' => "nodes/{$this->node->slug}/node.yml", 'contents' => $entry['def_raw']],
            ['path' => "nodes/{$this->node->slug}/de.md", 'contents' => $entry['md_raw']],
        ];
    }

    public function deserialize(): array
    {
        $entry = $this->contentEntry();

        return [
            'slug' => $this->node->slug,
            'title' => $this->node->title['de'] ?? '',
            'difficulty' => $this->node->difficulty,
            'points' => $this->node->points,
            'category' => $this->node->category,
            'interaction' => $this->node->interaction,
            'skills' => $this->node->skills,
            'related_lessons' => $this->node->related_lessons,
            'body' => $entry['body'] ?? null,
        ];
    }

    public function result(User $user): ?ActivityResult
    {
        $attempt = $this->attemptFor($user);

        if ($attempt === null) {
            return null;
        }

        return new ActivityResult(
            completed: $attempt->status === 'solved',
            score: $attempt->points,
            maxScore: $this->node->points,
            skills: $attempt->status === 'solved' ? array_values($this->node->skills) : [],
            completedAt: $attempt->flag_submitted_at,
        );
    }

    private function attemptFor(User $user): ?NodeAttempt
    {
        return NodeAttempt::query()
            ->where('user_id', $user->id)
            ->where('node_id', $this->node->id)
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contentEntry(): ?array
    {
        return $this->content->nodes()[$this->node->slug] ?? null;
    }
}

<?php

namespace App\Activities;

use App\Content\ContentIssue;
use App\Content\ContentRepository;
use App\Content\ContentValidator;
use App\Content\FrontMatter;
use App\Content\NodeMetaGenerator;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\User;
use Symfony\Component\Yaml\Yaml;

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
            runtimeType: 'container',
            // versionable: seit ADR 0108 (CMS-6d Teil 2) wertet
            // serialize($draft) den Entwurf tatsaechlich aus (analog zu
            // LessonActivity) -- echte Draft-Teilnahme ueber denselben
            // content_versions-Kreislauf, nicht nur der Vertrag.
            versionable: true,
            // reusable: eine Node kann ueber `related_lessons` aus mehreren
            // Lektionen heraus verlinkt werden, ist selbst keiner Lektion
            // oder keinem Track exklusiv zugeordnet.
            reusable: true,
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
            ['key' => 'scenario_title', 'type' => 'text', 'label' => 'Szenario-Titel', 'required' => true],
            ['key' => 'difficulty', 'type' => 'text', 'label' => 'Schwierigkeit', 'required' => true],
            ['key' => 'points', 'type' => 'number', 'label' => 'Punkte', 'required' => true],
            ['key' => 'category', 'type' => 'text', 'label' => 'Kategorie', 'required' => true],
            ['key' => 'interaction', 'type' => 'text', 'label' => 'Interaktionstyp', 'required' => true],
            ['key' => 'estimated_minutes', 'type' => 'number', 'label' => 'Dauer (Minuten)', 'required' => true],
            ['key' => 'skills', 'type' => 'list', 'label' => 'Skill-Kategorien', 'required' => false],
            ['key' => 'related_lessons', 'type' => 'list', 'label' => 'Verwandte Lektionen', 'required' => false],
            ['key' => 'body', 'type' => 'richtext', 'label' => 'Briefing / Hints / Write-up', 'required' => true],
            ['key' => 'hints', 'type' => 'json', 'label' => 'Hints (id, cost)', 'required' => false],
        ];
    }

    public function validate(?array $draft = null): array
    {
        // Derselbe Regelsatz wie `content:validate`, eingegrenzt auf Befunde
        // dieser Node -- keine zweite, eigene Pruefung. Mit $draft wird nicht
        // der Ist-Zustand geprueft, sondern das, was serialize($draft)
        // erzeugen wuerde (ADR 0108, analog zu LessonActivity::validate()).
        $nodes = $this->content->nodes();

        if ($draft !== null) {
            $nodes[$this->node->slug] = $this->syntheticEntry($draft);
        }

        $prefix = "nodes/{$this->node->slug}/";

        return array_values(array_filter(
            (new ContentValidator)->validate(
                themenfelder: $this->content->themenfelder(),
                tracks: $this->content->tracks(),
                achievements: $this->content->achievements(),
                lessons: $this->content->lessons(),
                nodes: $nodes,
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

        // Eine rein in Studio angelegte Node (ADR 0109, CMS-6d Teil 3) hat
        // keinen content/nodes/<slug>/-Eintrag -- ohne $draft gibt es dann
        // nichts zu serialisieren, MIT $draft muss trotzdem ein valider
        // node.yml/de.md-Rumpf entstehen, damit validate($draft) den
        // Entwurf einer neuen Node ueberhaupt pruefen kann.
        if ($entry === null && $draft === null) {
            return [];
        }

        $defRaw = $entry['def_raw'] ?? '';
        $mdRaw = $entry['md_raw'] ?? "---\n---\n";

        if ($draft === null) {
            return [
                ['path' => "nodes/{$this->node->slug}/node.yml", 'contents' => $defRaw],
                ['path' => "nodes/{$this->node->slug}/de.md", 'contents' => $mdRaw],
            ];
        }

        // Skalar-/Listenfelder in node.yml und Titel/Szenario-Titel in der
        // Frontmatter (analog LessonActivity::serialize()); `environment:`/
        // `flag:` bleiben unangetastet, weil NodeMetaGenerator sie nie
        // anfasst (ADR 0107/0108).
        $defRaw = NodeMetaGenerator::regenerateDef($defRaw, $draft);

        if (isset($draft['hints'])) {
            $defRaw = NodeMetaGenerator::regenerateHints($defRaw, $draft['hints']);
        }

        $mdRaw = NodeMetaGenerator::regenerateFrontMatter($mdRaw, $draft);

        if (array_key_exists('body', $draft)) {
            $mdRaw = $this->withNewBody($mdRaw, rtrim((string) $draft['body'], "\r\n"));
        }

        return [
            ['path' => "nodes/{$this->node->slug}/node.yml", 'contents' => $defRaw],
            ['path' => "nodes/{$this->node->slug}/de.md", 'contents' => $mdRaw],
        ];
    }

    /**
     * Ersetzt nur den Body eines de.md, die Frontmatter (Titel,
     * Szenario-Titel) bleibt Zeile fuer Zeile unangetastet.
     */
    private function withNewBody(string $mdRaw, string $newBody): string
    {
        $frontMatter = FrontMatter::parse($mdRaw);
        $lines = preg_split('/\R/', $mdRaw) ?: [];
        $frontMatterLines = array_slice($lines, 0, max(0, $frontMatter['bodyStartLine'] - 1));

        return implode("\n", $frontMatterLines)."\n".$newBody;
    }

    /**
     * Baut denselben Eintrag, den ContentRepository::nodes() fuer diese Node
     * liefern wuerde, aber aus serialize($draft) statt von der Platte --
     * damit validate($draft) den Entwurf pruefen kann, bevor er
     * (DB-direkt, ueber NodeContentPublisher) uebernommen wird.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function syntheticEntry(array $draft): array
    {
        $files = $this->serialize($draft);
        $defRaw = $files[0]['contents'] ?? '';
        $mdRaw = $files[1]['contents'] ?? '';

        $def = Yaml::parse($defRaw) ?? [];
        $frontMatter = FrontMatter::parse($mdRaw);

        return [
            'slug' => $this->node->slug,
            'def' => $def,
            'def_file' => "nodes/{$this->node->slug}/node.yml",
            'def_raw' => $defRaw,
            'md_file' => "nodes/{$this->node->slug}/de.md",
            'md_raw' => $mdRaw,
            'frontmatter' => $frontMatter['attributes'],
            'body' => $frontMatter['body'],
            'body_start_line' => $frontMatter['bodyStartLine'],
        ];
    }

    public function deserialize(): array
    {
        // ADR 0107 (CMS-6d): bevorzugt aus der DB (von content:sync befuellt)
        // -- ContentRepository bleibt nur noch Fallback fuer eine Node, deren
        // naechster Sync-Lauf noch aussteht (derselbe Fallback-Mechanismus
        // wie bei Lesson, ADR 0101).
        $body = $this->node->body ?? $this->contentEntry()['body'] ?? null;

        return [
            'slug' => $this->node->slug,
            'title' => $this->node->title['de'] ?? '',
            'scenario_title' => $this->node->scenario_title['de'] ?? '',
            'difficulty' => $this->node->difficulty,
            'points' => $this->node->points,
            'category' => $this->node->category,
            'interaction' => $this->node->interaction,
            'estimated_minutes' => $this->node->estimated_minutes,
            'skills' => $this->node->skills,
            'related_lessons' => $this->node->related_lessons,
            'hints' => $this->node->hints ?? [],
            'body' => $body,
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

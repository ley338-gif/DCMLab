<?php

namespace App\Activities;

use App\Content\ContentIssue;
use App\Content\ContentRepository;
use App\Content\ContentValidator;
use App\Content\FrontMatter;
use App\Content\NodeMetaGenerator;
use App\Content\RichContent\NodePayloadNormalizer;
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
        $syntheticEntry = null;

        if ($draft !== null) {
            $syntheticEntry = $this->syntheticEntry($draft);
            $nodes[$this->node->slug] = $syntheticEntry;
        }

        $prefix = "nodes/{$this->node->slug}/";

        $issues = array_values(array_filter(
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

        if ($draft !== null && isset($draft['hints']) && isset($syntheticEntry['rich_content'])) {
            $issues = [...$issues, ...$this->checkHintIdConsistency($draft['hints'], $syntheticEntry['rich_content'])];
        }

        return $issues;
    }

    /**
     * CMS-7d.3 (Betreiber-Vorgabe): `Node.hints` (Metadaten, id/cost) und
     * `rich_content.hints` (Text, je RichContentDocument) muessen exakt
     * dieselben Ids tragen -- kein Hint-Text ohne Kosten-/ID-Metadaten,
     * keine Hint-Metadaten ohne zugehoerigen Text.
     *
     * @param  list<array<string, mixed>>  $hintDefinitions
     * @param  array<string, mixed>  $richContent
     * @return list<ContentIssue>
     */
    private function checkHintIdConsistency(array $hintDefinitions, array $richContent): array
    {
        $metadataIds = array_map(fn (array $hint): string => (string) ($hint['id'] ?? ''), $hintDefinitions);
        $richContentIds = array_keys(is_array($richContent['hints'] ?? null) ? $richContent['hints'] : []);
        $file = "nodes/{$this->node->slug}/de.md";
        $issues = [];

        foreach (array_diff($metadataIds, $richContentIds) as $orphanId) {
            $issues[] = new ContentIssue($file, null, "Hint \"{$orphanId}\" aus hints hat keinen zugehoerigen Text in rich_content.hints");
        }

        foreach (array_diff($richContentIds, $metadataIds) as $orphanId) {
            $issues[] = new ContentIssue($file, null, "rich_content.hints enthaelt \"{$orphanId}\", aber kein Hint mit dieser Id in hints (Kosten fehlen)");
        }

        return $issues;
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

        // Seit CMS-7d.3 ist `rich_content` die kanonische Quelle fuer
        // Briefing/Hints/Write-up (DB), nicht mehr `content/**` -- der
        // Body-Teil von `de.md` wird deshalb nicht mehr aus dem Entwurf
        // regeneriert, nur noch Titel/Szenario-Titel in der Frontmatter.
        $mdRaw = NodeMetaGenerator::regenerateFrontMatter($mdRaw, $draft);

        return [
            ['path' => "nodes/{$this->node->slug}/node.yml", 'contents' => $defRaw],
            ['path' => "nodes/{$this->node->slug}/de.md", 'contents' => $mdRaw],
        ];
    }

    /**
     * Baut denselben Eintrag, den ContentRepository::nodes() fuer diese Node
     * liefern wuerde, aber aus serialize($draft) statt von der Platte --
     * damit validate($draft) den Entwurf pruefen kann, bevor er
     * (DB-direkt, ueber NodeContentPublisher) uebernommen wird. Seit
     * CMS-7d.3 zusaetzlich mit `rich_content` (der `node_content`-Umschlag,
     * ADR 0115): ein Entwurf, der ihn schon traegt, wird unveraendert
     * durchgereicht, ein alter `body`-tragender Entwurf ueber
     * `NodePayloadNormalizer` konvertiert -- `ContentValidator` prueft dann
     * gegen den Knotenbaum statt gegen Markdown-Text.
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
            'rich_content' => (new NodePayloadNormalizer)->normalize($draft)['rich_content'] ?? null,
        ];
    }

    public function deserialize(): array
    {
        // CMS-7d.3: rich_content (der node_content-Umschlag) ist die
        // kanonische Quelle. Ist die Spalte noch nicht befuellt, normalisiert
        // derselbe Normalizer wie ueberall sonst den Legacy-Body --
        // `NodeSections::parse()` wird dabei nicht mehr direkt hier
        // aufgerufen, das uebernimmt der Normalizer.
        $body = $this->node->body ?? $this->contentEntry()['body'] ?? '';

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
            'rich_content' => $this->node->rich_content
                ?? (new NodePayloadNormalizer)->normalize(['body' => $body])['rich_content'],
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

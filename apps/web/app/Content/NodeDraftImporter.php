<?php

namespace App\Content;

use App\Content\RichContent\NodePayloadNormalizer;
use App\Content\RichContent\RichContentToMarkdownSerializer;
use App\Models\Node;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Node-Gegenstueck zu `LessonDraftImporter` (`content:draft-node`): baut aus
 * einer ausserhalb von `content/` geschriebenen Node-Datei dasselbe
 * Entwurfs-Payload, das der Studio-Node-Editor anlegt
 * (`StudioNodeController::update()`). Reiner Rechenschritt ohne
 * DB-Schreibzugriff; validiert und angelegt wird im Befehl.
 *
 * Das Dateiformat ist genau das, was `content:export` fuer eine Node
 * schreibt (ADR 0122), und der Import ist dessen Umkehrung:
 *
 * - `de.md`-Frontmatter `title`/`scenario_title` -> gleichnamige Felder
 * - `de.md`-Body (`## Briefing`, `## Hints` mit `### <id>`, `## Write-up`)
 *   -> `rich_content` (`node_content`-Umschlag, ADR 0115) ueber
 *   `NodePayloadNormalizer`, wie ein Legacy-`body`-Entwurf
 * - optional `node.yml` (`--meta`): die Studio-Felder aus
 *   `NodeMetaGenerator::DEF_FIELDS` und `hints` (nur id/cost)
 *
 * Felder, die die Datei nicht setzt, kommen aus dem Live-Stand der Node.
 * `environment`, `flag`, `solve`, `scenario` usw. sind nie Teil des Payloads
 * (`NodeContentPublisher`, ADR 0107), `status` und `themenfeld` setzt Studio
 * ausserhalb des Entwurfs -- alle werden ignoriert.
 */
final class NodeDraftImporter
{
    /**
     * Die Studio-editierbaren `node.yml`-Felder ohne `hints`.
     */
    private const META_FIELDS = ['difficulty', 'points', 'category', 'interaction', 'estimated_minutes', 'skills', 'related_lessons'];

    public function __construct(
        private readonly RichContentToMarkdownSerializer $serializer = new RichContentToMarkdownSerializer,
        private readonly NodePayloadNormalizer $normalizer = new NodePayloadNormalizer,
    ) {}

    /**
     * @return array<string, mixed>|null Payload in der Feldreihenfolge des Studio-Editors;
     *                                   `null` = inhaltlich identisch mit dem Live-Stand, kein Entwurf noetig
     */
    public function build(Node $node, string $markdown, ?string $metaYaml = null): ?array
    {
        $frontMatter = FrontMatter::parse(str_replace("\r\n", "\n", $markdown));
        $attributes = $frontMatter['attributes'];
        $meta = $metaYaml !== null ? (Yaml::parse($metaYaml) ?? []) : [];

        if (! is_array($meta)) {
            throw new RuntimeException('node.yml ist kein YAML-Objekt.');
        }

        if (isset($meta['slug']) && (string) $meta['slug'] !== $node->slug) {
            throw new RuntimeException("node.yml gehoert zu Node \"{$meta['slug']}\", nicht zu {$node->slug}.");
        }

        if (trim($frontMatter['body']) === '') {
            throw new RuntimeException('Die Datei enthaelt keinen Body (## Briefing, ## Hints, ## Write-up).');
        }

        $payload = [
            'title' => (string) ($attributes['title'] ?? $node->title['de'] ?? ''),
            'scenario_title' => (string) ($attributes['scenario_title'] ?? $node->scenario_title['de'] ?? ''),
        ];

        foreach (self::META_FIELDS as $field) {
            $payload[$field] = array_key_exists($field, $meta) ? $meta[$field] : $node->{$field};
        }

        // Wie der Editor: Listen-Eintraege sind immer Strings -- ein
        // ungequotetes `related_lessons: [3.8]` kaeme sonst als Float an.
        foreach (['skills', 'related_lessons'] as $field) {
            $payload[$field] = array_values(array_map('strval', (array) ($payload[$field] ?? [])));
        }

        $payload['hints'] = self::hints(array_key_exists('hints', $meta) ? $meta['hints'] : $node->hints);
        $payload['rich_content'] = $this->normalizer->normalize(['body' => $frontMatter['body']])['rich_content'];

        return $this->sameAsLive($node, $payload) ? null : $payload;
    }

    /**
     * Nur id/cost, wie der Editor sie sendet. Fehlende Werte bleiben `null`
     * und scheitern an den Formregeln des Editors, statt still zu `0` zu
     * werden.
     *
     * @return list<array{id: string|null, cost: mixed}>
     */
    private static function hints(mixed $hints): array
    {
        $normalized = [];

        foreach (is_array($hints) ? $hints : [] as $hint) {
            $id = is_array($hint) ? ($hint['id'] ?? null) : null;
            $normalized[] = [
                'id' => is_scalar($id) ? (string) $id : null,
                'cost' => is_array($hint) ? ($hint['cost'] ?? null) : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sameAsLive(Node $node, array $payload): bool
    {
        // Noch nicht migrierte Node: derselbe Startwert, den
        // NodeActivity::deserialize() dem Editor gibt.
        $liveRichContent = $node->rich_content
            ?? $this->normalizer->normalize(['body' => (string) ($node->body ?? '')])['rich_content'];

        if (! $this->serializer->equivalentNodeContent($liveRichContent, $payload['rich_content'])) {
            return false;
        }

        foreach (['title', 'scenario_title'] as $field) {
            if (! ContentFieldComparison::same($node->{$field}['de'] ?? '', $payload[$field])) {
                return false;
            }
        }

        foreach (self::META_FIELDS as $field) {
            $live = in_array($field, ['skills', 'related_lessons'], true) ? ($node->{$field} ?? []) : $node->{$field};

            if (! ContentFieldComparison::same($live, $payload[$field])) {
                return false;
            }
        }

        return ContentFieldComparison::same(self::hints($node->hints), $payload['hints']);
    }
}

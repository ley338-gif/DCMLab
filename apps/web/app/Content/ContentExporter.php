<?php

namespace App\Content;

use App\Content\RichContent\MarkdownToRichContentConverter;
use App\Content\RichContent\NodePayloadNormalizer;
use App\Content\RichContent\RichContentToMarkdownSerializer;
use App\Content\RichContent\UnrepresentableRichContentException;
use App\Content\RichContent\UnsupportedRichContentVersionException;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\Themenfeld;
use App\Models\Track;

/**
 * Gegenstueck zu `content:sync` (ADR 0122, Phase 2): berechnet aus dem
 * veroeffentlichten DB-Stand, welche Dateien unter `content/` wie aussehen
 * muessten. Reiner Lesevorgang -- geschrieben wird im Command.
 *
 * Quelle sind die Live-Spalten (`lessons`/`nodes`/`tracks`), nie
 * `content_versions`: Entwuerfe (`draft`/`review`/`superseded`) erreichen
 * diese Spalten strukturell nie, veroeffentlichte Payloads koennen noch im
 * Legacy-Format vorliegen (ADR 0122, Phase 0, Punkt 4).
 *
 * Semantischer Diff statt Neuschreiben: jedes Feld wird mit dem Dateistand
 * verglichen (Default-Werte, fehlende Schluessel, `jsonb`-Schluessel-
 * reihenfolge und rein kosmetische Markdown-Unterschiede zaehlen nicht),
 * und nur abweichende Felder werden ueber die vorhandenen, chirurgischen
 * Generatoren ersetzt -- Kommentare und alle anderen Zeilen bleiben stehen.
 * Folge: ein zweiter Lauf direkt nach einem schreibenden Export findet
 * nichts mehr (deterministisch, idempotent), Zeilenenden sind LF.
 *
 * Bewusst nicht exportiert (Datei ist dort weiterhin die Wahrheit oder
 * Betreiberentscheidung offen): Pruefungen, Achievements, Glossar,
 * Datasets, Worklists, Tools, Skills, Themenfelder, `environment`/`flag`
 * einer Node, Track-`title`/`teaser`, `authors`/`updated`/`tools_checked`.
 */
final class ContentExporter
{
    public const SCOPES = ['lessons', 'nodes', 'tracks'];

    private const LESSON_META_DEFAULTS = [
        'level' => 'einsteiger',
        'duration_minutes' => 0,
        'tools' => [],
        'requires' => [],
        'glossary_terms' => [],
        'objectives_count' => 0,
    ];

    private const NODE_DEF_DEFAULTS = [
        'difficulty' => 'easy',
        'points' => 0,
        'category' => 'netzwerk',
        'interaction' => 'terminal',
        'estimated_minutes' => 0,
        'skills' => [],
        'related_lessons' => [],
    ];

    public function __construct(
        private readonly RichContentToMarkdownSerializer $serializer = new RichContentToMarkdownSerializer,
        private readonly MarkdownToRichContentConverter $converter = new MarkdownToRichContentConverter,
    ) {}

    /**
     * @param  list<string>  $scopes  Teilmenge von SCOPES
     */
    public function export(ContentRepository $files, array $scopes, ?string $id = null): ContentExportResult
    {
        $result = new ContentExportResult;

        if (in_array('lessons', $scopes, true)) {
            $this->exportLessons($files, $id, $result);
        }

        if (in_array('nodes', $scopes, true)) {
            $this->exportNodes($files, $id, $result);
        }

        if (in_array('tracks', $scopes, true)) {
            $this->exportTracks($files, $id, $result);
        }

        return $result;
    }

    private function exportLessons(ContentRepository $files, ?string $id, ContentExportResult $result): void
    {
        $entries = $files->lessons();
        $lessons = Lesson::query()->with('track')->when($id !== null, fn ($q) => $q->where('lesson_id', $id))->get()
            ->sortBy('lesson_id', SORT_NATURAL)->values();

        foreach ($lessons as $lesson) {
            $resource = "Lektion {$lesson->lesson_id}";
            $entry = $entries[$lesson->lesson_id] ?? null;

            if ($entry === null || $entry['meta_raw'] === null || $entry['md_raw'] === null || $entry['meta'] === null) {
                $result->error($resource, "keine Datei-Vorlage unter content/lessons/{$lesson->lesson_id}/ (nur in der DB angelegt, ADR 0122 offene Frage)");

                continue;
            }

            try {
                $this->exportLesson($lesson, $entry, $result);
            } catch (UnrepresentableRichContentException|UnsupportedRichContentVersionException $e) {
                $result->error($resource, $e->getMessage());
            }
        }
    }

    /**
     * @param  array<string, mixed>  $entry  ContentRepository::lessons()-Eintrag
     */
    private function exportLesson(Lesson $lesson, array $entry, ContentExportResult $result): void
    {
        $meta = $entry['meta'];
        $frontMatter = $entry['frontmatter'] ?? [];
        $metaRaw = self::lf((string) $entry['meta_raw']);
        $mdRaw = self::lf((string) $entry['md_raw']);
        $metaChanged = [];
        $mdChanged = [];

        $index = array_filter([
            'track' => $lesson->track?->slug,
            'order' => $lesson->order,
            'status' => $lesson->status,
        ], fn (mixed $value): bool => $value !== null);
        $index = array_filter($index, fn (mixed $value, string $key): bool => ! ContentFieldComparison::same($value, $meta[$key] ?? ($key === 'status' ? 'draft' : null)), ARRAY_FILTER_USE_BOTH);

        $metaFields = [];
        foreach (self::LESSON_META_DEFAULTS as $key => $default) {
            $dbValue = $key === 'objectives_count' ? count($lesson->objectives ?? []) : ($lesson->{$key} ?? $default);

            if (! ContentFieldComparison::same($dbValue, $meta[$key] ?? $default)) {
                $metaFields[$key] = $dbValue;
            }
        }

        $metaRaw = LessonMetaGenerator::regenerateIndex($metaRaw, $index);
        $metaRaw = LessonMetaGenerator::regenerateMeta($metaRaw, $metaFields);
        $metaChanged = [...array_keys($index), ...array_keys($metaFields)];

        $sandbox = ContentFieldComparison::sandbox($lesson->sandbox);
        if (! ContentFieldComparison::same($sandbox, ContentFieldComparison::sandbox($meta['sandbox'] ?? null))) {
            $metaRaw = LessonMetaGenerator::regenerateSandbox($metaRaw, $sandbox);
            $metaChanged[] = 'sandbox';
        }

        $relatedNode = ContentFieldComparison::relatedNode($lesson->related_node);
        if (! ContentFieldComparison::same($relatedNode, ContentFieldComparison::relatedNode($meta['related_node'] ?? null))) {
            $metaRaw = LessonMetaGenerator::regenerateRelatedNode($metaRaw, $relatedNode);
            $metaChanged[] = 'related_node';
        }

        $quiz = array_map(fn (array $q): array => ['id' => (string) $q['id'], 'type' => (string) $q['type'], 'answer' => $q['answer']], $lesson->quiz ?? []);
        $fileQuiz = array_map(fn (array $q): array => ['id' => (string) ($q['id'] ?? ''), 'type' => (string) ($q['type'] ?? ''), 'answer' => $q['answer'] ?? null], $meta['quiz'] ?? []);
        if (! ContentFieldComparison::same($quiz, $fileQuiz)) {
            $metaRaw = LessonQuizGenerator::regenerateMeta($metaRaw, array_map(fn (array $q): array => [...$q, 'question' => '', 'options' => []], $quiz));
            $metaChanged[] = 'quiz';
        }

        $frontMatterFields = [];
        foreach (['title', 'teaser'] as $key) {
            $dbValue = $lesson->{$key}['de'] ?? '';
            if (! ContentFieldComparison::same($dbValue, $frontMatter[$key] ?? '')) {
                $frontMatterFields[$key] = $dbValue;
            }
        }
        if (! ContentFieldComparison::same($lesson->objectives ?? [], $frontMatter['objectives'] ?? [])) {
            $frontMatterFields['objectives'] = $lesson->objectives ?? [];
        }

        $mdRaw = LessonMetaGenerator::regenerateFrontMatter($mdRaw, $frontMatterFields);
        $mdChanged = array_keys($frontMatterFields);

        $body = $this->lessonBody($lesson, self::lf((string) ($entry['body'] ?? '')));
        if ($body !== null) {
            $mdRaw = self::withBody($mdRaw, $body);
            $mdChanged[] = $lesson->rich_content !== null ? 'rich_content' : 'body';
        }

        $result->file("lessons/{$lesson->lesson_id}/meta.yml", $metaRaw, $metaChanged);
        $result->file("lessons/{$lesson->lesson_id}/de.md", $mdRaw, $mdChanged);
    }

    /**
     * Neuer Body oder `null`, wenn der Dateistand inhaltlich schon stimmt.
     * Prosa: `rich_content`, falls gesetzt, sonst Legacy-`body`. Quiz-
     * Abschnitt: immer aus `lessons.body` (ADR 0118, `QuizContentPublisher`
     * schreibt ihn dorthin).
     */
    private function lessonBody(Lesson $lesson, string $fileBody): ?string
    {
        $dbBody = self::lf((string) ($lesson->body ?? ''));

        if ($lesson->rich_content === null) {
            return $dbBody === $fileBody ? null : $dbBody;
        }

        $db = QuizContent::splitBody($dbBody);
        $file = QuizContent::splitBody($fileBody);
        $proseSame = $this->serializer->equivalent($lesson->rich_content, $this->converter->convert(self::prose($file)));
        $quizSame = trim($db['quiz_raw']) === trim($file['quiz_raw']);

        if ($proseSame && $quizSame) {
            return null;
        }

        if ($proseSame) {
            // Nur der Quiz-Abschnitt weicht ab -- die Prosa bleibt Zeichen
            // fuer Zeichen, wie sie in der Datei steht.
            return $file['quiz_raw'] !== ''
                ? self::joinLines($file['before'], $db['quiz_raw'], $file['after'])
                : rtrim($fileBody)."\n\n".trim($db['quiz_raw'])."\n";
        }

        return $this->composeLessonBody($lesson->rich_content, $db);
    }

    /**
     * `rich_content` fasst `before` + `after` (den Fusstext hinter dem Quiz)
     * zu EINEM Dokument zusammen (ADR 0118). Stimmen die letzten Bloecke
     * noch mit dem bisherigen `after` ueberein, kommt der Quiz-Abschnitt
     * wieder davor, sonst ans Ende.
     *
     * @param  array<string, mixed>  $richContent
     * @param  array{before: string, quiz_raw: string, after: string}  $db
     */
    private function composeLessonBody(array $richContent, array $db): string
    {
        $quiz = trim($db['quiz_raw']);

        if ($quiz === '') {
            return "\n".$this->serializer->serialize($richContent)."\n";
        }

        $blocks = is_array($richContent['content'] ?? null) ? array_values($richContent['content']) : [];
        $tail = $this->converter->convert(trim($db['after']))['content'];
        $split = count($blocks) - count($tail);

        if ($tail !== [] && $split >= 0 && $this->serializer->equivalent(
            ['type' => 'doc', 'content' => array_slice($blocks, $split)],
            ['type' => 'doc', 'content' => $tail],
        )) {
            $before = [...$richContent, 'content' => array_slice($blocks, 0, $split)];

            return "\n".$this->serializer->serialize($before)."\n\n".$quiz."\n\n".trim($db['after'])."\n";
        }

        return "\n".$this->serializer->serialize($richContent)."\n\n".$quiz."\n";
    }

    private function exportNodes(ContentRepository $files, ?string $id, ContentExportResult $result): void
    {
        $entries = $files->nodes();
        $themenfelder = Themenfeld::query()->pluck('slug', 'id');
        $nodes = Node::query()->when($id !== null, fn ($q) => $q->where('slug', $id))->orderBy('slug')->get();

        foreach ($nodes as $node) {
            $resource = "Node {$node->slug}";
            $entry = $entries[$node->slug] ?? null;

            if ($entry === null || $entry['def_raw'] === null || $entry['md_raw'] === null || $entry['def'] === null) {
                $result->error($resource, "keine Datei-Vorlage unter content/nodes/{$node->slug}/ (nur in Studio angelegt, ADR 0122 offene Frage)");

                continue;
            }

            try {
                $this->exportNode($node, $entry, $themenfelder->get($node->themenfeld_id), $result);
            } catch (UnrepresentableRichContentException|UnsupportedRichContentVersionException $e) {
                $result->error($resource, $e->getMessage());
            }
        }
    }

    /**
     * @param  array<string, mixed>  $entry  ContentRepository::nodes()-Eintrag
     */
    private function exportNode(Node $node, array $entry, ?string $themenfeld, ContentExportResult $result): void
    {
        $def = $entry['def'];
        $frontMatter = $entry['frontmatter'] ?? [];
        $defRaw = self::lf((string) $entry['def_raw']);
        $mdRaw = self::lf((string) $entry['md_raw']);

        $index = [];
        if (! ContentFieldComparison::same($node->status, $def['status'] ?? 'draft')) {
            $index['status'] = $node->status;
        }
        if ($themenfeld !== null && ! ContentFieldComparison::same($themenfeld, $def['themenfeld'] ?? 'dicom')) {
            $index['themenfeld'] = $themenfeld;
        }

        $defFields = [];
        foreach (self::NODE_DEF_DEFAULTS as $key => $default) {
            $dbValue = $node->{$key} ?? $default;

            if (! ContentFieldComparison::same($dbValue, $def[$key] ?? $default)) {
                $defFields[$key] = $dbValue;
            }
        }

        $defRaw = NodeMetaGenerator::regenerateIndex($defRaw, $index);
        $defRaw = NodeMetaGenerator::regenerateDef($defRaw, $defFields);
        $defChanged = [...array_keys($index), ...array_keys($defFields)];

        $hints = array_map(fn (array $h): array => ['id' => (string) $h['id'], 'cost' => (int) $h['cost']], $node->hints ?? []);
        $fileHints = array_map(fn (array $h): array => ['id' => (string) ($h['id'] ?? ''), 'cost' => (int) ($h['cost'] ?? 0)], $def['hints'] ?? []);
        if (! ContentFieldComparison::same($hints, $fileHints)) {
            $defRaw = NodeMetaGenerator::regenerateHints($defRaw, $hints);
            $defChanged[] = 'hints';
        }

        $frontMatterFields = [];
        foreach (['title', 'scenario_title'] as $key) {
            $dbValue = $node->{$key}['de'] ?? '';
            if (! ContentFieldComparison::same($dbValue, $frontMatter[$key] ?? '')) {
                $frontMatterFields[$key] = $dbValue;
            }
        }

        $mdRaw = NodeMetaGenerator::regenerateFrontMatter($mdRaw, $frontMatterFields);
        $mdChanged = array_keys($frontMatterFields);

        $body = $this->nodeBody($node, self::lf((string) ($entry['body'] ?? '')), array_column($hints, 'id'));
        if ($body !== null) {
            $mdRaw = self::withBody($mdRaw, $body);
            $mdChanged[] = $node->rich_content !== null ? 'rich_content' : 'body';
        }

        $result->file("nodes/{$node->slug}/node.yml", $defRaw, $defChanged);
        $result->file("nodes/{$node->slug}/de.md", $mdRaw, $mdChanged);
    }

    /**
     * @param  list<string>  $hintOrder
     */
    private function nodeBody(Node $node, string $fileBody, array $hintOrder): ?string
    {
        $dbBody = self::lf((string) ($node->body ?? ''));
        $richContent = $node->rich_content;

        if ($richContent === null) {
            return $dbBody === $fileBody ? null : $dbBody;
        }

        $fileContent = (new NodePayloadNormalizer($this->converter))->normalize(['body' => $fileBody])['rich_content'];

        if ($this->sameNodeContent($richContent, $fileContent)) {
            return null;
        }

        return "\n".$this->serializer->serializeNodeContent($richContent, $hintOrder)."\n";
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function sameNodeContent(array $a, array $b): bool
    {
        $hintsA = is_array($a['hints'] ?? null) ? $a['hints'] : [];
        $hintsB = is_array($b['hints'] ?? null) ? $b['hints'] : [];

        if (! ContentFieldComparison::same(array_keys($hintsA), array_keys($hintsB)) && ! ContentFieldComparison::same(self::sortedKeys($hintsA), self::sortedKeys($hintsB))) {
            return false;
        }

        foreach (['briefing', 'write_up'] as $section) {
            if (! is_array($a[$section] ?? null) || ! is_array($b[$section] ?? null) || ! $this->serializer->equivalent($a[$section], $b[$section])) {
                return false;
            }
        }

        foreach ($hintsA as $hintId => $document) {
            if (! is_array($document) || ! is_array($hintsB[$hintId] ?? null) || ! $this->serializer->equivalent($document, $hintsB[$hintId])) {
                return false;
            }
        }

        return true;
    }

    private function exportTracks(ContentRepository $files, ?string $id, ContentExportResult $result): void
    {
        $raw = $files->tracksRaw();

        if ($raw === null) {
            $result->error('tracks.yml', 'Datei fehlt');

            return;
        }

        $fileTracks = collect($files->tracks())->keyBy('slug');
        $themenfelder = Themenfeld::query()->pluck('slug', 'id');
        $raw = self::lf($raw);
        $changed = [];

        foreach (Track::query()->when($id !== null, fn ($q) => $q->where('slug', $id))->orderBy('slug')->get() as $track) {
            $fileTrack = $fileTracks->get($track->slug);

            if ($fileTrack === null) {
                $result->error("Track {$track->slug}", 'kein Eintrag in tracks.yml (nur in Studio angelegt, ADR 0122 offene Frage 4)');

                continue;
            }

            $fields = array_filter([
                'themenfeld' => $themenfelder->get($track->themenfeld_id),
                'order' => $track->order,
                'level' => $track->level,
                'hours' => $track->hours,
                'status' => $track->status,
            ], fn (mixed $value, string $key): bool => $value !== null && ! ContentFieldComparison::same($value, $fileTrack[$key] ?? null), ARRAY_FILTER_USE_BOTH);

            if ($fields === []) {
                continue;
            }

            $raw = (string) TrackCatalogGenerator::regenerateFields($raw, $track->slug, $fields);
            foreach (array_keys($fields) as $field) {
                $changed[] = "{$track->slug}.{$field}";
            }
        }

        $result->file('tracks.yml', $raw, $changed);
    }

    /**
     * @param  array{before: string, quiz_raw: string, after: string}  $split
     */
    private static function prose(array $split): string
    {
        return trim($split['after'] !== '' ? $split['before']."\n\n".$split['after'] : $split['before']);
    }

    private static function joinLines(string ...$parts): string
    {
        return implode("\n", array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    private static function withBody(string $mdRaw, string $body): string
    {
        $frontMatter = FrontMatter::parse($mdRaw);
        $lines = explode("\n", $mdRaw);

        return implode("\n", array_slice($lines, 0, max(0, $frontMatter['bodyStartLine'] - 1)))."\n".$body;
    }

    /**
     * @param  array<array-key, mixed>  $map
     * @return list<array-key>
     */
    private static function sortedKeys(array $map): array
    {
        $keys = array_keys($map);
        sort($keys);

        return $keys;
    }

    private static function lf(string $text): string
    {
        return str_replace("\r\n", "\n", $text);
    }
}

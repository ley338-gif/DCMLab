<?php

namespace App\Content\RichContent;

/**
 * Prueft ein Rich-Content-Dokument gegen das Schema aus ADR 0111/0112/0114/
 * 0116 (CMS-7a/CMS-7c/CMS-7d.1): ein strukturiertes JSON-Dokument (`{type: "doc", version,
 * content: [...]}`) ist Quelle der Wahrheit fuer Lesson-/Node-Fliesstext.
 * Das Schema ist bewusst DCMLab-eigen (snake_case, `code_block.text` statt
 * verschachteltem Text-Node) -- TipTap (CMS-7b) ist nur EIN Editor dafuer
 * ueber einen eigenen Adapter, nicht das Schema selbst, ein Dokument muss
 * daher unabhaengig von TipTap validierbar sein, bevor es gespeichert wird.
 *
 * Bewusst kein generisches JSON-Schema-Paket: derselbe Stil wie
 * `ContentValidator` -- eine kleine, lesbare, deterministische PHP-Pruefung
 * ohne zusaetzliche Abhaengigkeit. Eine falsche `doc.version` ist hier ein
 * gewoehnlicher Validierungsbefund (String in der Ergebnisliste) --
 * `RichContentRenderer` dagegen wirft dafuer eine echte Exception (ADR
 * 0112): rendern ist der Fall, in dem ein stiller Fallback ein falsch
 * dargestelltes Dokument waere, validieren nicht.
 *
 * @phpstan-type RichContentIssue string
 */
final class RichContentValidator
{
    private const BLOCK_TYPES = ['paragraph', 'heading', 'bullet_list', 'ordered_list', 'blockquote', 'code_block', 'table', 'self_check', 'callout', 'dicom_tag_table', 'horizontal_rule'];

    private const INLINE_TYPES = ['text', 'glossary_term', 'hard_break'];

    private const MARK_TYPES = ['bold', 'italic', 'code', 'link'];

    private const CODE_BLOCK_VARIANTS = ['code', 'console', 'terminal', 'diagram', 'mermaid', 'dicom_dump'];

    private const CALLOUT_KINDS = ['info', 'warning'];

    private const DICOM_TAG_TABLE_ROW_FIELDS = ['tag', 'keyword', 'vr', 'value'];

    private const CURRENT_VERSION = 1;

    /**
     * @return list<string> leer, wenn das Dokument valide ist
     */
    public function validate(mixed $doc): array
    {
        $issues = [];

        if (! is_array($doc)) {
            return ['doc: muss ein Objekt sein'];
        }

        if (($doc['type'] ?? null) !== 'doc') {
            $issues[] = 'doc.type: muss "doc" sein';
        }

        if (($doc['version'] ?? null) !== self::CURRENT_VERSION) {
            $issues[] = 'doc.version: muss '.self::CURRENT_VERSION.' sein';
        }

        if (! is_array($doc['content'] ?? null) || ! array_is_list($doc['content'])) {
            $issues[] = 'doc.content: muss eine Liste sein';

            return $issues;
        }

        foreach ($doc['content'] as $index => $node) {
            $issues = [...$issues, ...$this->validateBlock($node, "doc.content[{$index}]")];
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function validateBlock(mixed $node, string $path): array
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            return ["{$path}: muss ein Objekt mit \"type\" sein"];
        }

        $type = $node['type'];

        if (! in_array($type, self::BLOCK_TYPES, true)) {
            return ["{$path}.type: unbekannter Block-Typ \"{$type}\""];
        }

        return match ($type) {
            'paragraph' => $this->validateInlineContent($node, $path),
            'heading' => [
                ...$this->validateHeadingLevel($node, $path),
                ...$this->validateInlineContent($node, $path),
            ],
            'bullet_list', 'ordered_list' => $this->validateListItems($node, $path),
            'blockquote' => $this->validateBlockContent($node, $path),
            'code_block' => $this->validateCodeBlock($node, $path),
            'table' => $this->validateTable($node, $path),
            'self_check' => $this->validateSelfCheck($node, $path),
            'callout' => $this->validateCallout($node, $path),
            'dicom_tag_table' => $this->validateDicomTagTable($node, $path),
            'horizontal_rule' => [],
        };
    }

    /**
     * `callout` (ADR 0114, CMS-7c): Info-/Warnkasten -- ein gemeinsamer
     * Blocktyp fuer beide heutigen Arten statt je einem eigenen, damit
     * spaetere Arten (`tip`, `note`, `success`, ...) nur `attrs.kind`
     * erweitern, kein neues Schema brauchen.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function validateCallout(array $node, string $path): array
    {
        $issues = [];
        $kind = $node['attrs']['kind'] ?? null;

        if (! in_array($kind, self::CALLOUT_KINDS, true)) {
            $issues[] = "{$path}.attrs.kind: muss eine von \"".implode('", "', self::CALLOUT_KINDS).'" sein';
        }

        if (isset($node['attrs']['title']) && ! is_string($node['attrs']['title'])) {
            $issues[] = "{$path}.attrs.title: muss ein String sein, wenn gesetzt";
        }

        return [...$issues, ...$this->validateBlockContent($node, $path)];
    }

    /**
     * `dicom_tag_table` (ADR 0114, CMS-7c): fachlich strukturierte Zeilen
     * (Tag/Keyword/VR/Value) statt eines generischen `table`-Blocks --
     * jede Zeile ist bewusst ein reines Datenobjekt ohne eigenen `type`
     * (anders als jeder andere Knoten in diesem Schema), weil sie kein
     * Rich-Content-Block ist, sondern ein geschlossener, homogener
     * Datensatz. Das schafft Raum fuer spaetere Dictionary-Validierung,
     * ohne das Schema selbst nochmal aendern zu muessen.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function validateDicomTagTable(array $node, string $path): array
    {
        if (! is_array($node['content'] ?? null) || ! array_is_list($node['content'])) {
            return ["{$path}.content: muss eine Liste aus Tag-Zeilen sein"];
        }

        $issues = [];

        foreach ($node['content'] as $index => $row) {
            $rowPath = "{$path}.content[{$index}]";

            if (! is_array($row)) {
                $issues[] = "{$rowPath}: muss ein Objekt sein";

                continue;
            }

            foreach (self::DICOM_TAG_TABLE_ROW_FIELDS as $field) {
                if (! is_string($row[$field] ?? null)) {
                    $issues[] = "{$rowPath}.{$field}: muss ein String sein";
                }
            }
        }

        return $issues;
    }

    /**
     * `self_check` (ADR 0112): ein didaktischer Lernbaustein ("Antwort
     * anzeigen") -- fachlich ein eigener Block, kein beliebiges
     * `raw_html`, damit Renderer/Editor ihn gezielt behandeln koennen statt
     * generisches HTML durchzureichen.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function validateSelfCheck(array $node, string $path): array
    {
        $issues = [];

        if (! is_string($node['attrs']['summary'] ?? null) || $node['attrs']['summary'] === '') {
            $issues[] = "{$path}.attrs.summary: muss ein nicht-leerer String sein";
        }

        return [...$issues, ...$this->validateBlockContent($node, $path)];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function validateHeadingLevel(array $node, string $path): array
    {
        $level = $node['attrs']['level'] ?? null;

        if (! is_int($level) || $level < 1 || $level > 6) {
            return ["{$path}.attrs.level: muss eine Zahl zwischen 1 und 6 sein"];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function validateCodeBlock(array $node, string $path): array
    {
        $issues = [];
        $variant = $node['attrs']['variant'] ?? null;

        if (! in_array($variant, self::CODE_BLOCK_VARIANTS, true)) {
            $issues[] = "{$path}.attrs.variant: muss eine von \"".implode('", "', self::CODE_BLOCK_VARIANTS).'" sein';
        }

        if (isset($node['attrs']['language']) && ! is_string($node['attrs']['language'])) {
            $issues[] = "{$path}.attrs.language: muss ein String sein, wenn gesetzt";
        }

        if (! is_string($node['text'] ?? null)) {
            $issues[] = "{$path}.text: muss ein String sein";
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function validateListItems(array $node, string $path): array
    {
        if (! is_array($node['content'] ?? null) || ! array_is_list($node['content'])) {
            return ["{$path}.content: muss eine Liste sein"];
        }

        $issues = [];

        foreach ($node['content'] as $index => $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'list_item') {
                $issues[] = "{$path}.content[{$index}].type: muss \"list_item\" sein";

                continue;
            }

            $issues = [...$issues, ...$this->validateBlockContent($item, "{$path}.content[{$index}]")];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function validateTable(array $node, string $path): array
    {
        if (! is_array($node['content'] ?? null) || ! array_is_list($node['content'])) {
            return ["{$path}.content: muss eine Liste aus table_row sein"];
        }

        $issues = [];

        foreach ($node['content'] as $rowIndex => $row) {
            $rowPath = "{$path}.content[{$rowIndex}]";

            if (! is_array($row) || ($row['type'] ?? null) !== 'table_row' || ! is_array($row['content'] ?? null) || ! array_is_list($row['content'])) {
                $issues[] = "{$rowPath}: muss ein table_row mit einer Liste aus table_cell sein";

                continue;
            }

            foreach ($row['content'] as $cellIndex => $cell) {
                $cellPath = "{$rowPath}.content[{$cellIndex}]";

                if (! is_array($cell) || ($cell['type'] ?? null) !== 'table_cell') {
                    $issues[] = "{$cellPath}.type: muss \"table_cell\" sein";

                    continue;
                }

                if (isset($cell['attrs']['header']) && ! is_bool($cell['attrs']['header'])) {
                    $issues[] = "{$cellPath}.attrs.header: muss ein Boolean sein, wenn gesetzt";
                }

                $issues = [...$issues, ...$this->validateBlockContent($cell, $cellPath)];
            }
        }

        return $issues;
    }

    /**
     * Fuer Bloecke, deren `content` selbst wieder eine Liste von Bloecken ist
     * (list_item, blockquote, table_cell) statt Inline-Content.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function validateBlockContent(array $node, string $path): array
    {
        if (! is_array($node['content'] ?? null) || ! array_is_list($node['content'])) {
            return ["{$path}.content: muss eine Liste sein"];
        }

        $issues = [];

        foreach ($node['content'] as $index => $child) {
            $issues = [...$issues, ...$this->validateBlock($child, "{$path}.content[{$index}]")];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function validateInlineContent(array $node, string $path): array
    {
        if (! is_array($node['content'] ?? null) || ! array_is_list($node['content'])) {
            return ["{$path}.content: muss eine Liste sein"];
        }

        $issues = [];

        foreach ($node['content'] as $index => $inline) {
            $issues = [...$issues, ...$this->validateInline($inline, "{$path}.content[{$index}]")];
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function validateInline(mixed $node, string $path): array
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            return ["{$path}: muss ein Objekt mit \"type\" sein"];
        }

        $type = $node['type'];

        if (! in_array($type, self::INLINE_TYPES, true)) {
            return ["{$path}.type: unbekannter Inline-Typ \"{$type}\""];
        }

        return match ($type) {
            'text' => [
                ...$this->validateTextNode($node, $path),
                ...$this->validateMarks($node, $path),
            ],
            'glossary_term' => is_string($node['attrs']['slug'] ?? null)
                ? []
                : ["{$path}.attrs.slug: muss ein String sein"],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function validateTextNode(array $node, string $path): array
    {
        if (! is_string($node['text'] ?? null) || $node['text'] === '') {
            return ["{$path}.text: muss ein nicht-leerer String sein"];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function validateMarks(array $node, string $path): array
    {
        if (! isset($node['marks'])) {
            return [];
        }

        if (! is_array($node['marks']) || ! array_is_list($node['marks'])) {
            return ["{$path}.marks: muss eine Liste sein, wenn gesetzt"];
        }

        $issues = [];

        foreach ($node['marks'] as $index => $mark) {
            $markPath = "{$path}.marks[{$index}]";

            if (! is_array($mark) || ! is_string($mark['type'] ?? null)) {
                $issues[] = "{$markPath}: muss ein Objekt mit \"type\" sein";

                continue;
            }

            if (! in_array($mark['type'], self::MARK_TYPES, true)) {
                $issues[] = "{$markPath}.type: unbekannter Mark-Typ \"{$mark['type']}\"";

                continue;
            }

            if ($mark['type'] === 'link' && ! is_string($mark['attrs']['href'] ?? null)) {
                $issues[] = "{$markPath}.attrs.href: muss ein String sein";
            }
        }

        return $issues;
    }
}

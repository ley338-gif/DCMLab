<?php

namespace App\Content;

use App\Content\RichContent\RichContentToMarkdownSerializer;

/**
 * Ein einziger Begriff von "Feldwert aus der Datei == Feldwert in der DB"
 * (ADR 0122) -- `content:export` entscheidet damit, ob eine Datei
 * geschrieben werden muss, `content:sync` damit, ob ein uebersprungenes
 * Studio-Feld eine Warnung wert ist. Beide muessen exakt dieselbe Grenze
 * ziehen: eine Schutzregel, die bei rein formalen Unterschieden warnt, lehrt
 * das Falsche (Lehre aus PR #162).
 */
final class ContentFieldComparison
{
    /**
     * Wertgleichheit unabhaengig von Schluesselreihenfolge (`jsonb`) und
     * YAML-Schreibweise (`1.0`/`"1.0"`, `10`/`"10"`).
     */
    public static function same(mixed $a, mixed $b): bool
    {
        return RichContentToMarkdownSerializer::canonical(self::scalarized($a))
            === RichContentToMarkdownSerializer::canonical(self::scalarized($b));
    }

    /**
     * `sandbox` ohne Block, `null` und `{required: false}` ohne dataset/note
     * bedeuten dasselbe; `note: null` aus Studio-Payloads zaehlt nicht
     * (ADR 0122, Phase 0).
     *
     * @return array{required: bool, dataset?: string, note?: string}
     */
    public static function sandbox(mixed $sandbox): array
    {
        $sandbox = is_array($sandbox) ? $sandbox : [];
        $normalized = ['required' => (bool) ($sandbox['required'] ?? false)];

        foreach (['dataset', 'note'] as $key) {
            if (is_string($sandbox[$key] ?? null) && $sandbox[$key] !== '') {
                $normalized[$key] = $sandbox[$key];
            }
        }

        return $normalized;
    }

    /**
     * @return array{node: string|null, optional: bool}
     */
    public static function relatedNode(mixed $relatedNode): array
    {
        $relatedNode = is_array($relatedNode) ? $relatedNode : [];
        $node = $relatedNode['node'] ?? null;

        return [
            'node' => is_string($node) && $node !== '' ? $node : null,
            'optional' => (bool) ($relatedNode['optional'] ?? true),
        ];
    }

    private static function scalarized(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::scalarized(...), $value);
        }

        return is_int($value) || is_float($value) ? (string) $value : $value;
    }
}

<?php

namespace App\Content;

use Symfony\Component\Yaml\Yaml;

/**
 * Splittet eine <locale>.md-Datei in YAML-Frontmatter und Markdown-Body
 * (Abschnitt 4.2/4.7). Der Body beginnt absichtlich bei der echten Zeilennummer
 * der Quelldatei, damit spaetere Fehlermeldungen dorthin zeigen koennen.
 */
final class FrontMatter
{
    /**
     * @return array{attributes: array<string, mixed>, body: string, bodyStartLine: int}
     */
    public static function parse(string $raw): array
    {
        $lines = preg_split('/\R/', $raw) ?: [];

        if (($lines[0] ?? '') !== '---') {
            return ['attributes' => [], 'body' => $raw, 'bodyStartLine' => 1];
        }

        $closingIndex = null;
        for ($i = 1; $i < count($lines); $i++) {
            if ($lines[$i] === '---') {
                $closingIndex = $i;
                break;
            }
        }

        if ($closingIndex === null) {
            return ['attributes' => [], 'body' => $raw, 'bodyStartLine' => 1];
        }

        $yaml = implode("\n", array_slice($lines, 1, $closingIndex - 1));
        $attributes = Yaml::parse($yaml) ?? [];
        $body = implode("\n", array_slice($lines, $closingIndex + 1));

        return [
            'attributes' => is_array($attributes) ? $attributes : [],
            'body' => $body,
            'bodyStartLine' => $closingIndex + 2,
        ];
    }
}

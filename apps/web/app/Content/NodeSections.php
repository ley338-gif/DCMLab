<?php

namespace App\Content;

/**
 * Splittet den Markdown-Body einer Node in ihre drei Pflichtabschnitte
 * (Abschnitt 5.1): `## Briefing`, `## Hints` (mit `### h1`...`### h3`) und
 * `## Write-up`. Diese Ueberschriften sind Schluessel, keine Prosa -- der
 * Parser findet die Abschnitte darueber, nicht per Position.
 */
final class NodeSections
{
    /**
     * @return array{briefing: string, hints: array<string, string>, write_up: string}
     */
    public static function parse(string $body): array
    {
        $topLevel = self::splitByHeadings($body, '##');

        return [
            'briefing' => trim($topLevel['Briefing'] ?? ''),
            'hints' => self::splitByHeadings($topLevel['Hints'] ?? '', '###'),
            'write_up' => trim($topLevel['Write-up'] ?? ''),
        ];
    }

    /**
     * @return array<string, string> Ueberschrift (ohne #-Praefix) => Inhalt bis zur naechsten
     *                               gleich- oder hoeherrangigen Ueberschrift, getrimmt.
     */
    private static function splitByHeadings(string $markdown, string $marker): array
    {
        $pattern = '/^'.preg_quote($marker, '/').' (.+)$/m';
        $parts = preg_split($pattern, $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false || count($parts) < 2) {
            return [];
        }

        $sections = [];

        for ($i = 1; $i < count($parts); $i += 2) {
            $heading = trim($parts[$i]);
            $content = self::stripTrailingRule($parts[$i + 1] ?? '');
            $sections[$heading] = trim($content);
        }

        return $sections;
    }

    private static function stripTrailingRule(string $content): string
    {
        return preg_replace('/\R+---\s*$/', '', rtrim($content)) ?? $content;
    }
}

<?php

namespace App\Content;

use Symfony\Component\Yaml\Yaml;

/**
 * Erzeugt einzelne Einstellungsfelder in `exam.yml` und der Frontmatter von
 * `de.md` aus einem Entwurf (ADR 0082, W6.3) -- dasselbe chirurgische Muster
 * wie `LessonMetaGenerator`: nur die genannten Felder werden ersetzt, jede
 * andere Zeile (inklusive des `questions:`-Pools) bleibt unangetastet. Der
 * Fragenpool selbst ist bewusst nicht Teil dieses Generators, siehe
 * `ExamActivity`-Klassendoc.
 */
final class ExamMetaGenerator
{
    private const META_FIELDS = ['pass_percent', 'draw', 'duration_minutes', 'shuffle', 'min_per_lesson'];

    private const FRONT_MATTER_FIELDS = ['title', 'intro'];

    /**
     * @param  array<string, mixed>  $fields  nur die Schluessel aus META_FIELDS werden ausgewertet, andere ignoriert
     */
    public static function regenerateMeta(string $metaRaw, array $fields): string
    {
        foreach (self::META_FIELDS as $key) {
            if (! array_key_exists($key, $fields)) {
                continue;
            }

            $metaRaw = self::replaceLine($metaRaw, $key, "{$key}: ".self::dumpValue($fields[$key]));
        }

        return $metaRaw;
    }

    /**
     * @param  array<string, mixed>  $fields  nur die Schluessel aus FRONT_MATTER_FIELDS werden ausgewertet, andere ignoriert
     */
    public static function regenerateFrontMatter(string $mdRaw, array $fields): string
    {
        $lines = preg_split('/\R/', $mdRaw) ?: [];

        if (($lines[0] ?? '') !== '---') {
            return $mdRaw;
        }

        $closingIndex = null;

        for ($i = 1; $i < count($lines); $i++) {
            if ($lines[$i] === '---') {
                $closingIndex = $i;
                break;
            }
        }

        if ($closingIndex === null) {
            return $mdRaw;
        }

        $frontMatterBlock = implode("\n", array_slice($lines, 0, $closingIndex + 1));

        foreach (self::FRONT_MATTER_FIELDS as $key) {
            if (! array_key_exists($key, $fields)) {
                continue;
            }

            $frontMatterBlock = self::replaceLine($frontMatterBlock, $key, "{$key}: ".self::dumpValue($fields[$key]));
        }

        $rest = implode("\n", array_slice($lines, $closingIndex + 1));

        return $frontMatterBlock."\n".$rest;
    }

    private static function dumpValue(mixed $value): string
    {
        if (is_array($value)) {
            return '['.implode(', ', array_map(fn (mixed $item): string => trim(Yaml::dump($item)), $value)).']';
        }

        return trim(Yaml::dump($value));
    }

    /**
     * Ersetzt die erste Zeile, die mit `key:` beginnt; haengt eine neue
     * Zeile an, wenn das Feld noch nicht existiert. `\r?\n`-sicher, siehe
     * LessonQuizGenerator fuer den Grund (CRLF im echten Bestand).
     */
    private static function replaceLine(string $raw, string $key, string $newLine): string
    {
        $pattern = '/^'.preg_quote($key, '/').':[^\r\n]*/m';

        if (preg_match($pattern, $raw) === 1) {
            return preg_replace($pattern, $newLine, $raw, 1);
        }

        return rtrim($raw, "\r\n")."\n".$newLine;
    }
}

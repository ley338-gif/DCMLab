<?php

namespace App\Content;

use Symfony\Component\Yaml\Yaml;

/**
 * Erzeugt einzelne Felder in `node.yml` und der Frontmatter von `de.md` aus
 * einem Entwurf (ADR 0108, CMS-6d Teil 2) -- chirurgisch wie
 * `LessonMetaGenerator`: nur die genannten Felder werden ersetzt, jede andere
 * Zeile bleibt unangetastet -- insbesondere `environment:`/`flag:`, die
 * bewusst ausserhalb des Autorenmodells bleiben (ADR 0107).
 * `regenerateHints()` ersetzt den `hints:`-Block nach demselben Block-Muster
 * wie `LessonQuizGenerator`s `quiz:`-Block -- nur id/cost, der Hint-**Text**
 * selbst steckt im Body (`### h<n>`-Abschnitte, ADR 0107).
 */
final class NodeMetaGenerator
{
    private const DEF_FIELDS = ['difficulty', 'points', 'category', 'interaction', 'estimated_minutes', 'skills', 'related_lessons'];

    private const FRONT_MATTER_FIELDS = ['title', 'scenario_title'];

    /**
     * @param  array<string, mixed>  $fields  nur die Schluessel aus DEF_FIELDS werden ausgewertet, andere ignoriert
     */
    public static function regenerateDef(string $defRaw, array $fields): string
    {
        foreach (self::DEF_FIELDS as $key) {
            if (! array_key_exists($key, $fields)) {
                continue;
            }

            $defRaw = self::replaceLine($defRaw, $key, "{$key}: ".self::dumpValue($fields[$key]));
        }

        return $defRaw;
    }

    /**
     * @param  list<array{id: string, cost: int}>  $hints
     */
    public static function regenerateHints(string $defRaw, array $hints): string
    {
        $lines = ['hints:'];

        foreach ($hints as $hint) {
            $lines[] = "  - id: {$hint['id']}";
            $lines[] = "    cost: {$hint['cost']}";
        }

        return self::replaceBlock($defRaw, 'hints', implode("\n", $lines)."\n");
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

        // Innerhalb der Delimiter ersetzen/einfuegen statt eine Zeile blind
        // ans Ende des ganzen Blocks anzuhaengen (das laege hinter dem
        // schliessenden "---" und waere kein Frontmatter-Feld mehr) -- z. B.
        // bei einer frisch in Studio angelegten Node (ADR 0109), deren de.md
        // noch keine der beiden Felder traegt.
        $frontMatterLines = array_slice($lines, 1, $closingIndex - 1);
        $seen = [];

        foreach ($frontMatterLines as $index => $line) {
            foreach (self::FRONT_MATTER_FIELDS as $key) {
                if (array_key_exists($key, $fields) && preg_match('/^'.preg_quote($key, '/').':/', $line) === 1) {
                    $frontMatterLines[$index] = "{$key}: ".self::dumpValue($fields[$key]);
                    $seen[$key] = true;
                }
            }
        }

        foreach (self::FRONT_MATTER_FIELDS as $key) {
            if (array_key_exists($key, $fields) && ! ($seen[$key] ?? false)) {
                $frontMatterLines[] = "{$key}: ".self::dumpValue($fields[$key]);
            }
        }

        $rest = implode("\n", array_slice($lines, $closingIndex + 1));

        return "---\n".implode("\n", $frontMatterLines)."\n---\n".$rest;
    }

    private static function dumpValue(mixed $value): string
    {
        if (is_array($value)) {
            return '['.implode(', ', array_map(fn (mixed $item): string => trim(Yaml::dump($item)), $value)).']';
        }

        return trim(Yaml::dump($value));
    }

    /**
     * Ersetzt die erste Zeile, die mit `key:` beginnt; haengt eine neue Zeile
     * an, wenn das Feld noch nicht existiert. `\r?\n`-sicher, siehe
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

    /**
     * Ersetzt den gesamten mehrzeiligen Block, der mit `key:` beginnt (die
     * Schluesselzeile selbst plus jede folgende eingerueckte oder leere
     * Zeile) -- dasselbe Muster wie `LessonMetaGenerator::replaceBlock()`.
     */
    private static function replaceBlock(string $raw, string $key, string $newBlock): string
    {
        $pattern = '/^'.preg_quote($key, '/').':\r?\n(?:[ \t].*\r?\n|\r?\n)*/m';

        if (preg_match($pattern, $raw) === 1) {
            return preg_replace($pattern, $newBlock, $raw, 1);
        }

        return rtrim($raw, "\r\n")."\n".$newBlock;
    }
}

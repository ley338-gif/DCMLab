<?php

namespace App\Content;

use Symfony\Component\Yaml\Yaml;

/**
 * Ersetzt chirurgisch einzelne Felder EINES Eintrags in `content/tracks.yml`
 * (ADR 0122, `content:export`) -- dasselbe Blockmuster wie
 * `AchievementCatalogGenerator` (flache Top-Level-Liste `- slug: ...`, ein
 * Block reicht bis zur naechsten `- slug:`-Zeile), aber feldweise statt den
 * ganzen Eintrag neu zu schreiben: Kommentare, `title_key` und jede nicht
 * genannte Zeile bleiben unangetastet. Ein unbekannter Slug wird NICHT
 * angelegt -- ein nur in Studio existierender Track hat kein Datei-Vorbild,
 * das ist eine eigene Entscheidung (ADR 0122, offene Frage 4).
 */
final class TrackCatalogGenerator
{
    /**
     * @param  array<string, scalar>  $fields  z. B. themenfeld, order, level, hours, status
     */
    public static function regenerateFields(string $raw, string $slug, array $fields): ?string
    {
        $lines = preg_split('/\r?\n/', $raw) ?: [];
        [$start, $end] = self::blockRange($lines, $slug) ?? [null, null];

        if ($start === null) {
            return null;
        }

        foreach ($fields as $key => $value) {
            $newLine = "  {$key}: ".trim(Yaml::dump($value));
            $found = false;

            for ($i = $start + 1; $i < $end; $i++) {
                if (preg_match('/^\s+'.preg_quote($key, '/').':/', $lines[$i]) === 1) {
                    $lines[$i] = $newLine;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                // Hinter die letzte nicht-leere Zeile des Blocks, nicht hinter
                // die Leerzeile vor dem naechsten Eintrag.
                $insertAt = $end;
                while ($insertAt > $start + 1 && trim($lines[$insertAt - 1]) === '') {
                    $insertAt--;
                }

                array_splice($lines, $insertAt, 0, [$newLine]);
                $end++;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $lines
     * @return array{0: int, 1: int}|null [startLine, endLineExclusive]
     */
    private static function blockRange(array $lines, string $slug): ?array
    {
        $start = null;

        foreach ($lines as $i => $line) {
            if (preg_match('/^-\s+slug:\s*(\S+)/', $line, $match) !== 1) {
                continue;
            }

            if ($start !== null) {
                return [$start, $i];
            }

            if (trim($match[1], '"\'') === $slug) {
                $start = $i;
            }
        }

        return $start === null ? null : [$start, count($lines)];
    }
}

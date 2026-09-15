<?php

namespace App\Content;

/**
 * Der Kopfkommentar, der eine erzeugte Datei unter content/** als solche
 * ausweist (ADR 0071 "Schutz der Einbahnstraße", ADR 0074). `apply()` ist
 * idempotent: ruft `ContentWriter` dieselbe Datei erneut auf (z. B. weil
 * `serialize()` heute noch den unveraenderten Ist-Zustand zurueckgibt, siehe
 * ADR 0073), verdoppelt sich der Marker nicht.
 */
final class GeneratedFileMarker
{
    public const TEXT = 'Erzeugt ueber den Autoren-Editor -- nicht von Hand bearbeiten. Siehe App\Activities\ActivityContract.';

    public static function apply(string $path, string $contents): string
    {
        return str_ends_with($path, '.md')
            ? self::applyToMarkdown($contents)
            : self::applyToYaml($contents);
    }

    private static function applyToYaml(string $contents): string
    {
        $line = '# '.self::TEXT;

        if (str_starts_with($contents, $line)) {
            return $contents;
        }

        return $line."\n".$contents;
    }

    private static function applyToMarkdown(string $contents): string
    {
        $line = '<!-- '.self::TEXT.' -->';
        $lines = preg_split('/\R/', $contents) ?: [];

        if (($lines[0] ?? '') !== '---') {
            return str_starts_with($contents, $line) ? $contents : $line."\n\n".$contents;
        }

        $closingIndex = null;
        for ($i = 1; $i < count($lines); $i++) {
            if ($lines[$i] === '---') {
                $closingIndex = $i;
                break;
            }
        }

        if ($closingIndex === null) {
            return $contents;
        }

        if (($lines[$closingIndex + 1] ?? null) === '' && ($lines[$closingIndex + 2] ?? null) === $line) {
            return $contents;
        }

        array_splice($lines, $closingIndex + 1, 0, ['', $line]);

        return implode("\n", $lines);
    }
}

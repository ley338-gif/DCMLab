<?php

namespace App\Content;

/**
 * Findet die Zeilennummer der ersten Zeile, die eine Nadel enthaelt --
 * fuer Fehlermeldungen, die auf YAML-Schluessel zeigen sollen, ohne einen
 * vollstaendigen zeilenbewussten YAML-Parser zu brauchen.
 */
final class LineFinder
{
    public static function firstLineContaining(string $raw, string $needle, int $fallback = 1): int
    {
        $lines = preg_split('/\R/', $raw) ?: [];

        foreach ($lines as $index => $line) {
            if (str_contains($line, $needle)) {
                return $index + 1;
            }
        }

        return $fallback;
    }
}

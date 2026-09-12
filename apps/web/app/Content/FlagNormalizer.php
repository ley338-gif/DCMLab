<?php

namespace App\Content;

/**
 * Normalisierung fuer Flag-Werte (Abschnitt 5.2): trimmen, Mehrfach-Leerzeichen
 * auf eines, bei case_sensitive: false zusaetzlich lowercase. Dieselbe Regel
 * gilt beim Hashen in `content:build` (App\Console\Commands\ContentBuild) und
 * beim Pruefen der Eingabe in der Engine (services/engine/app/flag.py) --
 * beide Implementierungen muessen bei jeder Aenderung synchron bleiben.
 */
final class FlagNormalizer
{
    public static function normalize(string $value, bool $caseSensitive): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $caseSensitive ? $value : mb_strtolower($value);
    }

    public static function hash(string $value, bool $caseSensitive): string
    {
        return hash('sha256', self::normalize($value, $caseSensitive));
    }
}

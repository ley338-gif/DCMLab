<?php

namespace App\Content;

use Symfony\Component\Yaml\Yaml;

/**
 * Erzeugt einzelne Felder in `meta.yml` und der Frontmatter von `de.md` aus
 * einem Entwurf (ADR 0080/0081/0089, W6.2) -- chirurgisch wie
 * `LessonQuizGenerator`: nur die genannten Felder werden ersetzt, jede
 * andere Zeile (Kommentare eingeschlossen) bleibt unangetastet.
 * META_FIELDS/FRONT_MATTER_FIELDS decken einzeilige Felder ab (Skalar oder
 * Inline-Liste `[a, b]`); `regenerateSandbox()`/`regenerateRelatedNode()`
 * (ADR 0089, Feld umbenannt von `lab` auf `related_node` in CMS-8a, um die
 * Kollision mit dem neuen Lab-Activity-Typ zu vermeiden) ersetzen die
 * verschachtelten `sandbox:`/`related_node:`-Bloecke nach demselben
 * Block-Muster wie `LessonQuizGenerator`s `quiz:`-Block,
 * `regenerateFrontMatter()`s `objectives`-Zweig die mehrzeilige Liste in
 * der Frontmatter.
 */
final class LessonMetaGenerator
{
    private const META_FIELDS = ['level', 'duration_minutes', 'tools', 'requires', 'glossary_terms', 'objectives_count'];

    private const FRONT_MATTER_FIELDS = ['title', 'teaser'];

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
     * Index-Felder, die kein Autoren-Entwurf traegt, `content:export`
     * (ADR 0122) aber aus dem DB-Stand zurueckschreiben muss: `status`,
     * `track` (Slug) und `order` -- die beiden letzten verschiebt Studio
     * (`StudioTrackController::moveLesson()`/`reorderLessons()`). Bewusst
     * eine eigene Methode statt einer Erweiterung von META_FIELDS, damit
     * `LessonActivity::serialize()` weiterhin nichts davon anfasst.
     *
     * @param  array{status?: string, track?: string, order?: int}  $fields
     */
    public static function regenerateIndex(string $metaRaw, array $fields): string
    {
        foreach (['track', 'order', 'status'] as $key) {
            if (array_key_exists($key, $fields)) {
                $metaRaw = self::replaceLine($metaRaw, $key, "{$key}: ".self::dumpValue($fields[$key]));
            }
        }

        return $metaRaw;
    }

    /**
     * `sandbox: {required, dataset?, note?}` (ADR 0089) -- `dataset`/`note`
     * werden nur geschrieben, wenn sie einen Wert tragen (leer/null lassen
     * die Zeile ganz weg, wie im echten Bestand: `sandbox.dataset` fehlt
     * z. B. komplett, wenn `required: false`).
     *
     * @param  array{required: bool, dataset?: string|null, note?: string|null}  $sandbox
     */
    public static function regenerateSandbox(string $metaRaw, array $sandbox): string
    {
        $lines = ['sandbox:', '  required: '.self::dumpValue((bool) $sandbox['required'])];

        if (! empty($sandbox['dataset'])) {
            $lines[] = '  dataset: '.self::dumpValue($sandbox['dataset']);
        }

        if (! empty($sandbox['note'])) {
            $lines[] = '  note: '.self::dumpValue($sandbox['note']);
        }

        return self::replaceBlock($metaRaw, 'sandbox', implode("\n", $lines)."\n");
    }

    /**
     * `related_node: {node, optional}` (ADR 0089; Feldname seit CMS-8a --
     * vorher `lab`, umbenannt zur Vermeidung der Kollision mit dem neuen
     * Lab-Activity-Typ) -- `node` steht im echten Bestand immer als Zeile
     * da, auch wenn keine Node zugeordnet ist (`node: null`).
     *
     * @param  array{node?: string|null, optional?: bool}  $relatedNode
     */
    public static function regenerateRelatedNode(string $metaRaw, array $relatedNode): string
    {
        $node = $relatedNode['node'] ?? null;
        $lines = [
            'related_node:',
            '  node: '.($node === null || $node === '' ? 'null' : self::dumpValue($node)),
            '  optional: '.self::dumpValue((bool) ($relatedNode['optional'] ?? true)),
        ];

        return self::replaceBlock($metaRaw, 'related_node', implode("\n", $lines)."\n");
    }

    /**
     * @param  array<string, mixed>  $fields  nur die Schluessel aus FRONT_MATTER_FIELDS und `objectives` werden ausgewertet, andere ignoriert
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

        if (array_key_exists('objectives', $fields)) {
            /** @var list<string> $objectives */
            $objectives = $fields['objectives'];
            $objectivesLines = ['objectives:'];

            foreach ($objectives as $objective) {
                $objectivesLines[] = '  - '.self::dumpValue($objective);
            }

            $frontMatterBlock = self::replaceBlock(
                $frontMatterBlock,
                'objectives',
                implode("\n", $objectivesLines)."\n",
            );
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

    /**
     * Ersetzt den gesamten mehrzeiligen Block, der mit `key:` beginnt (die
     * Schluesselzeile selbst plus jede folgende eingerueckte oder leere
     * Zeile) -- dasselbe Muster wie `LessonQuizGenerator::regenerateMeta()`
     * fuer den `quiz:`-Block, hier fuer `sandbox:`/`lab:`/`objectives:`
     * wiederverwendet. Haengt den Block ans Ende an, wenn der Schluessel
     * noch nicht existiert.
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

<?php

namespace App\Content;

use Symfony\Component\Yaml\Yaml;

/**
 * Ersetzt oder ergaenzt chirurgisch genau einen Eintrag in
 * `content/achievements.yml` (ADR 0083, W6.4) -- anders als
 * `LessonQuizGenerator`/`ExamMetaGenerator` gibt es hier keinen
 * uebergeordneten Schluessel (`quiz:`), sondern eine flache Top-Level-Liste
 * (`- slug: ...`); jeder Block reicht von seiner `- slug:`-Zeile bis
 * (ausschliesslich) zur naechsten `- slug:`-Zeile oder Dateiende. Jeder
 * andere Eintrag (inklusive Kommentare vor dem ersten Eintrag) bleibt Zeile
 * fuer Zeile erhalten. Existiert der Slug noch nicht, wird ein neuer Block
 * ans Ende angehaengt.
 */
final class AchievementCatalogGenerator
{
    /**
     * @param  array<string, mixed>  $fields  Formularfelder, siehe AchievementEditorController::validatedFields()
     */
    public static function regenerateEntry(string $raw, string $slug, array $fields): string
    {
        $lines = $raw === '' ? [] : (preg_split('/\r?\n/', $raw) ?: []);
        $blocks = self::blockRanges($lines);
        $newBlock = self::dumpEntryLines($slug, $fields);

        if (isset($blocks[$slug])) {
            [$start, $end] = $blocks[$slug];

            $trailingBlank = 0;
            while ($end - $trailingBlank > $start && trim($lines[$end - $trailingBlank - 1]) === '') {
                $trailingBlank++;
            }

            array_splice($lines, $start, $end - $start, [
                ...$newBlock,
                ...array_fill(0, $trailingBlank, ''),
            ]);

            return implode("\n", $lines)."\n";
        }

        if ($lines !== [] && trim(end($lines)) !== '') {
            $lines[] = '';
        }

        array_push($lines, ...$newBlock);

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, array{0: int, 1: int}> slug => [startLine, endLineExclusive]
     */
    private static function blockRanges(array $lines): array
    {
        $blocks = [];
        $currentSlug = null;
        $currentStart = null;

        foreach ($lines as $i => $line) {
            if (preg_match('/^-\s+slug:\s*(\S+)/', $line, $match) === 1) {
                if ($currentSlug !== null) {
                    $blocks[$currentSlug] = [$currentStart, $i];
                }

                $currentSlug = $match[1];
                $currentStart = $i;
            }
        }

        if ($currentSlug !== null) {
            $blocks[$currentSlug] = [$currentStart, count($lines)];
        }

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return list<string>
     */
    private static function dumpEntryLines(string $slug, array $fields): array
    {
        $lines = [
            "- slug: {$slug}",
            '  name: '.self::dumpScalar($fields['name']),
            '  description: '.self::dumpScalar($fields['description']),
            '  image: '.self::dumpScalar($fields['image']),
            '  category: '.self::dumpScalar($fields['category']),
        ];

        if (! empty($fields['rarity'])) {
            $lines[] = '  rarity: '.self::dumpScalar($fields['rarity']);
        }

        if (! empty($fields['scope'])) {
            $lines[] = '  scope: '.self::dumpScalar($fields['scope']);
        }

        $lines[] = '  points: '.self::dumpScalar($fields['points']);
        $lines[] = '  is_hidden: '.self::dumpScalar($fields['is_hidden']);
        $lines[] = '  sort_order: '.self::dumpScalar($fields['sort_order']);

        /** @var array<string, mixed>|null $unlockWhen */
        $unlockWhen = $fields['unlock_when'] ?? null;
        $type = $unlockWhen['type'] ?? null;

        if ($unlockWhen !== null && $type !== null && $type !== '') {
            $lines[] = '  unlock_when:';
            $lines[] = '    type: '.self::dumpScalar($type);

            foreach (['activity_type', 'key', 'track'] as $subField) {
                if (! empty($unlockWhen[$subField])) {
                    $lines[] = "    {$subField}: ".self::dumpScalar($unlockWhen[$subField]);
                }
            }
        }

        return $lines;
    }

    private static function dumpScalar(mixed $value): string
    {
        return trim(Yaml::dump($value));
    }
}

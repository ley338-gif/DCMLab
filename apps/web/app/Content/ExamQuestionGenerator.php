<?php

namespace App\Content;

use Symfony\Component\Yaml\Yaml;

/**
 * Erzeugt den `questions:`-Block in `exam.yml` und die `### fNN — ...`-
 * Abschnitte in `de.md` aus einer strukturierten Fragenliste (ADR 0090,
 * W6.3): das Gegenstueck zu `ExamContent`, das dieselben Fragen wieder
 * einliest. Chirurgisch statt vollstaendig neu zu dumpen, nach demselben
 * Grundsatz wie `LessonQuizGenerator`.
 *
 * Anders als beim Lektions-Quiz ist der GESAMTE Fragenpool die Einheit,
 * nicht eine einzelne Karte -- ein Entwurf ersetzt immer die komplette
 * Liste (der Autoren-Editor zeigt dafuer eine Pool-Uebersicht gegen den
 * laufenden Entwurf, siehe ExamEditorController::coverage()).
 *
 * @phpstan-type ExamQuestionDraft array{id: string, is_ref: bool, ref_lesson?: string, ref_question?: string, type?: string, question?: string, options?: list<string>, answer?: mixed, explanation?: string, lesson: string, review: list<array{lesson: string, anchor: string}>, difficulty: int, tags: list<string>}
 */
final class ExamQuestionGenerator
{
    /**
     * @param  list<array<string, mixed>>  $questions
     */
    public static function regenerateMeta(string $metaRaw, array $questions): string
    {
        $lines = ['questions:'];

        foreach ($questions as $question) {
            $lines[] = "  - id: {$question['id']}";

            if (! empty($question['is_ref'])) {
                $lines[] = '    ref: '.self::dumpRef($question['ref_lesson'], $question['ref_question']);
            } else {
                $lines[] = "    type: {$question['type']}";
                $lines[] = '    answer: '.self::dumpAnswer($question['answer']);
            }

            $lines[] = '    lesson: '.self::dumpScalar($question['lesson']);

            /** @var list<array{lesson: string, anchor: string}> $review */
            $review = $question['review'];

            if (count($review) === 1) {
                $lines[] = '    review: '.self::dumpReviewInline($review[0]);
            } else {
                $lines[] = '    review:';
                foreach ($review as $target) {
                    $lines[] = '      - '.self::dumpReviewInline($target);
                }
            }

            $lines[] = '    difficulty: '.self::dumpScalar($question['difficulty']);
            $lines[] = '    tags: '.self::dumpTags($question['tags'] ?? []);
        }

        $newBlock = implode("\n", $lines)."\n";

        return self::replaceBlock($metaRaw, 'questions', $newBlock);
    }

    /**
     * Ersetzt den GESAMTEN Fragentext-Teil von `de.md` (alles nach der
     * Frontmatter) -- anders als bei der Lektion gibt es hier keinen
     * umschliessenden Abschnitt und keinen Text danach, der Koerper
     * besteht ausschliesslich aus den Fragenbloecken. `ref`-Fragen
     * bekommen keinen eigenen Block (ADR 0083: eine Erklaerung fuer
     * `ref`-Fragen ist bewusst noch nicht Teil dieses Editors).
     *
     * @param  list<array<string, mixed>>  $questions
     */
    public static function regenerateBody(array $questions): string
    {
        $blocks = [];

        foreach ($questions as $question) {
            if (! empty($question['is_ref'])) {
                continue;
            }

            $blocks[] = self::renderBlock($question);
        }

        return implode("\n\n", $blocks)."\n";
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private static function renderBlock(array $question): string
    {
        $type = (string) $question['type'];
        $suffix = match ($type) {
            'multi' => ' *(Mehrfachauswahl)*',
            'input' => ' *(Freitext)*',
            default => '',
        };

        $lines = ["### {$question['id']} — {$question['question']}{$suffix}", ''];

        if ($type === 'single' || $type === 'multi') {
            /** @var list<string> $options */
            $options = $question['options'] ?? [];
            foreach ($options as $index => $option) {
                $lines[] = ($index + 1).". {$option}";
            }
            $lines[] = '';
        } elseif ($type === 'truefalse') {
            $lines[] = '**Richtig / Falsch**';
            $lines[] = '';
        }

        $lines[] = "**Erklärung:** {$question['explanation']}";

        return implode("\n", $lines);
    }

    private static function dumpRef(string $lesson, string $question): string
    {
        return '{ lesson: '.self::dumpScalar($lesson).', question: '.self::dumpScalar($question).' }';
    }

    /**
     * @param  array{lesson: string, anchor: string}  $target
     */
    private static function dumpReviewInline(array $target): string
    {
        return '{ lesson: '.self::dumpScalar($target['lesson']).', anchor: '.self::dumpScalar($target['anchor']).' }';
    }

    /**
     * @param  list<string>  $tags
     */
    private static function dumpTags(array $tags): string
    {
        return '['.implode(', ', array_map(fn (string $tag): string => self::dumpScalar($tag), $tags)).']';
    }

    /**
     * `answer` ist je nach Fragetyp ein Index (single), eine Liste von
     * Indizes (multi), ein Boolean (truefalse) oder ein exakter String
     * (input) -- siehe `LessonQuizGenerator::dumpAnswer()` fuer denselben
     * Grundsatz.
     */
    private static function dumpAnswer(mixed $answer): string
    {
        if (is_array($answer)) {
            return '['.implode(', ', array_map(self::dumpAnswer(...), $answer)).']';
        }

        if (is_bool($answer) || is_int($answer)) {
            return Yaml::dump($answer);
        }

        return trim(Yaml::dump((string) $answer));
    }

    private static function dumpScalar(mixed $value): string
    {
        return trim(Yaml::dump($value));
    }

    /**
     * Ersetzt den gesamten mehrzeiligen Block, der mit `key:` beginnt --
     * dasselbe Muster wie `LessonMetaGenerator::replaceBlock()`.
     */
    private static function replaceBlock(string $raw, string $key, string $newBlock): string
    {
        $pattern = '/^'.preg_quote($key, '/').':\r?\n(?:[ \t].*\r?\n|\r?\n)*/m';

        if (preg_match($pattern, $raw) === 1) {
            return preg_replace($pattern, $newBlock, $raw, 1);
        }

        return rtrim($raw, "\r\n")."\n\n".$newBlock;
    }
}

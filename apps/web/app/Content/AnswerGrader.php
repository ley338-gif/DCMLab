<?php

namespace App\Content;

/**
 * Bewertet eine eingesendete Antwort gegen die serverseitig geladene
 * richtige Antwort -- geteilt zwischen dem Lektions-Quiz (QuizController,
 * Typen single/multi/input, seit P10.59) und der Track-Abschlusspruefung
 * (ExamController, zusaetzlich truefalse, seit P10.60). Reine Extraktion:
 * fuer die drei bestehenden Typen unveraendertes Verhalten.
 */
final class AnswerGrader
{
    public static function isCorrect(string $type, mixed $submitted, mixed $correctAnswer): bool
    {
        return match ($type) {
            'single' => (int) $submitted === (int) $correctAnswer,
            'multi' => is_array($submitted)
                && is_array($correctAnswer)
                && self::sameIntSet($submitted, $correctAnswer),
            'truefalse' => is_bool($submitted) && $submitted === (bool) $correctAnswer,
            'input' => is_string($submitted)
                && trim(mb_strtolower($submitted)) === trim(mb_strtolower((string) $correctAnswer)),
            default => false,
        };
    }

    /**
     * @param  array<int, mixed>  $a
     * @param  array<int, mixed>  $b
     */
    private static function sameIntSet(array $a, array $b): bool
    {
        $normalize = fn (array $values): array => collect($values)->map(fn ($v) => (int) $v)->sort()->values()->all();

        return $normalize($a) === $normalize($b);
    }
}

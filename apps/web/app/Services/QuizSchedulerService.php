<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\QuizReview;
use App\Models\User;

/**
 * Vereinfachter, binaerer SM-2 (Abschnitt 4.7, "quiz: # Wissenskarten fuer
 * Spaced Repetition"). Das originale SM-2 bewertet mit einer 0-5-Skala --
 * unsere Fragen sind binaer richtig/falsch (single/multi/input-Grading kennt
 * kein "halb richtig"), deshalb eine bewusst dokumentierte Vereinfachung,
 * kein 1:1-SM-2.
 */
final class QuizSchedulerService
{
    private const MIN_EASE_FACTOR = 1.3;

    private const MAX_EASE_FACTOR = 2.5;

    // Ohne Deckel waechst das Intervall bei vielen richtigen Antworten
    // hintereinander exponentiell (ease_factor^n) und ueberschreitet
    // irgendwann den Bereich, den Carbon/PHP-Datumsarithmetik noch
    // verarbeiten kann -- 10 Jahre sind fuer eine Wiederholungskarte ohnehin
    // praktisch "nie wieder faellig", ein hoeherer Wert bringt nichts.
    private const MAX_INTERVAL_DAYS = 3650;

    public function recordAnswer(User $user, Lesson $lesson, string $questionId, bool $correct): QuizReview
    {
        $review = QuizReview::firstOrNew([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'question_id' => $questionId,
        ]);

        if (! $review->exists) {
            $review->repetitions = 0;
            $review->ease_factor = self::MAX_EASE_FACTOR;
            $review->interval_days = 0;
        }

        if ($correct) {
            $review->repetitions++;
            $review->interval_days = min(self::MAX_INTERVAL_DAYS, match ($review->repetitions) {
                1 => 1,
                2 => 6,
                default => (int) round($review->interval_days * $review->ease_factor),
            });
            $review->ease_factor = min(self::MAX_EASE_FACTOR, $review->ease_factor + 0.1);
        } else {
            $review->repetitions = 0;
            $review->interval_days = 1;
            $review->ease_factor = max(self::MIN_EASE_FACTOR, $review->ease_factor - 0.2);
        }

        $review->last_result = $correct ? 'correct' : 'incorrect';
        $review->last_reviewed_at = now();
        $review->due_at = now()->addDays($review->interval_days);
        $review->save();

        return $review;
    }
}

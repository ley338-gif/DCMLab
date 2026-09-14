<?php

namespace App\Http\Controllers;

use App\Content\AnswerGrader;
use App\Content\ContentRepository;
use App\Content\QuizContent;
use App\Models\Lesson;
use App\Services\QuizSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    /**
     * Bewertet eine Quiz-Antwort serverseitig (die richtige Antwort steht nur
     * in meta.yml, nie im Client-Payload) und aktualisiert den
     * Wiederholungs-Zustand (Abschnitt 4.7).
     */
    public function answer(Request $request, Lesson $lesson, string $questionId, ContentRepository $content, QuizSchedulerService $scheduler): JsonResponse
    {
        $data = $request->validate(['value' => 'required']);

        $lessonContent = $content->lessons()[$lesson->lesson_id] ?? null;
        /** @var array<int, array<string, mixed>> $quizMeta */
        $quizMeta = $lessonContent['meta']['quiz'] ?? [];

        $entry = null;
        foreach ($quizMeta as $candidate) {
            if (($candidate['id'] ?? null) === $questionId) {
                $entry = $candidate;
                break;
            }
        }

        abort_if($entry === null, 404);

        $correctAnswer = QuizContent::answerFor($quizMeta, $questionId);
        $correct = AnswerGrader::isCorrect((string) $entry['type'], $data['value'], $correctAnswer);

        $review = $scheduler->recordAnswer($request->user(), $lesson, $questionId, $correct);

        return response()->json([
            'correct' => $correct,
            'correct_answer' => $correctAnswer,
            'due_at' => $review->due_at?->toIso8601String(),
        ]);
    }
}

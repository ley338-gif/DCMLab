<?php

namespace App\Http\Controllers;

use App\Content\AnswerGrader;
use App\Content\ContentRepository;
use App\Content\QuizContent;
use App\Models\Lesson;
use App\Services\QuizSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuizController extends Controller
{
    /**
     * Bewertet eine Quiz-Antwort serverseitig (die richtige Antwort steht nur
     * in meta.yml, nie im Client-Payload) und aktualisiert den
     * Wiederholungs-Zustand (Abschnitt 4.7).
     *
     * Autorisierung wie jeder andere Lesson-Endpunkt (`LessonPolicy::view()`,
     * siehe `LessonController`): ohne diesen Aufruf war dieser Endpunkt
     * bisher ueber eine bekannte Draft-Lesson-Id direkt erreichbar, ganz ohne
     * vorherigen Besuch von `LessonController::show()`. Fuer eine
     * autorisierte Draft-Vorschau wird die Antwort weiterhin korrekt
     * bewertet (der Lernweg soll vollstaendig testbar bleiben), aber der
     * Wiederholungs-Zustand (`QuizReview`) wird nicht persistiert -- eine
     * Vorschau darf keine echte Lernstatistik erzeugen.
     */
    public function answer(Request $request, Lesson $lesson, string $questionId, ContentRepository $content, QuizSchedulerService $scheduler): JsonResponse
    {
        abort_unless(Gate::allows('view', $lesson), 404);

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

        $dueAt = null;

        if ($lesson->isPublished()) {
            $review = $scheduler->recordAnswer($request->user(), $lesson, $questionId, $correct);
            $dueAt = $review->due_at?->toIso8601String();
        }

        return response()->json([
            'correct' => $correct,
            'correct_answer' => $correctAnswer,
            'due_at' => $dueAt,
        ]);
    }
}

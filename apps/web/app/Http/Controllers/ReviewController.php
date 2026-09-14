<?php

namespace App\Http\Controllers;

use App\Content\ContentRepository;
use App\Content\MarkdownRenderer;
use App\Content\QuizContent;
use App\Models\QuizReview;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    /**
     * Faellige Wissenskarten ueber alle Lektionen hinweg (Abschnitt 4.7) --
     * jede faellige Zeile verweist per lesson_id/question_id auf echten
     * Content, der hier live nachgeladen wird, nicht auf eine DB-Kopie.
     */
    public function index(ContentRepository $content): Response
    {
        $due = QuizReview::query()
            ->where('user_id', Auth::id())
            ->where('due_at', '<=', now())
            ->with('lesson')
            ->orderBy('due_at')
            ->get();

        $lessons = $content->lessons();
        $renderer = new MarkdownRenderer($content->glossary());

        $cards = $due
            ->map(function (QuizReview $review) use ($lessons, $renderer) {
                $lessonContent = $lessons[$review->lesson->lesson_id] ?? null;

                if ($lessonContent === null || $lessonContent['body'] === null) {
                    return null;
                }

                $quizMeta = $lessonContent['meta']['quiz'] ?? [];
                $split = QuizContent::splitBody($lessonContent['body']);
                $question = collect(QuizContent::parseQuestions($split['quiz_raw'], $quizMeta, $renderer))
                    ->firstWhere('id', $review->question_id);

                if ($question === null) {
                    return null;
                }

                return [
                    'lesson_id' => $review->lesson->lesson_id,
                    'lesson_title' => $lessonContent['frontmatter']['title'] ?? $review->lesson->lesson_id,
                    'question' => $question,
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('Review/Index', [
            'cards' => $cards,
        ]);
    }
}

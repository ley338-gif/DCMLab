<?php

namespace App\Http\Controllers;

use App\Content\ContentRepository;
use App\Models\Lesson;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Oeffentliches Glossar (content/glossary/de.yml) -- dieselben Begriffe, die
 * als {{term:x}}-Tooltip in Lektionen aufgeloest werden (Abschnitt 4,
 * MarkdownRenderer::resolveTerms), hier erstmals auch als durchsuchbare
 * Uebersicht mit den bisher ungenutzten see_also-/lesson-Rueckverweisen.
 */
class GlossaryController extends Controller
{
    public function index(ContentRepository $content): Response
    {
        $glossary = $content->glossary();

        $lessonTitles = Lesson::query()
            ->whereIn('lesson_id', collect($glossary)->pluck('lesson')->filter()->unique()->values())
            ->get()
            ->keyBy('lesson_id');

        $terms = collect($glossary)
            ->map(function (array $entry, string $slug) use ($glossary, $lessonTitles) {
                $lessonId = $entry['lesson'] ?? null;
                $lesson = $lessonId !== null ? $lessonTitles->get($lessonId) : null;

                return [
                    'slug' => $slug,
                    'term' => $entry['term'] ?? $slug,
                    'expansion' => $entry['expansion'] ?? null,
                    'short' => $entry['short'] ?? '',
                    'see_also' => collect((array) ($entry['see_also'] ?? []))
                        ->filter(fn (string $related) => array_key_exists($related, $glossary))
                        ->map(fn (string $related) => [
                            'slug' => $related,
                            'term' => $glossary[$related]['term'] ?? $related,
                        ])
                        ->values(),
                    'lesson' => $lesson !== null ? [
                        'lesson_id' => $lesson->lesson_id,
                        'title' => $lesson->title['de'] ?? $lesson->lesson_id,
                    ] : null,
                ];
            })
            ->values()
            ->sortBy(fn (array $term) => mb_strtolower($term['term']))
            ->values();

        return Inertia::render('Glossary/Index', [
            'terms' => $terms,
        ]);
    }
}

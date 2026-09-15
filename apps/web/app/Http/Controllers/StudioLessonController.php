<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonElement;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sichtbarkeit der Elementsequenz einer Lektion in Studio (ADR 0105,
 * CMS-6b) -- rein lesend, noch ohne Drag & Drop (CMS-6c). Dieselbe
 * Zugangsregel wie das uebrige Studio (`studio.access`, ADR 0098): jede
 * Rolle ausser Learner darf sehen, wie eine Lektion aktuell zusammengesetzt
 * ist.
 */
class StudioLessonController extends Controller
{
    public function show(Lesson $lesson): Response
    {
        Gate::authorize('studio.access');

        $elements = $lesson->elements()
            ->with('activity')
            ->get()
            ->map(function (LessonElement $element) {
                if ($element->type === 'content' || $element->activity === null) {
                    // Ein type=activity-Element ohne (mehr) verlinkte
                    // Activity ist eine verwaiste Zeile (activity_id ist
                    // nullOnDelete) -- zeigt sich hier nur als "unbekannt",
                    // statt einen Fehler zu werfen.
                    return [
                        'id' => $element->id,
                        'position' => $element->position,
                        'kind' => $element->type === 'content' ? 'content' : 'unbekannt',
                        'label' => $element->type === 'content' ? 'Lektionstext' : '—',
                    ];
                }

                return [
                    'id' => $element->id,
                    'position' => $element->position,
                    'kind' => $element->activity->type,
                    'label' => $element->activity->title['de'] ?? $element->activity->key,
                ];
            });

        return Inertia::render('Studio/Lesson', [
            'lesson' => [
                'lesson_id' => $lesson->lesson_id,
                'title' => $lesson->title['de'] ?? $lesson->lesson_id,
            ],
            'elements' => $elements,
        ]);
    }
}

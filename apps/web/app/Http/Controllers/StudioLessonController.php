<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lesson;
use App\Models\LessonElement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Elementsequenz einer Lektion in Studio (ADR 0105/0106, CMS-6b/CMS-6c) --
 * Ansehen bleibt fuer jede Nicht-Lernende-Rolle offen (`studio.access`,
 * ADR 0098); Umsortieren ist an dieselbe Regel gebunden wie der
 * Lektions-Editor selbst (`update` auf die `type=lesson`-Activity --
 * Reviewer/Administrator immer, ein Autor nur fuer zugewiesene Lektionen),
 * damit niemand die Reihenfolge einer fremden Lektion aendern kann, der er
 * nicht einmal ihren Text bearbeiten darf.
 */
class StudioLessonController extends Controller
{
    public function show(Lesson $lesson): Response
    {
        Gate::authorize('studio.access');

        $activity = $this->activityFor($lesson);

        return Inertia::render('Studio/Lesson', [
            'lesson' => [
                'lesson_id' => $lesson->lesson_id,
                'title' => $lesson->title['de'] ?? $lesson->lesson_id,
            ],
            'elements' => $this->elementRows($lesson),
            'can_manage' => Gate::allows('update', $activity),
        ]);
    }

    /**
     * Nimmt die vollstaendige, neue Reihenfolge als Liste von
     * `lesson_elements.id` entgegen (nicht nur zwei vertauschte Eintraege)
     * -- ein Drag & Drop im Browser kennt ohnehin schon die komplette neue
     * Liste, das erspart eine fehleranfaellige Positions-Arithmetik hier.
     */
    public function reorder(Request $request, Lesson $lesson): RedirectResponse
    {
        Gate::authorize('update', $this->activityFor($lesson));

        $elementIds = $lesson->elements()->pluck('id');

        $data = $request->validate([
            'order' => ['required', 'array', 'size:'.$elementIds->count()],
            'order.*' => ['integer', 'distinct', 'in:'.$elementIds->implode(',')],
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['order'] as $position => $elementId) {
                LessonElement::query()->where('id', $elementId)->update(['position' => $position]);
            }
        });

        return back()->with('status', 'Reihenfolge gespeichert.');
    }

    private function activityFor(Lesson $lesson): Activity
    {
        return Activity::query()->where('type', 'lesson')->where('key', $lesson->lesson_id)->firstOrFail();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function elementRows(Lesson $lesson): array
    {
        return array_values($lesson->elements()
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
            })
            ->all());
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lab;
use App\Models\Lesson;
use App\Models\LessonElement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
 *
 * Umbenannt von `StudioLessonController` (Studio-Lessons-Umbau): der
 * urspruengliche Name suggerierte faelschlich, dies sei der Lesson-Editor
 * selbst. `StudioLessonController` heisst jetzt die eigentliche
 * Lesson-Ressourcenliste (`studio.lessons.index`), der echte Editor bleibt
 * `LessonEditorController` (jetzt zusaetzlich unter `studio.lessons.*`
 * erreichbar) -- diese Klasse ist ausschliesslich der `lesson_elements`-
 * Composer, keine dritte Sache.
 *
 * `attachLab()` (CMS-8e prep): ein Lab hat -- anders als related_node/
 * sandbox/quiz -- kein `content:sync`-Pendant, das seinen `lesson_elements`-
 * Eintrag aus `meta.yml` backfuellen koennte (Labs sind bewusst DB-/Studio-
 * first, CMS-8a). Ohne diese Methode war ein veroeffentlichtes Lab zwar per
 * direkter URL erreichbar, aber ueber keinen Lernenden-Flow verlinkt. Legt
 * bewusst nur die Zeile an (Lab-spezifisch, kein generisches "Activity
 * anhaengen") -- Positionierung danach ganz normal per `reorder()`.
 */
class StudioLessonElementsController extends Controller
{
    public function show(Lesson $lesson): Response
    {
        Gate::authorize('studio.access');

        $activity = $this->activityFor($lesson);

        return Inertia::render('Studio/Lessons/Elements', [
            'lesson' => [
                'lesson_id' => $lesson->lesson_id,
                'title' => $lesson->title['de'] ?? $lesson->lesson_id,
            ],
            'elements' => $this->elementRows($lesson),
            'available_labs' => $this->availableLabs($lesson),
            'can_manage' => Gate::allows('update', $activity),
        ]);
    }

    /**
     * Haengt ein bereits veroeffentlichtes Lab als neues, letztes Element
     * an -- dieselbe Berechtigungsregel wie `reorder()`. Ein bereits an
     * dieser Lektion haengendes Lab ist kein technischer, aber ein
     * fachlicher Fehler (Autoren-Doppelklick, verwirrende Doppelkarte) und
     * wird deshalb als Validierungsfehler abgelehnt, nicht stillschweigend
     * ignoriert.
     *
     * Betreiber-Korrektur: der Duplikat-Check, die Positionsberechnung und
     * das `create()` bildeten zuvor keine atomare Einheit -- zwei
     * gleichzeitige Anfragen fuer dasselbe Lab an derselben Lektion
     * konnten beide den (noch leeren) Duplikat-Check und dasselbe
     * `max(position)` sehen und so entweder das Lab doppelt anhaengen oder
     * zwei Elemente mit identischer Position erzeugen. Ohne neue Spalte/
     * Unique-Constraint geloest ueber eine `lockForUpdate()`-Zeilensperre
     * auf die Lesson selbst: die zweite Transaktion wartet, bis die erste
     * committed hat, und sieht dann das gerade eingefuegte Element beim
     * eigenen (erneuten) Duplikat-Check und bei der Positionsberechnung.
     */
    public function attachLab(Request $request, Lesson $lesson): RedirectResponse
    {
        Gate::authorize('update', $this->activityFor($lesson));

        $data = $request->validate([
            'lab_slug' => [
                'required',
                'string',
                Rule::exists('labs', 'slug')->where('status', 'published'),
            ],
        ]);

        $attached = DB::transaction(function () use ($lesson, $data): bool {
            Lesson::query()->whereKey($lesson->id)->lockForUpdate()->firstOrFail();

            $labActivity = Activity::query()->where('type', 'lab')->where('key', $data['lab_slug'])->firstOrFail();

            $alreadyAttached = LessonElement::query()
                ->where('lesson_id', $lesson->id)
                ->where('activity_id', $labActivity->id)
                ->exists();

            if ($alreadyAttached) {
                return false;
            }

            $nextPosition = (LessonElement::query()->where('lesson_id', $lesson->id)->max('position') ?? -1) + 1;

            LessonElement::query()->create([
                'lesson_id' => $lesson->id,
                'type' => 'activity',
                'activity_id' => $labActivity->id,
                'position' => $nextPosition,
            ]);

            return true;
        });

        if (! $attached) {
            return back()->withErrors(['lab_slug' => 'Dieses Lab ist bereits Teil dieser Lektion.']);
        }

        return back()->with('status', 'Lab hinzugefügt.');
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

    /**
     * Veroeffentlichte Labs, die dieser Lektion noch nicht angehaengt sind
     * -- ein Lab ist bewusst wiederverwendbar (`LabActivity::supports()
     * ->reusable`), die Ausschlussliste ist deshalb nur pro Lektion, nicht
     * global.
     *
     * @return list<array{slug: string, title: string}>
     */
    private function availableLabs(Lesson $lesson): array
    {
        $attachedSlugs = $lesson->elements()
            ->with('activity')
            ->get()
            ->filter(fn (LessonElement $element) => $element->activity?->type === 'lab')
            ->map(fn (LessonElement $element) => $element->activity->key)
            ->all();

        return array_values(Lab::query()
            ->where('status', 'published')
            ->whereNotIn('slug', $attachedSlugs)
            ->orderBy('slug')
            ->get(['slug', 'title'])
            ->map(fn (Lab $lab) => ['slug' => $lab->slug, 'title' => $lab->title['de'] ?? $lab->slug])
            ->all());
    }
}

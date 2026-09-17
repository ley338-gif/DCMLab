<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Themenfeld;
use App\Models\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Track-Verwaltung in Studio (ADR 0100, CMS-4b): das erste First-Class-
 * Objekt aus dem "Lerninhalte"-Bereich des Studio-Auftrags (Abschnitt 1),
 * additiv neben dem weiterhin unveraenderten `content:sync`-Pfad -- ein per
 * Datei verwalteter Track bleibt unangetastet, solange niemand ihn hier
 * bearbeitet (`title`/`teaser` bleiben dann `null`, TrackController faellt
 * weiter auf `title_key` zurueck).
 *
 * Nur Archivieren, kein Loeschen (Betreiberentscheidung vor CMS-4: ein
 * geloeschter Track wuerde ueber `lessons.track_id` kaskadierend echten
 * Lernfortschritt mitreissen, siehe docs/studio-architecture-plan.md).
 *
 * `moveLesson()`/`reorderLessons()` (Studio-Lessons-Umbau): eine Lesson
 * gehoert immer genau einer Track (`lessons.track_id`, 1:n) -- "einer Track
 * zuordnen" ist deshalb immer ein VERSCHIEBEN (ggf. aus einer anderen
 * Track heraus), nie ein additives "Hinzufuegen" wie bei Lab/
 * lesson_elements. Beide Methoden sperren die betroffene(n) Zeile(n) per
 * `lockForUpdate()`, um dieselbe Race-Klasse zu schliessen, die
 * `StudioLessonElementsController::attachLab()` bereits (CMS-8e) fuer
 * `lesson_elements` behoben hat -- `reorderLessons()` sperrt dafuer nicht
 * nur die Track selbst, sondern auch jede ihrer aktuellen Lesson-Zeilen
 * (siehe dortiger Methoden-Kommentar), sonst haette eine gleichzeitige
 * `moveLesson()` eine dieser Lessons unbemerkt in eine andere Track
 * verschieben koennen.
 */
class StudioTrackController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Track::class);

        $tracks = Track::query()
            ->with(['themenfeld', 'lessons'])
            ->withCount('lessons')
            ->orderBy('order')
            ->get()
            ->map(fn (Track $track) => [
                'slug' => $track->slug,
                'title' => $track->title['de'] ?? $track->title_key,
                'teaser' => $track->teaser['de'] ?? null,
                'level' => $track->level,
                'hours' => $track->hours,
                'order' => $track->order,
                'status' => $track->status,
                'themenfeld_id' => $track->themenfeld_id,
                'lessons_count' => $track->lessons_count,
                'lessons' => $track->lessons->map(fn (Lesson $lesson) => [
                    'lesson_id' => $lesson->lesson_id,
                    'title' => $lesson->title['de'] ?? $lesson->lesson_id,
                    'order' => $lesson->order,
                ])->values(),
            ]);

        $themenfelder = Themenfeld::query()
            ->orderBy('order')
            ->get(['id', 'slug'])
            ->map(fn (Themenfeld $themenfeld) => [
                'id' => $themenfeld->id,
                'slug' => $themenfeld->slug,
            ]);

        // Fuer den "Lektion hierher verschieben"-Picker jeder Track-Karte:
        // dieselbe clientseitige Filter-Idee wie ueberall in Studio (Nodes/
        // Labs-Index) statt eines eigenen Endpunkts pro Track.
        $allLessons = Lesson::query()
            ->with('track:id,slug')
            ->orderBy('lesson_id')
            ->get()
            ->map(fn (Lesson $lesson) => [
                'lesson_id' => $lesson->lesson_id,
                'title' => $lesson->title['de'] ?? $lesson->lesson_id,
                'track_slug' => $lesson->track?->slug,
            ]);

        return Inertia::render('Studio/Tracks', [
            'tracks' => $tracks,
            'all_lessons' => $allLessons,
            'themenfelder' => $themenfelder,
            'can_manage' => Gate::allows('manage', Track::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Track::class);

        $data = $request->validate([
            'slug' => 'required|string|max:255|alpha_dash|unique:tracks,slug',
            'title' => 'required|string|max:255',
            'teaser' => 'nullable|string',
            'themenfeld_id' => 'required|integer|exists:themenfelder,id',
            'level' => 'required|string|in:einsteiger,aufbau,fortgeschritten',
            'hours' => 'required|integer|min:0',
            'order' => 'required|integer|min:0',
        ]);

        Track::query()->create([
            'slug' => $data['slug'],
            // title_key wird bei einem in Studio angelegten Track nie
            // gelesen (title ist gesetzt) -- er existiert nur, weil die
            // Spalte historisch NOT NULL ist (ADR 0100).
            'title_key' => "track.{$data['slug']}.title",
            'title' => ['de' => $data['title']],
            'teaser' => $data['teaser'] !== null ? ['de' => $data['teaser']] : null,
            'themenfeld_id' => $data['themenfeld_id'],
            'level' => $data['level'],
            'hours' => $data['hours'],
            'order' => $data['order'],
            'status' => 'draft',
        ]);

        return back()->with('status', 'Track angelegt.');
    }

    public function update(Request $request, Track $track): RedirectResponse
    {
        Gate::authorize('manage', $track);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'teaser' => 'nullable|string',
            'themenfeld_id' => 'required|integer|exists:themenfelder,id',
            'level' => 'required|string|in:einsteiger,aufbau,fortgeschritten',
            'hours' => 'required|integer|min:0',
            'order' => 'required|integer|min:0',
        ]);

        $track->update([
            'title' => ['de' => $data['title']],
            'teaser' => $data['teaser'] !== null ? ['de' => $data['teaser']] : null,
            'themenfeld_id' => $data['themenfeld_id'],
            'level' => $data['level'],
            'hours' => $data['hours'],
            'order' => $data['order'],
        ]);

        return back()->with('status', 'Track aktualisiert.');
    }

    public function publish(Track $track): RedirectResponse
    {
        Gate::authorize('manage', $track);

        $track->update(['status' => 'published']);

        return back()->with('status', 'Track veröffentlicht.');
    }

    public function unpublish(Track $track): RedirectResponse
    {
        Gate::authorize('manage', $track);

        $track->update(['status' => 'draft']);

        return back()->with('status', 'Veröffentlichung zurückgenommen.');
    }

    public function archive(Track $track): RedirectResponse
    {
        Gate::authorize('manage', $track);

        $track->update(['status' => 'archived']);

        return back()->with('status', 'Track archiviert.');
    }

    public function restore(Track $track): RedirectResponse
    {
        Gate::authorize('manage', $track);

        $track->update(['status' => 'draft']);

        return back()->with('status', 'Track wiederhergestellt.');
    }

    /**
     * Verschiebt eine bestehende Lesson in diese Track (ans Ende) --
     * niemals ein additives "Hinzufuegen": `lessons.track_id` ist 1:n, eine
     * Lesson gehoert danach ausschliesslich dieser Track, nicht mehr ihrer
     * vorherigen. Sperrreihenfolge bewusst Track zuerst, dann Lesson --
     * dieselbe feste Reihenfolge in jedem Aufruf verhindert eine
     * Verklemmung zwischen zwei gleichzeitigen Verschiebungen, die sich
     * ueberschneidende Zeilen in umgekehrter Reihenfolge sperren wuerden.
     */
    public function moveLesson(Request $request, Track $track): RedirectResponse
    {
        Gate::authorize('manage', $track);

        // `lesson_id` ist hier bewusst der fachliche Schluessel (Lesson::
        // getRouteKeyName(), z. B. "1.5"), nicht die numerische DB-id --
        // konsistent mit jedem anderen Studio-Endpunkt (Node/Lab/Lesson
        // werden ueberall per Slug/lesson_id adressiert, nie per Roh-id).
        $data = $request->validate([
            'lesson_id' => ['required', 'string', 'exists:lessons,lesson_id'],
        ]);

        DB::transaction(function () use ($track, $data): void {
            Track::query()->whereKey($track->id)->lockForUpdate()->firstOrFail();
            $lesson = Lesson::query()->where('lesson_id', $data['lesson_id'])->lockForUpdate()->firstOrFail();

            $nextOrder = (Lesson::query()->where('track_id', $track->id)->max('order') ?? -1) + 1;

            $lesson->update(['track_id' => $track->id, 'order' => $nextOrder]);

            // Haelt den Verzeichniseintrag konsistent mit der Lesson-Zeile
            // (beide tragen seit ContentSync historisch dieselben track_id/
            // order-Werte) -- nichts liest heute Activity::track_id fuer
            // type=lesson aktiv, aber ein stillschweigend abweichender Wert
            // waere trotzdem eine unnoetige, verwirrende Inkonsistenz.
            Activity::query()->where('type', 'lesson')->where('key', $lesson->lesson_id)
                ->update(['track_id' => $track->id, 'order' => $nextOrder]);
        });

        return back()->with('status', 'Lektion verschoben.');
    }

    /**
     * Nimmt die vollstaendige, neue Reihenfolge als Liste von
     * `lessons.lesson_id` entgegen (fachlicher Schluessel, konsistent mit
     * `moveLesson()` oben) -- analog zu
     * `StudioLessonElementsController::reorder()` fuer `lesson_elements`.
     *
     * Betreiber-Korrektur: eine Zeilensperre nur auf die Track reichte
     * nicht -- `moveLesson()` sperrt beim Verschieben einer Lesson IN eine
     * Track nur die ZIEL-Track, nie die (fuer diese Methode hier
     * eigentlich relevante) QUELL-Track der schon laenger dort
     * befindlichen Lessons. Ohne eigene Sperren auf den Lesson-Zeilen
     * selbst konnte ein gleichzeitiger `moveLesson()`-Aufruf eine Lesson
     * aus dieser Track herausverschieben, NACHDEM sie hier schon
     * unverschluesselt in die Permutation aufgenommen wurde -- die
     * anschliessende `order`-Zuweisung traf dann eine Lesson, die laengst
     * einer anderen Track gehoerte, und ueberschrieb dort versehentlich
     * eine fremde Position.
     *
     * Behoben durch: (1) jede zu dieser Track gehoerende Lesson-Zeile wird
     * hier selbst per `lockForUpdate()` gesperrt, nicht nur die Track; (2)
     * die Permutation wird gegen genau diesen gesperrten Bestand validiert
     * (`Rule::in()`, nicht mehr eine von Hand zusammengesetzte `in:`-Regel);
     * (3) jedes einzelne Update ist zusaetzlich auf `track_id = $track->id`
     * eingeschraenkt und prueft die betroffene Zeilenzahl -- genau EINE
     * erwartet, alles andere ist ein Invariantenbruch (sollte durch die
     * Sperren oben bereits ausgeschlossen sein, siehe
     * ContentPublishingService::applyOrFail() fuer dasselbe Muster: eine
     * defensive, aber nicht mehr normal erreichbare Absicherung, keine
     * Bruch der Transaktion riskieren statt eine Teil-Umsortierung).
     */
    public function reorderLessons(Request $request, Track $track): RedirectResponse
    {
        Gate::authorize('manage', $track);

        DB::transaction(function () use ($request, $track): void {
            $lockedTrack = Track::query()->whereKey($track->id)->lockForUpdate()->firstOrFail();

            $lockedLessons = Lesson::query()
                ->where('track_id', $lockedTrack->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'lesson_id']);

            $lessonIds = $lockedLessons->pluck('lesson_id');

            $data = $request->validate([
                'order' => ['required', 'array', 'size:'.$lessonIds->count()],
                'order.*' => ['string', 'distinct', Rule::in($lessonIds->all())],
            ]);

            $lessonsByLessonId = $lockedLessons->keyBy('lesson_id');

            foreach ($data['order'] as $position => $lessonId) {
                $lesson = $lessonsByLessonId[$lessonId];

                $updated = Lesson::query()
                    ->whereKey($lesson->id)
                    ->where('track_id', $lockedTrack->id)
                    ->update(['order' => $position]);

                if ($updated !== 1) {
                    throw new RuntimeException(
                        "reorderLessons(): Lesson \"{$lessonId}\" gehoerte beim Schreiben nicht mehr zur gesperrten Track {$lockedTrack->slug} -- Transaktion abgebrochen.",
                    );
                }

                Activity::query()->where('type', 'lesson')->where('key', $lessonId)->update(['order' => $position]);
            }
        });

        return back()->with('status', 'Reihenfolge gespeichert.');
    }
}

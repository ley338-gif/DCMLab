<?php

namespace App\Http\Controllers;

use App\Models\Themenfeld;
use App\Models\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

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
 */
class StudioTrackController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Track::class);

        $tracks = Track::query()
            ->with('themenfeld')
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
            ]);

        $themenfelder = Themenfeld::query()
            ->orderBy('order')
            ->get(['id', 'slug'])
            ->map(fn (Themenfeld $themenfeld) => [
                'id' => $themenfeld->id,
                'slug' => $themenfeld->slug,
            ]);

        return Inertia::render('Studio/Tracks', [
            'tracks' => $tracks,
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
}

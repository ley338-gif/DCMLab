<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "/" (Homebase-Umbau): fuer einen eingeloggten Nutzer ist die persoenliche
 * Home das Dashboard -- kein zweiter Ort, der dieselben Daten nochmal
 * rendert, sondern ein einfacher Redirect. Fuer einen Gast ist "/" die
 * oeffentliche Landingpage; die zeigt bewusst nur eine kleine, echte
 * Auswahl an veroeffentlichten Tracks/Labs (kein Duplikat des vollen
 * Katalogs unter tracks.index).
 */
class HomeController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        $highlightTracks = Track::query()
            ->where('status', 'published')
            ->withCount('lessons')
            ->orderBy('order')
            ->take(6)
            ->get()
            ->map(fn (Track $track) => [
                'slug' => $track->slug,
                'title_key' => $track->title_key,
                'title' => $track->title,
                'level' => $track->level,
                'hours' => $track->hours,
                'lessons_count' => $track->lessons_count,
            ]);

        $highlightLabs = Lab::query()
            ->where('status', 'published')
            ->orderBy('id')
            ->take(4)
            ->get()
            ->map(fn (Lab $lab) => [
                'slug' => $lab->slug,
                'title' => $lab->title['de'] ?? $lab->slug,
                'scenario_title' => $lab->scenario_title['de'] ?? '',
                'difficulty' => $lab->difficulty,
                'estimated_minutes' => $lab->estimated_minutes,
            ]);

        return Inertia::render('Welcome', [
            'highlight_tracks' => $highlightTracks,
            'highlight_labs' => $highlightLabs,
        ]);
    }
}

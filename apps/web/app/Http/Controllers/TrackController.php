<?php

namespace App\Http\Controllers;

use App\Models\Track;
use Inertia\Inertia;
use Inertia\Response;

class TrackController extends Controller
{
    /**
     * Zeigt die Trackuebersicht -- die Startseite fuer angemeldete wie
     * unangemeldete Besucher (Abschnitt 10, P2).
     */
    public function index(): Response
    {
        $tracks = Track::query()
            ->withCount('lessons')
            ->orderBy('order')
            ->get()
            ->map(fn (Track $track) => [
                'slug' => $track->slug,
                'title_key' => $track->title_key,
                'level' => $track->level,
                'hours' => $track->hours,
                'status' => $track->status,
                'lessons_count' => $track->lessons_count,
            ]);

        return Inertia::render('Tracks/Index', [
            'tracks' => $tracks,
        ]);
    }
}

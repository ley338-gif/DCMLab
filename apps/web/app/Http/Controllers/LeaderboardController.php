<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\ProfileService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Oeffentliche Bestenliste (Abschnitt 7): Opt-in ist per Default aus, also
 * meist leer bis Nutzer sich aktiv dafuer entscheiden.
 */
class LeaderboardController extends Controller
{
    public function index(ProfileService $profiles): Response
    {
        return Inertia::render('Leaderboard', [
            'entries' => $profiles->leaderboard()->map(fn (Profile $profile) => [
                'name' => $profile->user->name,
                'rank' => $profile->rank,
                'points' => $profile->points,
                'public_slug' => $profile->public_slug,
            ])->values(),
        ]);
    }
}

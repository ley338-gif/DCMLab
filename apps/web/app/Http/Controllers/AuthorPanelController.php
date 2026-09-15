<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Einstiegspunkt fuer Author/Reviewer/Administrator (ADR 0092/0098): vorher
 * gab es keine einzige Seite, von der aus diese Rollen ihre Werkzeuge finden
 * konnten -- jeder Editor war nur ueber eine URL mit fester Ressourcen-ID
 * erreichbar (`/de/author/lessons/{lesson}/edit`), nirgends verlinkt. Diese
 * Seite selbst listet keine bestehenden Inhalte durch (dafuer bleibt die
 * jeweilige Lern-Ansicht die Quelle), sondern zeigt nur, was fuer die
 * eigene Rolle als Naechstes zu tun ist: die eigenen zugewiesenen
 * Aktivitaeten (Author), die Review-Queue-Anzahl und den Zugang zur
 * Nutzerverwaltung (Reviewer/Administrator).
 */
class AuthorPanelController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('studio.access');

        $user = $request->user();

        $assignedActivities = $user->authoredActivities()
            ->orderBy('type')
            ->orderBy('key')
            ->get(['activities.id', 'activities.type', 'activities.key', 'activities.title'])
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'type' => $activity->type,
                'key' => $activity->key,
                'title' => $activity->title['de'] ?? $activity->key,
            ]);

        return Inertia::render('Author/Panel', [
            'role' => $user->role->value,
            'assigned_activities' => $assignedActivities,
            'review_queue_count' => in_array($user->role, [UserRole::Reviewer, UserRole::Administrator], true)
                ? ContentVersion::where('status', 'review')->count()
                : null,
        ]);
    }
}

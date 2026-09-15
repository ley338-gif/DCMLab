<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nutzerverwaltung im Autoren-Panel (ADR 0092): Rollen vergeben und
 * Autor:innen einzelnen Aktivitaeten zuordnen (`activity_authors`, ADR
 * 0071 W3) -- vorher ging beides nur direkt in der Datenbank. Es gibt
 * keine eigene Admin-Rolle (siehe UserPolicy), Reviewer ist die hoechste
 * bestehende Stufe.
 */
class AuthorUserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'authored_activities' => $user->authoredActivities()
                    ->orderBy('type')->orderBy('key')
                    ->get(['activities.id', 'activities.type', 'activities.key'])
                    ->map(fn (Activity $activity) => [
                        'id' => $activity->id,
                        'type' => $activity->type,
                        'key' => $activity->key,
                    ]),
            ]);

        $activities = Activity::query()
            ->orderBy('type')->orderBy('key')
            ->get(['id', 'type', 'key'])
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'type' => $activity->type,
                'key' => $activity->key,
            ]);

        return Inertia::render('Author/Users', [
            'users' => $users,
            'activities' => $activities,
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validate([
            'role' => 'required|string|in:learner,author,reviewer,administrator',
        ]);

        $user->role = UserRole::from($data['role']);
        $user->save();

        return back()->with('status', 'Rolle aktualisiert.');
    }

    public function assignActivity(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validate([
            'activity_id' => 'required|integer|exists:activities,id',
        ]);

        $user->authoredActivities()->syncWithoutDetaching([$data['activity_id']]);

        return back()->with('status', 'Aktivitaet zugewiesen.');
    }

    public function removeActivity(Request $request, User $user, Activity $activity): RedirectResponse
    {
        Gate::authorize('update', $user);

        $user->authoredActivities()->detach($activity->id);

        return back()->with('status', 'Zuweisung entfernt.');
    }
}

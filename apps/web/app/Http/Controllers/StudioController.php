<?php

namespace App\Http\Controllers;

use App\Models\SandboxTemplate;
use App\Models\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Einstiegspunkt fuer DCMLab Studio (ADR 0094 Abschnitt 12, CMS-3b): der
 * erste `/studio`-Bereich, additiv neben dem bestehenden Autoren-Panel
 * (`/de/author`), nicht dessen Ersatz -- Ressourcen ziehen einzeln um,
 * sobald sie eine eigene Studio-Seite bekommen (docs/studio-architecture-plan.md
 * Abschnitt 7). Dieselbe Zugangsregel wie AuthorPanelController
 * (`studio.access`, ADR 0098): jede Rolle ausser Learner.
 */
class StudioController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('studio.access');

        return Inertia::render('Studio/Dashboard', [
            'role' => $request->user()->role->value,
            'can_manage_sandbox_templates' => Gate::allows('manage', SandboxTemplate::class),
            'sandbox_template_count' => SandboxTemplate::query()->count(),
            'can_manage_tracks' => Gate::allows('manage', Track::class),
            'track_count' => Track::query()->count(),
        ]);
    }
}

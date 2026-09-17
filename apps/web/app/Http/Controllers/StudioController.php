<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\Lesson;
use App\Models\Node;
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
            'can_manage_nodes' => Gate::allows('manage', Node::class),
            'node_count' => Node::query()->count(),
            'can_manage_labs' => Gate::allows('manage', Lab::class),
            'lab_count' => Lab::query()->count(),
            // Lesson hat -- anders als Track/Node/Lab -- keine strukturelle
            // Policy, die ein "manage" ueberhaupt kennt (kein store()/
            // archive() in Studio, siehe StudioLessonController-Klassendoc):
            // jede Nicht-Lernende-Rolle, die bis hierher kommt
            // (`studio.access` oben), darf die Liste ansehen; ob eine
            // konkrete Lektion bearbeitbar ist, entscheidet erst
            // LessonEditorController pro Lektion.
            'can_manage_lessons' => true,
            'lesson_count' => Lesson::query()->count(),
        ]);
    }
}

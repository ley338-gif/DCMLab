<?php

namespace App\Http\Controllers;

use App\Models\SandboxTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Erste echte Studio-Ressourcenseite (ADR 0099, CMS-3b): der
 * `sandbox_templates`-Katalog (ADR 0096) hatte bisher ueberhaupt keine
 * Oberflaeche, nur `SandboxTemplateSeeder`. `viewAny` (jeder Nicht-Lernende)
 * sieht die Liste, `manage` (Reviewer/Administrator, ADR 0098) darf anlegen
 * und bearbeiten.
 */
class StudioSandboxTemplateController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', SandboxTemplate::class);

        $templates = SandboxTemplate::query()
            ->orderBy('name')
            ->get()
            ->map(fn (SandboxTemplate $template) => [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->name,
                'description' => $template->description,
                'runtime_provider' => $template->runtime_provider,
                'status' => $template->status,
            ]);

        return Inertia::render('Studio/SandboxTemplates', [
            'templates' => $templates,
            'can_manage' => Gate::allows('manage', SandboxTemplate::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', SandboxTemplate::class);

        $data = $request->validate([
            'slug' => 'required|string|max:255|alpha_dash|unique:sandbox_templates,slug',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        SandboxTemplate::query()->create([
            ...$data,
            // Kein zweiter Runtime-Provider registriert (ADR 0096) -- neue
            // Vorlagen starten bewusst als Entwurf, "Freigeben" ist ein
            // eigener, expliziter Schritt (update()).
            'runtime_provider' => 'docker',
            'status' => 'draft',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Vorlage angelegt.');
    }

    public function update(Request $request, SandboxTemplate $sandboxTemplate): RedirectResponse
    {
        Gate::authorize('manage', $sandboxTemplate);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|string|in:draft,published',
        ]);

        $sandboxTemplate->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Vorlage aktualisiert.');
    }
}

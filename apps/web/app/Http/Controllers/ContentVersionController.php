<?php

namespace App\Http\Controllers;

use App\Content\ActivityContentApplier;
use App\Content\ContentVersioningService;
use App\Models\ContentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Generischer Freigabe-Kreislauf fuer `content_versions` (ADR 0071/0080,
 * W6): unabhaengig davon, welcher Editor (Quiz, Lektion, ...) einen Entwurf
 * angelegt hat -- `ActivityContentApplier` entscheidet agnostisch gegenueber
 * der Herkunft des Entwurfs, WIE er wirksam wird (ADR 0074/0102), dieser
 * Controller macht dieselbe Verdrahtung fuer alle Editoren wiederverwendbar
 * statt sie je Editor zu duplizieren. Die Route heisst aus historischen
 * Gruenden noch "quiz-versions" (erster Editor war der Quiz-Editor),
 * verarbeitet aber jede Aktivitaet.
 */
class ContentVersionController extends Controller
{
    public function submit(ContentVersion $version, ContentVersioningService $versions): RedirectResponse
    {
        Gate::authorize('update', $version->activity);

        $versions->submitForReview($version);

        return back()->with('status', 'Zur Pruefung eingereicht.');
    }

    public function publish(Request $request, ContentVersion $version, ContentVersioningService $versions, ActivityContentApplier $applier): RedirectResponse
    {
        Gate::authorize('publish', $version->activity);

        $issues = $applier->apply($version->activity, $version->payload);

        if ($issues !== []) {
            return back()->withErrors(['content' => array_map(fn ($issue) => (string) $issue, $issues)]);
        }

        $versions->publish($version, $request->user());

        return back()->with('status', 'Veroeffentlicht.');
    }
}

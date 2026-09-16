<?php

namespace App\Http\Controllers;

use App\Content\ContentPublishingService;
use App\Content\ContentVersioningService;
use App\Models\ContentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Generischer Freigabe-Kreislauf fuer `content_versions` (ADR 0071/0080,
 * W6): unabhaengig davon, welcher Editor (Quiz, Lektion, ...) einen Entwurf
 * angelegt hat -- `ActivityContentApplier` entscheidet agnostisch gegenueber
 * der Herkunft des Entwurfs, WIE er wirksam wird (ADR 0074/0102), dieser
 * Controller macht dieselbe Verdrahtung fuer alle Editoren wiederverwendbar
 * statt sie je Editor zu duplizieren. Die Route heisst aus historischen
 * Gruenden noch "quiz-versions" (erster Editor war der Quiz-Editor),
 * verarbeitet aber jede Aktivitaet.
 *
 * `publish()`/`restore()` delegieren seit CMS-7d.3 (ADR 0118) an
 * `ContentPublishingService` -- Anwenden auf die Live-Ressource und das
 * Umhaengen der Versionshistorie laufen dort in EINER Transaktion, anders
 * als frueher hier als zwei getrennte Aufrufe.
 */
class ContentVersionController extends Controller
{
    public function submit(ContentVersion $version, ContentVersioningService $versions): RedirectResponse
    {
        Gate::authorize('update', $version->activity);

        $versions->submitForReview($version);

        return back()->with('status', 'Zur Pruefung eingereicht.');
    }

    public function publish(Request $request, ContentVersion $version, ContentPublishingService $publishing): RedirectResponse
    {
        Gate::authorize('publish', $version->activity);

        $issues = $publishing->publish($version, $request->user());

        if ($issues !== []) {
            return back()->withErrors(['content' => array_map(fn ($issue) => (string) $issue, $issues)]);
        }

        return back()->with('status', 'Veroeffentlicht.');
    }

    /**
     * Stellt eine historische, bereits veroeffentlichte Version wieder her
     * (ADR 0118) -- anders als `publish()` fuer eine Version im
     * `review`-Status, gilt hier dieselbe Berechtigung wie fuer eine echte
     * Veroeffentlichung (`publish`-Gate), nicht nur `update`.
     */
    public function restore(Request $request, ContentVersion $version, ContentPublishingService $publishing): RedirectResponse
    {
        Gate::authorize('publish', $version->activity);

        try {
            $publishing->restoreVersion($version, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['content' => [$exception->getMessage()]]);
        }

        return back()->with('status', 'Wiederhergestellt.');
    }
}

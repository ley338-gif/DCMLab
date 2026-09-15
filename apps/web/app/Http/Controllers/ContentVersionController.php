<?php

namespace App\Http\Controllers;

use App\Activities\ActivityRegistry;
use App\Content\ContentVersioningService;
use App\Content\ContentWriter;
use App\Models\ContentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Generischer Freigabe-Kreislauf fuer `content_versions` (ADR 0071/0080,
 * W6): unabhaengig davon, welcher Editor (Quiz, Lektion, ...) einen Entwurf
 * angelegt hat -- `ActivityRegistry::resolve()` und `ContentWriter::write()`
 * sind bereits agnostisch gegenueber der Herkunft des Entwurfs (ADR 0074),
 * dieser Controller macht dieselbe Verdrahtung fuer alle Editoren
 * wiederverwendbar statt sie je Editor zu duplizieren. Die Route heisst aus
 * historischen Gruenden noch "quiz-versions" (erster Editor war der
 * Quiz-Editor), verarbeitet aber jede Aktivitaet.
 */
class ContentVersionController extends Controller
{
    public function submit(ContentVersion $version, ContentVersioningService $versions): RedirectResponse
    {
        Gate::authorize('update', $version->activity);

        $versions->submitForReview($version);

        return back()->with('status', 'Zur Pruefung eingereicht.');
    }

    public function publish(Request $request, ContentVersion $version, ContentVersioningService $versions, ContentWriter $writer): RedirectResponse
    {
        Gate::authorize('publish', $version->activity);

        $activity = app(ActivityRegistry::class)->resolve($version->activity);
        $issues = $writer->write($activity, $version->payload);

        if ($issues !== []) {
            return back()->withErrors(['content' => array_map(fn ($issue) => (string) $issue, $issues)]);
        }

        $versions->publish($version, $request->user());

        return back()->with('status', 'Veroeffentlicht.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Content\ContentRepository;
use App\Content\RichContent\RichContentRenderer;
use App\Models\Activity;
use App\Models\Lab;
use App\Models\LabAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Eigenstaendige Learner-Route fuer ein Lab (CMS-8a, Abschnitt H:
 * "Standalone-Entscheidung"). Bewusst NUR die Anleitung + ein erster Attempt-
 * Eintrag -- Terminal, Sandbox-Start und Assertion-Auswertung sind CMS-8b/8d,
 * hier existiert noch keine Runtime-Anbindung.
 *
 * Betreiber-Review vor #128: Ansehen und Beginnen sind bewusst zwei
 * getrennte Aktionen (anders als bei Node, wo der reine Seitenaufruf schon
 * einen Attempt anlegt) -- sobald ein Attempt ab CMS-8b/8d an eine echte
 * SandboxSession/TTL/Quota/`activity_progress` gekoppelt ist, soll "started"
 * eindeutig heissen "der Nutzer hat die praktische Uebung begonnen", nicht
 * nur "das Briefing angesehen".
 */
class LabController extends Controller
{
    public function show(Lab $lab, ContentRepository $content): Response
    {
        $activity = $this->publishedOrPreviewableActivity($lab);
        $attempt = $this->attemptFor($activity);

        return Inertia::render('Labs/Show', [
            'lab' => [
                'slug' => $lab->slug,
                'title' => $lab->title['de'] ?? $lab->slug,
                'scenario_title' => $lab->scenario_title['de'] ?? '',
                'difficulty' => $lab->difficulty,
                'points' => $lab->points,
                'estimated_minutes' => $lab->estimated_minutes,
            ],
            'briefing_html' => $lab->rich_content !== null
                ? (new RichContentRenderer($content->glossary()))->render($lab->rich_content)
                : null,
            'attempt' => $attempt === null ? null : ['status' => $attempt->status],
        ]);
    }

    /**
     * Legt den Attempt erst hier an, nicht beim reinen Ansehen -- ein
     * bereits bestehender Attempt (insbesondere ein bereits geloester) wird
     * dabei nie zurueckgesetzt, ein zweiter Klick ist deshalb folgenlos.
     */
    public function start(Request $request, Lab $lab): RedirectResponse
    {
        $activity = $this->publishedOrPreviewableActivity($lab);

        LabAttempt::firstOrCreate(
            ['user_id' => $request->user()->id, 'activity_id' => $activity->id],
            ['status' => 'started', 'started_at' => now()],
        );

        return back();
    }

    /**
     * Wie bei Node (ADR 0110-Muster): ein nicht veroeffentlichtes Lab ist
     * fuer normale Lernende gesperrt, ausser fuer wen die zugehoerige
     * Activity bearbeiten darf (Vorschau aus dem Studio-Editor, CMS-8c) --
     * dieselbe Pruefung gilt fuer start(), sonst koennte ein Lernender einen
     * Attempt auf einem noch gar nicht freigegebenen Lab anlegen.
     */
    private function publishedOrPreviewableActivity(Lab $lab): Activity
    {
        $activity = Activity::query()->where('type', 'lab')->where('key', $lab->slug)->first();

        if ($lab->status !== 'published') {
            abort_unless($activity !== null && Gate::allows('update', $activity), 404);
        }

        abort_if($activity === null, 404);

        return $activity;
    }

    private function attemptFor(Activity $activity): ?LabAttempt
    {
        return LabAttempt::query()
            ->where('user_id', Auth::id())
            ->where('activity_id', $activity->id)
            ->first();
    }
}

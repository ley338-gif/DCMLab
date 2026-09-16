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
 * nur "das Briefing angesehen". Betreiber-Review (zweite Runde): show() und
 * start() pruefen deshalb bewusst UNTERSCHIEDLICH streng -- ein Autor darf
 * einen Draft ansehen (Vorschau), aber NIE darueber einen echten Attempt
 * anlegen. Sonst wuerde eine reine Vorschau ab CMS-8d eine echte
 * SandboxSession/Quota/TTL ausloesen koennen.
 */
class LabController extends Controller
{
    public function show(Lab $lab, ContentRepository $content): Response
    {
        $this->assertVisible($lab);
        $activity = $this->activityFor($lab);
        $attempt = $activity === null ? null : $this->attemptFor($activity);

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
            // Steuert den "Lab starten"-Button in Labs/Show.vue -- eine
            // Autoren-Vorschau eines Drafts darf den Button gar nicht erst
            // zeigen, sonst waere die 404-Sperre in start() die einzige
            // Verteidigungslinie.
            'can_start' => $lab->status === 'published',
        ]);
    }

    /**
     * Legt den Attempt erst hier an, nicht beim reinen Ansehen -- ein
     * bereits bestehender Attempt (insbesondere ein bereits geloester) wird
     * dabei nie zurueckgesetzt, ein zweiter Klick ist deshalb folgenlos.
     *
     * Betreiber-Review (zweite Runde): NUR fuer ein tatsaechlich
     * veroeffentlichtes Lab -- anders als show() gilt hier keine
     * Autoren-Ausnahme. Eine Draft-Vorschau darf niemals einen echten
     * LabAttempt erzeugen.
     */
    public function start(Request $request, Lab $lab): RedirectResponse
    {
        abort_unless($lab->status === 'published', 404);
        $activity = Activity::query()->where('type', 'lab')->where('key', $lab->slug)->firstOrFail();

        LabAttempt::firstOrCreate(
            ['user_id' => $request->user()->id, 'activity_id' => $activity->id],
            ['status' => 'started', 'started_at' => now()],
        );

        return back();
    }

    /**
     * Wie bei Node (ADR 0110-Muster): ein nicht veroeffentlichtes Lab ist
     * fuer normale Lernende gesperrt, ausser fuer wen die zugehoerige
     * Activity bearbeiten darf (Vorschau aus dem Studio-Editor, CMS-8c).
     */
    private function assertVisible(Lab $lab): void
    {
        $activity = $this->activityFor($lab);

        if ($lab->status === 'published') {
            abort_if($activity === null, 404);

            return;
        }

        abort_unless($activity !== null && Gate::allows('update', $activity), 404);
    }

    private function activityFor(Lab $lab): ?Activity
    {
        return Activity::query()->where('type', 'lab')->where('key', $lab->slug)->first();
    }

    private function attemptFor(Activity $activity): ?LabAttempt
    {
        return LabAttempt::query()
            ->where('user_id', Auth::id())
            ->where('activity_id', $activity->id)
            ->first();
    }
}

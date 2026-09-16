<?php

namespace App\Http\Controllers;

use App\Content\ContentRepository;
use App\Content\RichContent\RichContentRenderer;
use App\Models\Activity;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\SandboxTemplate;
use App\Services\RuntimeGoneException;
use App\Services\RuntimeNotReadyException;
use App\Services\RuntimeRequest;
use App\Services\RuntimeSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Eigenstaendige Learner-Route fuer ein Lab (CMS-8a, Abschnitt H:
 * "Standalone-Entscheidung"). CMS-8d verdrahtet hier den Runtime-Lifecycle
 * (Start/Zustand/Exec-Proxy/Beenden) -- alles auf derselben Route wie das
 * Briefing, keine zweite Seite (Betreiber-Vorgabe). Assertion-Auswertung
 * und Progress-/Profilpunkte-Verdrahtung sind bewusst NICHT Teil dieses
 * Commits (siehe `exec()`-Klassendoc) -- das kommt sauber im naechsten
 * Progress-Commit, ebenso wie `show()`s Runtime-/Assertion-Anzeige und die
 * Vue-Seite selbst.
 *
 * Betreiber-Review vor #128: Ansehen und Beginnen sind bewusst zwei
 * getrennte Aktionen (anders als bei Node, wo der reine Seitenaufruf schon
 * einen Attempt anlegt) -- sobald ein Attempt an eine echte
 * SandboxSession/TTL/Quota/`activity_progress` gekoppelt ist, soll "started"
 * eindeutig heissen "der Nutzer hat die praktische Uebung begonnen", nicht
 * nur "das Briefing angesehen". Betreiber-Review (zweite Runde): show() und
 * start() pruefen deshalb bewusst UNTERSCHIEDLICH streng -- ein Autor darf
 * einen Draft ansehen (Vorschau), aber NIE darueber einen echten Attempt
 * anlegen.
 *
 * Ownership-Grundsatz (CMS-8d, Betreiber-Vorgabe "immer ueber User +
 * LabAttempt pruefen"): KEINE Route hier nimmt eine sandbox_id/session_id
 * vom Client entgegen -- jeder Runtime-Endpunkt loest immer den eigenen
 * Attempt des anfragenden Nutzers auf (wie NodeController::attemptFor()),
 * nie eine vom Client mitgegebene ID. Das schliesst eine Luecke, die
 * SandboxController (sandbox/{sandboxId}/...) bewusst NICHT erbt.
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
     * Seit CMS-8d stoesst derselbe, idempotente Klick auch den Runtime-
     * Start an -- siehe Klassendoc und den CMS-8d-Plan Abschnitt B fuer die
     * volle Begruendung: `RuntimeRequest.runtimeKey` ist fuer diesen
     * Attempt immer "lab-attempt:{id}", das macht jeden erneuten Aufruf von
     * Natur aus sicher wiederholbar (Reuse bei aktiver/queued Sitzung, ein
     * echter Neustart nach reaped/destroyed, ein weicher Fehler bei
     * Quota/Konflikt -- nie ein Loeschen).
     *
     * Betreiber-Review (zweite Runde): NUR fuer ein tatsaechlich
     * veroeffentlichtes Lab -- anders als show() gilt hier keine
     * Autoren-Ausnahme. Eine Draft-Vorschau darf niemals einen echten
     * LabAttempt erzeugen.
     */
    public function start(Request $request, Lab $lab, ContentRepository $content, RuntimeSessionService $sessions): RedirectResponse
    {
        abort_unless($lab->status === 'published', 404);
        $activity = Activity::query()->where('type', 'lab')->where('key', $lab->slug)->firstOrFail();
        $user = $request->user();

        $attempt = LabAttempt::firstOrCreate(
            ['user_id' => $user->id, 'activity_id' => $activity->id],
            ['status' => 'started', 'started_at' => now()],
        );

        // Bereits geloest/abgebrochen: Runtime-Start ueberspringen, nicht
        // erneut anstossen (kein Nutzen, unnoetiger Container-Rebuild).
        if ($attempt->status !== 'started') {
            return back();
        }

        // Betreiber-Korrektur (CMS-8d): Runtime-Konfiguration kann nach dem
        // Publish wieder ungueltig werden (Template archiviert, Dataset aus
        // datasets.yml entfernt) -- ohne diese Pruefung wuerde
        // RuntimeSessionService::start() entweder eine ungefangene
        // ValidationException werfen (Template) oder Python erst beim
        // tatsaechlichen Sitzungsaufbau mit einem nicht sauber gemappten
        // DatasetNotFoundError scheitern (Dataset). Ein neutraler Fehler
        // statt eines 500ers.
        if (! $this->runtimeConfigIsAvailable($lab, $content)) {
            return back()->with('runtime_error', 'lab_unavailable');
        }

        $result = $sessions->start(new RuntimeRequest(
            userId: (string) $user->id,
            datasetSlug: (string) $lab->dataset,
            templateSlug: (string) $lab->runtime_template,
            runtimeKey: "lab-attempt:{$attempt->id}",
            activityId: $activity->id,
            labAttemptId: $attempt->id,
        ));

        if (isset($result['error'])) {
            return back()->with('runtime_error', $result['error']);
        }

        return back();
    }

    public function runtimeState(Lab $lab, RuntimeSessionService $sessions): JsonResponse
    {
        $attempt = $this->ownAttemptOrFail($lab);
        $sandboxId = $this->activeSandboxIdOrFail($attempt);

        try {
            $state = $sessions->state($sandboxId);
        } catch (RuntimeGoneException) {
            return response()->json(['error' => 'sandbox_not_found'], 404);
        }

        return response()->json([
            'status' => $state['status'],
            'queue_position' => $state['queue_position'] ?? null,
        ]);
    }

    /**
     * Duenner Runtime-Proxy (dieser Commit) -- reicht den Befehl an die
     * Sandbox weiter und liefert deren Antwort unveraendert zurueck.
     * Bewusst OHNE `events()`/`LabAssertionEvaluator`/solved-Logik: die
     * Auswertungs-/Abschluss-Verdrahtung (`exec -> events -> evaluate ->
     * atomic solve -> activity_progress -> profile points`) gehoert in den
     * naechsten, eigenen Progress-Commit, nicht hierher.
     */
    public function exec(Request $request, Lab $lab, RuntimeSessionService $sessions): JsonResponse
    {
        $data = $request->validate(['command' => 'required|string|max:4096']);

        $attempt = $this->ownAttemptOrFail($lab);
        $sandboxId = $this->activeSandboxIdOrFail($attempt);

        try {
            $result = $sessions->exec($sandboxId, $data['command']);
        } catch (RuntimeGoneException) {
            return response()->json(['error' => 'sandbox_not_found'], 404);
        } catch (RuntimeNotReadyException) {
            return response()->json(['error' => 'sandbox_not_ready'], 409);
        }

        return response()->json($result);
    }

    /**
     * Bewusstes Beenden/Neustart-Wunsch des Lernenden -- fuer einen bereits
     * `solved`-Attempt bietet die Learner-UI dafuer gar keinen Button mehr
     * an (Betreiber-Entscheidung: Runtime nach Solve weiter nutzbar, aber
     * nicht neu startbar), dieser Endpunkt selbst kennt aber keine
     * Sonderregel fuer den Attempt-Status -- er beendet, was aktiv ist.
     */
    public function destroyRuntime(Lab $lab, RuntimeSessionService $sessions): JsonResponse
    {
        $attempt = $this->ownAttemptOrFail($lab);
        $sandboxId = $this->activeSandboxIdOrFail($attempt);

        $sessions->destroy($sandboxId);

        return response()->json(['ok' => true]);
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

    /**
     * Betreiber-Korrektur: `runtime_template`/`dataset` zuerst explizit auf
     * nicht-leere Strings pruefen, bevor sie in eine Query bzw.
     * `array_key_exists()` wandern -- fuer altes oder manuell beschaedigtes
     * Datenmaterial (z. B. eine leere Zeichenkette statt `null`) soll das
     * ausschliesslich `false` ergeben, nie einen TypeError.
     */
    private function runtimeConfigIsAvailable(Lab $lab, ContentRepository $content): bool
    {
        return is_string($lab->runtime_template) && $lab->runtime_template !== ''
            && is_string($lab->dataset) && $lab->dataset !== ''
            && SandboxTemplate::query()->where('slug', $lab->runtime_template)->where('status', 'published')->exists()
            && array_key_exists($lab->dataset, $content->datasets());
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

    /**
     * Ownership-Grundsatz (Klassendoc): der EINZIGE Weg, wie ein
     * Runtime-Endpunkt an eine LabAttempt-Zeile kommt -- immer der eigene
     * Attempt des anfragenden Nutzers, nie eine vom Client mitgegebene ID.
     */
    private function ownAttemptOrFail(Lab $lab): LabAttempt
    {
        $activity = $this->activityFor($lab);
        abort_if($activity === null, 404);

        return LabAttempt::query()
            ->where('user_id', Auth::id())
            ->where('activity_id', $activity->id)
            ->firstOrFail();
    }

    private function activeSandboxIdOrFail(LabAttempt $attempt): string
    {
        $sandboxId = $attempt->currentSandboxSession?->runtime_instance_id;
        abort_if($sandboxId === null, 409, 'Keine aktive Runtime.');

        return $sandboxId;
    }
}

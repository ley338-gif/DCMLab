<?php

namespace App\Http\Controllers;

use App\Activities\ActivityProgressRecorder;
use App\Content\ContentRepository;
use App\Content\LabAssertionEvaluator;
use App\Content\RichContent\RichContentRenderer;
use App\Models\Activity;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\SandboxTemplate;
use App\Services\DashboardHomeService;
use App\Services\ProfileService;
use App\Services\RuntimeGoneException;
use App\Services\RuntimeNotReadyException;
use App\Services\RuntimeRequest;
use App\Services\RuntimeSessionService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Eigenstaendige Learner-Route fuer ein Lab (CMS-8a, Abschnitt H:
 * "Standalone-Entscheidung"). CMS-8d verdrahtet hier den kompletten
 * Runtime-Lifecycle (Start/Zustand/Exec/Beenden) inkl. Assertion-
 * Auswertung, Progress-/Profilpunkte-Verdrahtung und `show()`s Runtime-/
 * Assertion-Anzeige fuer `Labs/Show.vue` -- alles auf derselben Route wie
 * das Briefing, keine zweite Seite (Betreiber-Vorgabe).
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
    /**
     * Oeffentlicher Katalog aller veroeffentlichten Labs (IA-Luecke aus dem
     * Terminologie-Audit: Herausforderungen hatten mit /de/nodes bereits
     * eine eigene Uebersicht, Labs nicht) -- wie NodeController::index()
     * ohne Login sichtbar, `labsOverview()` liefert fuer Gaeste dieselben
     * Labs, nur ohne Attempt-Status (immer 'not_started'). Ein Klick auf
     * ein Lab fuehrt Gaeste ueber die bestehende auth-Middleware auf
     * labs/{lab} zum Login, dieselbe Redirect-Logik wie ueberall sonst.
     */
    public function index(DashboardHomeService $home): Response
    {
        return Inertia::render('Labs/Index', [
            'labs' => $home->labsOverview(Auth::user()),
        ]);
    }

    public function show(Request $request, Lab $lab, ContentRepository $content, RuntimeSessionService $sessions, DashboardHomeService $home): Response
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
            // Betreiber-Vorgabe (CMS-8d): NIE eine sandbox_id an Vue --
            // keine Lab-Route braucht sie vom Client, siehe Klassendoc.
            'runtime' => $attempt === null ? null : $this->liveRuntimeStatus($attempt, $sessions),
            'assertions' => $this->checklistFor($lab, $attempt !== null ? $attempt->assertions_passed : []),
            // Betreiber-Korrektur: ein lokales Prop statt globalem
            // Flash-Sharing -- start()s einziger Rueckkanal fuer einen
            // weichen Runtime-Fehler ist dieser Redirect zurueck auf
            // show(), kein anderer Ort braucht das.
            'runtime_error' => $request->session()->get('runtime_error'),
            // PR #148, Prioritaet 1: Labs/Show darf kein Dead-End mehr sein
            // -- immer mitberechnet (auch ohne Activity/Attempt gibt es
            // wenigstens den Katalog-Fallback), Vue zeigt es nur im
            // Abschluss-Bereich eines geloesten Attempts.
            'next_step' => $activity === null ? ['type' => 'labs_index', 'lesson_id' => null, 'lesson_title' => null] : $home->nextStepAfterLab($activity),
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

        try {
            $result = $sessions->start(new RuntimeRequest(
                userId: (string) $user->id,
                datasetSlug: (string) $lab->dataset,
                templateSlug: (string) $lab->runtime_template,
                runtimeKey: "lab-attempt:{$attempt->id}",
                activityId: $activity->id,
                labAttemptId: $attempt->id,
            ));
        } catch (ConnectionException|RequestException $e) {
            // Betreiber-Korrektur (Haerten): quota_exceeded/active_runtime_
            // exists kommen bereits als weiches ['error' => ...] zurueck
            // (siehe unten) -- ein unmapped 5xx/Transport-Fehler wird
            // dagegen geworfen, nicht zurueckgegeben, und braucht deshalb
            // sein eigenes catch, um denselben neutralen Rueckkanal zu
            // nutzen statt eines ungefangenen 500ers.
            report($e);

            return back()->with('runtime_error', 'sandbox_unavailable');
        }

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
        } catch (RuntimeNotReadyException) {
            // Siehe liveRuntimeStatus() -- theoretischer Verteidigungspfad,
            // state() meint normalerweise schon per 200 "queued".
            return response()->json(['status' => 'queued', 'queue_position' => null]);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return response()->json(['error' => 'sandbox_unavailable'], 503);
        }

        return response()->json([
            'status' => $state['status'],
            'queue_position' => $state['queue_position'] ?? null,
        ]);
    }

    /**
     * `exec -> events -> evaluate -> atomic solve -> activity_progress ->
     * profile points` (CMS-8d): `events()` wird bewusst NACH jedem exec()
     * gegen die VOLLE Exec-Historie ausgewertet, nicht nur gegen den
     * zuletzt ausgefuehrten Befehl -- das ist zugleich die einzige Stelle,
     * die fuer einen spaeteren `c_store_received`-Typ (nicht an den
     * letzten Befehl gebunden) schon richtig ist, ohne diesen Pfad nochmal
     * anzufassen.
     */
    public function exec(
        Request $request,
        Lab $lab,
        RuntimeSessionService $sessions,
        ActivityProgressRecorder $progressRecorder,
        ProfileService $profiles,
    ): JsonResponse {
        $data = $request->validate(['command' => 'required|string|max:4096']);
        $user = $request->user();

        $attempt = $this->ownAttemptOrFail($lab);
        $sandboxId = $this->activeSandboxIdOrFail($attempt);

        try {
            $result = $sessions->exec($sandboxId, $data['command']);
        } catch (RuntimeGoneException) {
            return response()->json(['error' => 'sandbox_not_found'], 404);
        } catch (RuntimeNotReadyException) {
            return response()->json(['error' => 'sandbox_not_ready'], 409);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return response()->json(['error' => 'sandbox_unavailable'], 503);
        }

        $assertionsPassed = $attempt->assertions_passed;
        $allSatisfied = false;
        $progressContext = null;

        // Schon geloest: nicht mehr neu auswerten (kein wiederholtes
        // persistProgress(), keine doppelten Achievement-Toasts) -- spart
        // in diesem Fall sogar den events()-Aufruf selbst.
        if ($attempt->status === 'started') {
            // events() ist ein externer Aufruf (Redis + curl in der
            // Toolbox, CMS-8b) -- bewusst AUSSERHALB der Transaktion, damit
            // die Datenbank-Sperre unten nicht auf einen Netzwerk-
            // Roundtrip wartet. Faengt dieselben Fehler wie exec() oben ab
            // -- ein zwischen exec() und events() weggeraeumter/noch nicht
            // bereiter Sandbox darf keinen teilweise gespeicherten Solve
            // erzeugen, sondern bricht sauber ab, BEVOR die Transaktion
            // ueberhaupt beginnt.
            try {
                $events = $sessions->events($sandboxId);
            } catch (RuntimeGoneException) {
                return response()->json(['error' => 'sandbox_not_found'], 404);
            } catch (RuntimeNotReadyException) {
                return response()->json(['error' => 'sandbox_not_ready'], 409);
            } catch (ConnectionException|RequestException $e) {
                report($e);

                return response()->json(['error' => 'sandbox_unavailable'], 503);
            }

            [$assertionsPassed, $allSatisfied, $progressContext] = DB::transaction(function () use ($lab, $attempt, $events, $progressRecorder, $user) {
                // Betreiber-Korrektur: lockForUpdate() schliesst die Race
                // zwischen zwei fast gleichzeitigen Terminal-Requests UND
                // stellt sicher, dass ein Fehler zwischen "solved
                // speichern" und "Progress buchen" nie einen Attempt
                // zuruecklaesst, der bereits solved ist, aber nie
                // ActivityProgressRecorder::persistProgress() sah (sonst
                // wuerde die naechste exec() wegen status==='solved' nicht
                // mehr neu auswerten -- der Progress waere dauerhaft
                // verloren).
                $locked = LabAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'started') {
                    return [$locked->assertions_passed, $locked->status === 'solved', null];
                }

                $eval = (new LabAssertionEvaluator)->evaluate($lab, $events, $locked->assertions_passed);
                $locked->assertions_passed = $eval->passed;

                $context = null;

                if ($eval->allSatisfied) {
                    $locked->status = 'solved';
                    $locked->completed_at = now();
                }

                $locked->save();

                if ($eval->allSatisfied) {
                    // Betreiber-Korrektur: NUR persistProgress() hier drin,
                    // NICHT evaluateAchievements() -- AchievementService::
                    // unlock() faengt einen Unique-Constraint-Verstoss zwar
                    // per PHP-catch ab, aber unter PostgreSQL bleibt diese
                    // Transaktion danach ohne SAVEPOINT "aborted" und wuerde
                    // beim COMMIT auch den gerade gespeicherten Attempt
                    // wieder verwerfen. Siehe ActivityProgressContext.
                    $context = $progressRecorder->persistProgress('lab', $lab->slug, $user);
                }

                return [$eval->passed, $eval->allSatisfied, $context];
            });
        }

        $unlocked = [];

        if ($progressContext !== null) {
            // Erst NACH dem Commit: eine Achievement-Race kann die
            // Attempt-/Progress-Transaktion so nicht mehr vergiften, und
            // ProfileService liest bereits den committeten
            // activity_progress.score.
            $unlocked = $progressRecorder->evaluateAchievements($progressContext);
            $profiles->recomputeAfterLabSolve($user);
        }

        return response()->json([
            ...$result,
            'assertions' => $this->checklistFor($lab, $assertionsPassed),
            'all_satisfied' => $allSatisfied,
            'unlocked_achievements' => $unlocked,
        ]);
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

        try {
            $sessions->destroy($sandboxId);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return response()->json(['error' => 'sandbox_unavailable'], 503);
        }

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

    /**
     * @return array{status: string, queue_position: int|null}|null
     */
    private function liveRuntimeStatus(LabAttempt $attempt, RuntimeSessionService $sessions): ?array
    {
        $session = $attempt->currentSandboxSession;
        $sandboxId = $session?->runtime_instance_id;

        if ($sandboxId === null) {
            return null;
        }

        try {
            $state = $sessions->state($sandboxId);
        } catch (RuntimeGoneException) {
            // PR #148, "Runtime ended/expired": diesen Zweig zu erreichen
            // bedeutet, dass RuntimeSessionService::withReconciliation() die
            // Sitzung GERADE JETZT (in diesem Aufruf) als 'reaped' reconciled
            // hat (CMS-8b Idle-Timeout-Cleanup) -- eine bereits explizit
            // zerstoerte Sitzung (restartRuntime()) wird nie erneut ueber
            // diesen Pfad erreicht, weil destroy() denselben current_
            // sandbox_session_id-Zeiger im selben Schritt nullt
            // (RuntimeSessionService::finishSession()); der naechste Aufruf
            // findet dann schon oben den `$sandboxId === null`-Fruehausstieg.
            // Keine neue Persistenz, keine Python-Aenderung noetig.
            return ['status' => 'expired', 'queue_position' => null];
        } catch (RuntimeNotReadyException) {
            // state() repraesentiert "noch nicht bereit" normalerweise
            // schon als 200 {status: 'queued'} -- dieser Zweig ist nur
            // Verteidigung fuer den theoretisch moeglichen 409-Pfad in
            // SandboxClient::mapKnownFailure(), keine echte Fehlermeldung.
            return ['status' => 'queued', 'queue_position' => null];
        } catch (ConnectionException|RequestException $e) {
            // Betreiber-Korrektur (Haerten): ein voruebergehend nicht
            // erreichbarer Sandbox-Service (Transport-Fehler oder ein von
            // mapKnownFailure() nicht abgefangener 5xx) darf nicht das
            // gesamte Lab-Briefing mit einem 500er zerstoeren -- ein
            // nicht-fataler Zustand statt einer geworfenen Exception. Die
            // Exception bleibt trotzdem serverseitig sichtbar (Logs), nur
            // der Lernende sieht keine Details.
            report($e);

            return ['status' => 'sandbox_unavailable', 'queue_position' => null];
        }

        return [
            'status' => $state['status'],
            'queue_position' => $state['queue_position'] ?? null,
        ];
    }

    /**
     * Betreiber-Vorgabe: keine rohen prefix-Werte und keine internen
     * `type:prefix`-Identifier an den Client -- nur Typ + erfuellt/nicht
     * erfuellt. Der Autoren-Index (nicht geheim, entspricht einfach der
     * Reihenfolge im Editor) gibt Vue bei mehreren gleichartigen
     * Assertions trotzdem eine stabile Zeilen-Identitaet.
     *
     * @param  list<string>  $assertionsPassed
     * @return list<array{index: int, type: string, passed: bool}>
     */
    private function checklistFor(Lab $lab, array $assertionsPassed): array
    {
        $checklist = [];

        foreach ($lab->assertions as $index => $assertion) {
            $checklist[] = [
                'index' => $index,
                'type' => (string) $assertion['type'],
                'passed' => in_array(LabAssertionEvaluator::identifierFor($assertion), $assertionsPassed, true),
            ];
        }

        return $checklist;
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

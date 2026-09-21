<?php

namespace App\Services;

use App\Models\LabAttempt;
use App\Models\SandboxSession;
use App\Models\SandboxTemplate;
use Illuminate\Validation\ValidationException;

/**
 * Zentraler Aufrufer von RuntimeProviderRegistry (CMS-8b, Betreiber-Review):
 * buendelt Vorlagen-/Provider-Aufloesung, `sandbox_sessions`-Persistenz,
 * `lab_attempts`-Verknuepfung und Gone/Reaped-Reconciliation an EINER
 * Stelle, statt sie im Controller zu belassen -- `SandboxController`
 * (Sandbox, heute) und der kuenftige Lab-Runtime-Start (CMS-8d) rufen
 * denselben Service auf, keine Logik-Kopie.
 */
final readonly class RuntimeSessionService
{
    public function __construct(
        private RuntimeProviderRegistry $runtimeProviders,
    ) {}

    /**
     * @return array<string, mixed> `$result` des Providers (inkl. eines
     *                              weichen `error`-Schluessels bei Quote/
     *                              Konflikt) plus `session_id`, wenn eine
     *                              SandboxSession angelegt wurde.
     */
    public function start(RuntimeRequest $request): array
    {
        $template = SandboxTemplate::query()
            ->where('slug', $request->templateSlug)
            ->where('status', 'published')
            ->first();

        if ($template === null) {
            throw ValidationException::withMessages([
                'template' => 'Es ist keine veroeffentlichte Runtime-Vorlage mit diesem Slug vorhanden.',
            ]);
        }

        $result = $this->runtimeProviders->for($template->runtime_provider)->create($request);

        if (isset($result['error'])) {
            return $result;
        }

        $session = $this->reuseOrCreateSession($request, $template, $result);

        if ($request->labAttemptId !== null) {
            LabAttempt::query()->whereKey($request->labAttemptId)->update([
                'current_sandbox_session_id' => $session->id,
            ]);
        }

        return [...$result, 'session_id' => $session->id];
    }

    /**
     * CMS-8b, Betreiber-Review (drittes Review): Python ist fuer denselben
     * `runtime_key` idempotent -- ein zweiter `start()`-Aufruf mit
     * gleichem `runtime_key` bekommt dieselbe `sandbox_id` zurueck, KEINE
     * neue Runtime. Ohne diese Wiederverwendung wuerde jeder erneute
     * Start-Klick eine weitere `SandboxSession`-Zeile fuer dieselbe
     * Runtime anlegen; `sessionFor()` (immer die neueste Zeile) wuerde
     * `destroy()` dann nur auf die neueste Zeile anwenden, aeltere Zeilen
     * blieben dauerhaft faelschlich `running`.
     *
     * @param  array<string, mixed>  $result
     */
    private function reuseOrCreateSession(RuntimeRequest $request, SandboxTemplate $template, array $result): SandboxSession
    {
        $runtimeId = $result['sandbox_id'] ?? null;

        $existing = $runtimeId !== null
            ? SandboxSession::query()->where('runtime_instance_id', $runtimeId)->latest('id')->first()
            : null;

        if ($existing !== null) {
            // Wiederverwendung nur bei echtem Owner-Match -- eine falsche
            // Zuordnung waere schlimmer als ein harter Fehler (in der
            // Praxis sollte das nie passieren, Pythons `runtime_key` ist
            // bereits Owner-scoped; das hier ist die zweite,
            // Laravel-seitige Verteidigungslinie).
            if ((string) $existing->user_id !== $request->userId
                || $existing->runtime_provider !== $template->runtime_provider
                || $existing->lab_attempt_id !== $request->labAttemptId) {
                throw new RuntimeSessionOwnerMismatchException($runtimeId);
            }

            // Betreiber-Review (Live-Smoke-Test PR #148): eine wiederverwendete
            // Zeile kann von einem frueheren Lebenszyklus noch `status='reaped'`
            // (oder `'destroyed'`) samt `finished_at` tragen -- Pythons
            // `runtime_key`-Idempotenz kann denselben `sandbox_id` liefern,
            // obwohl die Sitzung zwischenzeitlich unter Warteschlangen-Druck
            // reconciled wurde und jetzt (z. B. nach Freiwerden eines
            // Kontingent-Slots) tatsaechlich wieder aktiv ist. Ohne dieses
            // Update wuerde die Zeile eine tote Sitzung vortaeuschen, obwohl
            // `state()` sie beim naechsten Aufruf laengst wieder als laufend
            // meldet.
            $existing->update([
                'status' => $result['status'] ?? 'running',
                'finished_at' => null,
                'last_activity_at' => now(),
            ]);

            return $existing;
        }

        return SandboxSession::query()->create([
            'user_id' => $request->userId,
            'activity_id' => $request->activityId,
            'sandbox_template_id' => $template->id,
            'lab_attempt_id' => $request->labAttemptId,
            'runtime_provider' => $template->runtime_provider,
            'runtime_instance_id' => $runtimeId,
            'status' => $result['status'] ?? 'running',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    /**
     * IDOR-Schutz fuer state()/exec()/destroy() (Betreiber-Review nach PR
     * #175): anders als jede Node-/Lesson-Session-ID ist die sandbox_id ein
     * echter Client-Parameter (siehe Klassenkommentar von
     * `SandboxController`). Nur eine existierende, dem Nutzer gehoerende
     * `SandboxSession`-Zeile zaehlt als Treffer -- eine unbekannte oder
     * einem anderen Nutzer gehoerende ID (auch eine alte, vor ADR 0096/
     * CMS-2b entstandene ohne eindeutige Zuordnung) wird bewusst NICHT ueber
     * einen Fallback freigegeben.
     */
    public function belongsToUser(string $sandboxId, int $userId): bool
    {
        return SandboxSession::query()
            ->where('runtime_instance_id', $sandboxId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function state(string $sandboxId): array
    {
        return $this->withReconciliation($sandboxId, fn (RuntimeProviderContract $provider) => $provider->state($sandboxId));
    }

    /**
     * @return array<string, mixed>
     */
    public function exec(string $sandboxId, string $command): array
    {
        $session = $this->sessionFor($sandboxId);
        $session?->update(['last_activity_at' => now()]);

        return $this->withReconciliation($sandboxId, fn (RuntimeProviderContract $provider) => $provider->exec($sandboxId, $command), $session);
    }

    /**
     * @return array<string, mixed>
     */
    public function events(string $sandboxId): array
    {
        return $this->withReconciliation($sandboxId, fn (RuntimeProviderContract $provider) => $provider->events($sandboxId));
    }

    public function destroy(string $sandboxId): void
    {
        $session = $this->sessionFor($sandboxId);

        $this->runtimeProviders->for($this->runtimeProviderFor($sandboxId, $session))->delete($sandboxId);

        $this->finishSession($session, 'destroyed');
    }

    /**
     * Ruft `$call` ueber den zur Sitzung passenden Provider auf und
     * reconciled EINMAL zentral, wenn die Runtime-Seite die Sitzung nicht
     * mehr kennt (RuntimeGoneException, CMS-8b Betreiber-Review) --
     * `state()`/`exec()`/`events()` teilen sich diesen Pfad, statt ihn
     * dreifach zu duplizieren.
     *
     * @param  callable(RuntimeProviderContract): array<string, mixed>  $call
     * @return array<string, mixed>
     */
    private function withReconciliation(string $sandboxId, callable $call, ?SandboxSession $session = null): array
    {
        $session ??= $this->sessionFor($sandboxId);
        $provider = $this->runtimeProviders->for($this->runtimeProviderFor($sandboxId, $session));

        try {
            return $call($provider);
        } catch (RuntimeGoneException $e) {
            $this->reconcileGone($session);

            throw $e;
        }
    }

    private function reconcileGone(?SandboxSession $session): void
    {
        $this->finishSession($session, 'reaped');
    }

    /**
     * Gemeinsamer Abschluss-Pfad fuer `destroy()` (explizites Beenden) und
     * `reconcileGone()` (Python meldet 404, CMS-8b Betreiber-Review): in
     * BEIDEN Faellen ist die Runtime vorbei, und `LabAttempt.
     * current_sandbox_session_id` darf nicht auf eine beendete Session
     * zeigen bleiben -- sonst wuerde ein spaeterer Lab-Runtime-Neustart
     * (CMS-8d) einen Komfortzeiger auf eine tote Session vorfinden. Vorher
     * raeumte nur `reconcileGone()` den Zeiger; `destroy()` liess ihn
     * stehen.
     */
    private function finishSession(?SandboxSession $session, string $status): void
    {
        if ($session === null) {
            return;
        }

        $session->update(['status' => $status, 'finished_at' => now()]);

        if ($session->lab_attempt_id === null) {
            return;
        }

        LabAttempt::query()
            ->whereKey($session->lab_attempt_id)
            ->where('current_sandbox_session_id', $session->id)
            ->update(['current_sandbox_session_id' => null]);
    }

    /**
     * `$session` kann bereits vorab geladen uebergeben werden, um dieselbe
     * Abfrage nicht doppelt zu stellen; sonst wird sie hier nachgeholt.
     * "docker" bleibt der Fallback, solange keine Sitzung bekannt ist (z. B.
     * eine sandbox_id aus der Zeit vor ADR 0096/CMS-2b).
     */
    private function runtimeProviderFor(string $sandboxId, ?SandboxSession $session = null): string
    {
        $session ??= $this->sessionFor($sandboxId);

        if ($session === null) {
            return 'docker';
        }

        return $session->runtime_provider;
    }

    private function sessionFor(string $sandboxId): ?SandboxSession
    {
        return SandboxSession::query()
            ->where('runtime_instance_id', $sandboxId)
            ->latest('id')
            ->first();
    }
}

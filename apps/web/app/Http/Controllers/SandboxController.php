<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lesson;
use App\Models\SandboxSession;
use App\Models\SandboxTemplate;
use App\Services\AchievementService;
use App\Services\RuntimeProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Proxy zur Spielwiese (Abschnitt 6) -- kein eigener LAUFZEITzustand auf der
 * Laravel-Seite ("kein Zustand ueber Sitzungen hinweg, Neustart ist immer
 * eine Zeile"): die sandbox_id lebt weiterhin nur im Browser. Seit ADR 0096
 * (CMS-2b) schreibt create() aber einen durablen SandboxSession-Datensatz
 * (Historie/Audit, nicht Laufzeitzustand) -- state()/exec()/destroy() nutzen
 * ihn, um `runtime_provider` korrekt nachzuschlagen statt ihn zu raten.
 * Kennt eine sandbox_id keine Sitzung (z. B. aus einer Zeit vor dieser
 * Migration), bleibt "docker" der Fallback.
 */
class SandboxController extends Controller
{
    public function create(Lesson $lesson, RuntimeProviderRegistry $runtimeProviders, AchievementService $achievements): JsonResponse
    {
        $datasetSlug = $lesson->sandbox['dataset'] ?? null;

        if ($datasetSlug === null) {
            throw ValidationException::withMessages([
                'lesson' => 'Diese Lektion hat keine Spielwiese.',
            ]);
        }

        $template = SandboxTemplate::query()->where('status', 'published')->first();

        if ($template === null) {
            throw ValidationException::withMessages([
                'lesson' => 'Es ist keine freigegebene Sandbox-Vorlage vorhanden.',
            ]);
        }

        $result = $runtimeProviders->for($template->runtime_provider)->create((string) Auth::id(), $datasetSlug);

        if (isset($result['error'])) {
            return response()->json($result, 429);
        }

        // activity_id bleibt null, solange content:sync fuer diese Lektion
        // noch keinen type=sandbox-Eintrag angelegt hat (ContentSync::
        // syncLessons()) -- die Sitzung entsteht trotzdem.
        SandboxSession::query()->create([
            'user_id' => Auth::id(),
            'activity_id' => Activity::query()->where('type', 'sandbox')->where('key', $lesson->lesson_id)->value('id'),
            'sandbox_template_id' => $template->id,
            'runtime_provider' => $template->runtime_provider,
            'runtime_instance_id' => $result['sandbox_id'] ?? null,
            'status' => $result['status'] ?? 'running',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        // "sandbox-starter" nur bei wirklich erzeugter Umgebung, nicht beim
        // reinen Anklicken der Lektion (Achievement-System, Abschnitt 6).
        $unlockResult = $achievements->unlock(Auth::user(), 'sandbox-starter', [
            'lesson' => $lesson->lesson_id,
            'source' => 'sandbox_started',
        ]);

        $unlockedAchievements = $unlockResult->isNewlyUnlocked() && $unlockResult->definition !== null
            ? [$achievements->toArray($unlockResult->definition, $unlockResult->unlock)]
            : [];

        return response()->json([...$result, 'unlocked_achievements' => $unlockedAchievements], 201);
    }

    public function state(string $sandboxId, RuntimeProviderRegistry $runtimeProviders): JsonResponse
    {
        return response()->json($runtimeProviders->for($this->runtimeProviderFor($sandboxId))->state($sandboxId));
    }

    public function exec(Request $request, string $sandboxId, RuntimeProviderRegistry $runtimeProviders): JsonResponse
    {
        $data = $request->validate(['command' => 'required|string']);

        $session = $this->sessionFor($sandboxId);
        $session?->update(['last_activity_at' => now()]);

        return response()->json($runtimeProviders->for($this->runtimeProviderFor($sandboxId, $session))->exec($sandboxId, $data['command']));
    }

    public function destroy(string $sandboxId, RuntimeProviderRegistry $runtimeProviders): JsonResponse
    {
        $session = $this->sessionFor($sandboxId);

        $runtimeProviders->for($this->runtimeProviderFor($sandboxId, $session))->delete($sandboxId);

        $session?->update(['status' => 'destroyed', 'finished_at' => now()]);

        return response()->json(['ok' => true]);
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

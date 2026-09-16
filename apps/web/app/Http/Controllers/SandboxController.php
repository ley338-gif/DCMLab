<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lesson;
use App\Models\SandboxTemplate;
use App\Services\AchievementService;
use App\Services\RuntimeGoneException;
use App\Services\RuntimeNotReadyException;
use App\Services\RuntimeRequest;
use App\Services\RuntimeSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Proxy zur Spielwiese (Abschnitt 6) -- kein eigener LAUFZEITzustand auf der
 * Laravel-Seite ("kein Zustand ueber Sitzungen hinweg, Neustart ist immer
 * eine Zeile"): die sandbox_id lebt weiterhin nur im Browser. Seit CMS-8b
 * (Betreiber-Review) ist dieser Controller nur noch ein duenner HTTP-
 * Wrapper -- die eigentliche Vorlagen-/Provider-Aufloesung, `sandbox_
 * sessions`-Persistenz und Gone/Reaped-Reconciliation lebt zentral in
 * `RuntimeSessionService`, damit ein kuenftiger Lab-Runtime-Start (CMS-8d)
 * dieselbe Logik wiederverwendet statt sie zu kopieren.
 */
class SandboxController extends Controller
{
    public function create(Lesson $lesson, RuntimeSessionService $sessions, AchievementService $achievements): JsonResponse
    {
        $datasetSlug = $lesson->sandbox['dataset'] ?? null;

        if ($datasetSlug === null) {
            throw ValidationException::withMessages([
                'lesson' => 'Diese Lektion hat keine Spielwiese.',
            ]);
        }

        // Sandbox waehlt weiterhin "die erste veroeffentlichte Vorlage"
        // (unveraendertes Verhalten) -- nur DIESE Entscheidung bleibt hier,
        // RuntimeSessionService validiert danach den konkreten Slug, statt
        // selbst zu raten (CMS-8b: ein Lab wird spaeter seinen eigenen,
        // vom Autor gewaehlten Slug mitgeben, keine "erste Vorlage" mehr).
        $template = SandboxTemplate::query()->where('status', 'published')->first();

        if ($template === null) {
            throw ValidationException::withMessages([
                'lesson' => 'Es ist keine freigegebene Sandbox-Vorlage vorhanden.',
            ]);
        }

        $result = $sessions->start(new RuntimeRequest(
            userId: (string) Auth::id(),
            datasetSlug: $datasetSlug,
            templateSlug: $template->slug,
            runtimeKey: 'sandbox:user:'.Auth::id(),
            activityId: Activity::query()->where('type', 'sandbox')->where('key', $lesson->lesson_id)->value('id'),
        ));

        if (isset($result['error'])) {
            return response()->json($result, $result['error'] === 'active_runtime_exists' ? 409 : 429);
        }

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

    public function state(string $sandboxId, RuntimeSessionService $sessions): JsonResponse
    {
        try {
            return response()->json($sessions->state($sandboxId));
        } catch (RuntimeGoneException) {
            return response()->json(['error' => 'sandbox_not_found'], 404);
        }
    }

    public function exec(Request $request, string $sandboxId, RuntimeSessionService $sessions): JsonResponse
    {
        // 4096 Zeichen: identisch zu services/sandbox's Pydantic-Limit
        // (CMS-8b, Betreiber-Review) -- der Befehl wird seit den Exec-Facts
        // dauerhaft in Redis gespeichert, nicht mehr nur transient
        // ausgefuehrt.
        $data = $request->validate(['command' => 'required|string|max:4096']);

        try {
            return response()->json($sessions->exec($sandboxId, $data['command']));
        } catch (RuntimeGoneException) {
            return response()->json(['error' => 'sandbox_not_found'], 404);
        } catch (RuntimeNotReadyException) {
            return response()->json(['error' => 'sandbox_not_ready'], 409);
        }
    }

    public function destroy(string $sandboxId, RuntimeSessionService $sessions): JsonResponse
    {
        $sessions->destroy($sandboxId);

        return response()->json(['ok' => true]);
    }
}

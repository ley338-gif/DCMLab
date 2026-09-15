<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\SandboxTemplate;
use App\Services\AchievementService;
use App\Services\RuntimeProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Proxy zur Spielwiese (Abschnitt 6) -- kein eigener Zustand auf der
 * Laravel-Seite: "kein Zustand ueber Sitzungen hinweg, Neustart ist immer
 * eine Zeile". Die sandbox_id lebt nur im Browser (Vue-Komponentenstand).
 *
 * Seit ADR 0096 (CMS-2) geht jeder Aufruf ueber RuntimeProviderRegistry statt
 * direkt gegen SandboxClientContract -- state()/exec()/destroy() kennen den
 * urspruenglichen Provider einer sandbox_id noch nicht (kein SandboxSession-
 * Datensatz, siehe docs/offene-fragen.md) und loesen deshalb weiterhin fest
 * "docker" auf; nur create() prueft bereits gegen ein echtes, freigegebenes
 * SandboxTemplate.
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
        return response()->json($runtimeProviders->for('docker')->state($sandboxId));
    }

    public function exec(Request $request, string $sandboxId, RuntimeProviderRegistry $runtimeProviders): JsonResponse
    {
        $data = $request->validate(['command' => 'required|string']);

        return response()->json($runtimeProviders->for('docker')->exec($sandboxId, $data['command']));
    }

    public function destroy(string $sandboxId, RuntimeProviderRegistry $runtimeProviders): JsonResponse
    {
        $runtimeProviders->for('docker')->delete($sandboxId);

        return response()->json(['ok' => true]);
    }
}

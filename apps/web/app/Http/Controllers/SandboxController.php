<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Services\AchievementService;
use App\Services\SandboxClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Proxy zur Spielwiese (Abschnitt 6) -- kein eigener Zustand auf der
 * Laravel-Seite: "kein Zustand ueber Sitzungen hinweg, Neustart ist immer
 * eine Zeile". Die sandbox_id lebt nur im Browser (Vue-Komponentenstand).
 */
class SandboxController extends Controller
{
    public function create(Lesson $lesson, SandboxClient $sandbox, AchievementService $achievements): JsonResponse
    {
        $datasetSlug = $lesson->sandbox['dataset'] ?? null;

        if ($datasetSlug === null) {
            throw ValidationException::withMessages([
                'lesson' => 'Diese Lektion hat keine Spielwiese.',
            ]);
        }

        $result = $sandbox->create((string) Auth::id(), $datasetSlug);

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

    public function state(string $sandboxId, SandboxClient $sandbox): JsonResponse
    {
        return response()->json($sandbox->state($sandboxId));
    }

    public function exec(Request $request, string $sandboxId, SandboxClient $sandbox): JsonResponse
    {
        $data = $request->validate(['command' => 'required|string']);

        return response()->json($sandbox->exec($sandboxId, $data['command']));
    }

    public function destroy(string $sandboxId, SandboxClient $sandbox): JsonResponse
    {
        $sandbox->delete($sandboxId);

        return response()->json(['ok' => true]);
    }
}

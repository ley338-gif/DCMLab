<?php

namespace App\Http\Controllers;

use App\Models\ContentVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Uebergreifende Review-Queue (ADR 0092): vorher musste ein Reviewer jede
 * einzelne Editor-Seite kennen und einzeln aufrufen, um zu sehen, ob dort
 * ein Entwurf zur Pruefung eingereicht wurde -- `content_versions` ist
 * bereits generisch ueber alle Aktivitaetstypen (ADR 0071, W3,
 * `App\Content\ContentVersioningService`-Klassendoc), diese Seite ist die
 * erste, die das auch quer ueber alle Typen ausliest statt je Aktivitaet.
 * Freigeben selbst bleibt der bestehende, generische
 * `ContentVersionController::publish()` -- kein zweiter Freigabeweg.
 */
class ReviewQueueController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $items = ContentVersion::query()
            ->where('status', 'review')
            ->with(['activity', 'author'])
            ->latest()
            ->get()
            ->unique('activity_id')
            ->map(function (ContentVersion $version) {
                $activity = $version->activity;

                return [
                    'version_id' => $version->id,
                    'activity_type' => $activity->type,
                    'activity_key' => $activity->key,
                    'title' => $activity->title['de'] ?? $activity->key,
                    'author_name' => $version->author?->name,
                    'edit_url' => $this->editUrl($activity->type, $activity->key, $version->payload),
                ];
            })
            ->values();

        return Inertia::render('Author/ReviewQueue', [
            'items' => $items,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function editUrl(string $type, string $key, array $payload): ?string
    {
        return match ($type) {
            'lesson' => "/de/author/lessons/{$key}/edit",
            'exam' => "/de/author/exams/{$key}/edit",
            'node' => "/de/studio/nodes/{$key}",
            // Alle Achievements teilen eine Aktivitaet (`type=achievement,
            // key=catalog`, ADR 0083) -- der konkrete Slug steht nur im
            // Entwurfs-Payload selbst, nie im Aktivitaetsschluessel.
            'achievement' => isset($payload['slug']) ? "/de/author/achievements/{$payload['slug']}/edit" : null,
            default => null,
        };
    }
}

<?php

namespace App\Http\Controllers;

use App\Activities\ActivityRegistry;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vierter Autoren-Editor (ADR 0071/0083, W6.4): ein einzelnes Achievement
 * in `content/achievements.yml` anlegen oder bearbeiten. Anders als bei
 * Lektion/Prüfung gibt es keine feste Menge editierbarer Instanzen -- der
 * Slug in der Route kann ein bestehendes Achievement sein (wird geladen)
 * oder ein neuer (Formular startet mit Standardwerten), siehe
 * `AchievementCatalogActivity`-Klassendoc fuer die Aktivitaets-Modellierung.
 *
 * Bewusst nicht Teil dieses Editors: ein echter Bild-Upload. `image` bleibt
 * ein Freitextfeld (Dateiname unter `public/images/achievements/`) -- ein
 * Datei-Upload braucht einen eigenen, sicherheitsgeprueften Endpunkt
 * ausserhalb der `content_versions`-Transaktion (Bilder sind keine
 * versionierte Prosa), siehe docs/offene-fragen.md.
 */
class AchievementEditorController extends Controller
{
    public function edit(string $slug, ContentRepository $content): Response
    {
        $activity = $this->activity();
        Gate::authorize('update', $activity);

        $achievements = $content->achievements();
        $existing = $this->findBySlug($achievements, $slug);

        $pendingVersion = $activity->contentVersions()
            ->whereIn('status', ['draft', 'review'])
            ->latest()
            ->first();

        return Inertia::render('Author/AchievementEditor', [
            'slug' => $slug,
            'fields' => $pendingVersion !== null && ($pendingVersion->payload['slug'] ?? null) === $slug
                ? $pendingVersion->payload
                : $this->fieldsFrom($slug, $existing),
            'is_new' => $existing === null,
            'pending_version' => $pendingVersion === null ? null : [
                'id' => $pendingVersion->id,
                'status' => $pendingVersion->status,
                'slug' => $pendingVersion->payload['slug'] ?? null,
            ],
            'can_publish' => Gate::allows('publish', $activity),
        ]);
    }

    public function validateDraft(Request $request, string $slug): JsonResponse
    {
        $activity = $this->activity();
        Gate::authorize('update', $activity);

        $issues = app(ActivityRegistry::class)->resolve($activity)->validate($this->validatedFields($request, $slug));

        return response()->json([
            'issues' => array_map(fn ($issue) => (string) $issue, $issues),
        ]);
    }

    public function storeDraft(Request $request, string $slug, ContentVersioningService $versions): RedirectResponse
    {
        $activity = $this->activity();
        Gate::authorize('update', $activity);

        $versions->createDraft($activity, $this->validatedFields($request, $slug), $request->user());

        return back()->with('status', 'Entwurf gespeichert.');
    }

    private function activity(): Activity
    {
        return Activity::query()->where('type', 'achievement')->where('key', 'catalog')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFields(Request $request, string $slug): array
    {
        $fields = $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
            'image' => 'required|string',
            'category' => 'required|string',
            'rarity' => 'nullable|string',
            'scope' => 'nullable|string|in:personal,global',
            'points' => 'required|integer|min:0',
            'is_hidden' => 'boolean',
            'sort_order' => 'required|integer|min:0',
            'unlock_when' => 'nullable|array',
            'unlock_when.type' => 'nullable|string|in:activity_completed,track_passed,first_solve',
            'unlock_when.activity_type' => 'nullable|string',
            'unlock_when.key' => 'nullable|string',
            'unlock_when.track' => 'nullable|string',
        ]);

        $fields['slug'] = $slug;

        return $fields;
    }

    /**
     * @param  array<int, array<string, mixed>>  $achievements
     * @return array<string, mixed>|null
     */
    private function findBySlug(array $achievements, string $slug): ?array
    {
        foreach ($achievements as $achievement) {
            if (($achievement['slug'] ?? null) === $slug) {
                return $achievement;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $achievement
     * @return array<string, mixed>
     */
    private function fieldsFrom(string $slug, ?array $achievement): array
    {
        return [
            'slug' => $slug,
            'name' => $achievement['name'] ?? '',
            'description' => $achievement['description'] ?? '',
            'image' => $achievement['image'] ?? '',
            'category' => $achievement['category'] ?? '',
            'rarity' => $achievement['rarity'] ?? '',
            'scope' => $achievement['scope'] ?? '',
            'points' => $achievement['points'] ?? 0,
            'is_hidden' => $achievement['is_hidden'] ?? false,
            'sort_order' => $achievement['sort_order'] ?? 0,
            'unlock_when' => $achievement['unlock_when'] ?? null,
        ];
    }
}

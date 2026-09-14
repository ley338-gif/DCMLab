<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\Profile;
use App\Models\TrackBadge;
use App\Services\AchievementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Oeffentliches Profil unter dem zufaelligen Slug (Abschnitt 7): kein
 * Login noetig, keine E-Mail-Adresse oder sonstige Kontodaten sichtbar --
 * nur Rang, Punkte, Skill-Radar und Achievements.
 */
class PublicProfileController extends Controller
{
    public function show(string $slug, AchievementService $achievements): InertiaResponse
    {
        $profile = Profile::query()->where('public_slug', $slug)->with('user')->firstOrFail();

        return Inertia::render('Profiles/Show', [
            'profile' => $this->profileData($profile, $achievements),
            'slug' => $slug,
        ]);
    }

    public function exportPdf(string $slug, AchievementService $achievements): Response
    {
        $profile = Profile::query()->where('public_slug', $slug)->with('user')->firstOrFail();

        $pdf = Pdf::loadView('profiles.pdf', ['profile' => $this->profileData($profile, $achievements)]);

        return $pdf->download("dcm-lab-profil-{$slug}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function profileData(Profile $profile, AchievementService $achievementService): array
    {
        // "Pionier" (frueher "First Blood"): unveraendertes Altsystem aus
        // ADR 0009, bewusst getrennt von den neuen, generischen Achievements
        // unten, siehe docs/achievements.md.
        $pioneerAchievements = Achievement::query()
            ->where('user_id', $profile->user_id)
            ->where('type', 'first_blood')
            ->with('node')
            ->orderByDesc('awarded_at')
            ->get();

        $trackBadges = TrackBadge::query()
            ->where('user_id', $profile->user_id)
            ->with('track')
            ->orderByDesc('awarded_at')
            ->get();

        return [
            'name' => $profile->user->name,
            'rank' => $profile->rank,
            'points' => $profile->points,
            'skill_vector' => $profile->skill_vector,
            'member_since' => $profile->created_at?->toDateString(),
            'first_bloods' => $pioneerAchievements->map(fn (Achievement $achievement) => [
                'node_title' => $achievement->node?->title['de'] ?? $achievement->node?->slug,
                'awarded_at' => $achievement->awarded_at->toDateString(),
            ])->values(),
            'track_badges' => $trackBadges->map(fn (TrackBadge $badge) => [
                'track_title_key' => $badge->track?->title_key,
                'awarded_at' => $badge->awarded_at->toDateString(),
            ])->values(),
            'achievements' => $achievementService->listForUser($profile->user)->values(),
        ];
    }
}

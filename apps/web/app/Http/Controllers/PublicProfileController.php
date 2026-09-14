<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\AchievementService;
use App\Services\ProfileService;
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
    public function show(string $slug, ProfileService $profiles, AchievementService $achievements): InertiaResponse
    {
        $profile = Profile::query()->where('public_slug', $slug)->with('user')->firstOrFail();

        return Inertia::render('Profiles/Show', [
            'profile' => $this->profileData($profile, $profiles, $achievements),
            'slug' => $slug,
        ]);
    }

    public function exportPdf(string $slug, ProfileService $profiles, AchievementService $achievements): Response
    {
        $profile = Profile::query()->where('public_slug', $slug)->with('user')->firstOrFail();

        $pdf = Pdf::loadView('profiles.pdf', ['profile' => $this->profileData($profile, $profiles, $achievements)]);

        return $pdf->download("dcm-lab-profil-{$slug}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function profileData(Profile $profile, ProfileService $profileService, AchievementService $achievementService): array
    {
        // "Pionier" (frueher "First Blood"): unveraendertes Altsystem aus
        // ADR 0009/0066, ueber ProfileService::achievementsFor() (ADR 0070)
        // zusammengefasst und hier nach kind wieder auf die bestehenden Props
        // aufgeteilt -- bewusst getrennt von den neuen, generischen
        // Achievements unten, siehe docs/achievements.md.
        $pioneerAchievements = collect($profileService->achievementsFor($profile->user));

        return [
            'name' => $profile->user->name,
            'rank' => $profile->rank,
            'points' => $profile->points,
            'skill_vector' => $profile->skill_vector,
            'member_since' => $profile->created_at?->toDateString(),
            'first_bloods' => $pioneerAchievements->where('kind', 'first_blood')->map(fn (array $entry) => [
                'node_title' => $entry['node_title'],
                'awarded_at' => $entry['awarded_at']->toDateString(),
            ])->values(),
            'track_badges' => $pioneerAchievements->where('kind', 'track_passed')->map(fn (array $entry) => [
                'track_title_key' => $entry['track_title_key'],
                'awarded_at' => $entry['awarded_at']->toDateString(),
            ])->values(),
            'achievements' => $achievementService->listForUser($profile->user)->values(),
        ];
    }
}

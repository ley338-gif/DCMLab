<?php

namespace App\Http\Controllers;

use App\Models\Profile;
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
        return [
            'name' => $profile->user->name,
            'rank' => $profile->rank,
            'points' => $profile->points,
            'skill_vector' => $profile->skill_vector,
            'member_since' => $profile->created_at?->toDateString(),
            'achievements' => $achievementService->listForUser($profile->user)->values(),
        ];
    }
}

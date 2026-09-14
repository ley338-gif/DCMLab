<?php

namespace App\Http\Controllers;

use App\Models\Profile;
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
    public function show(string $slug, ProfileService $profiles): InertiaResponse
    {
        $profile = Profile::query()->where('public_slug', $slug)->with('user')->firstOrFail();

        return Inertia::render('Profiles/Show', [
            'profile' => $this->profileData($profile, $profiles),
            'slug' => $slug,
        ]);
    }

    public function exportPdf(string $slug, ProfileService $profiles): Response
    {
        $profile = Profile::query()->where('public_slug', $slug)->with('user')->firstOrFail();

        $pdf = Pdf::loadView('profiles.pdf', ['profile' => $this->profileData($profile, $profiles)]);

        return $pdf->download("dcm-lab-profil-{$slug}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function profileData(Profile $profile, ProfileService $profiles): array
    {
        $achievements = collect($profiles->achievementsFor($profile->user));

        return [
            'name' => $profile->user->name,
            'rank' => $profile->rank,
            'points' => $profile->points,
            'skill_vector' => $profile->skill_vector,
            'member_since' => $profile->created_at?->toDateString(),
            'first_bloods' => $achievements->where('kind', 'first_blood')->map(fn (array $entry) => [
                'node_title' => $entry['node_title'],
                'awarded_at' => $entry['awarded_at']->toDateString(),
            ])->values(),
            'track_badges' => $achievements->where('kind', 'track_passed')->map(fn (array $entry) => [
                'track_title_key' => $entry['track_title_key'],
                'awarded_at' => $entry['awarded_at']->toDateString(),
            ])->values(),
        ];
    }
}

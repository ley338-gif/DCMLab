<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\Profile;
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
    public function show(string $slug): InertiaResponse
    {
        $profile = Profile::query()->where('public_slug', $slug)->with('user')->firstOrFail();

        return Inertia::render('Profiles/Show', [
            'profile' => $this->profileData($profile),
            'slug' => $slug,
        ]);
    }

    public function exportPdf(string $slug): Response
    {
        $profile = Profile::query()->where('public_slug', $slug)->with('user')->firstOrFail();

        $pdf = Pdf::loadView('profiles.pdf', ['profile' => $this->profileData($profile)]);

        return $pdf->download("dcm-lab-profil-{$slug}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function profileData(Profile $profile): array
    {
        $achievements = Achievement::query()
            ->where('user_id', $profile->user_id)
            ->where('type', 'first_blood')
            ->with('node')
            ->orderByDesc('awarded_at')
            ->get();

        return [
            'name' => $profile->user->name,
            'rank' => $profile->rank,
            'points' => $profile->points,
            'skill_vector' => $profile->skill_vector,
            'member_since' => $profile->created_at?->toDateString(),
            'first_bloods' => $achievements->map(fn (Achievement $achievement) => [
                'node_title' => $achievement->node?->title['de'] ?? $achievement->node?->slug,
                'awarded_at' => $achievement->awarded_at->toDateString(),
            ])->values(),
        ];
    }
}

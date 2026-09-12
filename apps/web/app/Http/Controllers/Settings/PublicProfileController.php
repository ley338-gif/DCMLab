<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicProfileController extends Controller
{
    public function edit(Request $request, ProfileService $profiles): Response
    {
        $profile = $profiles->profileFor($request->user());

        return Inertia::render('settings/PublicProfile', [
            'publicSlug' => $profile->public_slug,
            'leaderboardOptIn' => $profile->leaderboard_opt_in,
        ]);
    }

    public function update(Request $request, ProfileService $profiles): RedirectResponse
    {
        $data = $request->validate(['leaderboard_opt_in' => 'required|boolean']);

        $profile = $profiles->profileFor($request->user());
        $profile->leaderboard_opt_in = $data['leaderboard_opt_in'];
        $profile->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings updated.')]);

        return to_route('public-profile.edit');
    }
}

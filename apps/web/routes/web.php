<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\SandboxController;
use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

// Abschnitt 8: Sprache steht von Anfang an in der URL, auch wenn aktuell nur
// "de" existiert -- Root leitet dorthin um, kein Sprachumschalter im UI.
Route::redirect('/', '/de');

Route::prefix('de')->group(function () {
    Route::get('/', [TrackController::class, 'index'])->name('home');
    Route::get('tracks/{track}', [TrackController::class, 'show'])->name('tracks.show');

    // Oeffentlich, kein Login noetig (Abschnitt 7: Profil per Slug erreichbar).
    Route::get('leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');
    Route::get('profiles/{slug}', [PublicProfileController::class, 'show'])->name('profiles.show');
    Route::get('profiles/{slug}/export', [PublicProfileController::class, 'exportPdf'])->name('profiles.export');

    Route::inertia('impressum', 'Legal/Impressum')->name('legal.impressum');
    Route::inertia('datenschutz', 'Legal/Datenschutz')->name('legal.datenschutz');
    Route::inertia('nutzungsbedingungen', 'Legal/Nutzungsbedingungen')->name('legal.nutzungsbedingungen');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
        Route::post('lessons/{lesson}/complete', [LessonController::class, 'complete'])->name('lessons.complete');
        Route::post('lessons/{lesson}/reopen', [LessonController::class, 'reopen'])->name('lessons.reopen');
        Route::post('lessons/{lesson}/sandbox', [SandboxController::class, 'create'])
            ->middleware('throttle:10,1')
            ->name('lessons.sandbox');

        Route::prefix('sandbox/{sandboxId}')->name('sandbox.')->middleware('throttle:30,1')->group(function () {
            Route::get('/', [SandboxController::class, 'state'])->name('state');
            Route::post('exec', [SandboxController::class, 'exec'])->name('exec');
            Route::delete('/', [SandboxController::class, 'destroy'])->name('destroy');
        });

        // Sandbox fuehrt vom Nutzer eingegebene Befehle in echten Containern aus
        // (Abschnitt 6) -- die Node-Engine simuliert nur, aber beide Gruppen
        // bekommen dasselbe Limit, damit kein Skript per Dauerfeuer die Engine
        // oder den Sandbox-Orchestrator flutet.
        Route::prefix('nodes/{node}')->name('nodes.')->middleware('throttle:60,1')->group(function () {
            Route::get('/', [NodeController::class, 'show'])->name('show');
            Route::get('state', [NodeController::class, 'state'])->name('state');
            Route::post('exec', [NodeController::class, 'exec'])->name('exec');
            Route::post('config', [NodeController::class, 'setConfig'])->name('config');
            Route::post('action', [NodeController::class, 'triggerAction'])->name('action');
            Route::post('hint', [NodeController::class, 'useHint'])->name('hint');
            Route::post('write-up', [NodeController::class, 'viewWriteUp'])->name('write-up');
            Route::post('flag', [NodeController::class, 'submitFlag'])->name('flag');
        });
    });

    require __DIR__.'/settings.php';
});

// Web-Standard-Pfad, darf keinen Locale-Praefix tragen.
Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');

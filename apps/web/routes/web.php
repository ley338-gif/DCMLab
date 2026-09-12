<?php

use App\Http\Controllers\LessonController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

// Abschnitt 8: Sprache steht von Anfang an in der URL, auch wenn aktuell nur
// "de" existiert -- Root leitet dorthin um, kein Sprachumschalter im UI.
Route::redirect('/', '/de');

Route::prefix('de')->group(function () {
    Route::get('/', [TrackController::class, 'index'])->name('home');
    Route::get('tracks/{track}', [TrackController::class, 'show'])->name('tracks.show');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        Route::get('lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
        Route::post('lessons/{lesson}/complete', [LessonController::class, 'complete'])->name('lessons.complete');
        Route::post('lessons/{lesson}/reopen', [LessonController::class, 'reopen'])->name('lessons.reopen');

        Route::prefix('nodes/{node}')->name('nodes.')->group(function () {
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

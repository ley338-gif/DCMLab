<?php

use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

// Abschnitt 8: Sprache steht von Anfang an in der URL, auch wenn aktuell nur
// "de" existiert -- Root leitet dorthin um, kein Sprachumschalter im UI.
Route::redirect('/', '/de');

Route::prefix('de')->group(function () {
    Route::get('/', [TrackController::class, 'index'])->name('home');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');
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

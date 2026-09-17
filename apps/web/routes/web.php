<?php

use App\Http\Controllers\AchievementEditorController;
use App\Http\Controllers\AuthorPanelController;
use App\Http\Controllers\AuthorUserController;
use App\Http\Controllers\ContentVersionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamEditorController;
use App\Http\Controllers\GlossaryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonEditorController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuizEditorController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewQueueController;
use App\Http\Controllers\SandboxController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\StudioLabController;
use App\Http\Controllers\StudioLessonController;
use App\Http\Controllers\StudioLessonElementsController;
use App\Http\Controllers\StudioNodeController;
use App\Http\Controllers\StudioSandboxTemplateController;
use App\Http\Controllers\StudioTrackController;
use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

// Abschnitt 8: Sprache steht von Anfang an in der URL, auch wenn aktuell nur
// "de" existiert -- Root leitet dorthin um, kein Sprachumschalter im UI.
Route::redirect('/', '/de');

Route::prefix('de')->group(function () {
    // Homebase-Umbau: "/" ist jetzt personalisiert (Gast: Landingpage,
    // eingeloggt: Redirect auf das Dashboard) -- der volle Katalog, den
    // frueher "home" gezeigt hat, lebt unveraendert unter tracks.index
    // weiter (siehe TrackController::index Klassendoc).
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('tracks', [TrackController::class, 'index'])->name('tracks.index');
    Route::get('tracks/{track}', [TrackController::class, 'show'])->name('tracks.show');

    // Oeffentlich, kein Login noetig (Abschnitt 7: Profil per Slug erreichbar).
    Route::get('leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');
    Route::get('glossar', [GlossaryController::class, 'index'])->name('glossary.index');
    Route::get('nodes', [NodeController::class, 'index'])->name('nodes.index');
    Route::get('profiles/{slug}', [PublicProfileController::class, 'show'])->name('profiles.show');
    Route::get('profiles/{slug}/export', [PublicProfileController::class, 'exportPdf'])->name('profiles.export');

    Route::inertia('impressum', 'Legal/Impressum')->name('legal.impressum');
    Route::inertia('datenschutz', 'Legal/Datenschutz')->name('legal.datenschutz');
    Route::inertia('nutzungsbedingungen', 'Legal/Nutzungsbedingungen')->name('legal.nutzungsbedingungen');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('review', [ReviewController::class, 'index'])->name('review.index');

        Route::get('lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
        Route::post('lessons/{lesson}/complete', [LessonController::class, 'complete'])->name('lessons.complete');
        Route::post('lessons/{lesson}/reopen', [LessonController::class, 'reopen'])->name('lessons.reopen');
        Route::post('lessons/{lesson}/sandbox', [SandboxController::class, 'create'])
            ->middleware('throttle:10,1')
            ->name('lessons.sandbox');

        Route::prefix('lessons/{lesson}/quiz')->name('quiz.')->middleware('throttle:60,1')->group(function () {
            Route::post('{questionId}/answer', [QuizController::class, 'answer'])->name('answer');
        });

        // Autoren-Panel (ADR 0092): Einstiegspunkt, Review-Queue und
        // Nutzerverwaltung fuer Author/Reviewer -- vorher gab es keine
        // Seite, von der aus die einzelnen Editoren unten ueberhaupt
        // auffindbar waren.
        Route::get('author', [AuthorPanelController::class, 'index'])->name('author.index');
        Route::get('author/review-queue', [ReviewQueueController::class, 'index'])->name('author.review-queue.index');
        Route::prefix('author/users')->name('author.users.')->group(function () {
            Route::get('/', [AuthorUserController::class, 'index'])->name('index');
            Route::patch('{user}', [AuthorUserController::class, 'updateRole'])->name('update');
            Route::post('{user}/activities', [AuthorUserController::class, 'assignActivity'])->name('activities.store');
            Route::delete('{user}/activities/{activity}', [AuthorUserController::class, 'removeActivity'])->name('activities.destroy');
        });

        // DCMLab Studio (ADR 0094 Abschnitt 12/0099, CMS-3b): additiv neben
        // dem Autoren-Panel oben, nicht dessen Ersatz -- siehe
        // StudioController Klassendoc.
        Route::get('studio', [StudioController::class, 'index'])->name('studio.index');
        Route::prefix('studio/sandbox-templates')->name('studio.sandbox-templates.')->group(function () {
            Route::get('/', [StudioSandboxTemplateController::class, 'index'])->name('index');
            Route::post('/', [StudioSandboxTemplateController::class, 'store'])->name('store');
            Route::patch('{sandboxTemplate}', [StudioSandboxTemplateController::class, 'update'])->name('update');
        });
        Route::prefix('studio/tracks')->name('studio.tracks.')->group(function () {
            Route::get('/', [StudioTrackController::class, 'index'])->name('index');
            Route::post('/', [StudioTrackController::class, 'store'])->name('store');
            Route::patch('{track}', [StudioTrackController::class, 'update'])->name('update');
            Route::post('{track}/publish', [StudioTrackController::class, 'publish'])->name('publish');
            Route::post('{track}/unpublish', [StudioTrackController::class, 'unpublish'])->name('unpublish');
            Route::post('{track}/archive', [StudioTrackController::class, 'archive'])->name('archive');
            Route::post('{track}/restore', [StudioTrackController::class, 'restore'])->name('restore');
            // Studio-Lessons-Umbau: eine Lesson gehoert genau einer Track
            // (lessons.track_id ist 1:n) -- "Zuordnen" ist deshalb immer ein
            // Verschieben, nie ein "Hinzufuegen" wie bei Lab/lesson_elements.
            Route::post('{track}/lessons', [StudioTrackController::class, 'moveLesson'])->name('move-lesson');
            Route::patch('{track}/lessons/reorder', [StudioTrackController::class, 'reorderLessons'])->name('reorder-lessons');
        });
        // Lesson-Ressource (Studio-Lessons-Umbau): index hier, der eigentliche
        // Editor bleibt LessonEditorController (siehe author.lessons.edit.*
        // weiter unten fuer den unveraenderten Kompatibilitaets-Pfad) --
        // dieselben vier Methoden, jetzt zusaetzlich unter studio.lessons.*
        // erreichbar und dort kanonisch (Studio-Breadcrumbs, keine zweite
        // Editor-Implementierung).
        Route::prefix('studio/lessons')->name('studio.lessons.')->group(function () {
            Route::get('/', [StudioLessonController::class, 'index'])->name('index');
            Route::get('{lesson}', [LessonEditorController::class, 'edit'])->name('edit');
            Route::post('{lesson}/validate', [LessonEditorController::class, 'validateDraft'])->name('validate');
            Route::post('{lesson}', [LessonEditorController::class, 'storeDraft'])->name('update');
            Route::get('{lesson}/preview', [LessonEditorController::class, 'preview'])->name('preview');
        });
        // Elementsequenz (ADR 0105/0106, CMS-6b/CMS-6c) -- umbenannt von
        // StudioLessonController zu StudioLessonElementsController
        // (Studio-Lessons-Umbau), damit der Name nicht mehr faelschlich den
        // Lesson-Editor selbst suggeriert.
        Route::get('studio/lessons/{lesson}/elements', [StudioLessonElementsController::class, 'show'])->name('studio.lessons.elements.show');
        Route::patch('studio/lessons/{lesson}/elements/reorder', [StudioLessonElementsController::class, 'reorder'])->name('studio.lessons.elements.reorder');
        // Lab an eine Lektion haengen (CMS-8e prep): erzeugt den
        // lesson_elements-Eintrag, den es fuer ein freiplatzierbares Lab
        // bislang an keiner Stelle geben konnte (siehe
        // StudioLessonElementsController Klassendoc-Ergaenzung).
        Route::post('studio/lessons/{lesson}/elements/labs', [StudioLessonElementsController::class, 'attachLab'])->name('studio.lessons.elements.attach-lab');
        // Node-Editor (ADR 0107/0108/0109, CMS-6d): store/duplicate/archive/
        // restore/updateThemenfeld sind strukturelle Eingriffe (NodePolicy),
        // update() speichert einen Content-Entwurf (ActivityPolicy) --
        // einreichen/freigeben laufen ueber den generischen
        // author.quiz-versions-Kreislauf weiter unten.
        Route::prefix('studio/nodes')->name('studio.nodes.')->group(function () {
            Route::get('/', [StudioNodeController::class, 'index'])->name('index');
            Route::post('/', [StudioNodeController::class, 'store'])->name('store');
            Route::get('{node}', [StudioNodeController::class, 'edit'])->name('edit');
            Route::post('{node}/validate', [StudioNodeController::class, 'validateDraft'])->name('validate');
            Route::patch('{node}', [StudioNodeController::class, 'update'])->name('update');
            Route::patch('{node}/themenfeld', [StudioNodeController::class, 'updateThemenfeld'])->name('update-themenfeld');
            Route::post('{node}/duplicate', [StudioNodeController::class, 'duplicate'])->name('duplicate');
            Route::post('{node}/archive', [StudioNodeController::class, 'archive'])->name('archive');
            Route::post('{node}/restore', [StudioNodeController::class, 'restore'])->name('restore');
            Route::get('{node}/preview', [StudioNodeController::class, 'preview'])->name('preview');
        });

        // Lab-Editor (CMS-8c, nach Node-Vorbild): store/archive/restore
        // sind strukturelle Eingriffe (LabPolicy), update() speichert einen
        // Content-Entwurf (ActivityPolicy) -- einreichen/freigeben laufen
        // ueber denselben generischen author.quiz-versions-Kreislauf weiter
        // unten. Kein Themenfeld-Aequivalent, kein duplicate()/preview().
        Route::prefix('studio/labs')->name('studio.labs.')->group(function () {
            Route::get('/', [StudioLabController::class, 'index'])->name('index');
            Route::post('/', [StudioLabController::class, 'store'])->name('store');
            Route::get('{lab}', [StudioLabController::class, 'edit'])->name('edit');
            Route::post('{lab}/validate', [StudioLabController::class, 'validateDraft'])->name('validate');
            Route::patch('{lab}', [StudioLabController::class, 'update'])->name('update');
            Route::post('{lab}/archive', [StudioLabController::class, 'archive'])->name('archive');
            Route::post('{lab}/restore', [StudioLabController::class, 'restore'])->name('restore');
        });

        // Autoren-Editoren (ADR 0071/0080/0081, W6) -- Policy-gepruefte
        // Berechtigung liegt in den Controller-Methoden, nicht in der
        // Route, weil sie gegen die Aktivitaet der Lektion prueft, nicht
        // gegen die Lektion selbst.
        Route::prefix('author/lessons/{lesson}/quiz')->name('author.lessons.quiz.')->group(function () {
            Route::get('/', [QuizEditorController::class, 'edit'])->name('edit');
            Route::post('validate', [QuizEditorController::class, 'validateDraft'])->name('validate');
            Route::post('/', [QuizEditorController::class, 'storeDraft'])->name('store');
        });
        Route::prefix('author/lessons/{lesson}/edit')->name('author.lessons.edit.')->group(function () {
            Route::get('/', [LessonEditorController::class, 'edit'])->name('edit');
            Route::post('validate', [LessonEditorController::class, 'validateDraft'])->name('validate');
            Route::post('/', [LessonEditorController::class, 'storeDraft'])->name('store');
            Route::get('preview', [LessonEditorController::class, 'preview'])->name('preview');
        });
        Route::prefix('author/exams/{track}/edit')->name('author.exams.edit.')->group(function () {
            Route::get('/', [ExamEditorController::class, 'edit'])->name('edit');
            Route::post('validate', [ExamEditorController::class, 'validateDraft'])->name('validate');
            Route::post('/', [ExamEditorController::class, 'storeDraft'])->name('store');
        });
        // Kein Model-Binding -- ein Achievement-Slug ist kein Eloquent-Model
        // (ADR 0083), sondern ein Eintrag in achievements.yml.
        Route::prefix('author/achievements/{slug}/edit')->name('author.achievements.edit.')->group(function () {
            Route::get('/', [AchievementEditorController::class, 'edit'])->name('edit');
            Route::post('validate', [AchievementEditorController::class, 'validateDraft'])->name('validate');
            Route::post('/', [AchievementEditorController::class, 'storeDraft'])->name('store');
            Route::post('image', [AchievementEditorController::class, 'uploadImage'])
                ->middleware('throttle:10,1')
                ->name('image');
        });
        // Generischer Freigabe-Kreislauf fuer jeden Editor -- Name aus
        // historischen Gruenden noch "quiz-versions" (ADR 0081), verarbeitet
        // aber jede Aktivitaet.
        Route::prefix('author/quiz-versions/{version}')->name('author.quiz-versions.')->group(function () {
            Route::post('submit', [ContentVersionController::class, 'submit'])->name('submit');
            Route::post('publish', [ContentVersionController::class, 'publish'])->name('publish');
            Route::post('restore', [ContentVersionController::class, 'restore'])->name('restore');
        });

        Route::prefix('tracks/{track}/exam')->name('tracks.exam.')->middleware('throttle:60,1')->group(function () {
            Route::post('start', [ExamController::class, 'start'])->name('start');
            Route::get('{attempt}', [ExamController::class, 'show'])->name('show');
            Route::post('{attempt}/answer', [ExamController::class, 'answer'])->name('answer');
            Route::get('{attempt}/result', [ExamController::class, 'result'])->name('result');
        });

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

        // Eigenstaendige Lab-Route (CMS-8a, Abschnitt H) -- innerhalb einer
        // Lesson zeigt lesson_elements nur eine Launch-/Status-Karte, das
        // eigentliche Lab-Erlebnis lebt hier. Ansehen und Beginnen sind
        // bewusst getrennt (Betreiber-Review vor #128, siehe
        // LabController-Klassendoc). Runtime/exec (CMS-8d) bekommen
        // dieselbe throttle:30,1-Gruppe wie sandbox/{sandboxId} (Abschnitt
        // C, gleiche Container-Attach-Kosten) -- KEINE Route nimmt eine
        // sandbox_id vom Client, jeder Endpunkt loest immer den eigenen
        // Attempt des Nutzers auf.
        Route::get('labs/{lab}', [LabController::class, 'show'])->name('labs.show');
        Route::post('labs/{lab}/start', [LabController::class, 'start'])->name('labs.start');
        Route::prefix('labs/{lab}')->name('labs.')->middleware('throttle:30,1')->group(function () {
            Route::get('runtime', [LabController::class, 'runtimeState'])->name('runtime.state');
            Route::post('exec', [LabController::class, 'exec'])->name('exec');
            Route::delete('runtime', [LabController::class, 'destroyRuntime'])->name('runtime.destroy');
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

<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Zentraler Autorisierungsvertrag fuer JEDEN Lesson-Endpunkt, der Inhalt
 * oder Fortschritt einer konkreten Lesson beruehrt (`LessonController`:
 * show/complete/reopen, `QuizController::answer()`, `SandboxController::
 * create()` -- analog `NodePolicy::view()` fuer Node). Eine veroeffentlichte
 * Lesson ist fuer jeden angemeldeten Nutzer offen; eine nicht
 * veroeffentlichte (draft/review/archiviert) nur fuer wer die zugehoerige
 * `type=lesson`-Activity bearbeiten darf (zugewiesener Autor oder Reviewer/
 * Administrator, `ActivityPolicy::update()`) -- dieselbe Regel wie die
 * Studio-Content-Vorschau (`LessonEditorController::preview()`), keine
 * zweite, eigens erfundene Berechtigung.
 */
class LessonPolicy
{
    public function view(User $user, Lesson $lesson): bool
    {
        if ($lesson->isPublished()) {
            return true;
        }

        $activity = $this->activityFor($lesson);

        // Published Content Boundary Hardening: `Gate::forUser($user)`, nicht
        // die ambiente `Gate::allows()`-Variante -- vorher wurde intern
        // immer der GLOBAL angemeldete Nutzer geprueft, nicht der explizit
        // uebergebene `$user`-Parameter. In jedem bisherigen Aufrufpfad
        // (Controller ruft `Gate::allows('view', $lesson)` fuer den eigenen
        // angemeldeten Nutzer auf) war das zufaellig deckungsgleich; sobald
        // `view()` aber fuer einen ANDEREN Nutzer als den aktuell
        // angemeldeten geprueft wird (z. B. LessonPrerequisiteService fuer
        // eine Voraussetzung), muss die Pruefung explizit an `$user` haengen.
        return $activity !== null && Gate::forUser($user)->allows('update', $activity);
    }

    private function activityFor(Lesson $lesson): ?Activity
    {
        return Activity::query()->where('type', 'lesson')->where('key', $lesson->lesson_id)->first();
    }
}

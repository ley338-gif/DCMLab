<?php

namespace App\Http\Controllers;

use App\Activities\ActivityProgressRecorder;
use App\Content\LearnerViewBuilder;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    /**
     * Zeigt eine Lektion: gerenderte Werkzeugleiste (Abschnitt 4.4), Prosa
     * mit aufgeloesten Glossar-Begriffen, Fortschritt fuer den Nutzer. Die
     * eigentliche Prop-Konstruktion lebt seit CMS-7d.3 Phase 6 (ADR 0118)
     * in `LearnerViewBuilder`.
     *
     * Autorisierung (analog Node, ADR 0110/0119, Haertung): zentral in
     * `LessonPolicy::view()`, ueber `Gate::allows('view', $lesson)` -- diese
     * Methode UND jeder andere Lesson-Endpunkt, der Inhalt/Fortschritt
     * beruehrt (complete/reopen unten, `QuizController::answer()`,
     * `SandboxController::create()`), rufen dieselbe Regel auf. `trackProgress`
     * haengt jetzt am Veroeffentlichungsstatus (vorher immer `true`): eine
     * autorisierte Draft-Vorschau (zugewiesener Autor oder Reviewer/
     * Administrator) darf die echte, interaktive Ansicht sehen, legt dabei
     * aber -- genau wie die bereits bestehende editorielle Vorschau
     * (`LessonEditorController::preview()`, `trackProgress: false`) -- keinen
     * echten `LessonProgress`-Datensatz an. `draft_preview` (aus
     * `lessonProps()`) zeigt dem Frontend serverseitig ermittelt, dass
     * Ergebnisse/Fortschritt hier nicht gewertet werden.
     */
    public function show(Lesson $lesson, LearnerViewBuilder $builder): Response
    {
        abort_unless(Gate::allows('view', $lesson), 404);

        return Inertia::render(
            'Lessons/Show',
            $builder->lessonProps($lesson, Auth::user(), trackProgress: $lesson->isPublished()),
        );
    }

    public function complete(Request $request, Lesson $lesson, ActivityProgressRecorder $progressRecorder): RedirectResponse
    {
        abort_unless(Gate::allows('view', $lesson), 404);

        $this->setStatus($request, $lesson, 'completed', $progressRecorder);

        return back();
    }

    public function reopen(Request $request, Lesson $lesson, ActivityProgressRecorder $progressRecorder): RedirectResponse
    {
        abort_unless(Gate::allows('view', $lesson), 404);

        $this->setStatus($request, $lesson, 'started', $progressRecorder);

        return back();
    }

    private function setStatus(Request $request, Lesson $lesson, string $status, ActivityProgressRecorder $progressRecorder): void
    {
        // Eine autorisierte Draft-Vorschau darf diese Buttons anklicken
        // (Lessons/Show.vue zeigt sie unveraendert), aber keinerlei
        // persistente Lernstatistik erzeugen -- kein LessonProgress, kein
        // ActivityProgress, keine Achievements. Der `Gate::allows('view', ...)`-
        // Aufruf in complete()/reopen() garantiert bereits, dass nur
        // autorisierte Nutzer hierher kommen; fuer eine veroeffentlichte
        // Lesson bleibt das Verhalten unveraendert.
        if (! $lesson->isPublished()) {
            return;
        }

        $progress = LessonProgress::firstOrNew(['user_id' => $request->user()->id, 'lesson_id' => $lesson->id]);
        $progress->status = $status;
        $progress->started_at ??= now();
        $progress->completed_at = $status === 'completed' ? now() : null;
        $progress->save();

        $progressRecorder->record('lesson', $lesson->lesson_id, $request->user());
    }
}

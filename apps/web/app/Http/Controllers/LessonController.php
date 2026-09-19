<?php

namespace App\Http\Controllers;

use App\Activities\ActivityProgressRecorder;
use App\Content\LearnerViewBuilder;
use App\Models\Activity;
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
     * in `LearnerViewBuilder`, damit die Draft-Vorschau
     * (`LessonEditorController::preview()`) denselben Weg nimmt --
     * `trackProgress: true` ist der einzige Unterschied zum Vorschau-Aufruf.
     */
    public function show(Lesson $lesson, LearnerViewBuilder $builder): Response
    {
        // Seit ADR 0119 (Lesson-Sichtbarkeit gehaertet, analog ADR 0110 fuer
        // Node): eine nicht veroeffentlichte Lektion (draft/review, sowie
        // archiviert) ist fuer normale Lernende gesperrt -- nur wer die
        // zugehoerige Activity bearbeiten darf (zugewiesener Autor oder
        // Reviewer/Administrator, ActivityPolicy) sieht sie trotzdem, das
        // ist die "Vorschau" aus dem Studio-Editor (LessonEditorController::
        // preview() ruft denselben Gate::authorize() bereits explizit auf),
        // keine zweite Route.
        if ($lesson->status !== 'published') {
            $activity = Activity::query()->where('type', 'lesson')->where('key', $lesson->lesson_id)->first();
            abort_unless($activity !== null && Gate::allows('update', $activity), 404);
        }

        return Inertia::render('Lessons/Show', $builder->lessonProps($lesson, Auth::user(), trackProgress: true));
    }

    public function complete(Request $request, Lesson $lesson, ActivityProgressRecorder $progressRecorder): RedirectResponse
    {
        $this->setStatus($request, $lesson, 'completed', $progressRecorder);

        return back();
    }

    public function reopen(Request $request, Lesson $lesson, ActivityProgressRecorder $progressRecorder): RedirectResponse
    {
        $this->setStatus($request, $lesson, 'started', $progressRecorder);

        return back();
    }

    private function setStatus(Request $request, Lesson $lesson, string $status, ActivityProgressRecorder $progressRecorder): void
    {
        $progress = LessonProgress::firstOrNew(['user_id' => $request->user()->id, 'lesson_id' => $lesson->id]);
        $progress->status = $status;
        $progress->started_at ??= now();
        $progress->completed_at = $status === 'completed' ? now() : null;
        $progress->save();

        $progressRecorder->record('lesson', $lesson->lesson_id, $request->user());
    }
}

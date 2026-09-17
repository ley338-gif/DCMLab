<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\Track;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lesson-Ressourcenliste in Studio (Studio-Lessons-Umbau) -- nach dem
 * Vorbild von StudioNodeController/StudioLabController::index(), aber
 * bewusst NUR die Liste: der eigentliche Editor bleibt
 * LessonEditorController (wiederverwendet, keine zweite Implementierung --
 * siehe routes/web.php `studio.lessons.*`), und Lesson hat -- anders als
 * Node/Lab -- keine eigene strukturelle Lifecycle (kein store()/archive()/
 * restore() hier): eine Lesson-Zeile entsteht weiterhin ausschliesslich
 * ueber `content:sync`. Studio-seitige Lesson-Erstellung ist bekannte,
 * bewusst zurueckgestellte Folgearbeit (siehe PR-Beschreibung), kein
 * Versehen.
 */
class StudioLessonController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('studio.access');

        $lessons = Lesson::query()
            ->with('track')
            ->orderBy('track_id')
            ->orderBy('order')
            ->get();

        $activitiesByKey = Activity::query()
            ->where('type', 'lesson')
            ->whereIn('key', $lessons->pluck('lesson_id'))
            ->with(['contentVersions' => function ($query) {
                $query->whereIn('status', ['draft', 'review'])->latest();
            }])
            ->get()
            ->keyBy('key');

        $rows = $lessons->map(function (Lesson $lesson) use ($activitiesByKey) {
            $activity = $activitiesByKey->get($lesson->lesson_id);
            $pendingVersion = $activity?->contentVersions->first();

            return [
                'lesson_id' => $lesson->lesson_id,
                'title' => $lesson->title['de'] ?? $lesson->lesson_id,
                'track' => $lesson->track !== null ? [
                    'slug' => $lesson->track->slug,
                    'title' => $lesson->track->title['de'] ?? $lesson->track->title_key,
                ] : null,
                'level' => $lesson->level,
                'status' => $lesson->status,
                'pending_version_status' => $pendingVersion instanceof ContentVersion ? $pendingVersion->status : null,
            ];
        })->values();

        $tracks = Track::query()
            ->orderBy('order')
            ->get()
            ->map(fn (Track $track) => [
                'slug' => $track->slug,
                'title' => $track->title['de'] ?? $track->title_key,
            ])
            ->values();

        return Inertia::render('Studio/Lessons/Index', [
            'lessons' => $rows,
            'tracks' => $tracks,
        ]);
    }
}

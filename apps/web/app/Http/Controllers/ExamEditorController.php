<?php

namespace App\Http\Controllers;

use App\Activities\ActivityRegistry;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Content\ExamContent;
use App\Models\Activity;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dritter Autoren-Editor (ADR 0071/0082, W6.3): die Einstellungen einer
 * Track-Abschlusspruefung (Titel, Einleitung, Bestehensgrenze, Fragenzahl je
 * Versuch, Dauer, Mischen, Mindestfragen je Lektion). Der Fragenpool selbst
 * (`questions:` in exam.yml, die `### fNN — ...`-Abschnitte in de.md) bleibt
 * bewusst Kommandozeilen-Sache -- die statistischen Anforderungen an den
 * Pool (Typmischung, Schwierigkeitsanteil, Mindestfragen je Lektion, siehe
 * ContentValidator::checkExamStructure()) sind Eigenschaften des GESAMTEN
 * Pools, nicht einer einzelnen Frage, und brauchen einen eigenen,
 * bulk-faehigen Editor (ADR 0082, offene-fragen.md). Was dieser Editor
 * stattdessen liefert: eine live berechnete Pool-Uebersicht (Abdeckung je
 * Lektion, Cross-Fragen, Typmischung, Schwierigkeitsanteil) als
 * Fortschrittsanzeige statt einer Fehlermeldung erst nach dem Speichern
 * (`dcm-lab-lms-agent-prompt.md` Abschnitt 5, W6.3).
 */
class ExamEditorController extends Controller
{
    public function edit(Track $track, ContentRepository $content): Response
    {
        $activity = $this->activityFor($track);
        Gate::authorize('update', $activity);

        $pendingVersion = $activity->contentVersions()
            ->whereIn('status', ['draft', 'review'])
            ->latest()
            ->first();

        return Inertia::render('Author/ExamEditor', [
            'track' => [
                'slug' => $track->slug,
                'title_key' => $track->title_key,
            ],
            'fields' => $pendingVersion !== null ? $pendingVersion->payload : $this->currentFields($track, $content),
            'coverage' => $this->coverage($track, $content),
            'pending_version' => $pendingVersion === null ? null : [
                'id' => $pendingVersion->id,
                'status' => $pendingVersion->status,
            ],
            'can_publish' => Gate::allows('publish', $activity),
        ]);
    }

    public function validateDraft(Request $request, Track $track): JsonResponse
    {
        $activity = $this->activityFor($track);
        Gate::authorize('update', $activity);

        $issues = app(ActivityRegistry::class)->resolve($activity)->validate($this->validatedFields($request));

        return response()->json([
            'issues' => array_map(fn ($issue) => (string) $issue, $issues),
        ]);
    }

    public function storeDraft(Request $request, Track $track, ContentVersioningService $versions): RedirectResponse
    {
        $activity = $this->activityFor($track);
        Gate::authorize('update', $activity);

        $versions->createDraft($activity, $this->validatedFields($request), $request->user());

        return back()->with('status', 'Entwurf gespeichert.');
    }

    private function activityFor(Track $track): Activity
    {
        return Activity::query()->where('type', 'exam')->where('key', $track->slug)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFields(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string',
            'intro' => 'required|string',
            'pass_percent' => 'required|integer|min:50|max:100',
            'draw' => 'required|integer|min:1',
            'duration_minutes' => 'required|integer|min:1',
            'shuffle' => 'boolean',
            'min_per_lesson' => 'nullable|integer|min:0',
        ]);
    }

    /**
     * Ist-Zustand direkt aus `content/` (wie LessonEditorController), nicht
     * aus DB-Modellen.
     *
     * @return array<string, mixed>
     */
    private function currentFields(Track $track, ContentRepository $content): array
    {
        $entry = $content->exams()[$track->slug] ?? null;

        if ($entry === null) {
            return [
                'title' => '', 'intro' => '', 'pass_percent' => 80, 'draw' => 1,
                'duration_minutes' => 10, 'shuffle' => false, 'min_per_lesson' => 4,
            ];
        }

        $meta = $entry['meta'] ?? [];
        $frontmatter = $entry['frontmatter'] ?? [];

        return [
            'title' => $frontmatter['title'] ?? '',
            'intro' => $frontmatter['intro'] ?? '',
            'pass_percent' => $meta['pass_percent'] ?? 80,
            'draw' => $meta['draw'] ?? 1,
            'duration_minutes' => $meta['duration_minutes'] ?? 10,
            'shuffle' => $meta['shuffle'] ?? false,
            'min_per_lesson' => $meta['min_per_lesson'] ?? 4,
        ];
    }

    /**
     * Live-Uebersicht des bestehenden Fragenpools (nicht des Entwurfs -- die
     * Poolpflege bleibt Kommandozeile, siehe Klassendoc): Abdeckung je
     * Lektion gegen `min_per_lesson`, Cross-Fragen gegen die feste
     * Mindestzahl 4, Typmischung und Schwierigkeitsanteil gegen dieselben
     * Bandbreiten wie `ContentValidator::checkExamStructure()` -- eine
     * Anzeige zur Orientierung, keine zweite Quelle der Wahrheit; die
     * tatsaechliche Pruefung bleibt bei `ContentValidator`.
     *
     * @return array<string, mixed>
     */
    private function coverage(Track $track, ContentRepository $content): array
    {
        $entry = $content->exams()[$track->slug] ?? null;
        $lessons = $content->lessons();

        if ($entry === null) {
            return ['pool_size' => 0, 'lessons' => [], 'cross_count' => 0, 'type_shares' => [], 'difficulty3_share' => 0.0];
        }

        /** @var list<array<string, mixed>> $questions */
        $questions = $entry['meta']['questions'] ?? [];
        $minPerLesson = $entry['meta']['min_per_lesson'] ?? 4;

        $trackLessonIds = array_keys(array_filter(
            $lessons,
            fn (array $lesson) => ($lesson['meta']['track'] ?? null) === $track->slug,
        ));

        $countsByLesson = [];
        $typeCounts = ['single' => 0, 'multi' => 0, 'truefalse' => 0, 'input' => 0];
        $difficulty3Count = 0;

        foreach ($questions as $question) {
            $lessonId = (string) ($question['lesson'] ?? '');
            $countsByLesson[$lessonId] = ($countsByLesson[$lessonId] ?? 0) + 1;

            $type = ExamContent::typeFor($question, $lessons);
            if (array_key_exists($type, $typeCounts)) {
                $typeCounts[$type]++;
            }

            if (($question['difficulty'] ?? null) === 3) {
                $difficulty3Count++;
            }
        }

        $poolSize = count($questions);

        return [
            'pool_size' => $poolSize,
            'min_per_lesson' => $minPerLesson,
            'lessons' => array_map(fn (string $lessonId) => [
                'lesson_id' => $lessonId,
                'count' => $countsByLesson[$lessonId] ?? 0,
                'ok' => ($countsByLesson[$lessonId] ?? 0) >= $minPerLesson,
            ], $trackLessonIds),
            'cross_count' => $countsByLesson['cross'] ?? 0,
            'cross_ok' => ($countsByLesson['cross'] ?? 0) >= 4,
            'type_shares' => $poolSize === 0 ? [] : [
                ['type' => 'single', 'percent' => round($typeCounts['single'] / $poolSize * 100, 1), 'min' => 35, 'max' => 40],
                ['type' => 'multi', 'percent' => round($typeCounts['multi'] / $poolSize * 100, 1), 'min' => 20, 'max' => 25],
                ['type' => 'truefalse', 'percent' => round($typeCounts['truefalse'] / $poolSize * 100, 1), 'min' => 20, 'max' => 25],
                ['type' => 'input', 'percent' => round($typeCounts['input'] / $poolSize * 100, 1), 'min' => 10, 'max' => 15],
            ],
            'difficulty3_share' => $poolSize === 0 ? 0.0 : round($difficulty3Count / $poolSize * 100, 1),
        ];
    }
}

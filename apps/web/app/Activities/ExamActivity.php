<?php

namespace App\Activities;

use App\Content\ContentIssue;
use App\Content\ContentRepository;
use App\Content\ContentValidator;
use App\Models\ExamAttempt;
use App\Models\Track;
use App\Models\User;

/**
 * Aktivitaetsvertrag fuer eine Track-Abschlusspruefung (ADR 0072). Eine
 * Pruefung ist heute genau eine pro Track (`exams/<track>/`) -- ob das
 * so bleibt, ist eine offene Frage vor W6.3 (siehe docs/offene-fragen.md).
 * Der laufende Versuchszustand (gezogene Fragen, Antworten) bleibt in
 * `exam_attempts`; hier zaehlt nur der letzte abgeschlossene Versuch.
 */
final readonly class ExamActivity implements ActivityContract
{
    public function __construct(
        private Track $track,
        private ContentRepository $content,
    ) {}

    public function activityType(): string
    {
        return ActivityType::Exam->value;
    }

    public function key(): string
    {
        return $this->track->slug;
    }

    public function supports(): ActivitySupports
    {
        return new ActivitySupports(
            isGraded: true,
            tracksCompletion: true,
            needsContainer: false,
            authorable: true,
            freelyPlaceable: false,
        );
    }

    public function learnerView(User $user): array
    {
        $latest = $this->latestAttemptFor($user);

        return [
            'type' => $this->activityType(),
            'track' => $this->track->slug,
            'title_key' => $this->track->title_key,
            'status' => $latest === null ? 'not_started' : $latest->status,
            'passed' => $latest !== null && $latest->passed === true,
        ];
    }

    public function authorView(): array
    {
        return [
            ['key' => 'title', 'type' => 'text', 'label' => 'Titel', 'required' => true],
            ['key' => 'intro', 'type' => 'text', 'label' => 'Einleitung', 'required' => true],
            ['key' => 'pass_percent', 'type' => 'number', 'label' => 'Bestehensgrenze (%)', 'required' => true],
            ['key' => 'draw', 'type' => 'number', 'label' => 'Fragenzahl je Versuch', 'required' => true],
            ['key' => 'questions', 'type' => 'json', 'label' => 'Fragenpool', 'required' => true],
        ];
    }

    public function validate(?array $draft = null): array
    {
        $prefix = "exams/{$this->track->slug}/";

        return array_values(array_filter(
            (new ContentValidator)->validate(
                themenfelder: $this->content->themenfelder(),
                tracks: $this->content->tracks(),
                achievements: $this->content->achievements(),
                lessons: $this->content->lessons(),
                nodes: $this->content->nodes(),
                exams: $this->content->exams(),
                tools: $this->content->tools(),
                toolsRaw: $this->content->toolsRaw(),
                glossary: $this->content->glossary(),
                datasets: $this->content->datasets(),
                skills: $this->content->skills(),
            ),
            fn (ContentIssue $issue): bool => str_starts_with($issue->file, $prefix),
        ));
    }

    public function serialize(?array $draft = null): array
    {
        $entry = $this->contentEntry();

        if ($entry === null) {
            return [];
        }

        return [
            ['path' => "exams/{$this->track->slug}/exam.yml", 'contents' => $entry['meta_raw']],
            ['path' => "exams/{$this->track->slug}/de.md", 'contents' => $entry['md_raw']],
        ];
    }

    public function deserialize(): array
    {
        $entry = $this->contentEntry();

        return [
            'track' => $this->track->slug,
            'title_key' => $this->track->title_key,
            'pass_percent' => $entry['meta']['pass_percent'] ?? null,
            'draw' => $entry['meta']['draw'] ?? null,
            'duration_minutes' => $entry['meta']['duration_minutes'] ?? null,
            'shuffle' => $entry['meta']['shuffle'] ?? false,
            'questions' => $entry['meta']['questions'] ?? [],
            'body' => $entry['body'] ?? null,
        ];
    }

    public function result(User $user): ?ActivityResult
    {
        $latest = $this->latestAttemptFor($user);

        if ($latest === null || $latest->status !== 'completed') {
            return null;
        }

        return new ActivityResult(
            completed: $latest->passed === true,
            score: $latest->score_correct,
            maxScore: $latest->score_total,
            // Die additive, pro Frage auf zwei Tags gedeckelte
            // Skill-Zuordnung (ProfileService::recomputeAfterExamAttempt)
            // gehoert in die Berechnung von activity_progress, nicht in
            // diese Leseansicht -- bleibt fuer W0 bewusst leer.
            skills: [],
            completedAt: $latest->completed_at,
        );
    }

    private function latestAttemptFor(User $user): ?ExamAttempt
    {
        return ExamAttempt::query()
            ->where('user_id', $user->id)
            ->where('track_id', $this->track->id)
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contentEntry(): ?array
    {
        return $this->content->exams()[$this->track->slug] ?? null;
    }
}

<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\Profile;
use App\Models\Track;
use App\Models\TrackBadge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Voller Ablauf einer Track-Abschlusspruefung (P10.60): starten, alle
 * gezogenen Fragen beantworten (Server ist die Wahrheit ueber die
 * Reihenfolge, nicht der Test), abschliessen, Bestehen/Punkte/Badge.
 */
class ExamControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    /** @var array<string, mixed> */
    private array $correctAnswers = [
        'f01' => 0,
        'f02' => 1,
        'f03' => [0, 1],
        'f04' => true,
        'f05' => 0,
        'f06' => [0, 1],
        'f07' => false,
        'f08' => 'test',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->contentDir = storage_path('framework/testing/exam-content-'.Str::random(12));
        $this->buildContentFixture();
        $this->app->instance(ContentRepository::class, new ContentRepository($this->contentDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_starting_creates_an_in_progress_attempt_with_the_full_pool(): void
    {
        [$user, $track] = $this->userAndTrack();

        $this->actingAs($user)->post("/de/tracks/{$track->slug}/exam/start")->assertRedirect();

        $attempt = ExamAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('in_progress', $attempt->status);
        $this->assertCount(8, $attempt->question_ids);
        $this->assertSame(0, $attempt->current_index);
    }

    public function test_starting_twice_resumes_the_same_attempt(): void
    {
        [$user, $track] = $this->userAndTrack();

        $this->actingAs($user)->post("/de/tracks/{$track->slug}/exam/start");
        $first = ExamAttempt::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->post("/de/tracks/{$track->slug}/exam/start");
        $this->assertSame(1, ExamAttempt::where('user_id', $user->id)->count());
        $this->assertSame($first->id, ExamAttempt::where('user_id', $user->id)->firstOrFail()->id);
    }

    public function test_answering_every_question_advances_current_index_and_survives_a_reload(): void
    {
        [$user, $track] = $this->userAndTrack();
        $attempt = $this->startAttempt($user, $track);

        $currentId = $attempt->fresh()->currentQuestionId();
        $this->actingAs($user)
            ->postJson("/de/tracks/{$track->slug}/exam/{$attempt->id}/answer", ['value' => $this->correctAnswers[$currentId]])
            ->assertOk()
            ->assertJson(['correct' => true]);

        // "Reload" heisst: der Server, nicht der Client, weiss noch, wo der
        // Versuch steht.
        $this->assertSame(1, $attempt->fresh()->current_index);
        $this->actingAs($user)
            ->get("/de/tracks/{$track->slug}/exam/{$attempt->id}")
            ->assertOk();
    }

    public function test_passing_all_questions_marks_the_attempt_passed_and_awards_badge_and_points(): void
    {
        [$user, $track] = $this->userAndTrack();
        $attempt = $this->startAttempt($user, $track);

        foreach (range(1, 8) as $_) {
            $current = $attempt->fresh();
            $currentId = $current->currentQuestionId();
            $this->actingAs($user)
                ->postJson("/de/tracks/{$track->slug}/exam/{$attempt->id}/answer", ['value' => $this->correctAnswers[$currentId]])
                ->assertOk()
                ->assertJson(['correct' => true]);
        }

        $this->actingAs($user)
            ->get("/de/tracks/{$track->slug}/exam/{$attempt->id}")
            ->assertRedirect("/de/tracks/{$track->slug}/exam/{$attempt->id}/result");

        $attempt = $attempt->fresh();
        $this->assertSame('completed', $attempt->status);
        $this->assertTrue($attempt->passed);
        $this->assertSame(8, $attempt->score_correct);

        $this->assertDatabaseHas('track_badges', ['user_id' => $user->id, 'track_id' => $track->id]);

        $profile = Profile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(50, $profile->points);

        $this->assertTrue($attempt->badge_awarded);

        $this->actingAs($user)
            ->get("/de/tracks/{$track->slug}/exam/{$attempt->id}/result")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('attempt.badge_awarded', true)
                ->where('attempt.points_awarded', 50)
            );
    }

    /**
     * P10.65: ein zweiter, ebenfalls bestandener Versuch auf demselben
     * Track darf kein zweites Badge, keine weiteren Punkte und keine
     * Belohnungsanzeige mehr erzeugen -- die Vergabe ist einmalig.
     */
    public function test_a_second_passing_attempt_on_an_already_passed_track_awards_nothing_again(): void
    {
        [$user, $track] = $this->userAndTrack();

        $this->passAttempt($user, $track);
        $profileAfterFirst = Profile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(50, $profileAfterFirst->points);

        $secondAttempt = $this->passAttempt($user, $track);

        $this->assertSame(1, TrackBadge::where('user_id', $user->id)->where('track_id', $track->id)->count());
        $this->assertFalse($secondAttempt->badge_awarded);

        $profileAfterSecond = Profile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(50, $profileAfterSecond->points);

        $this->actingAs($user)
            ->get("/de/tracks/{$track->slug}/exam/{$secondAttempt->id}/result")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('attempt.badge_awarded', false)
                ->where('attempt.points_awarded', null)
            );
    }

    public function test_failing_the_attempt_awards_no_badge(): void
    {
        [$user, $track] = $this->userAndTrack();
        $attempt = $this->startAttempt($user, $track);

        // pass_percent ist 50 -- zwei von acht (25 %) reichen nicht.
        $wrongValue = fn (string $id) => match (true) {
            is_bool($this->correctAnswers[$id]) => ! $this->correctAnswers[$id],
            is_array($this->correctAnswers[$id]) => [99],
            is_int($this->correctAnswers[$id]) => 99,
            default => 'ganz falsch',
        };

        foreach (range(1, 8) as $i) {
            $current = $attempt->fresh();
            $currentId = $current->currentQuestionId();
            $value = $i <= 2 ? $this->correctAnswers[$currentId] : $wrongValue($currentId);

            $this->actingAs($user)
                ->postJson("/de/tracks/{$track->slug}/exam/{$attempt->id}/answer", ['value' => $value]);
        }

        $this->actingAs($user)->get("/de/tracks/{$track->slug}/exam/{$attempt->id}");

        $attempt = $attempt->fresh();
        $this->assertSame('completed', $attempt->status);
        $this->assertFalse($attempt->passed);
        $this->assertDatabaseMissing('track_badges', ['user_id' => $user->id, 'track_id' => $track->id]);
    }

    public function test_another_users_attempt_cannot_be_accessed(): void
    {
        [$owner, $track] = $this->userAndTrack();
        $attempt = $this->startAttempt($owner, $track);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get("/de/tracks/{$track->slug}/exam/{$attempt->id}")
            ->assertForbidden();
    }

    private function startAttempt(User $user, Track $track): ExamAttempt
    {
        $this->actingAs($user)->post("/de/tracks/{$track->slug}/exam/start");

        return ExamAttempt::where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->firstOrFail();
    }

    private function passAttempt(User $user, Track $track): ExamAttempt
    {
        $attempt = $this->startAttempt($user, $track);

        foreach (range(1, 8) as $_) {
            $currentId = $attempt->fresh()->currentQuestionId();
            $this->actingAs($user)
                ->postJson("/de/tracks/{$track->slug}/exam/{$attempt->id}/answer", ['value' => $this->correctAnswers[$currentId]]);
        }

        $this->actingAs($user)->get("/de/tracks/{$track->slug}/exam/{$attempt->id}");

        return $attempt->fresh();
    }

    /**
     * @return array{0: User, 1: Track}
     */
    private function userAndTrack(): array
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        $user = User::factory()->create();

        return [$user, $track];
    }

    private function buildContentFixture(): void
    {
        $lessonMeta = <<<'YAML'
        id: "1.0"
        track: fundamente
        order: 0
        duration_minutes: 5
        level: einsteiger
        objectives_count: 0
        requires: []
        tools: []
        glossary_terms: []
        tools_checked: "2026-09-17"
        status: draft
        updated: "2026-09-17"
        YAML;

        $lessonMarkdown = <<<'MD'
        ---
        title: Test
        teaser: Test
        objectives: []
        ---

        ## Intro

        Testinhalt.
        MD;

        $examMeta = <<<'YAML'
        track: fundamente
        title_key: exam.fundamente.title
        pass_percent: 50
        draw: 8
        duration_minutes: 10
        shuffle: true

        questions:
          - id: f01
            type: single
            answer: 0
            lesson: "1.0"
            review: { lesson: "1.0", anchor: "intro" }
            difficulty: 1
            tags: [netzwerk]
          - id: f02
            type: single
            answer: 1
            lesson: "1.0"
            review: { lesson: "1.0", anchor: "intro" }
            difficulty: 1
            tags: [netzwerk]
          - id: f03
            type: multi
            answer: [0, 1]
            lesson: "1.0"
            review: { lesson: "1.0", anchor: "intro" }
            difficulty: 2
            tags: [datenmodell]
          - id: f04
            type: truefalse
            answer: true
            lesson: "1.0"
            review: { lesson: "1.0", anchor: "intro" }
            difficulty: 1
            tags: [netzwerk]
          - id: f05
            type: single
            answer: 0
            lesson: "cross"
            review:
              - { lesson: "1.0", anchor: "intro" }
            difficulty: 3
            tags: [netzwerk]
          - id: f06
            type: multi
            answer: [0, 1]
            lesson: "cross"
            review:
              - { lesson: "1.0", anchor: "intro" }
            difficulty: 3
            tags: [datenmodell]
          - id: f07
            type: truefalse
            answer: false
            lesson: "cross"
            review:
              - { lesson: "1.0", anchor: "intro" }
            difficulty: 2
            tags: [netzwerk]
          - id: f08
            type: input
            answer: "test"
            lesson: "cross"
            review:
              - { lesson: "1.0", anchor: "intro" }
            difficulty: 2
            tags: [datenmodell]
        YAML;

        $examMarkdown = <<<'MD'
        ---
        title: Testpruefung
        intro: Test.
        ---

        ### f01 — Testfrage eins?

        1. Richtig
        2. Falsch

        **Erklärung:** Test.

        ### f02 — Testfrage zwei?

        1. Falsch
        2. Richtig

        **Erklärung:** Test.

        ### f03 — Testfrage drei? *(Mehrfachauswahl)*

        1. Richtig eins
        2. Richtig zwei
        3. Falsch

        **Erklärung:** Test.

        ### f04 — Testaussage vier.

        **Richtig / Falsch**

        **Erklärung:** Test.

        ### f05 — Testfrage fuenf (uebergreifend)?

        1. Richtig
        2. Falsch

        **Erklärung:** Test.

        ### f06 — Testfrage sechs (uebergreifend)? *(Mehrfachauswahl)*

        1. Richtig eins
        2. Richtig zwei
        3. Falsch

        **Erklärung:** Test.

        ### f07 — Testaussage sieben (uebergreifend).

        **Richtig / Falsch**

        **Erklärung:** Test.

        ### f08 — Testfrage acht (uebergreifend)? *(Freitext)*

        **Erklärung:** Test.
        MD;

        File::ensureDirectoryExists($this->contentDir.'/lessons/1.0');
        File::ensureDirectoryExists($this->contentDir.'/exams/fundamente');
        File::ensureDirectoryExists($this->contentDir.'/glossary');
        File::put($this->contentDir.'/lessons/1.0/meta.yml', $lessonMeta);
        File::put($this->contentDir.'/lessons/1.0/de.md', $lessonMarkdown);
        File::put($this->contentDir.'/exams/fundamente/exam.yml', $examMeta);
        File::put($this->contentDir.'/exams/fundamente/de.md', $examMarkdown);
        File::put($this->contentDir.'/glossary/de.yml', '');
    }
}

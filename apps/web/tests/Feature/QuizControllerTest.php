<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\QuizReview;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Serverseitige Quiz-Bewertung (Abschnitt 4.7): die richtige Antwort steht
 * nur in meta.yml, nie im Client-Payload -- hier wird genau das geprueft,
 * nicht die Engine (die kennt kein Quiz).
 */
class QuizControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->contentDir = storage_path('framework/testing/quiz-content-'.Str::random(12));
        $this->buildContentFixture();
        $this->app->instance(ContentRepository::class, new ContentRepository($this->contentDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_correct_single_choice_answer_is_graded_correct(): void
    {
        [$user, $lesson] = $this->userAndLesson();

        $response = $this->actingAs($user)
            ->postJson("/de/lessons/{$lesson->lesson_id}/quiz/q1/answer", ['value' => 1]);

        $response->assertOk();
        $response->assertJson(['correct' => true]);
        $this->assertDatabaseHas('quiz_reviews', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'question_id' => 'q1',
            'last_result' => 'correct',
            'repetitions' => 1,
        ]);
    }

    public function test_incorrect_single_choice_answer_is_graded_wrong(): void
    {
        [$user, $lesson] = $this->userAndLesson();

        $response = $this->actingAs($user)
            ->postJson("/de/lessons/{$lesson->lesson_id}/quiz/q1/answer", ['value' => 0]);

        $response->assertOk();
        $response->assertJson(['correct' => false]);
    }

    public function test_multi_choice_answer_is_order_independent(): void
    {
        [$user, $lesson] = $this->userAndLesson();

        $response = $this->actingAs($user)
            ->postJson("/de/lessons/{$lesson->lesson_id}/quiz/q2/answer", ['value' => [2, 0]]);

        $response->assertOk();
        $response->assertJson(['correct' => true]);
    }

    public function test_input_answer_is_graded_case_insensitively(): void
    {
        [$user, $lesson] = $this->userAndLesson();

        $response = $this->actingAs($user)
            ->postJson("/de/lessons/{$lesson->lesson_id}/quiz/q3/answer", ['value' => 'DCMDUMP DATEI.DCM']);

        $response->assertOk();
        $response->assertJson(['correct' => true]);
    }

    public function test_unknown_question_id_returns_404(): void
    {
        [$user, $lesson] = $this->userAndLesson();

        $this->actingAs($user)
            ->postJson("/de/lessons/{$lesson->lesson_id}/quiz/q99/answer", ['value' => 0])
            ->assertNotFound();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        [, $lesson] = $this->userAndLesson();

        $this->postJson("/de/lessons/{$lesson->lesson_id}/quiz/q1/answer", ['value' => 1])
            ->assertUnauthorized();
    }

    /**
     * ADR 0110/0119-Haertung, uebertragen auf Quiz: vor diesem PR pruefte
     * dieser Endpunkt den Veroeffentlichungsstatus der Lesson ueberhaupt
     * nicht -- ein normaler Lernender mit bekannter Draft-Lesson-Id konnte
     * hier direkt eine echte Quiz-Antwort einreichen, ganz ohne vorherigen
     * autorisierten Besuch von LessonController::show().
     */
    public function test_a_learner_cannot_answer_a_quiz_question_for_a_lesson_that_is_not_yet_published(): void
    {
        $lesson = $this->draftLessonWithActivity();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/de/lessons/{$lesson->lesson_id}/quiz/q1/answer", ['value' => 1])
            ->assertNotFound();

        $this->assertSame(0, QuizReview::query()->count());
    }

    /**
     * Eine autorisierte Draft-Vorschau darf die Frage vollstaendig testen
     * (echte Bewertung, korrektes `correct`), darf dabei aber keinen
     * Wiederholungs-Zustand persistieren -- eine Vorschau erzeugt keine
     * echte Lernstatistik.
     */
    public function test_an_authorized_previewer_gets_a_correct_grade_but_no_persisted_review_for_a_draft_lesson(): void
    {
        $lesson = $this->draftLessonWithActivity();
        $author = User::factory()->author()->create();
        Activity::query()->where('key', $lesson->lesson_id)->sole()->authorUsers()->attach($author);

        $response = $this->actingAs($author)
            ->postJson("/de/lessons/{$lesson->lesson_id}/quiz/q1/answer", ['value' => 1]);

        $response->assertOk()->assertJson(['correct' => true, 'due_at' => null]);
        $this->assertSame(0, QuizReview::query()->count());
    }

    private function draftLessonWithActivity(): Lesson
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);

        return $lesson;
    }

    /**
     * @return array{0: User, 1: Lesson}
     */
    private function userAndLesson(): array
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        $user = User::factory()->create();

        return [$user, $lesson];
    }

    private function buildContentFixture(): void
    {
        $meta = <<<'YAML'
        id: "1.0"
        track: fundamente
        order: 0
        duration_minutes: 5
        level: einsteiger
        objectives_count: 1
        requires: []
        tools: []
        glossary_terms: []
        quiz:
          - id: q1
            type: single
            answer: 1
          - id: q2
            type: multi
            answer: [0, 2]
          - id: q3
            type: input
            answer: "dcmdump datei.dcm"
        tools_checked: "2026-09-16"
        status: draft
        updated: "2026-09-16"
        YAML;

        $markdown = <<<'MD'
        ---
        title: Test
        teaser: Test
        objectives:
          - Eins
        ---

        ## Intro

        Testinhalt.

        ## Quiz

        **q1 — Frage eins?**
        1. Falsch
        2. Richtig
        3. Auch falsch

        **q2 — Frage zwei?** *(Mehrfachauswahl)*
        1. Richtig eins
        2. Falsch
        3. Richtig zwei

        **q3 — Frage drei?** *(Freitext)*

        ---

        **Als Nächstes:** Weiter geht's.
        MD;

        File::ensureDirectoryExists($this->contentDir.'/lessons/1.0');
        File::ensureDirectoryExists($this->contentDir.'/glossary');
        File::put($this->contentDir.'/lessons/1.0/meta.yml', $meta);
        File::put($this->contentDir.'/lessons/1.0/de.md', $markdown);
        File::put($this->contentDir.'/glossary/de.yml', '');
    }
}

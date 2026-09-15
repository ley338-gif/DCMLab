<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Der Pruefungs-Editor (ADR 0071/0082, W6.3): Einstellungen ohne
 * Kommandozeile pflegen, denselben Kreislauf wie Quiz- und Lektions-Editor
 * (ADR 0080/0081) wiederverwendend. Der Fragenpool selbst ist bewusst noch
 * nicht Teil dieses Editors, siehe Klassendoc von ExamEditorController.
 */
class ExamEditorControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->contentDir = storage_path('framework/testing/exam-editor-'.Str::random(12));
        File::ensureDirectoryExists($this->contentDir.'/exams/fundamente');
        File::ensureDirectoryExists($this->contentDir.'/lessons/1.0');
        // Ein statistisch gueltiger Pool (8 Fragen: 3 single/2 multi/2
        // truefalse/1 input = 37.5/25/25/12.5%, je in Band; 2 von 8 mit
        // difficulty 3 = 25%; 4 cross- und 4 Lektionsfragen), damit
        // ContentValidator::checkExamStructure() beim Freigeben nicht wegen
        // des (in diesem Editor unveraenderten) Fragenpools scheitert.
        File::put(
            $this->contentDir.'/exams/fundamente/exam.yml',
            <<<'YAML'
            track: fundamente
            title_key: exam.fundamente.title
            pass_percent: 80
            draw: 1
            duration_minutes: 10
            shuffle: false
            questions:
              - id: f01
                type: single
                answer: 0
                lesson: "1.0"
                review: { lesson: "1.0", anchor: "intro" }
                difficulty: 3
                tags: []
              - id: f02
                type: multi
                answer: [0, 1]
                lesson: "1.0"
                review: { lesson: "1.0", anchor: "intro" }
                difficulty: 1
                tags: []
              - id: f03
                type: truefalse
                answer: true
                lesson: "1.0"
                review: { lesson: "1.0", anchor: "intro" }
                difficulty: 1
                tags: []
              - id: f04
                type: input
                answer: "test"
                lesson: "1.0"
                review: { lesson: "1.0", anchor: "intro" }
                difficulty: 1
                tags: []
              - id: f05
                type: single
                answer: 1
                lesson: "cross"
                review: [{ lesson: "1.0", anchor: "intro" }]
                difficulty: 3
                tags: []
              - id: f06
                type: single
                answer: 2
                lesson: "cross"
                review: [{ lesson: "1.0", anchor: "intro" }]
                difficulty: 1
                tags: []
              - id: f07
                type: multi
                answer: [0, 2]
                lesson: "cross"
                review: [{ lesson: "1.0", anchor: "intro" }]
                difficulty: 1
                tags: []
              - id: f08
                type: truefalse
                answer: false
                lesson: "cross"
                review: [{ lesson: "1.0", anchor: "intro" }]
                difficulty: 1
                tags: []

            YAML,
        );
        File::put(
            $this->contentDir.'/exams/fundamente/de.md',
            <<<'MD'
            ---
            title: Alte Pruefung
            intro: Alter Intro
            ---

            ### f01 — Frage eins?

            1. A
            2. B
            3. C
            4. D

            **Erklärung:** Testerklaerung eins.

            ### f02 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

            1. A
            2. B
            3. C

            **Erklärung:** Testerklaerung zwei.

            ### f03 — Aussage drei.

            **Richtig / Falsch**

            **Erklärung:** Testerklaerung drei.

            ### f04 — Wie lautet Antwort vier? *(Freitext)*

            **Erklärung:** Testerklaerung vier.

            ### f05 — Frage fuenf?

            1. A
            2. B
            3. C

            **Erklärung:** Testerklaerung fuenf.

            ### f06 — Frage sechs?

            1. A
            2. B
            3. C

            **Erklärung:** Testerklaerung sechs.

            ### f07 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

            1. A
            2. B
            3. C

            **Erklärung:** Testerklaerung sieben.

            ### f08 — Aussage acht.

            **Richtig / Falsch**

            **Erklärung:** Testerklaerung acht.

            MD,
        );
        File::put(
            $this->contentDir.'/lessons/1.0/meta.yml',
            "id: \"1.0\"\ntrack: fundamente\nlevel: einsteiger\nduration_minutes: 5\nrequires: []\ntools: []\nglossary_terms: []\nquiz:\n  - id: q1\n    type: single\n    answer: 0\ntools_checked: \"".now()->toDateString()."\"\nstatus: draft\n",
        );
        File::put(
            $this->contentDir.'/lessons/1.0/de.md',
            "---\ntitle: Lektion\nteaser: Teaser\nobjectives: []\n---\n\n## Intro\n\nText.\n\n## Quiz\n\n**q1 — Testfrage?**\n1. A\n2. B\n",
        );
        $this->app->instance(ContentRepository::class, new ContentRepository($this->contentDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_a_learner_cannot_open_the_editor(): void
    {
        [$track] = $this->trackAndActivity();
        $learner = User::factory()->create();

        $this->actingAs($learner)->get("/de/author/exams/{$track->slug}/edit")->assertForbidden();
    }

    public function test_an_assigned_author_sees_the_current_fields_and_coverage(): void
    {
        [$track, , $author] = $this->trackAndActivity();

        $this->actingAs($author)
            ->get("/de/author/exams/{$track->slug}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Author/ExamEditor')
                ->where('fields.title', 'Alte Pruefung')
                ->where('fields.pass_percent', 80)
                ->where('coverage.pool_size', 8)
                ->where('coverage.cross_count', 4)
                ->has('fields.questions', 8)
                ->where('fields.questions.0.id', 'f01')
                ->where('fields.questions.0.answer', 0)
                ->where('fields.questions.0.explanation', 'Testerklaerung eins.')
                ->where('catalog.lessons', ['1.0'])
                ->where('catalog.lesson_headings', fn ($headings) => $headings['1.0'] === ['intro', 'quiz'])
                ->where('catalog.lesson_quiz_questions', fn ($quiz) => $quiz['1.0'] === ['q1'])
            );
    }

    public function test_validate_reports_coverage_for_the_submitted_draft_not_the_saved_pool(): void
    {
        [$track, , $author] = $this->trackAndActivity();

        $payload = array_merge($this->settingsPayload(), ['questions' => []]);

        $this->actingAs($author)
            ->postJson("/de/author/exams/{$track->slug}/edit/validate", $payload)
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('coverage.pool_size', 0)->etc());
    }

    public function test_the_full_lifecycle_can_edit_an_existing_question(): void
    {
        [$track, $activity, $author] = $this->trackAndActivity();
        $reviewer = User::factory()->reviewer()->create();

        $questions = $this->basePoolQuestions();
        $questions[0]['question'] = 'Neu formulierte Frage eins?';
        $questions[0]['explanation'] = 'Neue Erklärung eins.';
        $payload = array_merge($this->settingsPayload(), ['questions' => $questions]);

        $this->actingAs($author)
            ->postJson("/de/author/exams/{$track->slug}/edit/validate", $payload)
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('issues', [])->etc());

        $this->actingAs($author)
            ->post("/de/author/exams/{$track->slug}/edit", $payload)
            ->assertRedirect();

        $version = ContentVersion::where('activity_id', $activity->id)->firstOrFail();
        $this->actingAs($author)->post("/de/author/quiz-versions/{$version->id}/submit")->assertRedirect();
        $this->actingAs($reviewer)->post("/de/author/quiz-versions/{$version->id}/publish")->assertRedirect();

        $this->assertSame('published', $version->refresh()->status);

        $writtenBody = File::get($this->contentDir.'/exams/fundamente/de.md');
        $this->assertStringContainsString('### f01 — Neu formulierte Frage eins?', $writtenBody);
        $this->assertStringContainsString('Neue Erklärung eins.', $writtenBody);
        $this->assertStringContainsString('### f02 — Welche Aussagen stimmen?', $writtenBody, 'unveraenderte Fragen bleiben erhalten.');
    }

    public function test_the_full_lifecycle_can_add_a_ref_question_replacing_an_own_question(): void
    {
        [$track, $activity, $author] = $this->trackAndActivity();
        $reviewer = User::factory()->reviewer()->create();

        $questions = $this->basePoolQuestions();
        // f06 (single, cross) wird durch eine ref-Frage auf denselben Typ
        // ersetzt (die Lektions-Quizfrage q1 ist ebenfalls "single") --
        // Pool-Zusammensetzung (Typmischung/Cross-Anzahl) bleibt gleich.
        $questions[5] = [
            'id' => 'f06', 'is_ref' => true, 'ref_lesson' => '1.0', 'ref_question' => 'q1',
            'lesson' => 'cross', 'review' => [['lesson' => '1.0', 'anchor' => 'intro']],
            'difficulty' => 1, 'tags' => [],
        ];
        $payload = array_merge($this->settingsPayload(), ['questions' => $questions]);

        $this->actingAs($author)
            ->post("/de/author/exams/{$track->slug}/edit", $payload)
            ->assertRedirect();

        $version = ContentVersion::where('activity_id', $activity->id)->firstOrFail();
        $this->actingAs($author)->post("/de/author/quiz-versions/{$version->id}/submit")->assertRedirect();
        $this->actingAs($reviewer)->post("/de/author/quiz-versions/{$version->id}/publish")->assertRedirect();

        $this->assertSame('published', $version->refresh()->status);

        $writtenMeta = File::get($this->contentDir.'/exams/fundamente/exam.yml');
        $writtenBody = File::get($this->contentDir.'/exams/fundamente/de.md');

        $parsed = Yaml::parse($writtenMeta);
        $f06 = collect($parsed['questions'])->firstWhere('id', 'f06');
        $this->assertSame(['lesson' => '1.0', 'question' => 'q1'], $f06['ref']);
        $this->assertStringNotContainsString('### f06', $writtenBody, 'ref-Fragen bekommen keinen eigenen Block.');
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(): array
    {
        return [
            'title' => 'Alte Pruefung',
            'intro' => 'Alter Intro',
            'pass_percent' => 80,
            'draw' => 1,
            'duration_minutes' => 10,
            'shuffle' => false,
            'min_per_lesson' => 1,
        ];
    }

    /**
     * Dieselben acht Fragen wie im setUp()-Fixture, als Editor-Entwurf --
     * Basis fuer Tests, die einzelne Fragen aendern/ersetzen, ohne die
     * statistische Gueltigkeit des Pools zu verlieren.
     *
     * @return list<array<string, mixed>>
     */
    private function basePoolQuestions(): array
    {
        $review = [['lesson' => '1.0', 'anchor' => 'intro']];

        return [
            ['id' => 'f01', 'is_ref' => false, 'type' => 'single', 'question' => 'Frage eins?', 'options' => ['A', 'B', 'C', 'D'], 'answer' => 0, 'explanation' => 'Testerklaerung eins.', 'lesson' => '1.0', 'review' => $review, 'difficulty' => 3, 'tags' => []],
            ['id' => 'f02', 'is_ref' => false, 'type' => 'multi', 'question' => 'Welche Aussagen stimmen?', 'options' => ['A', 'B', 'C'], 'answer' => [0, 1], 'explanation' => 'Testerklaerung zwei.', 'lesson' => '1.0', 'review' => $review, 'difficulty' => 1, 'tags' => []],
            ['id' => 'f03', 'is_ref' => false, 'type' => 'truefalse', 'question' => 'Aussage drei.', 'answer' => true, 'explanation' => 'Testerklaerung drei.', 'lesson' => '1.0', 'review' => $review, 'difficulty' => 1, 'tags' => []],
            ['id' => 'f04', 'is_ref' => false, 'type' => 'input', 'question' => 'Wie lautet Antwort vier?', 'answer' => 'test', 'explanation' => 'Testerklaerung vier.', 'lesson' => '1.0', 'review' => $review, 'difficulty' => 1, 'tags' => []],
            ['id' => 'f05', 'is_ref' => false, 'type' => 'single', 'question' => 'Frage fuenf?', 'options' => ['A', 'B', 'C'], 'answer' => 1, 'explanation' => 'Testerklaerung fuenf.', 'lesson' => 'cross', 'review' => $review, 'difficulty' => 3, 'tags' => []],
            ['id' => 'f06', 'is_ref' => false, 'type' => 'single', 'question' => 'Frage sechs?', 'options' => ['A', 'B', 'C'], 'answer' => 2, 'explanation' => 'Testerklaerung sechs.', 'lesson' => 'cross', 'review' => $review, 'difficulty' => 1, 'tags' => []],
            ['id' => 'f07', 'is_ref' => false, 'type' => 'multi', 'question' => 'Welche Aussagen stimmen?', 'options' => ['A', 'B', 'C'], 'answer' => [0, 2], 'explanation' => 'Testerklaerung sieben.', 'lesson' => 'cross', 'review' => $review, 'difficulty' => 1, 'tags' => []],
            ['id' => 'f08', 'is_ref' => false, 'type' => 'truefalse', 'question' => 'Aussage acht.', 'answer' => false, 'explanation' => 'Testerklaerung acht.', 'lesson' => 'cross', 'review' => $review, 'difficulty' => 1, 'tags' => []],
        ];
    }

    public function test_the_full_lifecycle_writes_only_the_settings_and_preserves_the_question_pool(): void
    {
        [$track, $activity, $author] = $this->trackAndActivity();
        $reviewer = User::factory()->reviewer()->create();

        $payload = [
            'title' => 'Neue Pruefung',
            'intro' => 'Neuer Intro',
            'pass_percent' => 85,
            'draw' => 1,
            'duration_minutes' => 15,
            'shuffle' => true,
            'min_per_lesson' => 1,
        ];

        $this->actingAs($author)
            ->postJson("/de/author/exams/{$track->slug}/edit/validate", $payload)
            ->assertOk();

        $this->actingAs($author)
            ->post("/de/author/exams/{$track->slug}/edit", $payload)
            ->assertRedirect();

        $version = ContentVersion::where('activity_id', $activity->id)->firstOrFail();

        $this->actingAs($author)->post("/de/author/quiz-versions/{$version->id}/submit")->assertRedirect();
        $this->actingAs($reviewer)->post("/de/author/quiz-versions/{$version->id}/publish")->assertRedirect();

        $this->assertSame('published', $version->refresh()->status);

        $writtenMeta = File::get($this->contentDir.'/exams/fundamente/exam.yml');
        $writtenBody = File::get($this->contentDir.'/exams/fundamente/de.md');

        $this->assertStringContainsString('pass_percent: 85', $writtenMeta);
        $this->assertStringContainsString('id: f01', $writtenMeta, 'Der Fragenpool muss erhalten bleiben.');
        $this->assertStringContainsString('Neue Pruefung', $writtenBody);
        $this->assertStringContainsString('### f01 — Frage eins?', $writtenBody, 'Der Fragenpool muss erhalten bleiben.');
    }

    /**
     * @return array{0: Track, 1: Activity, 2: User}
     */
    private function trackAndActivity(): array
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        $activity = Activity::factory()->create(['type' => 'exam', 'key' => 'fundamente']);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);

        return [$track, $activity, $author];
    }
}

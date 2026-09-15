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
use Tests\TestCase;

/**
 * Der Quiz-Editor (ADR 0071/0080, W6-DoD): "Der Typ laesst sich vollstaendig
 * ohne Kommandozeile anlegen, pruefen, einreichen, freigeben und danach
 * benutzen." Dieser Test durchlaeuft genau diese Kette einmal komplett.
 */
class QuizEditorControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->contentDir = storage_path('framework/testing/quiz-editor-'.Str::random(12));
        File::ensureDirectoryExists($this->contentDir.'/lessons/1.0');
        File::put($this->contentDir.'/lessons/1.0/meta.yml', "id: \"1.0\"\ntrack: fundamente\nrequires: []\ntools: []\nglossary_terms: []\nstatus: draft\n");
        File::put(
            $this->contentDir.'/lessons/1.0/de.md',
            "---\ntitle: Test\nteaser: Test\nobjectives: []\n---\n\n## Intro\n\n```\n\$ dcmdump datei.dcm\n(0008,0060) CS [CT]\n```\n\n**Was du daran abliest:** Test.\n",
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
        [$lesson] = $this->lessonAndActivity();
        $learner = User::factory()->create();

        $this->actingAs($learner)->get("/de/author/lessons/{$lesson->lesson_id}/quiz")->assertForbidden();
    }

    public function test_an_author_not_assigned_to_the_lesson_cannot_open_the_editor(): void
    {
        [$lesson] = $this->lessonAndActivity();
        $someoneElse = User::factory()->author()->create();

        $this->actingAs($someoneElse)->get("/de/author/lessons/{$lesson->lesson_id}/quiz")->assertForbidden();
    }

    public function test_an_assigned_author_can_open_the_editor(): void
    {
        [$lesson, $activity, $author] = $this->lessonAndActivity();

        $this->actingAs($author)
            ->get("/de/author/lessons/{$lesson->lesson_id}/quiz")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Author/QuizEditor')
                ->where('lesson.lesson_id', '1.0')
                ->where('pending_version', null)
                ->where('can_publish', false),
            );
    }

    public function test_validating_an_invalid_draft_returns_the_issue_without_saving_anything(): void
    {
        [$lesson, , $author] = $this->lessonAndActivity();

        $response = $this->actingAs($author)->postJson("/de/author/lessons/{$lesson->lesson_id}/quiz/validate", [
            'questions' => [
                ['id' => 'q1', 'type' => 'single', 'answer' => 5, 'question' => 'Frage?', 'options' => ['A', 'B']],
            ],
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('issues'));
        $this->assertSame(0, ContentVersion::count());
    }

    /**
     * ADR 0104 (CMS-6a): eine Quiz-Freigabe schreibt jetzt direkt in die DB
     * (QuizContentPublisher) -- content/ bleibt unangetastet.
     */
    public function test_the_full_lifecycle_from_draft_to_publish_writes_to_the_db_without_touching_content_files(): void
    {
        [$lesson, $activity, $author] = $this->lessonAndActivity();
        $reviewer = User::factory()->reviewer()->create();

        $originalMeta = File::get($this->contentDir.'/lessons/1.0/meta.yml');
        $originalBody = File::get($this->contentDir.'/lessons/1.0/de.md');

        $payload = [
            'questions' => [
                ['id' => 'q1', 'type' => 'single', 'answer' => 0, 'question' => 'Was stimmt?', 'options' => ['Richtig', 'Falsch']],
            ],
        ];

        // 1. Pruefen, ohne zu speichern.
        $validateResponse = $this->actingAs($author)
            ->postJson("/de/author/lessons/{$lesson->lesson_id}/quiz/validate", $payload);
        $validateResponse->assertOk();
        $this->assertSame([], $validateResponse->json('issues'));

        // 2. Entwurf anlegen.
        $this->actingAs($author)
            ->post("/de/author/lessons/{$lesson->lesson_id}/quiz", $payload)
            ->assertRedirect();

        $version = ContentVersion::where('activity_id', $activity->id)->firstOrFail();
        $this->assertSame('draft', $version->status);

        // 3. Zur Pruefung einreichen.
        $this->actingAs($author)
            ->post("/de/author/quiz-versions/{$version->id}/submit")
            ->assertRedirect();
        $this->assertSame('review', $version->refresh()->status);

        // Ein Autor darf nicht freigeben.
        $this->actingAs($author)
            ->post("/de/author/quiz-versions/{$version->id}/publish")
            ->assertForbidden();

        // 4. Freigeben -- schreibt direkt in die DB, nicht mehr nach content/.
        $this->actingAs($reviewer)
            ->post("/de/author/quiz-versions/{$version->id}/publish")
            ->assertRedirect();

        $this->assertSame('published', $version->refresh()->status);
        $this->assertTrue($version->is_current);

        // content/ bleibt vollstaendig unangetastet.
        $this->assertSame($originalMeta, File::get($this->contentDir.'/lessons/1.0/meta.yml'));
        $this->assertSame($originalBody, File::get($this->contentDir.'/lessons/1.0/de.md'));

        $lesson->refresh();
        $this->assertSame([['id' => 'q1', 'type' => 'single', 'answer' => 0]], $lesson->quiz);
        $this->assertStringContainsString('Was stimmt?', $lesson->body);

        // 5. Erneutes Oeffnen zeigt den frisch veroeffentlichten Stand.
        $this->actingAs($author)
            ->get("/de/author/lessons/{$lesson->lesson_id}/quiz")
            ->assertInertia(fn ($page) => $page
                ->where('questions.0.id', 'q1')
                ->where('questions.0.question', 'Was stimmt?'),
            );
    }

    /**
     * @return array{0: Lesson, 1: Activity, 2: User}
     */
    private function lessonAndActivity(): array
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        // Body wie ihn ein bereits gelaufener content:sync (ADR 0101) aus
        // derselben Datei in die DB uebernommen haette.
        $lesson = Lesson::factory()->create([
            'lesson_id' => '1.0',
            'track_id' => $track->id,
            'body' => "## Intro\n\n```\n\$ dcmdump datei.dcm\n(0008,0060) CS [CT]\n```\n\n**Was du daran abliest:** Test.",
        ]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);

        return [$lesson, $activity, $author];
    }
}

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
 * Der Lektions-Editor (ADR 0071/0081, W6.2): Metadaten und Prosa ohne
 * Kommandozeile pflegen, denselben Kreislauf wie der Quiz-Editor (ADR 0080)
 * wiederverwendend.
 */
class LessonEditorControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->contentDir = storage_path('framework/testing/lesson-editor-'.Str::random(12));
        File::ensureDirectoryExists($this->contentDir.'/lessons/1.0');
        File::put(
            $this->contentDir.'/lessons/1.0/meta.yml',
            "id: \"1.0\"\ntrack: fundamente\nlevel: einsteiger\nduration_minutes: 5\nrequires: []\ntools: [dcmdump]\nglossary_terms: [dicom]\ntools_checked: \"".now()->toDateString()."\"\nstatus: draft\n",
        );
        File::put(
            $this->contentDir.'/lessons/1.0/de.md',
            "---\ntitle: Alter Titel\nteaser: Alter Teaser\nobjectives: []\n---\n\n## Intro\n\n```\n\$ dcmdump datei.dcm\n(0008,0060) CS [CT]\n```\n\n**Was du daran abliest:** Test.\n\n## Quiz\n\n**q1 — Frage?**\n1. A\n2. B\n\n---\n\n**Als Nächstes:** weiter.\n",
        );
        File::ensureDirectoryExists($this->contentDir.'/tools');
        File::ensureDirectoryExists($this->contentDir.'/glossary');
        File::put($this->contentDir.'/tools/de.yml', "dcmdump:\n  name: dcmdump\n  purpose: Test\n");
        File::put($this->contentDir.'/glossary/de.yml', "dicom:\n  term: DICOM\n  definition: Test\n");
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

        $this->actingAs($learner)->get("/de/author/lessons/{$lesson->lesson_id}/edit")->assertForbidden();
    }

    public function test_an_assigned_author_sees_the_current_fields(): void
    {
        [$lesson, , $author] = $this->lessonAndActivity();

        $this->actingAs($author)
            ->get("/de/author/lessons/{$lesson->lesson_id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Author/LessonEditor')
                ->where('fields.title', 'Alter Titel')
                ->where('fields.level', 'einsteiger')
                ->where('catalog.tools', ['dcmdump'])
            );
    }

    public function test_the_editable_body_excludes_the_quiz_section(): void
    {
        [$lesson, , $author] = $this->lessonAndActivity();

        $this->actingAs($author)
            ->get("/de/author/lessons/{$lesson->lesson_id}/edit")
            ->assertInertia(fn ($page) => $page
                ->where('fields.body', fn (string $body) => str_contains($body, 'Was du daran abliest')
                    && ! str_contains($body, 'q1 — Frage'))
            );
    }

    public function test_the_full_lifecycle_preserves_the_quiz_section_on_publish(): void
    {
        [$lesson, $activity, $author] = $this->lessonAndActivity();
        $reviewer = User::factory()->reviewer()->create();

        $payload = [
            'title' => 'Neuer Titel',
            'teaser' => 'Neuer Teaser',
            'level' => 'aufbau',
            'duration_minutes' => 15,
            'tools' => ['dcmdump'],
            'requires' => [],
            'glossary_terms' => ['dicom'],
            'body' => "## Neue Einleitung\n\n```\n\$ dcmdump datei.dcm\n(0008,0060) CS [CT]\n```\n\n**Was du daran abliest:** Neu.",
        ];

        $this->actingAs($author)
            ->postJson("/de/author/lessons/{$lesson->lesson_id}/edit/validate", $payload)
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('issues', [])->etc());

        $this->actingAs($author)
            ->post("/de/author/lessons/{$lesson->lesson_id}/edit", $payload)
            ->assertRedirect();

        $version = ContentVersion::where('activity_id', $activity->id)->firstOrFail();

        $this->actingAs($author)->post("/de/author/quiz-versions/{$version->id}/submit")->assertRedirect();
        $this->actingAs($reviewer)->post("/de/author/quiz-versions/{$version->id}/publish")->assertRedirect();

        $this->assertSame('published', $version->refresh()->status);

        $writtenMeta = File::get($this->contentDir.'/lessons/1.0/meta.yml');
        $writtenBody = File::get($this->contentDir.'/lessons/1.0/de.md');

        $this->assertStringContainsString('level: aufbau', $writtenMeta);
        $this->assertStringContainsString('duration_minutes: 15', $writtenMeta);
        $this->assertStringContainsString('Neue Einleitung', $writtenBody);
        $this->assertStringContainsString('q1 — Frage?', $writtenBody, 'Der Quiz-Abschnitt muss erhalten bleiben.');
        $this->assertStringContainsString('Als Nächstes', $writtenBody);
    }

    /**
     * @return array{0: Lesson, 1: Activity, 2: User}
     */
    private function lessonAndActivity(): array
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);

        return [$lesson, $activity, $author];
    }
}

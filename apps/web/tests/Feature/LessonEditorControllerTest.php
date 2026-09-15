<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\Node;
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
            "id: \"1.0\"\ntrack: fundamente\nlevel: einsteiger\nduration_minutes: 5\nrequires: []\ntools: [dcmdump]\nglossary_terms: [dicom]\nobjectives_count: 1\nsandbox:\n  required: false\nlab:\n  node: null\n  optional: true\ntools_checked: \"".now()->toDateString()."\"\nstatus: draft\n",
        );
        File::put(
            $this->contentDir.'/lessons/1.0/de.md',
            "---\ntitle: Alter Titel\nteaser: Alter Teaser\nobjectives:\n  - Altes Lernziel\n---\n\n## Intro\n\n```\n\$ dcmdump datei.dcm\n(0008,0060) CS [CT]\n```\n\n**Was du daran abliest:** Test.\n\n## Quiz\n\n**q1 — Frage?**\n1. A\n2. B\n\n---\n\n**Als Nächstes:** weiter.\n",
        );
        File::ensureDirectoryExists($this->contentDir.'/tools');
        File::ensureDirectoryExists($this->contentDir.'/glossary');
        File::put($this->contentDir.'/tools/de.yml', "dcmdump:\n  name: dcmdump\n  purpose: Test\n");
        File::put($this->contentDir.'/glossary/de.yml', "dicom:\n  term: DICOM\n  definition: Test\n");
        File::put(
            $this->contentDir.'/datasets.yml',
            "ct-thorax-60:\n  patient: \"TEST^PATIENT\"\n  patient_id: \"0000\"\n  study: Test\n  series: [Test]\n  file_count: 1\n",
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
                ->where('fields.objectives', ['Altes Lernziel'])
                ->where('fields.sandbox.required', false)
                ->where('fields.lab.optional', true)
                ->where('catalog.tools', ['dcmdump'])
                ->where('catalog.datasets', ['ct-thorax-60'])
            );
    }

    /**
     * Seit CMS-6d Teil 3 (ADR 0109) kann eine Node rein in Studio angelegt
     * sein, ohne jemals eine content/nodes/**-Datei zu haben -- der
     * Composer-Katalog muss sie trotzdem als Lab-Auswahl anbieten, nicht nur
     * den datei-basierten Bestand.
     */
    public function test_the_node_catalog_includes_a_studio_only_node_without_a_content_file(): void
    {
        [$lesson, , $author] = $this->lessonAndActivity();
        Node::factory()->create(['slug' => 'studio-only-node']);

        $this->actingAs($author)
            ->get("/de/author/lessons/{$lesson->lesson_id}/edit")
            ->assertInertia(fn ($page) => $page
                ->where('catalog.nodes', fn ($nodes) => collect($nodes)->contains('studio-only-node'))
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

    /**
     * ADR 0102 (CMS-5b): ein Lektionsfeld-Publish schreibt jetzt direkt in
     * die DB (LessonContentPublisher) -- content/ wird dabei nicht mehr
     * angefasst. Die Datei bleibt exakt so, wie sie in setUp() angelegt
     * wurde (bewusster Alt-Titel/Alt-Teaser als Nachweis).
     */
    public function test_the_full_lifecycle_writes_to_the_db_and_preserves_the_quiz_section_without_touching_content_files(): void
    {
        [$lesson, $activity, $author] = $this->lessonAndActivity();
        $reviewer = User::factory()->reviewer()->create();

        $originalMeta = File::get($this->contentDir.'/lessons/1.0/meta.yml');
        $originalBody = File::get($this->contentDir.'/lessons/1.0/de.md');

        $payload = [
            'title' => 'Neuer Titel',
            'teaser' => 'Neuer Teaser',
            'level' => 'aufbau',
            'duration_minutes' => 15,
            'tools' => ['dcmdump'],
            'requires' => [],
            'glossary_terms' => ['dicom'],
            'objectives' => ['Neues Lernziel eins', 'Neues Lernziel zwei'],
            'sandbox' => ['required' => true, 'dataset' => 'ct-thorax-60', 'note' => null],
            'lab' => ['node' => 'silent-ct', 'optional' => false],
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

        // content/ bleibt vollstaendig unangetastet -- der zentrale Punkt
        // dieser ADR.
        $this->assertSame($originalMeta, File::get($this->contentDir.'/lessons/1.0/meta.yml'));
        $this->assertSame($originalBody, File::get($this->contentDir.'/lessons/1.0/de.md'));

        $lesson->refresh();
        $this->assertSame('Neuer Titel', $lesson->title['de']);
        $this->assertSame('Neuer Teaser', $lesson->teaser['de']);
        $this->assertSame('aufbau', $lesson->level);
        $this->assertSame(15, $lesson->duration_minutes);
        $this->assertSame(['dcmdump'], $lesson->tools);
        $this->assertSame(['dicom'], $lesson->glossary_terms);
        $this->assertSame(['Neues Lernziel eins', 'Neues Lernziel zwei'], $lesson->objectives);
        $this->assertSame(2, $lesson->objectives_count, 'objectives_count muss zur Listenlaenge passen.');
        $this->assertTrue($lesson->sandbox['required']);
        $this->assertSame('ct-thorax-60', $lesson->sandbox['dataset']);
        $this->assertSame('silent-ct', $lesson->lab['node']);
        $this->assertFalse($lesson->lab['optional']);
        $this->assertStringContainsString('Neue Einleitung', $lesson->body);
        $this->assertStringContainsString('q1 — Frage?', $lesson->body, 'Der Quiz-Abschnitt muss erhalten bleiben.');
        $this->assertStringContainsString('Als Nächstes', $lesson->body);

        $activity->refresh();
        $this->assertSame('Neuer Titel', $activity->title['de']);
        $this->assertSame('Neuer Teaser', $activity->teaser['de']);

        // ADR 0102-Nachtrag: re-oeffnen zeigt den frisch veroeffentlichten
        // Stand, nicht die veraltete Datei (die bewusst nie geschrieben
        // wurde) -- currentFields() muss deshalb die DB bevorzugen.
        $this->actingAs($author)
            ->get("/de/author/lessons/{$lesson->lesson_id}/edit")
            ->assertInertia(fn ($page) => $page
                ->where('fields.title', 'Neuer Titel')
                ->where('fields.level', 'aufbau')
                ->where('fields.objectives', ['Neues Lernziel eins', 'Neues Lernziel zwei'])
                ->where('fields.body', fn (string $body) => str_contains($body, 'Neue Einleitung')
                    && ! str_contains($body, 'q1 — Frage')),
            );
    }

    /**
     * @return array{0: Lesson, 1: Activity, 2: User}
     */
    private function lessonAndActivity(): array
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        // Body/Titel/Teaser/Lernziele wie sie ein bereits gelaufener
        // content:sync (ADR 0101) aus derselben Datei in die DB uebernommen
        // haette -- die Ist-Zustands-Quelle fuer einen Lektionsfeld-Publish
        // ist seit ADR 0102 die DB, nicht mehr die Datei.
        $lesson = Lesson::factory()->create([
            'lesson_id' => '1.0',
            'track_id' => $track->id,
            'level' => 'einsteiger',
            'duration_minutes' => 5,
            'requires' => [],
            'tools' => ['dcmdump'],
            'glossary_terms' => ['dicom'],
            'title' => ['de' => 'Alter Titel'],
            'teaser' => ['de' => 'Alter Teaser'],
            'objectives' => ['Altes Lernziel'],
            'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
            'lab' => ['node' => null, 'optional' => true],
            'body' => "## Intro\n\n```\n\$ dcmdump datei.dcm\n(0008,0060) CS [CT]\n```\n\n**Was du daran abliest:** Test.\n\n## Quiz\n\n**q1 — Frage?**\n1. A\n2. B\n\n---\n\n**Als Nächstes:** weiter.",
        ]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);

        return [$lesson, $activity, $author];
    }
}

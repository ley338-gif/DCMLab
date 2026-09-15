<?php

namespace Tests\Unit\Content;

use App\Content\ActivityContentApplier;
use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ADR 0102 (CMS-5b): entscheidet, ob ein freigegebener Entwurf direkt in die
 * DB geht (Lektionsfeld-Entwurf) oder weiterhin ueber ContentWriter nach
 * content/ geschrieben wird (alles andere, bis CMS-6/CMS-8).
 *
 * `LessonActivity::validate()` prueft weiterhin gegen den Ist-Zustand aus
 * `content/` (ADR 0080) -- ein minimaler, echter Dateibestand ist deshalb
 * auch fuer den neuen, DB-schreibenden Pfad noetig, damit die Freigabe
 * ueberhaupt die Validierung passiert (siehe LessonEditorControllerTest fuer
 * dasselbe Muster).
 */
class ActivityContentApplierTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentDir = storage_path('framework/testing/applier-'.uniqid());
        File::ensureDirectoryExists($this->contentDir.'/lessons/1.0');
        File::put($this->contentDir.'/lessons/1.0/meta.yml', "id: \"1.0\"\ntrack: fundamente\nlevel: einsteiger\nduration_minutes: 5\nrequires: []\ntools: []\nglossary_terms: []\nobjectives_count: 1\nsandbox:\n  required: false\nlab:\n  node: null\n  optional: true\nstatus: draft\n");
        File::put($this->contentDir.'/lessons/1.0/de.md', "---\ntitle: Alt\nteaser: Alt\nobjectives:\n  - Altes Ziel\n---\n\nAlte Prosa.\n\n```\n\$ dcmdump datei.dcm\n```\n\n**Was du daran abliest:** Test.\n");
        $this->app->instance(ContentRepository::class, new ContentRepository($this->contentDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_a_lesson_field_draft_is_applied_directly_to_the_db_without_touching_content_files(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'title' => ['de' => 'Alt']]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);

        $issues = $this->app->make(ActivityContentApplier::class)->apply($activity, [
            'title' => 'Neu', 'teaser' => 'Neu', 'level' => 'einsteiger', 'duration_minutes' => 5,
            'objectives' => ['Ziel'], 'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
            'lab' => ['node' => null, 'optional' => true], 'body' => "Neue Prosa.\n\n```\n\$ dcmdump datei.dcm\n```\n\n**Was du daran abliest:** Test.",
        ]);

        $this->assertSame([], $issues);
        $this->assertSame('Neu', $lesson->fresh()->title['de']);
        // content/ bleibt exakt wie in setUp() angelegt -- der Kernpunkt
        // dieses Pfads.
        $this->assertStringNotContainsString('Neue Prosa.', File::get($this->contentDir.'/lessons/1.0/de.md'));
    }

    public function test_a_quiz_draft_for_the_same_lesson_type_writes_via_content_writer_instead(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'title' => ['de' => 'Unveraendert']]);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $activity = Activity::query()->where('type', 'lesson')->where('key', '1.0')->firstOrFail();

        $issues = $this->app->make(ActivityContentApplier::class)->apply($activity, [
            'quiz' => [['id' => 'q1', 'type' => 'single', 'answer' => 1, 'question' => 'Frage?', 'options' => ['A', 'B']]],
        ]);

        // Der Lektions-Datensatz bleibt unangetastet -- LessonContentPublisher
        // wurde nicht aufgerufen, weil das Payload einen quiz-Schluessel hat.
        $this->assertSame('Unveraendert', $lesson->fresh()->title['de']);
        // Stattdessen ging es ueber ContentWriter -- die Datei traegt jetzt
        // den Quiz-Abschnitt.
        $this->assertSame([], $issues);
        $this->assertStringContainsString('Quiz', File::get($this->contentDir.'/lessons/1.0/de.md'));
    }

    public function test_it_reports_validation_issues_without_applying_anything(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'title' => ['de' => 'Unveraendert']]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);

        // objectives darf laut ContentValidator nicht leer sein.
        $issues = $this->app->make(ActivityContentApplier::class)->apply($activity, [
            'title' => 'Neu', 'teaser' => 'Neu', 'level' => 'einsteiger', 'duration_minutes' => 5,
            'objectives' => [], 'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
            'lab' => ['node' => null, 'optional' => true], 'body' => 'Neue Prosa.',
        ]);

        $this->assertNotEmpty($issues);
        $this->assertSame('Unveraendert', $lesson->fresh()->title['de']);
    }
}

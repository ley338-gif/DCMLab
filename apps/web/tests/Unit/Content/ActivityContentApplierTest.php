<?php

namespace Tests\Unit\Content;

use App\Content\ActivityContentApplier;
use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Node;
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
        File::put($this->contentDir.'/themenfelder.yml', "- slug: dicom\n  order: 1\n  title_key: themenfeld.dicom.title\n  status: published\n");
        File::ensureDirectoryExists($this->contentDir.'/nodes/test-node');
        File::put($this->contentDir.'/nodes/test-node/node.yml', "slug: test-node\ndifficulty: easy\npoints: 10\ncategory: netzwerk\nskills: [netzwerk]\nrelated_lessons: []\nestimated_minutes: 15\nstatus: draft\n");
        File::put($this->contentDir.'/nodes/test-node/de.md', "---\ntitle: Alt\nscenario_title: Alt\n---\n\n## Briefing\n\nAlter Text.\n\n```\n\$ echoscu foo\n```\n\n**Was du daran abliest:** Test.\n");
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

    public function test_a_quiz_draft_for_the_same_lesson_type_is_also_applied_directly_to_the_db(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create([
            'lesson_id' => '1.0',
            'track_id' => $track->id,
            'title' => ['de' => 'Unveraendert'],
            'body' => "Alte Prosa.\n\n```\n\$ dcmdump datei.dcm\n```\n\n**Was du daran abliest:** Test.",
        ]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);

        $originalFile = File::get($this->contentDir.'/lessons/1.0/de.md');

        $issues = $this->app->make(ActivityContentApplier::class)->apply($activity, [
            'quiz' => [['id' => 'q1', 'type' => 'single', 'answer' => 1, 'question' => 'Frage?', 'options' => ['A', 'B']]],
        ]);

        // Lektionsfelder (title etc.) bleiben unangetastet -- nur die
        // Quiz-spezifischen Felder aendern sich, ueber QuizContentPublisher.
        $this->assertSame([], $issues);
        $lesson->refresh();
        $this->assertSame('Unveraendert', $lesson->title['de']);
        $this->assertSame([['id' => 'q1', 'type' => 'single', 'answer' => 1]], $lesson->quiz);
        $this->assertStringContainsString('Frage?', $lesson->body);
        $this->assertSame($originalFile, File::get($this->contentDir.'/lessons/1.0/de.md'));
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

    public function test_a_node_draft_is_applied_directly_to_the_db_without_touching_content_files(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node', 'title' => ['de' => 'Alt']]);
        $activity = Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);

        $issues = $this->app->make(ActivityContentApplier::class)->apply($activity, [
            'title' => 'Neu', 'scenario_title' => 'Neues Szenario', 'difficulty' => 'medium',
            'points' => 20, 'category' => 'netzwerk', 'interaction' => 'terminal', 'estimated_minutes' => 15,
            'skills' => ['netzwerk'], 'related_lessons' => [], 'hints' => [],
            'body' => "## Briefing\n\nNeuer Text.\n\n```\n\$ echoscu foo\n```\n\n**Was du daran abliest:** Test.",
        ]);

        $this->assertSame([], $issues);
        $this->assertSame('Neu', $node->fresh()->title['de']);
        // content/ bleibt exakt wie in setUp() angelegt -- der Kernpunkt
        // dieses Pfads.
        $this->assertStringNotContainsString('Neuer Text.', File::get($this->contentDir.'/nodes/test-node/de.md'));
    }
}

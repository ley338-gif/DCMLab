<?php

namespace Tests\Unit\Content;

use App\Content\ActivityContentApplier;
use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\Lab;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\SandboxTemplate;
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
 *
 * Seit CMS-7d.3 (ADR 0118) traegt ein Payload `rich_content`, nicht mehr
 * `body` -- `ActivityContentApplier::apply()` normalisiert selbst nicht
 * (das macht ausschliesslich `ContentPublishingService`, der `apply()` in
 * der Praxis immer vorschaltet), Tests, die `apply()` direkt aufrufen,
 * muessen deshalb bereits ein normalisiertes Payload liefern.
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
        File::put($this->contentDir.'/lessons/1.0/meta.yml', "id: \"1.0\"\ntrack: fundamente\nlevel: einsteiger\nduration_minutes: 5\nrequires: []\ntools: []\nglossary_terms: []\nobjectives_count: 1\nsandbox:\n  required: false\nrelated_node:\n  node: null\n  optional: true\nstatus: draft\n");
        File::put($this->contentDir.'/lessons/1.0/de.md', "---\ntitle: Alt\nteaser: Alt\nobjectives:\n  - Altes Ziel\n---\n\nAlte Prosa.\n\n```\n\$ dcmdump datei.dcm\n```\n\n**Was du daran abliest:** Test.\n");
        File::put($this->contentDir.'/themenfelder.yml', "- slug: dicom\n  order: 1\n  title_key: themenfeld.dicom.title\n  status: published\n");
        File::ensureDirectoryExists($this->contentDir.'/nodes/test-node');
        File::put($this->contentDir.'/nodes/test-node/node.yml', "slug: test-node\ndifficulty: easy\npoints: 10\ncategory: netzwerk\nskills: [netzwerk]\nrelated_lessons: []\nestimated_minutes: 15\nstatus: draft\n");
        File::put($this->contentDir.'/nodes/test-node/de.md', "---\ntitle: Alt\nscenario_title: Alt\n---\n\n## Briefing\n\nAlter Text.\n\n```\n\$ echoscu foo\n```\n\n**Was du daran abliest:** Test.\n");
        File::put($this->contentDir.'/datasets.yml', "test-dataset:\n  patient: \"MUSTER^ERIKA\"\n  patient_id: \"1\"\n  study: \"Test\"\n  series: [\"A\"]\n  file_count: 1\n");
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
            'related_node' => ['node' => null, 'optional' => true], 'rich_content' => $this->richContent(
                'Neue Prosa.',
                '$ dcmdump datei.dcm',
            ),
        ]);

        $this->assertSame([], $issues);
        $this->assertSame('Neu', $lesson->fresh()->title['de']);
        // content/ bleibt exakt wie in setUp() angelegt -- der Kernpunkt
        // dieses Pfads.
        $this->assertStringNotContainsString('Neue Prosa.', File::get($this->contentDir.'/lessons/1.0/de.md'));
    }

    /**
     * @return array<string, mixed>
     */
    private function richContent(string $introText, string $commandLine): array
    {
        return [
            'type' => 'doc', 'version' => 1,
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $introText]]],
                ['type' => 'code_block', 'attrs' => ['variant' => 'terminal'], 'text' => $commandLine],
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => 'Was du daran abliest:', 'marks' => [['type' => 'bold']]],
                    ['type' => 'text', 'text' => ' Test.'],
                ]],
            ],
        ];
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

        // ContentValidator verlangt mindestens einen Codeblock -- ein
        // rich_content ohne jeden code_block-Knoten ist ein Verstoss.
        $issues = $this->app->make(ActivityContentApplier::class)->apply($activity, [
            'title' => 'Neu', 'teaser' => 'Neu', 'level' => 'einsteiger', 'duration_minutes' => 5,
            'objectives' => ['Ziel'], 'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
            'related_node' => ['node' => null, 'optional' => true],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Neue Prosa.']]],
            ]],
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
            'rich_content' => [
                'type' => 'node_content', 'version' => 1,
                'briefing' => $this->richContent('Neuer Text.', '$ echoscu foo'),
                'hints' => [],
                'write_up' => ['type' => 'doc', 'version' => 1, 'content' => []],
            ],
        ]);

        $this->assertSame([], $issues);
        $this->assertSame('Neu', $node->fresh()->title['de']);
        // content/ bleibt exakt wie in setUp() angelegt -- der Kernpunkt
        // dieses Pfads.
        $this->assertStringNotContainsString('Neuer Text.', File::get($this->contentDir.'/nodes/test-node/de.md'));
    }

    /**
     * Anders als bei Node/Lesson gibt es hier gar kein content/**-Verzeichnis
     * zu verschonen -- ein Lab hatte nie ein Dateipendant (CMS-8a).
     */
    public function test_a_lab_draft_is_applied_directly_to_the_db(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $lab = Lab::factory()->create(['slug' => 'test-lab', 'title' => ['de' => 'Alt']]);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'test-lab']);

        $issues = $this->app->make(ActivityContentApplier::class)->apply($activity, [
            'title' => 'Neu', 'scenario_title' => 'Neues Szenario', 'difficulty' => 'easy',
            'points' => 10, 'estimated_minutes' => 10,
            'runtime_template' => 'dicom-basic-tools', 'dataset' => 'test-dataset',
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu']],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
        ]);

        $this->assertSame([], $issues);
        $this->assertSame('Neu', $lab->fresh()->title['de']);
    }
}

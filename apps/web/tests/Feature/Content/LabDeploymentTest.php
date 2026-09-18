<?php

namespace Tests\Feature\Content;

use App\Content\LabDeploymentImporter;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\ContentVersion;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\Lesson;
use App\Models\LessonElement;
use App\Models\SandboxSession;
use App\Models\SandboxTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Lab-Content-Lifecycle-Audit, PR #152: `labs:export`/`labs:import` bringen
 * ein veroeffentlichtes Lab deterministisch, natural-key-basiert und ohne
 * Nutzerfortschritt zwischen Umgebungen -- Studio/DB/ContentVersioning
 * bleiben der einzige lebende Authoring-Pfad, dies ist reines Deployment.
 */
class LabDeploymentTest extends TestCase
{
    use RefreshDatabase;

    private string $deployPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deployPath = sys_get_temp_dir().'/dcmlab-labs-deploy-test-'.uniqid();
        config(['services.labs_deploy.path' => $this->deployPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->deployPath);

        parent::tearDown();
    }

    public function test_export_writes_the_expected_artifact_with_no_ids_and_no_timestamps(): void
    {
        $lesson = Lesson::factory()->create(['lesson_id' => '2.2']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $lab = Lab::factory()->create([
            'slug' => 'c-store-live',
            'difficulty' => 'medium',
            'points' => 20,
            'estimated_minutes' => 15,
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'dicom_instance_received', 'patient_id' => '4711', 'modality' => 'CT', 'min_instances' => 60]],
            'title' => ['de' => 'C-STORE: CT-Studie an das PACS uebertragen'],
            'scenario_title' => ['de' => 'Vollstaendige Studie ans Testarchiv senden'],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
        ]);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => $lab->slug]);
        LessonElement::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'activity',
            'position' => 2,
            'activity_id' => $activity->id,
        ]);

        $this->artisan('labs:export', ['slug' => 'c-store-live'])->assertExitCode(0);

        $path = "{$this->deployPath}/c-store-live.json";
        $this->assertFileExists($path);

        $artifact = json_decode(File::get($path), true);

        $this->assertSame([
            'schema_version' => 1,
            'slug' => 'c-store-live',
            'lab' => [
                'title' => 'C-STORE: CT-Studie an das PACS uebertragen',
                'scenario_title' => 'Vollstaendige Studie ans Testarchiv senden',
                'difficulty' => 'medium',
                'points' => 20,
                'estimated_minutes' => 15,
                'runtime_template' => 'dicom-basic-tools',
                'dataset' => 'ct-thorax-60',
                'assertions' => [['type' => 'dicom_instance_received', 'patient_id' => '4711', 'modality' => 'CT', 'min_instances' => 60]],
                'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
            ],
            'placements' => [['lesson_id' => '2.2', 'position' => 2]],
        ], $artifact);

        // Betreiber-Vorgabe: keine DB-IDs, keine Zeitstempel im Artefakt.
        $raw = File::get($path);
        $this->assertStringNotContainsString('"id"', $raw);
        $this->assertStringNotContainsString('created_at', $raw);
        $this->assertStringNotContainsString('updated_at', $raw);
    }

    public function test_export_is_byte_identical_for_unchanged_data(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create([
            'slug' => 'c-store-live',
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'dicom_instance_received', 'patient_id' => '4711']],
        ]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-store-live']);

        $this->artisan('labs:export', ['slug' => 'c-store-live'])->assertExitCode(0);
        $first = File::get("{$this->deployPath}/c-store-live.json");

        $this->artisan('labs:export', ['slug' => 'c-store-live'])->assertExitCode(0);
        $second = File::get("{$this->deployPath}/c-store-live.json");

        $this->assertSame($first, $second);
    }

    public function test_export_without_a_slug_only_exports_published_labs(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'published-lab', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60', 'assertions' => [['type' => 'dicom_instance_received']]]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'published-lab']);
        Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);

        $this->artisan('labs:export')->assertExitCode(0);

        $this->assertFileExists("{$this->deployPath}/published-lab.json");
        $this->assertFileDoesNotExist("{$this->deployPath}/draft-lab.json");
    }

    /**
     * `LabActivity::supports()->reusable === true` -- Export darf nicht
     * verlustbehaftet auf die erste Verknuepfung reduzieren.
     */
    public function test_export_captures_every_lesson_placement_sorted_deterministically(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $lab = Lab::factory()->create(['slug' => 'multi-lab', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60', 'assertions' => [['type' => 'dicom_instance_received']]]);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'multi-lab']);
        $lessonB = Lesson::factory()->create(['lesson_id' => '2.3']);
        $lessonA = Lesson::factory()->create(['lesson_id' => '2.2']);
        // Bewusst in umgekehrter Natural-Key-Reihenfolge angelegt, um die
        // deterministische Sortierung im Artefakt tatsaechlich zu pruefen.
        LessonElement::factory()->create(['lesson_id' => $lessonB->id, 'type' => 'activity', 'position' => 0, 'activity_id' => $activity->id]);
        LessonElement::factory()->create(['lesson_id' => $lessonA->id, 'type' => 'activity', 'position' => 3, 'activity_id' => $activity->id]);

        $this->artisan('labs:export', ['slug' => 'multi-lab'])->assertExitCode(0);
        $artifact = json_decode(File::get("{$this->deployPath}/multi-lab.json"), true);

        $this->assertSame([
            ['lesson_id' => '2.2', 'position' => 3],
            ['lesson_id' => '2.3', 'position' => 0],
        ], $artifact['placements']);
    }

    /**
     * Zieltest aus dem Audit: leere DB -> Import -> vollstaendiges Lab.
     */
    public function test_import_on_a_fresh_database_recreates_the_full_lab(): void
    {
        $artifact = $this->validArtifact();
        $lesson = Lesson::factory()->create(['lesson_id' => '2.2']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        app(LabDeploymentImporter::class)->import($artifact);

        $lab = Lab::where('slug', 'c-store-live')->firstOrFail();
        $this->assertSame('published', $lab->status);
        $this->assertSame('ct-thorax-60', $lab->dataset);
        $this->assertSame('dicom-basic-tools', $lab->runtime_template);
        $this->assertSame([['type' => 'dicom_instance_received', 'patient_id' => '4711', 'modality' => 'CT', 'min_instances' => 60]], $lab->assertions);

        $activity = Activity::where('type', 'lab')->where('key', 'c-store-live')->firstOrFail();

        $version = ContentVersion::where('activity_id', $activity->id)->where('is_current', true)->firstOrFail();
        $this->assertSame('published', $version->status);
        $this->assertNotNull($version->created_by);
        $this->assertNotNull($version->reviewed_by);

        $element = LessonElement::where('type', 'activity')->where('activity_id', $activity->id)->firstOrFail();
        $this->assertSame($lesson->id, $element->lesson_id);
        $this->assertSame(2, $element->position);
    }

    public function test_second_identical_import_is_idempotent(): void
    {
        $artifact = $this->validArtifact();
        Lesson::factory()->create(['lesson_id' => '2.2']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        app(LabDeploymentImporter::class)->import($artifact);
        app(LabDeploymentImporter::class)->import($artifact);

        $this->assertSame(1, Lab::where('slug', 'c-store-live')->count());
        $this->assertSame(1, Activity::where('type', 'lab')->where('key', 'c-store-live')->count());

        $activity = Activity::where('type', 'lab')->where('key', 'c-store-live')->firstOrFail();
        $this->assertSame(1, ContentVersion::where('activity_id', $activity->id)->count(), 'ein inhaltlich identischer erneuter Import darf keine neue Version erzeugen');
        $this->assertSame(1, LessonElement::where('type', 'activity')->where('activity_id', $activity->id)->count());
    }

    public function test_changed_content_creates_a_new_current_version_without_leaving_the_old_one_current(): void
    {
        $artifact = $this->validArtifact();
        Lesson::factory()->create(['lesson_id' => '2.2']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        app(LabDeploymentImporter::class)->import($artifact);

        $changed = $artifact;
        $changed['lab']['points'] = 25;
        app(LabDeploymentImporter::class)->import($changed);

        $activity = Activity::where('type', 'lab')->where('key', 'c-store-live')->firstOrFail();
        $this->assertSame(2, ContentVersion::where('activity_id', $activity->id)->count());
        $this->assertSame(1, ContentVersion::where('activity_id', $activity->id)->where('is_current', true)->count());
        $this->assertSame(25, Lab::where('slug', 'c-store-live')->value('points'));
    }

    public function test_two_placements_across_two_lessons_are_both_created(): void
    {
        $lessonA = Lesson::factory()->create(['lesson_id' => '2.2']);
        $lessonB = Lesson::factory()->create(['lesson_id' => '2.3']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        $artifact = $this->validArtifact();
        $artifact['placements'] = [
            ['lesson_id' => '2.2', 'position' => 2],
            ['lesson_id' => '2.3', 'position' => 0],
        ];
        app(LabDeploymentImporter::class)->import($artifact);

        $activity = Activity::where('type', 'lab')->where('key', 'c-store-live')->firstOrFail();
        $elements = LessonElement::where('type', 'activity')->where('activity_id', $activity->id)->get()->keyBy('lesson_id');

        $this->assertCount(2, $elements);
        $this->assertSame(2, $elements->get($lessonA->id)->position);
        $this->assertSame(0, $elements->get($lessonB->id)->position);
    }

    public function test_reimporting_two_placements_creates_no_duplicates(): void
    {
        Lesson::factory()->create(['lesson_id' => '2.2']);
        Lesson::factory()->create(['lesson_id' => '2.3']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        $artifact = $this->validArtifact();
        $artifact['placements'] = [
            ['lesson_id' => '2.2', 'position' => 2],
            ['lesson_id' => '2.3', 'position' => 0],
        ];
        app(LabDeploymentImporter::class)->import($artifact);
        app(LabDeploymentImporter::class)->import($artifact);

        $activity = Activity::where('type', 'lab')->where('key', 'c-store-live')->firstOrFail();
        $this->assertSame(2, LessonElement::where('type', 'activity')->where('activity_id', $activity->id)->count());
    }

    /**
     * Entfernen einer von zwei Verknuepfungen darf nur die eigene betroffene
     * Zeile loeschen, die andere bleibt unangetastet.
     */
    public function test_removing_one_of_two_placements_keeps_the_other(): void
    {
        $lessonA = Lesson::factory()->create(['lesson_id' => '2.2']);
        $lessonB = Lesson::factory()->create(['lesson_id' => '2.3']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        $artifact = $this->validArtifact();
        $artifact['placements'] = [
            ['lesson_id' => '2.2', 'position' => 2],
            ['lesson_id' => '2.3', 'position' => 0],
        ];
        app(LabDeploymentImporter::class)->import($artifact);

        $reduced = $artifact;
        $reduced['placements'] = [['lesson_id' => '2.3', 'position' => 0]];
        app(LabDeploymentImporter::class)->import($reduced);

        $activity = Activity::where('type', 'lab')->where('key', 'c-store-live')->firstOrFail();
        $elements = LessonElement::where('type', 'activity')->where('activity_id', $activity->id)->get();

        $this->assertCount(1, $elements);
        $this->assertSame($lessonB->id, $elements->first()->lesson_id);
        $this->assertNull(LessonElement::where('type', 'activity')->where('activity_id', $activity->id)->where('lesson_id', $lessonA->id)->first());
    }

    public function test_moving_a_single_placement_to_a_different_lesson_leaves_no_orphan(): void
    {
        $artifact = $this->validArtifact();
        Lesson::factory()->create(['lesson_id' => '2.2']);
        $newLesson = Lesson::factory()->create(['lesson_id' => '2.3']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        app(LabDeploymentImporter::class)->import($artifact);

        $moved = $artifact;
        $moved['placements'] = [['lesson_id' => '2.3', 'position' => 0]];
        app(LabDeploymentImporter::class)->import($moved);

        $activity = Activity::where('type', 'lab')->where('key', 'c-store-live')->firstOrFail();
        $elements = LessonElement::where('type', 'activity')->where('activity_id', $activity->id)->get();

        $this->assertCount(1, $elements, 'genau eine Zeile -- keine verwaiste alte Verknuepfung');
        $this->assertSame($newLesson->id, $elements->first()->lesson_id);
        $this->assertSame(0, $elements->first()->position);
    }

    public function test_an_empty_placements_list_deletes_the_owned_lesson_element(): void
    {
        $artifact = $this->validArtifact();
        Lesson::factory()->create(['lesson_id' => '2.2']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        app(LabDeploymentImporter::class)->import($artifact);

        $standalone = $artifact;
        $standalone['placements'] = [];
        app(LabDeploymentImporter::class)->import($standalone);

        $activity = Activity::where('type', 'lab')->where('key', 'c-store-live')->firstOrFail();
        $this->assertSame(0, LessonElement::where('type', 'activity')->where('activity_id', $activity->id)->count());
    }

    public function test_export_import_round_trip_preserves_both_placements(): void
    {
        $lessonA = Lesson::factory()->create(['lesson_id' => '2.2']);
        $lessonB = Lesson::factory()->create(['lesson_id' => '2.3']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $lab = Lab::factory()->create([
            'slug' => 'multi-lab',
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'dicom_instance_received']],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
        ]);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'multi-lab']);
        LessonElement::factory()->create(['lesson_id' => $lessonA->id, 'type' => 'activity', 'position' => 1, 'activity_id' => $activity->id]);
        LessonElement::factory()->create(['lesson_id' => $lessonB->id, 'type' => 'activity', 'position' => 4, 'activity_id' => $activity->id]);

        $this->artisan('labs:export', ['slug' => 'multi-lab'])->assertExitCode(0);

        LessonElement::where('activity_id', $activity->id)->delete();
        ContentVersion::where('activity_id', $activity->id)->delete();
        $lab->delete();
        $activity->delete();

        $this->artisan('labs:import', ['slug' => 'multi-lab'])->assertExitCode(0);

        $newActivity = Activity::where('type', 'lab')->where('key', 'multi-lab')->firstOrFail();
        $elements = LessonElement::where('type', 'activity')->where('activity_id', $newActivity->id)->get()->keyBy('lesson_id');

        $this->assertCount(2, $elements);
        $this->assertSame(1, $elements->get($lessonA->id)->position);
        $this->assertSame(4, $elements->get($lessonB->id)->position);
    }

    public function test_an_unknown_dataset_aborts_the_whole_import_without_a_partial_lab(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $artifact = $this->validArtifact();
        $artifact['lab']['dataset'] = 'does-not-exist';

        $this->expectException(RuntimeException::class);

        try {
            app(LabDeploymentImporter::class)->import($artifact);
        } finally {
            $this->assertSame(0, Lab::where('slug', 'c-store-live')->count());
            $this->assertSame(0, Activity::where('type', 'lab')->where('key', 'c-store-live')->count());
        }
    }

    public function test_an_unpublished_runtime_template_aborts_the_whole_import(): void
    {
        SandboxTemplate::factory()->create(['slug' => 'dicom-basic-tools', 'status' => 'draft']);
        $artifact = $this->validArtifact();

        $this->expectException(RuntimeException::class);

        try {
            app(LabDeploymentImporter::class)->import($artifact);
        } finally {
            $this->assertSame(0, Lab::where('slug', 'c-store-live')->count());
        }
    }

    public function test_an_invalid_assertion_aborts_the_whole_import(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $artifact = $this->validArtifact();
        $artifact['lab']['assertions'] = [];

        $this->expectException(RuntimeException::class);

        try {
            app(LabDeploymentImporter::class)->import($artifact);
        } finally {
            $this->assertSame(0, Lab::where('slug', 'c-store-live')->count());
        }
    }

    /**
     * Betreiber-Vorgabe: Content Deployment und Lernfortschritt strikt
     * getrennt -- ein erneuter Import eines bestehenden, bereits genutzten
     * Labs darf `lab_attempts`/`sandbox_sessions`/`activity_progress` nicht
     * anfassen.
     */
    public function test_reimporting_never_touches_existing_user_progress_or_runtime_data(): void
    {
        $artifact = $this->validArtifact();
        Lesson::factory()->create(['lesson_id' => '2.2']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        app(LabDeploymentImporter::class)->import($artifact);

        $activity = Activity::where('type', 'lab')->where('key', 'c-store-live')->firstOrFail();
        $user = User::factory()->create();

        $attempt = LabAttempt::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'status' => 'solved',
            'assertions_passed' => ['dicom_instance_received:x'],
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
        $session = SandboxSession::factory()->create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'lab_attempt_id' => $attempt->id,
        ]);
        $progress = ActivityProgress::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'completed' => true,
            'score' => 20,
            'max_score' => 20,
        ]);

        $changed = $artifact;
        $changed['lab']['points'] = 30;
        app(LabDeploymentImporter::class)->import($changed);

        $attempt->refresh();
        $session->refresh();
        $progress->refresh();

        $this->assertSame('solved', $attempt->status);
        $this->assertSame(['dicom_instance_received:x'], $attempt->assertions_passed);
        $this->assertSame($session->id, SandboxSession::where('lab_attempt_id', $attempt->id)->value('id'));
        $this->assertSame(20, $progress->score);
        $this->assertSame(1, LabAttempt::where('user_id', $user->id)->count());
        $this->assertSame(1, ActivityProgress::where('user_id', $user->id)->count());
    }

    public function test_labs_import_command_reports_failure_but_continues_with_other_artifacts(): void
    {
        Lesson::factory()->create(['lesson_id' => '2.2']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        $broken = $this->validArtifact();
        $broken['slug'] = 'broken-lab';
        $broken['lab']['dataset'] = 'does-not-exist';
        File::ensureDirectoryExists($this->deployPath);
        File::put("{$this->deployPath}/broken-lab.json", json_encode($broken));
        File::put("{$this->deployPath}/c-store-live.json", json_encode($this->validArtifact()));

        $this->artisan('labs:import')->assertExitCode(1);

        $this->assertSame(0, Lab::where('slug', 'broken-lab')->count());
        $this->assertSame(1, Lab::where('slug', 'c-store-live')->count());
    }

    /**
     * Ein strukturell kaputtes Artefakt (Top-Level, nicht nur ein
     * ungueltiger Fachwert) neben einem gueltigen: das gueltige wird
     * trotzdem importiert, das kaputte bricht nur sich selbst ab, der
     * Command-Exitcode bleibt FAILURE.
     */
    public function test_batch_import_with_one_structurally_broken_artifact_still_imports_the_valid_one(): void
    {
        Lesson::factory()->create(['lesson_id' => '2.2']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        $malformed = $this->validArtifact();
        $malformed['slug'] = 'malformed-lab';
        unset($malformed['lab']);
        File::ensureDirectoryExists($this->deployPath);
        File::put("{$this->deployPath}/malformed-lab.json", json_encode($malformed));
        File::put("{$this->deployPath}/c-store-live.json", json_encode($this->validArtifact()));

        $this->artisan('labs:import')->assertExitCode(1);

        $this->assertSame(0, Activity::where('type', 'lab')->where('key', 'malformed-lab')->count());
        $this->assertSame(1, Lab::where('slug', 'c-store-live')->count());
    }

    public function test_labs_import_with_an_explicit_slug_rejects_a_mismatched_artifact_slug(): void
    {
        Lesson::factory()->create(['lesson_id' => '2.2']);
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        $artifact = $this->validArtifact();
        File::ensureDirectoryExists($this->deployPath);
        // Datei heisst "other-name.json", deklariert intern aber "c-store-live".
        File::put("{$this->deployPath}/other-name.json", json_encode($artifact));

        $this->artisan('labs:import', ['slug' => 'other-name'])->assertExitCode(1);

        $this->assertSame(0, Lab::where('slug', 'c-store-live')->count());
    }

    /**
     * @return array<string, array{0: callable(array<string, mixed>): array<string, mixed>}>
     */
    public static function invalidArtifactStructureProvider(): array
    {
        $without = fn (string $key): callable => function (array $a) use ($key): array {
            unset($a[$key]);

            return $a;
        };

        return [
            'schema_version fehlt' => [$without('schema_version')],
            'schema_version = 2' => [fn (array $a): array => [...$a, 'schema_version' => 2]],
            'slug fehlt' => [$without('slug')],
            'slug ungueltig' => [fn (array $a): array => [...$a, 'slug' => 'Not A Slug!']],
            'lab fehlt' => [$without('lab')],
            'lab falscher Typ' => [fn (array $a): array => [...$a, 'lab' => 'not-an-object']],
            'placements fehlt' => [$without('placements')],
            'placements kein Array' => [fn (array $a): array => [...$a, 'placements' => 'not-a-list']],
            'placements-Eintrag ohne lesson_id' => [fn (array $a): array => [...$a, 'placements' => [['position' => 0]]]],
            'placements-Eintrag mit falschem position-Typ' => [fn (array $a): array => [...$a, 'placements' => [['lesson_id' => '2.2', 'position' => '0']]]],
        ];
    }

    #[DataProvider('invalidArtifactStructureProvider')]
    public function test_structurally_invalid_artifacts_are_rejected_before_any_write(callable $mutate): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $artifact = $mutate($this->validArtifact());

        $this->expectException(RuntimeException::class);

        try {
            app(LabDeploymentImporter::class)->import($artifact);
        } finally {
            $this->assertSame(0, Lab::where('slug', 'c-store-live')->count());
            $this->assertSame(0, Activity::where('type', 'lab')->where('key', 'c-store-live')->count());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validArtifact(): array
    {
        return [
            'schema_version' => 1,
            'slug' => 'c-store-live',
            'lab' => [
                'title' => 'C-STORE: CT-Studie an das PACS uebertragen',
                'scenario_title' => 'Vollstaendige Studie ans Testarchiv senden',
                'difficulty' => 'medium',
                'points' => 20,
                'estimated_minutes' => 15,
                'runtime_template' => 'dicom-basic-tools',
                'dataset' => 'ct-thorax-60',
                'assertions' => [['type' => 'dicom_instance_received', 'patient_id' => '4711', 'modality' => 'CT', 'min_instances' => 60]],
                'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
            ],
            'placements' => [['lesson_id' => '2.2', 'position' => 2]],
        ];
    }
}

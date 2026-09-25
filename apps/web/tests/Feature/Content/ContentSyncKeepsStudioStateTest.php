<?php

namespace Tests\Feature\Content;

use App\Activities\ActivityRegistry;
use App\Content\ContentPublishingService;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ADR 0122. Phase 0 hat mit diesem Test belegt, dass `content:sync` einen in
 * Studio veroeffentlichten Stand still mit `content/` ueberschrieb (damals
 * als Charakterisierungstest mit umgekehrten Assertions). Phase 3 hat die
 * Schutzregel eingebaut -- jetzt bleibt der Studio-Stand erhalten:
 *
 *   Regel A: veroeffentlichte Version -> Publisher-Felder bleiben
 *   Regel B: Track-Einstellungen, Node-Themenfeld nur beim Anlegen
 *   --force-from-files: bewusste Ruecksetzung, fragt nach
 *
 * Laeuft auf einer Kopie des echten `content/`-Bestands und ausschliesslich
 * in der Test-DB (SQLite `:memory:`, ADR 0069).
 */
class ContentSyncKeepsStudioStateTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/content-sync-'.bin2hex(random_bytes(4)));
        File::copyDirectory(base_path('../../content'), $this->dir);
        $this->app->instance(ContentRepository::class, new ContentRepository($this->dir));
        $this->assertSame(0, Artisan::call('content:sync'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publishViaStudio(Activity $activity, array $payload): ContentVersion
    {
        $versions = app(ContentVersioningService::class);
        $draft = $versions->createDraft($activity, $payload, User::factory()->author()->create());
        $versions->submitForReview($draft);

        $issues = app(ContentPublishingService::class)->publish($draft->fresh(), User::factory()->reviewer()->create());
        $this->assertSame([], $issues, 'Studio-Publish muss gueltig sein, sonst beweist der Test nichts.');

        return $draft->fresh();
    }

    private function editFile(string $relative, string $search, string $replace): void
    {
        $path = "{$this->dir}/{$relative}";
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString($search, $contents);
        file_put_contents($path, str_replace($search, $replace, $contents));
    }

    private function publishLesson15(): ContentVersion
    {
        $activity = Activity::query()->where('type', 'lesson')->where('key', '1.5')->firstOrFail();
        $payload = app(ActivityRegistry::class)->resolve($activity)->deserialize();
        $payload['title'] = 'Studio-Titel';
        $payload['teaser'] = 'Studio-Teaser';
        $payload['level'] = 'fortgeschritten';
        $payload['duration_minutes'] = 42;
        $payload['tools'] = [...$payload['tools'], 'dcmdump'];
        $payload['objectives'] = ['Studio-Lernziel A', 'Studio-Lernziel B'];

        return $this->publishViaStudio($activity, $payload);
    }

    public function test_sync_keeps_studio_published_lesson_metadata(): void
    {
        $fileLesson = Lesson::where('lesson_id', '1.5')->firstOrFail();
        $version = $this->publishLesson15();
        $published = Lesson::where('lesson_id', '1.5')->firstOrFail();

        // Lesson.status ist NICHT Teil des Studio-Payloads (Phase 0, Punkt 2).
        $this->assertSame($fileLesson->status, $published->status);

        Artisan::call('content:sync');
        $output = Artisan::output();

        $afterSync = Lesson::where('lesson_id', '1.5')->firstOrFail();
        foreach (['title', 'teaser', 'level', 'duration_minutes', 'tools', 'objectives', 'objectives_count', 'rich_content'] as $field) {
            $this->assertSame($published->{$field}, $afterSync->{$field}, $field);
        }
        $this->assertSame(['de' => 'Studio-Titel'], Activity::query()->where('type', 'lesson')->where('key', '1.5')->value('title'));

        // Die Warnung nennt Version und genau die abweichenden Felder.
        $this->assertStringContainsString("Lektion 1.5: in Studio veroeffentlicht (ContentVersion #{$version->id})", $output);
        $this->assertStringContainsString('title, teaser, level, duration_minutes, tools, objectives, objectives_count nicht uebernommen', $output);
    }

    public function test_non_studio_lesson_fields_still_come_from_the_file(): void
    {
        $this->publishLesson15();
        $this->editFile('lessons/1.5/meta.yml', 'tools_checked: "2026-09-12"', 'tools_checked: "2026-09-20"');

        Artisan::call('content:sync');

        $this->assertSame('2026-09-20', Lesson::where('lesson_id', '1.5')->firstOrFail()->tools_checked->toDateString());
    }

    public function test_no_warning_when_the_file_already_matches_the_studio_state(): void
    {
        // Studio-Payloads tragen `sandbox.note: null`, die Datei nicht --
        // das darf keine Warnung ausloesen (Lehre aus PR #162).
        $activity = Activity::query()->where('type', 'lesson')->where('key', '1.5')->firstOrFail();
        $this->publishViaStudio($activity, [
            ...app(ActivityRegistry::class)->resolve($activity)->deserialize(),
            'objectives' => Lesson::where('lesson_id', '1.5')->firstOrFail()->objectives,
        ]);

        Artisan::call('content:sync');

        $this->assertStringNotContainsString('Lektion 1.5:', Artisan::output());
    }

    public function test_sync_keeps_a_studio_published_quiz(): void
    {
        $fileLesson = Lesson::where('lesson_id', '1.1')->firstOrFail();
        $this->assertCount(3, $fileLesson->quiz);
        $activity = Activity::query()->where('type', 'lesson')->where('key', '1.1')->firstOrFail();

        $this->publishViaStudio($activity, ['quiz' => [[
            'id' => 'q1', 'type' => 'single', 'answer' => 0,
            'question' => 'Studio-Frage?', 'options' => ['Studio-Antwort', 'Falsch'],
        ]]]);

        Artisan::call('content:sync');
        $output = Artisan::output();

        $afterSync = Lesson::where('lesson_id', '1.1')->firstOrFail();
        $this->assertCount(1, $afterSync->quiz);
        $this->assertStringContainsString('Studio-Frage?', (string) $afterSync->body);
        $this->assertStringContainsString('Lektion 1.1 (Quiz)', $output);
        // Nur der Quiz-Schutz greift -- die Lektionsfelder derselben Lektion
        // haben keine eigene Version und bleiben datei-gefuehrt.
        $this->assertStringNotContainsString('Lektion 1.1: in Studio', $output);
    }

    public function test_sync_keeps_a_studio_published_node_including_its_status(): void
    {
        // `gefiltert` steht in node.yml auf `status: draft`.
        $activity = Activity::query()->where('type', 'node')->where('key', 'gefiltert')->firstOrFail();
        $fileNode = Node::where('slug', 'gefiltert')->firstOrFail();
        $this->assertSame('draft', $fileNode->status);

        $payload = app(ActivityRegistry::class)->resolve($activity)->deserialize();
        $payload['title'] = 'Studio-Node-Titel';
        $payload['points'] = $fileNode->points + 5;
        $this->publishViaStudio($activity, $payload);

        Artisan::call('content:sync');

        $afterSync = Node::where('slug', 'gefiltert')->firstOrFail();
        $this->assertSame('published', $afterSync->status);
        $this->assertSame('Studio-Node-Titel', $afterSync->title['de']);
        $this->assertSame($fileNode->points + 5, $afterSync->points);
        $this->assertNotNull($afterSync->rich_content);
        $this->assertSame(['de' => 'Studio-Node-Titel'], Activity::query()->where('type', 'node')->where('key', 'gefiltert')->value('title'));
    }

    public function test_an_archived_node_without_published_version_is_still_file_driven(): void
    {
        // Bewusst unveraendert (ADR 0122, offene Betreiberfrage 1): ohne
        // veroeffentlichte Version bleibt Node.status datei-gefuehrt.
        Node::where('slug', 'silent-ct')->firstOrFail()->update(['status' => 'archived']);

        Artisan::call('content:sync');

        $this->assertSame('published', Node::where('slug', 'silent-ct')->value('status'));
    }

    public function test_sync_keeps_studio_track_settings_and_node_themenfeld(): void
    {
        $track = Track::where('slug', 'fundamente')->firstOrFail();
        $track->update(['level' => 'fortgeschritten', 'hours' => 99, 'order' => 42, 'status' => 'draft']);
        $node = Node::where('slug', 'silent-ct')->firstOrFail();
        $otherThemenfeld = Track::where('slug', 'hl7')->value('themenfeld_id');
        $node->update(['themenfeld_id' => $otherThemenfeld]);

        Artisan::call('content:sync');
        $output = Artisan::output();

        $this->assertSame(
            ['level' => 'fortgeschritten', 'hours' => 99, 'order' => 42, 'status' => 'draft'],
            Track::where('slug', 'fundamente')->firstOrFail()->only(['level', 'hours', 'order', 'status']),
        );
        $this->assertSame($otherThemenfeld, Node::where('slug', 'silent-ct')->value('themenfeld_id'));
        $this->assertStringContainsString('Track fundamente: Einstellungen werden in Studio gepflegt', $output);
    }

    public function test_sandbox_and_quiz_activities_keep_a_studio_move(): void
    {
        $services = Track::where('slug', 'services')->firstOrFail();
        foreach (['lesson', 'quiz'] as $type) {
            Activity::query()->where('type', $type)->where('key', '1.1')->update(['track_id' => $services->id, 'order' => 99]);
        }

        Artisan::call('content:sync');

        $this->assertSame($services->id, Activity::query()->where('type', 'quiz')->where('key', '1.1')->value('track_id'));
        $this->assertSame(99, Activity::query()->where('type', 'quiz')->where('key', '1.1')->value('order'));
    }

    public function test_a_database_without_content_versions_syncs_file_changes_as_before(): void
    {
        $this->assertSame(0, ContentVersion::query()->count());
        $this->editFile('lessons/1.5/de.md', 'title: SCU und SCP — Rollen, nicht Geräte', 'title: Aus der Datei geändert');
        $this->editFile('nodes/gefiltert/node.yml', 'status: draft', 'status: published');

        Artisan::call('content:sync');

        $this->assertSame('Aus der Datei geändert', Lesson::where('lesson_id', '1.5')->value('title')['de']);
        $this->assertSame('published', Node::where('slug', 'gefiltert')->value('status'));
        $this->assertStringNotContainsString('nicht uebernommen', Artisan::output());
    }

    public function test_force_from_files_overwrites_the_studio_state_after_confirmation(): void
    {
        $fileLesson = Lesson::where('lesson_id', '1.5')->firstOrFail();
        $this->publishLesson15();
        Track::where('slug', 'fundamente')->firstOrFail()->update(['hours' => 99]);

        $this->artisan('content:sync', ['--force-from-files' => true])
            ->expectsConfirmation('content/ ueberschreibt damit jeden in Studio veroeffentlichten Stand (Metadaten, Quiz, Node-Felder, Track-Einstellungen). Wirklich fortfahren?', 'yes')
            ->assertSuccessful();

        $afterSync = Lesson::where('lesson_id', '1.5')->firstOrFail();
        $this->assertSame($fileLesson->title, $afterSync->title);
        $this->assertSame($fileLesson->objectives, $afterSync->objectives);
        $this->assertSame(7, Track::where('slug', 'fundamente')->value('hours'));
    }

    public function test_force_from_files_changes_nothing_when_declined(): void
    {
        $this->publishLesson15();

        $this->artisan('content:sync', ['--force-from-files' => true])
            ->expectsConfirmation('content/ ueberschreibt damit jeden in Studio veroeffentlichten Stand (Metadaten, Quiz, Node-Felder, Track-Einstellungen). Wirklich fortfahren?', 'no')
            ->assertFailed();

        $this->assertSame('Studio-Titel', Lesson::where('lesson_id', '1.5')->value('title')['de']);
    }

    public function test_force_from_files_refuses_without_interaction(): void
    {
        $this->publishLesson15();

        $this->assertSame(1, Artisan::call('content:sync', ['--force-from-files' => true, '--no-interaction' => true]));
        $this->assertSame('Studio-Titel', Lesson::where('lesson_id', '1.5')->value('title')['de']);
    }
}

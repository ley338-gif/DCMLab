<?php

namespace Tests\Feature\Content;

use App\Activities\ActivityRegistry;
use App\Content\ContentPublishingService;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Content\FrontMatter;
use App\Content\QuizContent;
use App\Content\RichContent\MarkdownToRichContentConverter;
use App\Content\RichContent\RichContentToMarkdownSerializer;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * ADR 0122, Phase 2: `content:export` schreibt den veroeffentlichten
 * DB-Stand nach content/**. Arbeitet auf einer Kopie des echten Bestands
 * (nie auf content/ selbst) und ausschliesslich in der Test-DB
 * (SQLite `:memory:`, ADR 0069).
 */
class ContentExportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/content-export-'.bin2hex(random_bytes(4)));
        File::copyDirectory(base_path('../../content'), $this->dir);
        $this->useContent($this->dir);
        $this->assertSame(0, Artisan::call('content:sync'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function useContent(string $dir): void
    {
        $this->app->instance(ContentRepository::class, new ContentRepository($dir));
    }

    private function export(array $options = []): int
    {
        return Artisan::call('content:export', ['--path' => $this->dir, ...$options]);
    }

    private function file(string $relative): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents("{$this->dir}/{$relative}"));
    }

    /**
     * @return array<string, string> relativer Pfad => Inhalt, fuer Byte-Vergleiche
     */
    private function snapshot(): array
    {
        $files = [];

        foreach (File::allFiles($this->dir) as $file) {
            $files[str_replace('\\', '/', $file->getRelativePathname())] = (string) file_get_contents($file->getPathname());
        }

        ksort($files);

        return $files;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publishViaStudio(Activity $activity, array $payload): void
    {
        $versions = app(ContentVersioningService::class);
        $draft = $versions->createDraft($activity, $payload, User::factory()->author()->create());
        $versions->submitForReview($draft);

        $this->assertSame([], app(ContentPublishingService::class)->publish($draft->fresh(), User::factory()->reviewer()->create()));
    }

    /**
     * Wie der Lesson-Editor: deserialize() liefert objectives nicht mit
     * (LessonEditorController ergaenzt sie aus der Spalte).
     *
     * @return array<string, mixed>
     */
    private function lessonPayload(string $id): array
    {
        return [
            ...app(ActivityRegistry::class)->resolve($this->lessonActivity($id))->deserialize(),
            'objectives' => Lesson::where('lesson_id', $id)->firstOrFail()->objectives,
        ];
    }

    private function lessonActivity(string $id): Activity
    {
        return Activity::query()->where('type', 'lesson')->where('key', $id)->firstOrFail();
    }

    public function test_a_freshly_synced_database_matches_content_exactly(): void
    {
        $exit = $this->export(['--check' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('entspricht dem veroeffentlichten DB-Stand', $output);
    }

    public function test_studio_published_lesson_metadata_is_exported_and_survives_a_resync(): void
    {
        $activity = $this->lessonActivity('1.5');
        $payload = app(ActivityRegistry::class)->resolve($activity)->deserialize();
        $payload['title'] = 'Studio-Titel: mit Doppelpunkt';
        $payload['objectives'] = ['Studio-Lernziel A', 'Studio-Lernziel B'];
        $payload['duration_minutes'] = 42;
        $payload['tools'] = [...$payload['tools'], 'dcmdump'];
        $this->publishViaStudio($activity, $payload);

        $metaBefore = $this->file('lessons/1.5/meta.yml');

        $this->assertSame(1, $this->export(['--check' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('lessons/1.5/meta.yml', $output);
        $this->assertStringContainsString('lessons/1.5/de.md  [title, objectives]', $output);
        $this->assertSame($metaBefore, $this->file('lessons/1.5/meta.yml'), '--check darf nichts schreiben');

        $this->assertSame(0, $this->export());

        $meta = Yaml::parse($this->file('lessons/1.5/meta.yml'));
        $frontMatter = FrontMatter::parse($this->file('lessons/1.5/de.md'))['attributes'];
        $this->assertSame(42, $meta['duration_minutes']);
        $this->assertSame(2, $meta['objectives_count']);
        $this->assertContains('dcmdump', $meta['tools']);
        $this->assertSame('Studio-Titel: mit Doppelpunkt', $frontMatter['title']);
        $this->assertSame(['Studio-Lernziel A', 'Studio-Lernziel B'], $frontMatter['objectives']);
        // Chirurgisch: nicht betroffene Zeilen bleiben stehen.
        $this->assertStringContainsString('tools_checked: "2026-09-12"', $this->file('lessons/1.5/meta.yml'));

        // Deterministisch/idempotent: ein zweiter Lauf findet nichts mehr
        // und aendert kein Byte.
        $snapshot = $this->snapshot();
        $this->assertSame(0, $this->export(['--check' => true]), Artisan::output());
        $this->assertSame(0, $this->export());
        $this->assertSame($snapshot, $this->snapshot());

        // Export -> content:sync -> Export: der Studio-Stand kommt ueber die
        // Dateien unveraendert zurueck, ein erneuter Export ist leer.
        Artisan::call('content:sync');
        $this->assertSame('Studio-Titel: mit Doppelpunkt', Lesson::where('lesson_id', '1.5')->value('title')['de']);
        $this->assertSame(['Studio-Lernziel A', 'Studio-Lernziel B'], Lesson::where('lesson_id', '1.5')->firstOrFail()->objectives);
        $this->assertSame(0, $this->export(['--check' => true]), Artisan::output());
    }

    public function test_the_exported_files_rebuild_the_same_state_in_a_fresh_database(): void
    {
        $activity = $this->lessonActivity('1.5');
        $payload = $this->lessonPayload('1.5');
        $payload['teaser'] = 'Studio-Teaser';
        $this->publishViaStudio($activity, $payload);
        $this->assertSame(0, $this->export());
        $exported = $this->snapshot();

        // Frische DB aus den exportierten Dateien (migrate:fresh geht in der
        // Test-Transaktion der :memory:-DB nicht -- dieselbe Wirkung fuer
        // alles, was content:sync befuellt).
        Schema::withoutForeignKeyConstraints(function (): void {
            foreach (['lesson_elements', 'content_versions', 'activities', 'lessons', 'nodes', 'tracks', 'themenfelder'] as $table) {
                DB::table($table)->delete();
            }
        });
        $this->assertSame(0, Artisan::call('content:sync'));

        $this->assertSame('Studio-Teaser', Lesson::where('lesson_id', '1.5')->value('teaser')['de']);
        $this->assertSame(0, $this->export(['--check' => true]), Artisan::output());
        $this->assertSame(0, $this->export());
        $this->assertSame($exported, $this->snapshot());
    }

    public function test_a_studio_published_quiz_is_exported_to_meta_and_body(): void
    {
        $this->publishViaStudio($this->lessonActivity('1.1'), ['quiz' => [[
            'id' => 'q1', 'type' => 'single', 'answer' => 1,
            'question' => 'Studio-Frage?', 'options' => ['Falsch', 'Richtig'],
        ]]]);

        $this->assertSame(0, $this->export(['--only' => 'lessons', '--id' => '1.1']));

        $meta = Yaml::parse($this->file('lessons/1.1/meta.yml'));
        $this->assertSame([['id' => 'q1', 'type' => 'single', 'answer' => 1]], $meta['quiz']);

        $split = QuizContent::splitBody(FrontMatter::parse($this->file('lessons/1.1/de.md'))['body']);
        $this->assertStringContainsString('Studio-Frage?', $split['quiz_raw']);
        // Prosa vor und Fusstext nach dem Quiz bleiben, wo sie waren.
        $this->assertStringContainsString('## Selbstcheck', $split['before']);
        $this->assertStringContainsString('**Als Nächstes:**', $split['after']);

        $this->assertSame(0, $this->export(['--check' => true]), Artisan::output());
    }

    public function test_changed_rich_content_prose_is_serialized_and_reads_back_equal(): void
    {
        $lesson = Lesson::where('lesson_id', '1.1')->firstOrFail();
        $activity = $this->lessonActivity('1.1');
        $payload = $this->lessonPayload('1.1');
        $payload['rich_content']['content'][] = ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Neuer Absatz aus Studio mit '],
            ['type' => 'glossary_term', 'attrs' => ['slug' => 'dicom']],
            ['type' => 'text', 'text' => '.'],
        ]];
        $this->publishViaStudio($activity, $payload);
        $published = Lesson::where('lesson_id', '1.1')->firstOrFail()->rich_content;

        $this->assertSame(0, $this->export(['--id' => '1.1']));

        $split = QuizContent::splitBody(FrontMatter::parse($this->file('lessons/1.1/de.md'))['body']);
        $prose = trim($split['before']."\n\n".$split['after']);
        $this->assertTrue((new RichContentToMarkdownSerializer)->equivalent($published, (new MarkdownToRichContentConverter)->convert($prose)));
        // Der Quiz-Abschnitt aus lessons.body bleibt erhalten.
        $this->assertSame(trim(QuizContent::splitBody((string) $lesson->body)['quiz_raw']), trim($split['quiz_raw']));
        $this->assertSame(0, $this->export(['--check' => true]), Artisan::output());
    }

    public function test_a_studio_published_node_is_exported_including_status_and_hints(): void
    {
        $activity = Activity::query()->where('type', 'node')->where('key', 'gefiltert')->firstOrFail();
        $payload = app(ActivityRegistry::class)->resolve($activity)->deserialize();
        $payload['title'] = 'Studio-Node';
        $payload['points'] += 5;
        $this->publishViaStudio($activity, $payload);

        $defBefore = $this->file('nodes/gefiltert/node.yml');
        $this->assertSame(0, $this->export(['--only' => 'nodes']));

        $def = Yaml::parse($this->file('nodes/gefiltert/node.yml'));
        $this->assertSame('published', $def['status']);
        $this->assertSame(Node::where('slug', 'gefiltert')->value('points'), $def['points']);
        $this->assertSame('Studio-Node', FrontMatter::parse($this->file('nodes/gefiltert/de.md'))['attributes']['title']);
        // environment/flag gehoeren nicht zum DB-Stand und bleiben unberuehrt.
        $this->assertSame(Yaml::parse($defBefore)['environment'], $def['environment']);
        $this->assertSame(Yaml::parse($defBefore)['flag'] ?? null, $def['flag'] ?? null);

        $this->assertSame(0, $this->export(['--check' => true]), Artisan::output());
    }

    public function test_studio_track_settings_are_exported_to_tracks_yml(): void
    {
        Track::where('slug', 'fundamente')->firstOrFail()->update(['hours' => 99, 'status' => 'draft']);

        $this->assertSame(0, $this->export(['--only' => 'tracks']));

        $track = collect(Yaml::parse($this->file('tracks.yml')))->firstWhere('slug', 'fundamente');
        $this->assertSame(99, $track['hours']);
        $this->assertSame('draft', $track['status']);
        $this->assertSame('track.fundamente.title', $track['title_key']);
        $this->assertStringStartsWith('# Track-Definitionen', $this->file('tracks.yml'));
        $this->assertSame(0, $this->export(['--check' => true]), Artisan::output());
    }

    public function test_draft_and_review_versions_are_never_exported(): void
    {
        $activity = $this->lessonActivity('1.5');
        $payload = $this->lessonPayload('1.5');
        $versions = app(ContentVersioningService::class);

        $versions->createDraft($activity, [...$payload, 'title' => 'Nur Entwurf'], User::factory()->author()->create());
        $review = $versions->createDraft($this->lessonActivity('1.1'), ['quiz' => []], User::factory()->author()->create());
        $versions->submitForReview($review);

        $this->assertSame(2, ContentVersion::query()->whereIn('status', ['draft', 'review'])->count());
        $this->assertSame(0, $this->export(['--check' => true]), Artisan::output());
    }

    public function test_check_detects_a_manually_edited_file(): void
    {
        $path = "{$this->dir}/lessons/1.5/de.md";
        file_put_contents($path, str_replace('Called und Calling AE Title korrekt zuordnen', 'Von Hand geaendert', (string) file_get_contents($path)));

        $this->assertSame(1, $this->export(['--check' => true]));
        $this->assertStringContainsString('lessons/1.5/de.md  [objectives]', Artisan::output());
    }

    public function test_unrepresentable_rich_content_is_reported_and_its_file_left_alone(): void
    {
        $lesson = Lesson::where('lesson_id', '1.5')->firstOrFail();
        $lesson->update(['rich_content' => ['type' => 'doc', 'version' => 1, 'content' => [
            ['type' => 'callout', 'attrs' => ['kind' => 'info'], 'content' => []],
        ]]]);
        $before = $this->file('lessons/1.5/de.md');

        $this->assertSame(1, $this->export(['--id' => '1.5']));
        $output = Artisan::output();
        $this->assertStringContainsString('Lektion 1.5', $output);
        $this->assertStringContainsString('callout', $output);
        $this->assertSame($before, $this->file('lessons/1.5/de.md'));
    }

    public function test_only_accepts_exportable_scopes(): void
    {
        $this->assertSame(1, $this->export(['--only' => 'lessons,exams']));
        $this->assertStringContainsString('Nicht exportierbar: exams', Artisan::output());
    }
}

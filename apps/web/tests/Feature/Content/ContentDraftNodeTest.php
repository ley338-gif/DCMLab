<?php

namespace Tests\Feature\Content;

use App\Activities\ActivityRegistry;
use App\Content\ContentExporter;
use App\Content\ContentPublishingService;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Content\NodeDraftImporter;
use App\Content\RichContent\NodePayloadNormalizer;
use App\Content\RichContent\RichContentToMarkdownSerializer;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Node;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * `content:draft-node`: das Node-Gegenstueck zu `content:draft`. Legt aus
 * einer Datei im Format von `content:export` einen Studio-Entwurf an -- nie
 * eingereicht, nie veroeffentlicht. Liest den echten content/-Bestand nur und
 * laeuft ausschliesslich in der Test-DB (SQLite `:memory:`, ADR 0069).
 */
class ContentDraftNodeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Die Felder, die der Studio-Node-Editor sendet (StudioNodeController::validatedFields()).
     */
    private const STUDIO_FIELDS = ['title', 'scenario_title', 'difficulty', 'points', 'category', 'interaction', 'estimated_minutes', 'skills', 'related_lessons', 'hints', 'rich_content'];

    private string $dir;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(ContentRepository::class, new ContentRepository(base_path('../../content')));
        $this->assertSame(0, Artisan::call('content:sync'));

        $this->dir = storage_path('framework/testing/content-draft-node-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->dir);
        $this->author = User::factory()->reviewer()->create();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    /**
     * Eine Datei der Node aus content/, optional abgewandelt.
     */
    private function nodeFile(string $slug, string $name = 'de.md', array $replace = []): string
    {
        $contents = str_replace("\r\n", "\n", (string) file_get_contents(base_path("../../content/nodes/{$slug}/{$name}")));

        foreach ($replace as $search => $new) {
            $this->assertStringContainsString($search, $contents);
            $contents = str_replace($search, $new, $contents);
        }

        return $this->write("{$slug}-{$name}", $contents);
    }

    private function write(string $name, string $contents): string
    {
        $path = "{$this->dir}/{$name}";
        file_put_contents($path, $contents);

        return $path;
    }

    private function draft(string $slug, string $file, array $options = []): int
    {
        return Artisan::call('content:draft-node', [
            'node' => $slug,
            '--from' => $file,
            '--author' => (string) $this->author->id,
            '--no-interaction' => true,
            ...$options,
        ]);
    }

    private function activity(string $slug): Activity
    {
        return Activity::query()->where('type', 'node')->where('key', $slug)->firstOrFail();
    }

    private function briefingChanged(string $text = 'Neues Ticket aus dem PACS-Betrieb:'): string
    {
        return $this->nodeFile('gefiltert', replace: ['Ticket aus dem PACS-Betrieb:' => $text]);
    }

    public function test_changed_prose_becomes_a_draft_shaped_like_the_studio_payload(): void
    {
        $live = Node::where('slug', 'gefiltert')->firstOrFail();

        $this->assertSame(0, $this->draft('gefiltert', $this->briefingChanged()), Artisan::output());

        $version = ContentVersion::query()->where('activity_id', $this->activity('gefiltert')->id)->sole();
        $this->assertSame('draft', $version->status);
        $this->assertFalse($version->is_current);
        $this->assertSame($this->author->id, $version->created_by);

        // Dieselben Felder wie im Editor -- kein environment/flag, kein status.
        $payload = $version->payload;
        $this->assertSame(self::STUDIO_FIELDS, array_keys($payload));
        $this->assertSame('node_content', $payload['rich_content']['type']);
        $this->assertSame(['h1', 'h2', 'h3'], array_keys($payload['rich_content']['hints']));

        // Was die Datei nicht setzt, kommt aus dem Live-Stand.
        $this->assertSame($live->title['de'], $payload['title']);
        $this->assertSame($live->points, $payload['points']);
        $this->assertSame($live->related_lessons, $payload['related_lessons']);
        $this->assertSame($live->hints, $payload['hints']);

        // Der Entwurf liest sich als genau dieser Text zurueck.
        $markdown = (new RichContentToMarkdownSerializer)->serializeNodeContent($payload['rich_content'], ['h1', 'h2', 'h3']);
        $this->assertStringContainsString('Neues Ticket aus dem PACS-Betrieb:', $markdown);

        // Nichts ist live gegangen.
        $fresh = Node::where('slug', 'gefiltert')->firstOrFail();
        $this->assertNull($fresh->rich_content);
        $this->assertSame($live->body, $fresh->body);
    }

    public function test_an_unchanged_file_creates_no_version(): void
    {
        $file = $this->nodeFile('gefiltert');
        $meta = $this->nodeFile('gefiltert', 'node.yml');

        $this->assertSame(0, $this->draft('gefiltert', $file, ['--meta' => $meta]));

        $this->assertStringContainsString('Keine Aenderung', Artisan::output());
        $this->assertSame(0, ContentVersion::query()->count());
    }

    public function test_exporting_the_live_state_and_importing_it_changes_nothing(): void
    {
        // Ein in Studio veroeffentlichter Stand: rich_content gesetzt,
        // Metadaten weichen von content/ ab.
        $activity = $this->activity('gefiltert');
        $payload = app(ActivityRegistry::class)->resolve($activity)->deserialize();
        unset($payload['slug']);
        $payload['points'] += 5;
        $payload['hints'][0]['cost'] = 3;
        $payload['rich_content']['briefing']['content'][] = ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Neu aus Studio: '],
            ['type' => 'text', 'text' => 'fett', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' und '],
            ['type' => 'glossary_term', 'attrs' => ['slug' => 'dicom']],
            ['type' => 'text', 'text' => '.'],
        ]];
        $versions = app(ContentVersioningService::class);
        $published = $versions->createDraft($activity, $payload, User::factory()->author()->create());
        $versions->submitForReview($published);
        $this->assertSame([], app(ContentPublishingService::class)->publish($published->fresh(), $this->author));

        $exported = collect(app(ContentExporter::class)->export(app(ContentRepository::class), ['nodes'], 'gefiltert')->files())
            ->pluck('contents', 'path');
        $this->assertStringContainsString('Neu aus Studio: **fett** und {{term:dicom}}.', $exported['nodes/gefiltert/de.md']);
        $file = $this->write('export-de.md', $exported['nodes/gefiltert/de.md']);
        $meta = $this->write('export-node.yml', $exported['nodes/gefiltert/node.yml']);

        // Artisan::output() leert den Puffer -- einmal lesen.
        $this->assertSame(0, $this->draft('gefiltert', $file));
        $this->assertStringContainsString('Keine Aenderung', Artisan::output());

        $this->assertSame(0, $this->draft('gefiltert', $file, ['--meta' => $meta]));
        $this->assertStringContainsString('Keine Aenderung', Artisan::output());

        $this->assertSame(['published'], ContentVersion::query()->where('activity_id', $activity->id)->pluck('status')->all());
    }

    public function test_every_node_survives_the_export_import_round_trip(): void
    {
        // Ein Live-Stand je Node, der von content/ abweicht -- sonst
        // exportiert content:export nichts und der Rueckweg bliebe ungeprueft.
        $normalizer = new NodePayloadNormalizer;
        $nodes = Node::query()->orderBy('slug')->get();

        foreach ($nodes as $node) {
            $envelope = $normalizer->normalize(['body' => (string) $node->body])['rich_content'];
            $envelope['briefing']['content'][] = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => "Live-Stand von {$node->slug}."]]];
            $node->update(['rich_content' => $envelope, 'points' => $node->points + 1]);
        }

        $result = app(ContentExporter::class)->export(app(ContentRepository::class), ['nodes']);
        $this->assertSame([], $result->errors());
        $files = collect($result->files())->pluck('contents', 'path');
        $importer = app(NodeDraftImporter::class);

        foreach ($nodes as $node) {
            $markdown = $files["nodes/{$node->slug}/de.md"];
            $this->assertStringContainsString("Live-Stand von {$node->slug}.", $markdown);
            $this->assertNull($importer->build($node->fresh(), $markdown), "Node {$node->slug}: de.md");
            $this->assertNull($importer->build($node->fresh(), $markdown, $files["nodes/{$node->slug}/node.yml"]), "Node {$node->slug}: de.md + node.yml");
        }
    }

    public function test_meta_file_sets_the_studio_fields_and_nothing_else(): void
    {
        $meta = $this->nodeFile('gefiltert', 'node.yml', [
            'points: 50' => 'points: 70',
            "  - id: h1\n    cost: 2" => "  - id: h1\n    cost: 1",
            'related_lessons: ["3.8"]' => 'related_lessons: [3.8]',
        ]);

        $this->assertSame(0, $this->draft('gefiltert', $this->nodeFile('gefiltert'), ['--meta' => $meta]), Artisan::output());

        $payload = ContentVersion::query()->sole()->payload;
        $this->assertSame(70, $payload['points']);
        $this->assertSame(['id' => 'h1', 'cost' => 1], $payload['hints'][0]);
        $this->assertSame(['3.8'], $payload['related_lessons'], 'Listen-Eintraege bleiben Strings');
        $this->assertSame(self::STUDIO_FIELDS, array_keys($payload));
    }

    public function test_a_new_hint_needs_its_cost_from_meta(): void
    {
        $file = $this->nodeFile('gefiltert', replace: ['## Write-up' => "### h4\n\nNeuer Hinweis.\n\n## Write-up"]);

        $this->assertSame(1, $this->draft('gefiltert', $file));
        $this->assertStringContainsString('"h4", aber kein Hint mit dieser Id in hints', Artisan::output());
        $this->assertSame(0, ContentVersion::query()->count());

        $meta = $this->nodeFile('gefiltert', 'node.yml', ["    cost: 6\n" => "    cost: 6\n  - id: h4\n    cost: 8\n"]);
        $this->assertSame(0, $this->draft('gefiltert', $file, ['--meta' => $meta]), Artisan::output());
        $this->assertSame(['id' => 'h4', 'cost' => 8], ContentVersion::query()->sole()->payload['hints'][3]);
    }

    public function test_an_invalid_draft_is_rejected_and_nothing_is_created(): void
    {
        // Ein unbekannter Glossarbegriff verletzt dieselbe Regel wie in Studio.
        $this->assertSame(1, $this->draft('gefiltert', $this->briefingChanged('Ticket aus dem PACS-Betrieb, {{term:gibt-es-nicht}}:')));
        $this->assertStringContainsString('ungueltig', Artisan::output());

        // Die Formregeln des Editors gelten auch: unbekannte Schwierigkeit.
        $meta = $this->nodeFile('gefiltert', 'node.yml', ['difficulty: hard' => 'difficulty: extrem']);
        $this->assertSame(1, $this->draft('gefiltert', $this->nodeFile('gefiltert'), ['--meta' => $meta]));
        $output = Artisan::output();
        $this->assertStringContainsString('ungueltig', $output);
        $this->assertStringContainsString('difficulty', $output);

        $this->assertSame(0, ContentVersion::query()->count());
    }

    public function test_the_meta_file_of_another_node_is_rejected(): void
    {
        $meta = $this->nodeFile('silent-ct', 'node.yml');

        $this->assertSame(1, $this->draft('gefiltert', $this->briefingChanged(), ['--meta' => $meta]));
        $this->assertStringContainsString('gehoert zu Node "silent-ct"', Artisan::output());
        $this->assertSame(0, ContentVersion::query()->count());
    }

    public function test_an_unknown_node_is_rejected(): void
    {
        $this->assertSame(1, $this->draft('gibt-es-nicht', $this->briefingChanged()));
        $this->assertStringContainsString('Node "gibt-es-nicht" existiert nicht', Artisan::output());
        $this->assertSame(0, ContentVersion::query()->count());
    }

    public function test_an_open_version_is_only_superseded_on_purpose(): void
    {
        $this->assertSame(0, $this->draft('gefiltert', $this->briefingChanged()));
        $first = ContentVersion::query()->sole();

        $second = $this->briefingChanged('Ein weiteres Ticket aus dem PACS-Betrieb:');
        $this->assertSame(1, $this->draft('gefiltert', $second));
        $this->assertStringContainsString("Offene Version(en) dieser Node: #{$first->id}", Artisan::output());
        $this->assertSame('draft', $first->fresh()->status);

        $this->assertSame(0, $this->draft('gefiltert', $second, ['--supersede' => true]));
        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame(1, ContentVersion::query()->where('status', 'draft')->count());
    }

    public function test_confirming_the_prompt_supersedes_the_open_version(): void
    {
        $this->draft('gefiltert', $this->briefingChanged());
        $first = ContentVersion::query()->sole();

        $this->artisan('content:draft-node', [
            'node' => 'gefiltert',
            '--from' => $this->briefingChanged('Ein weiteres Ticket aus dem PACS-Betrieb:'),
            '--author' => (string) $this->author->id,
        ])->expectsConfirmation('Fortfahren?', 'yes')->assertSuccessful();

        $this->assertSame('superseded', $first->fresh()->status);
    }

    public function test_the_author_must_exist_and_be_allowed_to_edit(): void
    {
        $file = $this->briefingChanged();

        $this->assertSame(1, Artisan::call('content:draft-node', ['node' => 'gefiltert', '--from' => $file, '--no-interaction' => true]));
        $this->assertStringContainsString('--author ist Pflicht', Artisan::output());

        $this->assertSame(1, Artisan::call('content:draft-node', ['node' => 'gefiltert', '--from' => $file, '--author' => 'niemand@example.org', '--no-interaction' => true]));
        $this->assertStringContainsString('existiert nicht', Artisan::output());

        $learner = User::factory()->create();
        $this->assertSame(1, Artisan::call('content:draft-node', ['node' => 'gefiltert', '--from' => $file, '--author' => $learner->email, '--no-interaction' => true]));
        $this->assertStringContainsString('nicht bearbeiten', Artisan::output());

        $this->assertSame(0, ContentVersion::query()->count());
    }
}

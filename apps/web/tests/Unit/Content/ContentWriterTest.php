<?php

namespace Tests\Unit\Content;

use App\Activities\ActivityContract;
use App\Activities\ActivityResult;
use App\Activities\ActivitySupports;
use App\Content\ContentBuilder;
use App\Content\ContentIssue;
use App\Content\ContentRepository;
use App\Content\ContentWriter;
use App\Content\GeneratedFileMarker;
use App\Content\NullCacheInvalidator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ADR 0071/0074, W2-DoD: "Import gefolgt von sofortigem Export laesst
 * [den Bestand] inhaltlich unveraendert; ein Diff gegen den Git-Stand zeigt
 * nur die Kopf-Marker." Serialisiert wird hier direkt gegen einen
 * Test-ActivityContract statt der echten content/ (siehe ADR 0074 --
 * ContentWriter erst gegen echten Bestand laufen lassen, wenn jemand das
 * Ergebnis sehen und reviewen kann, nicht automatisiert in dieser Phase).
 */
class ContentWriterTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentDir = storage_path('framework/testing/content-writer-'.uniqid());
        File::ensureDirectoryExists($this->contentDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_write_refuses_to_write_when_validate_reports_issues(): void
    {
        $activity = $this->activityWithIssues([new ContentIssue('nodes/foo/node.yml', null, 'kaputt')]);

        $issues = $this->makeWriter()->write($activity);

        $this->assertNotEmpty($issues);
        $this->assertFileDoesNotExist($this->contentDir.'/nodes/foo/node.yml');
    }

    public function test_write_persists_every_serialized_file_with_a_generated_marker(): void
    {
        $activity = $this->validActivity([
            ['path' => 'nodes/foo/node.yml', 'contents' => "slug: foo\ndifficulty: easy\n"],
            ['path' => 'nodes/foo/de.md', 'contents' => "---\ntitle: Foo\n---\n\nBriefing.\n"],
        ]);

        $issues = $this->makeWriter()->write($activity);

        $this->assertSame([], $issues);

        $yaml = File::get($this->contentDir.'/nodes/foo/node.yml');
        $md = File::get($this->contentDir.'/nodes/foo/de.md');

        $this->assertStringStartsWith('# '.GeneratedFileMarker::TEXT, $yaml);
        $this->assertStringContainsString('slug: foo', $yaml);
        $this->assertStringContainsString('<!-- '.GeneratedFileMarker::TEXT.' -->', $md);
        $this->assertStringContainsString('Briefing.', $md);
    }

    public function test_writing_the_same_activity_twice_does_not_duplicate_the_marker_or_leave_tmp_files(): void
    {
        $activity = $this->validActivity([
            ['path' => 'nodes/foo/node.yml', 'contents' => "slug: foo\n"],
        ]);
        $writer = $this->makeWriter();

        $writer->write($activity);
        $writer->write($activity);

        $yaml = File::get($this->contentDir.'/nodes/foo/node.yml');
        $this->assertSame(1, substr_count($yaml, GeneratedFileMarker::TEXT));

        $leftoverTmpFiles = collect(File::allFiles($this->contentDir))
            ->filter(fn ($file) => str_contains($file->getFilename(), '.tmp-'));
        $this->assertCount(0, $leftoverTmpFiles);
    }

    public function test_import_then_export_changes_only_the_header_marker(): void
    {
        $original = "slug: foo\ndifficulty: easy\n";
        $activity = $this->validActivity([
            ['path' => 'nodes/foo/node.yml', 'contents' => $original],
        ]);

        $this->makeWriter()->write($activity);

        $written = File::get($this->contentDir.'/nodes/foo/node.yml');
        $withoutMarker = preg_replace('/^# '.preg_quote(GeneratedFileMarker::TEXT, '/')."\n/", '', $written);

        $this->assertSame($original, $withoutMarker);
    }

    private function makeWriter(): ContentWriter
    {
        return new ContentWriter(
            new ContentRepository($this->contentDir),
            new ContentBuilder,
            new NullCacheInvalidator,
        );
    }

    /**
     * @param  list<array{path: string, contents: string}>  $files
     */
    private function validActivity(array $files): ActivityContract
    {
        return $this->activity($files, []);
    }

    /**
     * @param  list<ContentIssue>  $issues
     */
    private function activityWithIssues(array $issues): ActivityContract
    {
        return $this->activity([], $issues);
    }

    /**
     * @param  list<array{path: string, contents: string}>  $files
     * @param  list<ContentIssue>  $issues
     */
    private function activity(array $files, array $issues): ActivityContract
    {
        return new class($files, $issues) implements ActivityContract
        {
            public function __construct(
                private array $files,
                private array $issues,
            ) {}

            public function activityType(): string
            {
                return 'node';
            }

            public function key(): string
            {
                return 'foo';
            }

            public function supports(): ActivitySupports
            {
                return new ActivitySupports(true, true, true, true, true);
            }

            public function learnerView(User $user): array
            {
                return [];
            }

            public function authorView(): array
            {
                return [];
            }

            public function validate(?array $draft = null): array
            {
                return $this->issues;
            }

            public function serialize(?array $draft = null): array
            {
                return $this->files;
            }

            public function deserialize(): array
            {
                return [];
            }

            public function result(User $user): ?ActivityResult
            {
                return null;
            }
        };
    }
}

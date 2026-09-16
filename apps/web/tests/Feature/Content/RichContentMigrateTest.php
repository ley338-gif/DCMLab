<?php

namespace Tests\Feature\Content;

use App\Content\ContentRepository;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * rich-content:migrate (ADR 0117, CMS-7d.2). Betreiber-Korrektur zum
 * urspruenglichen Plan: Quelle ist `Lesson::body`/`Node::body` (DB), NICHT
 * `content/` -- `content/` ist nach Studio-Freigaben (ADR 0102/0108)
 * absichtlich potenziell veraltet und dient hier nur als Fallback, wenn
 * die DB-Spalte `null` ist.
 */
class RichContentMigrateTest extends TestCase
{
    use RefreshDatabase;

    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            File::deleteDirectory($dir);
        }

        parent::tearDown();
    }

    private function lesson(array $attributes = []): Lesson
    {
        return Lesson::factory()->create(array_merge([
            'track_id' => Track::factory(),
            'body' => "## Intro\n\nSauberer Absatz.\n",
        ], $attributes));
    }

    private function node(array $attributes = []): Node
    {
        return Node::factory()->create(array_merge([
            'body' => "## Briefing\n\nBriefing-Text.\n\n## Hints\n\n### h1\n\nHint-Text.\n\n## Write-up\n\nWrite-up-Text.\n",
        ], $attributes));
    }

    private function migrate(array $options = []): int
    {
        return Artisan::call('rich-content:migrate', $options);
    }

    public function test_dry_run_reports_what_would_be_migrated_without_writing(): void
    {
        $lesson = $this->lesson();
        $node = $this->node();

        $exitCode = $this->migrate();
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('1 Lektionen, 1 Nodes geprueft', $output);
        $this->assertStringContainsString('2 zu migrieren, 0 bereits vorhanden', $output);
        $this->assertStringContainsString('0 geschrieben', $output);

        $this->assertNull($lesson->fresh()->rich_content);
        $this->assertNull($node->fresh()->rich_content);
    }

    public function test_dry_run_flag_behaves_like_omitting_apply(): void
    {
        $this->lesson();

        $exitCode = $this->migrate(['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('0 geschrieben', Artisan::output());
    }

    public function test_apply_writes_the_prepared_documents(): void
    {
        $lesson = $this->lesson();
        $node = $this->node();

        $exitCode = $this->migrate(['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('2 geschrieben', $output);

        $lessonDoc = $lesson->fresh()->rich_content;
        $this->assertSame('doc', $lessonDoc['type']);
        $this->assertSame(1, $lessonDoc['version']);
        $this->assertSame('Sauberer Absatz.', $lessonDoc['content'][1]['content'][0]['text']);

        $nodeEnvelope = $node->fresh()->rich_content;
        $this->assertSame('node_content', $nodeEnvelope['type']);
        $this->assertSame(1, $nodeEnvelope['version']);
        $this->assertSame('Briefing-Text.', $nodeEnvelope['briefing']['content'][0]['content'][0]['text']);
        $this->assertSame('Hint-Text.', $nodeEnvelope['hints']['h1']['content'][0]['content'][0]['text']);
        $this->assertSame('Write-up-Text.', $nodeEnvelope['write_up']['content'][0]['content'][0]['text']);
    }

    public function test_a_second_apply_run_is_idempotent_and_writes_nothing(): void
    {
        $this->lesson();
        $this->node();

        $this->migrate(['--apply' => true]);

        $exitCode = $this->migrate(['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('0 zu migrieren, 2 bereits vorhanden', $output);
        $this->assertStringContainsString('0 geschrieben', $output);
    }

    public function test_it_prefers_the_db_body_over_a_stale_content_file(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/de.md' => "---\ntitle: Alt\n---\n\n## Veraltet\n\n![Alt](veraltet.png)\n",
        ]);
        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        $lesson = $this->lesson([
            'lesson_id' => '1.0',
            'body' => "## Aktuell\n\nFrischer Text aus der DB.\n",
        ]);

        $exitCode = $this->migrate(['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $doc = $lesson->fresh()->rich_content;
        $this->assertSame('Frischer Text aus der DB.', $doc['content'][1]['content'][0]['text']);
    }

    public function test_it_falls_back_to_the_content_file_only_when_the_db_body_is_null(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/de.md' => "---\ntitle: Datei\n---\n\n## Aus Datei\n\nText aus content/.\n",
        ]);
        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        $lesson = $this->lesson(['lesson_id' => '1.0', 'body' => null]);

        $exitCode = $this->migrate(['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $doc = $lesson->fresh()->rich_content;
        $this->assertSame('Text aus content/.', $doc['content'][1]['content'][0]['text']);
    }

    public function test_it_fails_without_writing_anything_when_a_body_is_missing_everywhere(): void
    {
        $dir = $this->buildContentDir([]);
        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        $broken = $this->lesson(['lesson_id' => '1.0', 'body' => null]);
        $clean = $this->lesson(['lesson_id' => '2.0']);

        $exitCode = $this->migrate(['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('kein_body', $output);
        $this->assertNull($broken->fresh()->rich_content);
        $this->assertNull($clean->fresh()->rich_content, 'ein ansonsten sauberer Lektion darf trotzdem nichts geschrieben werden');
    }

    public function test_an_unmodeled_construct_blocks_the_whole_run_without_writing(): void
    {
        $broken = $this->lesson(['body' => "## Intro\n\nText.\n\n![Alt](bild.png)\n"]);
        $clean = $this->lesson();

        $exitCode = $this->migrate(['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('bild', $output);
        $this->assertNull($broken->fresh()->rich_content);
        $this->assertNull($clean->fresh()->rich_content);
    }

    public function test_it_fails_without_writing_when_existing_rich_content_is_invalid(): void
    {
        $lesson = $this->lesson([
            'rich_content' => ['type' => 'doc', 'version' => 2, 'content' => []],
        ]);

        $exitCode = $this->migrate(['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('invalid_existing', $output);
        $this->assertSame(2, $lesson->fresh()->rich_content['version'], 'bestehendes ungueltiges rich_content bleibt unangetastet');
    }

    public function test_it_fails_without_writing_when_existing_rich_content_diverges(): void
    {
        $lesson = $this->lesson([
            'body' => "## Intro\n\nAktueller Text.\n",
            'rich_content' => [
                'type' => 'doc',
                'version' => 1,
                'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Veralteter Text.']]]],
            ],
        ]);

        $exitCode = $this->migrate(['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('diverged', $output);
        $this->assertSame('Veralteter Text.', $lesson->fresh()->rich_content['content'][0]['content'][0]['text']);
    }

    public function test_the_quiz_section_of_a_lesson_is_excluded_like_the_audit_command(): void
    {
        $lesson = $this->lesson([
            'body' => "## Intro\n\nSauberer Text.\n\n## Quiz\n\n![Alt](bild.png)\n\n---\n\n**Als Nächstes:** weiter.\n",
        ]);

        $exitCode = $this->migrate(['--apply' => true]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertNotNull($lesson->fresh()->rich_content);
    }

    private function buildContentDir(array $files): string
    {
        $dir = storage_path('framework/testing/rich-content-migrate-'.Str::random(12));
        $this->tempDirs[] = $dir;

        foreach ($files as $relative => $content) {
            $target = $dir.'/'.$relative;
            File::ensureDirectoryExists(dirname($target));
            File::put($target, $content);
        }

        return $dir;
    }
}

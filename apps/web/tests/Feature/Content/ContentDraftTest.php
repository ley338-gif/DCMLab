<?php

namespace Tests\Feature\Content;

use App\Content\ContentRepository;
use App\Content\RichContent\MarkdownToRichContentConverter;
use App\Content\RichContent\RichContentToMarkdownSerializer;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * `content:draft` (Vorschlag aus docs/lektions-backlog.md): legt aus einer
 * Datei einen Studio-Entwurf an -- nie eingereicht, nie veroeffentlicht.
 * Liest den echten content/-Bestand nur und laeuft ausschliesslich in der
 * Test-DB (SQLite `:memory:`, ADR 0069).
 */
class ContentDraftTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(ContentRepository::class, new ContentRepository(base_path('../../content')));
        $this->assertSame(0, Artisan::call('content:sync'));

        $this->dir = storage_path('framework/testing/content-draft-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->dir);
        $this->author = User::factory()->reviewer()->create();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    /**
     * Die Datei der Lektion, optional abgewandelt.
     */
    private function draftFile(string $lessonId, array $replace = []): string
    {
        $markdown = str_replace("\r\n", "\n", (string) file_get_contents(base_path("../../content/lessons/{$lessonId}/de.md")));

        foreach ($replace as $search => $new) {
            $this->assertStringContainsString($search, $markdown);
            $markdown = str_replace($search, $new, $markdown);
        }

        $path = "{$this->dir}/{$lessonId}.md";
        file_put_contents($path, $markdown);

        return $path;
    }

    private function draft(string $lessonId, string $file, array $options = []): int
    {
        return Artisan::call('content:draft', [
            'lesson' => $lessonId,
            '--from' => $file,
            '--author' => (string) $this->author->id,
            '--no-interaction' => true,
            ...$options,
        ]);
    }

    private function activityId(string $lessonId): int
    {
        return Activity::query()->where('type', 'lesson')->where('key', $lessonId)->value('id');
    }

    public function test_changed_prose_becomes_a_draft_and_nothing_goes_live(): void
    {
        $live = Lesson::where('lesson_id', '1.5')->firstOrFail();
        $file = $this->draftFile('1.5', ['Kurze Pause. Dann:' => 'Eine lange Pause. Dann:']);

        $this->assertSame(0, $this->draft('1.5', $file), Artisan::output());

        $version = ContentVersion::query()->where('activity_id', $this->activityId('1.5'))->sole();
        $this->assertSame('draft', $version->status);
        $this->assertFalse($version->is_current);
        $this->assertSame($this->author->id, $version->created_by);
        $this->assertStringContainsString('Eine lange Pause', json_encode($version->payload['rich_content'], JSON_UNESCAPED_UNICODE));
        $this->assertArrayNotHasKey('quiz', $version->payload);
        $this->assertSame($live->objectives, $version->payload['objectives']);

        // Nichts ist live gegangen.
        $this->assertSame($live->rich_content, Lesson::where('lesson_id', '1.5')->firstOrFail()->rich_content);
        $this->assertSame($live->body, Lesson::where('lesson_id', '1.5')->firstOrFail()->body);
    }

    public function test_the_draft_prose_reads_back_as_the_file_prose(): void
    {
        $file = $this->draftFile('1.5', ['Kurze Pause. Dann:' => 'Eine lange Pause. Dann:']);
        $this->draft('1.5', $file);

        $payload = ContentVersion::query()->where('activity_id', $this->activityId('1.5'))->sole()->payload;
        $markdown = (new RichContentToMarkdownSerializer)->serialize($payload['rich_content']);

        $this->assertStringContainsString('Eine lange Pause. Dann:', $markdown);
        $this->assertTrue((new RichContentToMarkdownSerializer)->equivalent($payload['rich_content'], (new MarkdownToRichContentConverter)->convert($markdown)));
    }

    public function test_an_unchanged_file_creates_no_version(): void
    {
        $this->assertSame(0, $this->draft('1.5', $this->draftFile('1.5')));

        $this->assertStringContainsString('Keine Aenderung', Artisan::output());
        $this->assertSame(0, ContentVersion::query()->count());
    }

    public function test_a_changed_quiz_becomes_a_separate_quiz_draft_with_raw_text(): void
    {
        $file = $this->draftFile('1.1', ['**q1 — ' => '**q1 — Neu formuliert: ']);

        $this->assertSame(0, $this->draft('1.1', $file), Artisan::output());

        $payload = ContentVersion::query()->where('activity_id', $this->activityId('1.1'))->sole()->payload;
        $this->assertSame(['quiz'], array_keys($payload));
        $this->assertStringStartsWith('Neu formuliert: ', $payload['quiz'][0]['question']);
        $this->assertStringNotContainsString('<', $payload['quiz'][0]['question'], 'roher Markdown-Text, kein HTML');
        $this->assertSame(Lesson::where('lesson_id', '1.1')->firstOrFail()->quiz[0]['answer'], $payload['quiz'][0]['answer']);
        $this->assertCount(3, $payload['quiz']);
    }

    public function test_prose_and_quiz_changed_together_needs_part(): void
    {
        $file = $this->draftFile('1.1', ['**q1 — ' => '**q1 — Neu: ', '## Selbstcheck' => '## Selbstcheck (neu)']);

        $this->assertSame(1, $this->draft('1.1', $file));
        $this->assertStringContainsString('ADR 0121', Artisan::output());
        $this->assertSame(0, ContentVersion::query()->count());

        $this->assertSame(0, $this->draft('1.1', $file, ['--part' => 'lesson']), Artisan::output());
        $this->assertArrayHasKey('rich_content', ContentVersion::query()->sole()->payload);
    }

    public function test_part_quiz_creates_only_the_quiz_draft(): void
    {
        $file = $this->draftFile('1.1', ['**q1 — ' => '**q1 — Neu: ', '## Selbstcheck' => '## Selbstcheck (neu)']);

        $this->assertSame(0, $this->draft('1.1', $file, ['--part' => 'quiz']), Artisan::output());

        $this->assertSame(['quiz'], array_keys(ContentVersion::query()->sole()->payload));
    }

    public function test_a_quiz_mismatch_does_not_block_a_lesson_only_draft(): void
    {
        // Neue Frage q9 ohne type/answer: fuer --part=quiz ein Fehler, fuer
        // --part=lesson irrelevant.
        $file = $this->draftFile('1.1', [
            '## Selbstcheck' => '## Selbstcheck (neu)',
            '**q1 — ' => "**q9 — Neue Frage ohne Antwort?**\n1. A\n2. B\n\n**q1 — ",
        ]);

        $this->assertSame(1, $this->draft('1.1', $file, ['--part' => 'quiz']));
        $this->assertStringContainsString('ohne type/answer: q9', Artisan::output());

        $this->assertSame(0, $this->draft('1.1', $file, ['--part' => 'lesson']), Artisan::output());
        $this->assertArrayHasKey('rich_content', ContentVersion::query()->sole()->payload);
    }

    public function test_confirming_the_prompt_supersedes_the_open_version(): void
    {
        $this->draft('1.5', $this->draftFile('1.5', ['Kurze Pause. Dann:' => 'Eine lange Pause. Dann:']));
        $first = ContentVersion::query()->sole();

        $this->artisan('content:draft', [
            'lesson' => '1.5',
            '--from' => $this->draftFile('1.5', ['Kurze Pause. Dann:' => 'Eine sehr lange Pause. Dann:']),
            '--author' => (string) $this->author->id,
        ])->expectsConfirmation('Fortfahren?', 'yes')->assertSuccessful();

        $this->assertSame('superseded', $first->fresh()->status);
    }

    public function test_unquoted_yaml_ids_in_meta_stay_strings(): void
    {
        $metaPath = "{$this->dir}/meta.yml";
        file_put_contents($metaPath, str_replace('requires: ["1.1"]', 'requires: [1.1]', (string) file_get_contents(base_path('../../content/lessons/1.5/meta.yml'))));

        $this->draft('1.5', $this->draftFile('1.5', ['Kurze Pause. Dann:' => 'Eine lange Pause. Dann:']), ['--meta' => $metaPath]);

        $this->assertSame(['1.1'], ContentVersion::query()->sole()->payload['requires']);
    }

    public function test_meta_file_sets_metadata(): void
    {
        $metaPath = "{$this->dir}/meta.yml";
        file_put_contents($metaPath, str_replace('duration_minutes: 8', 'duration_minutes: 20', (string) file_get_contents(base_path('../../content/lessons/1.5/meta.yml'))));

        $this->assertSame(0, $this->draft('1.5', $this->draftFile('1.5'), ['--meta' => $metaPath]), Artisan::output());

        $this->assertSame(20, ContentVersion::query()->sole()->payload['duration_minutes']);
    }

    public function test_an_invalid_draft_is_rejected_and_nothing_is_created(): void
    {
        // Ein unbekannter Glossarbegriff verletzt dieselbe Regel wie in Studio.
        $file = $this->draftFile('1.5', ['Kurze Pause. Dann:' => 'Kurze Pause, {{term:gibt-es-nicht}}. Dann:']);

        $this->assertSame(1, $this->draft('1.5', $file));
        $this->assertStringContainsString('ungueltig', Artisan::output());
        $this->assertSame(0, ContentVersion::query()->count());
    }

    public function test_an_open_version_is_only_superseded_on_purpose(): void
    {
        $file = $this->draftFile('1.5', ['Kurze Pause. Dann:' => 'Eine lange Pause. Dann:']);
        $this->assertSame(0, $this->draft('1.5', $file));
        $first = ContentVersion::query()->sole();

        $second = $this->draftFile('1.5', ['Kurze Pause. Dann:' => 'Eine sehr lange Pause. Dann:']);
        $this->assertSame(1, $this->draft('1.5', $second));
        $this->assertStringContainsString("#{$first->id}", Artisan::output());
        $this->assertSame('draft', $first->fresh()->status);

        $this->assertSame(0, $this->draft('1.5', $second, ['--supersede' => true]));
        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame(1, ContentVersion::query()->where('status', 'draft')->count());
    }

    public function test_the_author_must_exist_and_be_allowed_to_edit(): void
    {
        $file = $this->draftFile('1.5', ['Kurze Pause. Dann:' => 'Eine lange Pause. Dann:']);

        $this->assertSame(1, Artisan::call('content:draft', ['lesson' => '1.5', '--from' => $file, '--no-interaction' => true]));
        $this->assertStringContainsString('--author ist Pflicht', Artisan::output());

        $learner = User::factory()->create();
        $this->assertSame(1, Artisan::call('content:draft', ['lesson' => '1.5', '--from' => $file, '--author' => $learner->email, '--no-interaction' => true]));
        $this->assertStringContainsString('nicht bearbeiten', Artisan::output());

        $this->assertSame(0, ContentVersion::query()->count());
    }
}

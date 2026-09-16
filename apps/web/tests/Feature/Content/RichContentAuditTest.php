<?php

namespace Tests\Feature\Content;

use App\Content\ContentRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * rich-content:audit (ADR 0115, CMS-7d.1) -- Dry-Run vor jeder Migration.
 * `rich-content:audit` liest nur `ContentRepository::lessons()`/`nodes()`/
 * `glossary()`, deshalb reichen minimale Fixtures ohne meta.yml/node.yml.
 */
class RichContentAuditTest extends TestCase
{
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            File::deleteDirectory($dir);
        }

        parent::tearDown();
    }

    public function test_a_clean_lesson_and_node_produce_no_blocking_findings(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/de.md' => $this->frontMatter()."## Intro\n\nEin sauberer Absatz.\n",
            'nodes/sample/de.md' => $this->frontMatter().$this->validNodeBody(),
        ]);

        $result = $this->audit($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
        $this->assertStringContainsString('keine blockierenden Funde', $result['output']);
    }

    public function test_a_thematic_break_blocks_with_the_real_file_line(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/de.md' => $this->frontMatter()."## Intro\n\nDavor.\n\n---\n\nDanach.\n",
        ]);

        $result = $this->audit($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Lektion 1.0 [prose]', $result['output']);
        $this->assertStringContainsString('horizontale_trennlinie', $result['output']);

        // Frontmatter ist 3 Zeilen (---/title/---), body beginnt also bei
        // Datei-Zeile 4 -- "---" liegt danach auf Zeile 9. Der Offset aus
        // body_start_line muss die reale Dateizeile treffen, nicht die
        // Zeile relativ zum geprueften Markdown-Fragment.
        $this->assertStringContainsString('Zeile 9,', $result['output']);
    }

    public function test_unknown_raw_html_blocks(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/de.md' => $this->frontMatter()."## Intro\n\n<div>Fremdes HTML</div>\n",
        ]);

        $result = $this->audit($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('unbekanntes_html', $result['output']);
        $this->assertStringContainsString('Fremdes HTML', $result['output']);
    }

    public function test_an_image_blocks(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/de.md' => $this->frontMatter()."## Intro\n\nText davor.\n\n![Alt](bild.png)\n",
        ]);

        $result = $this->audit($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Lektion 1.0 [prose]', $result['output']);
        $this->assertStringContainsString('bild:', $result['output']);
    }

    public function test_the_kein_beispiel_marker_does_not_block(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/de.md' => $this->frontMatter()."## Intro\n\n<!-- kein-beispiel -->\n```\nA -> B\n```\n",
        ]);

        $result = $this->audit($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
    }

    public function test_a_self_check_does_not_block(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/de.md' => $this->frontMatter()."<details>\n<summary>Frage?</summary>\n\nAntwort.\n</details>\n",
        ]);

        $result = $this->audit($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
    }

    public function test_the_quiz_section_of_a_lesson_is_excluded_from_the_audit(): void
    {
        // Das Bild steckt im Quiz-Teil -- QuizContent::splitBody() trennt
        // ihn vor der Konvertierung ab, der Audit darf ihn also nicht sehen.
        $dir = $this->buildContentDir([
            'lessons/1.0/de.md' => $this->frontMatter()
                ."## Intro\n\nSauberer Text.\n\n## Quiz\n\n![Alt](bild.png)\n\n---\n\n**Als Nächstes:** weiter.\n",
        ]);

        $result = $this->audit($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
    }

    public function test_node_sections_are_audited_independently(): void
    {
        $broken = <<<'MD'
        ## Briefing

        Sauberer Briefing-Text.

        ## Hints

        ### h1

        Sauberer Hint.

        ### h2

        <div>Fremdes HTML nur in h2</div>

        ## Write-up

        Sauberer Write-up-Text.
        MD;

        $dir = $this->buildContentDir([
            'nodes/sample/de.md' => $this->frontMatter().$broken,
        ]);

        $result = $this->audit($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Node sample [hints.h2]', $result['output']);
        $this->assertStringNotContainsString('Node sample [briefing]', $result['output']);
        $this->assertStringNotContainsString('Node sample [hints.h1]', $result['output']);
        $this->assertStringNotContainsString('Node sample [write_up]', $result['output']);
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    private function audit(string $path): array
    {
        $this->app->instance(ContentRepository::class, new ContentRepository($path));

        $exitCode = Artisan::call('rich-content:audit');

        return ['exitCode' => $exitCode, 'output' => Artisan::output()];
    }

    private function buildContentDir(array $files): string
    {
        $dir = storage_path('framework/testing/rich-content-audit-'.Str::random(12));
        $this->tempDirs[] = $dir;

        foreach ($files as $relative => $content) {
            $target = $dir.'/'.$relative;
            File::ensureDirectoryExists(dirname($target));
            File::put($target, $content);
        }

        return $dir;
    }

    private function frontMatter(): string
    {
        return "---\ntitle: Test\n---\n\n";
    }

    private function validNodeBody(): string
    {
        return "## Briefing\n\nText.\n\n## Hints\n\n### h1\n\nText.\n\n## Write-up\n\nText.\n";
    }
}

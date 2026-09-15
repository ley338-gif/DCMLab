<?php

namespace Tests\Unit\Content\RichContent;

use App\Content\RichContent\RichContentRenderer;
use App\Content\RichContent\UnsupportedRichContentVersionException;
use Tests\TestCase;

class RichContentRendererTest extends TestCase
{
    private function doc(array $content): array
    {
        return ['type' => 'doc', 'version' => 1, 'content' => $content];
    }

    /**
     * ADR 0112: eine unbekannte Dokumentversion wird nicht still gerendert
     * -- ein `version: 1`-Leser darf ein spaeteres Dokument nicht
     * "irgendwie" darstellen.
     */
    public function test_it_throws_for_an_unsupported_document_version(): void
    {
        $this->expectException(UnsupportedRichContentVersionException::class);

        (new RichContentRenderer)->render(['type' => 'doc', 'version' => 2, 'content' => []]);
    }

    public function test_it_renders_a_self_check(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'self_check', 'attrs' => ['summary' => 'Frage?'], 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Antwort.']]],
            ]],
        ]));

        $this->assertSame('<details><summary>Frage?</summary><p>Antwort.</p></details>', $html);
    }

    public function test_it_escapes_the_self_check_summary(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'self_check', 'attrs' => ['summary' => '<script>'], 'content' => []],
        ]));

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_it_renders_a_paragraph_with_marks(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Fett', 'marks' => [['type' => 'bold']]],
                ['type' => 'text', 'text' => ' und '],
                ['type' => 'text', 'text' => 'kursiv', 'marks' => [['type' => 'italic']]],
            ]],
        ]));

        $this->assertSame('<p><strong>Fett</strong> und <em>kursiv</em></p>', $html);
    }

    public function test_it_renders_a_link_mark(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'DCMLab', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.test']]]],
            ]],
        ]));

        $this->assertSame('<p><a href="https://example.test">DCMLab</a></p>', $html);
    }

    public function test_it_escapes_text_content(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '<script>alert(1)</script>']]],
        ]));

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_it_assigns_unique_heading_anchors_in_document_order(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Stolperfallen']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Stolperfallen']]],
        ]));

        $this->assertStringContainsString('<h2 id="stolperfallen">', $html);
        $this->assertStringContainsString('<h2 id="stolperfallen-2">', $html);
    }

    public function test_it_renders_lists(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'bullet_list', 'content' => [
                ['type' => 'list_item', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Eins']]]]],
                ['type' => 'list_item', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Zwei']]]]],
            ]],
        ]));

        $this->assertSame('<ul><li><p>Eins</p></li><li><p>Zwei</p></li></ul>', $html);
    }

    public function test_it_renders_a_known_glossary_term_with_a_tooltip(): void
    {
        $renderer = new RichContentRenderer(['dicom' => ['term' => 'DICOM', 'expansion' => 'Digital Imaging', 'short' => 'Bildstandard']]);

        $html = $renderer->render($this->doc([
            ['type' => 'paragraph', 'content' => [['type' => 'glossary_term', 'attrs' => ['slug' => 'dicom']]]],
        ]));

        $this->assertStringContainsString('class="glossary-term"', $html);
        $this->assertStringContainsString('data-term="dicom"', $html);
        $this->assertStringContainsString('>DICOM<', $html);
    }

    public function test_it_falls_back_to_the_bare_slug_for_an_unknown_glossary_term(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'paragraph', 'content' => [['type' => 'glossary_term', 'attrs' => ['slug' => 'unbekannt']]]],
        ]));

        $this->assertSame('<p>unbekannt</p>', $html);
    }

    public function test_it_renders_a_console_code_block_with_prompt_lines(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'code_block', 'attrs' => ['variant' => 'console'], 'text' => "\$ echoscu foo\nErfolgreich."],
        ]));

        $this->assertStringContainsString('lesson-console', $html);
        $this->assertStringContainsString('lesson-line-prompt', $html);
        $this->assertStringContainsString('lesson-line-output', $html);
    }

    public function test_it_renders_a_labeled_code_block_with_a_language(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'code_block', 'attrs' => ['variant' => 'code', 'language' => 'python'], 'text' => 'print(1)'],
        ]));

        $this->assertStringContainsString('data-lang="python"', $html);
        $this->assertStringContainsString('language-python', $html);
    }

    public function test_it_renders_a_diagram_code_block(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => 'A -> B'],
        ]));

        $this->assertStringContainsString('lesson-diagram', $html);
    }

    public function test_it_renders_a_mermaid_code_block(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'code_block', 'attrs' => ['variant' => 'mermaid'], 'text' => 'graph TD; A-->B;'],
        ]));

        $this->assertSame('<pre class="mermaid">graph TD; A--&gt;B;</pre>', $html);
    }

    public function test_it_renders_a_table(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'table', 'content' => [
                ['type' => 'table_row', 'content' => [
                    ['type' => 'table_cell', 'attrs' => ['header' => true], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Tag']]]]],
                ]],
                ['type' => 'table_row', 'content' => [
                    ['type' => 'table_cell', 'attrs' => ['header' => false], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '(0008,0060)']]]]],
                ]],
            ]],
        ]));

        $this->assertSame('<table><tbody><tr><th><p>Tag</p></th></tr><tr><td><p>(0008,0060)</p></td></tr></tbody></table>', $html);
    }

    public function test_it_renders_a_blockquote(): void
    {
        $html = (new RichContentRenderer)->render($this->doc([
            ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Zitat.']]]]],
        ]));

        $this->assertSame('<blockquote><p>Zitat.</p></blockquote>', $html);
    }
}

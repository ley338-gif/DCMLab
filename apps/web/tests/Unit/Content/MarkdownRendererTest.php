<?php

namespace Tests\Unit\Content;

use App\Content\MarkdownRenderer;
use Tests\TestCase;

class MarkdownRendererTest extends TestCase
{
    public function test_it_renders_plain_markdown(): void
    {
        $renderer = new MarkdownRenderer;

        $html = $renderer->render('# Titel'."\n\nText.");

        $this->assertStringContainsString('<h1>Titel</h1>', $html);
        $this->assertStringContainsString('<p>Text.</p>', $html);
    }

    public function test_it_resolves_glossary_terms_to_tooltips(): void
    {
        $renderer = new MarkdownRenderer([
            'scu' => [
                'term' => 'SCU',
                'expansion' => 'Service Class User',
                'short' => 'Wer anruft.',
            ],
        ]);

        $html = $renderer->render('{{term:scu}} steht fuer etwas.');

        $this->assertStringContainsString('class="glossary-term"', $html);
        $this->assertStringContainsString('data-term="scu"', $html);
        $this->assertStringContainsString('Service Class User', $html);
        $this->assertStringContainsString('>SCU<', $html);
    }

    public function test_unknown_term_falls_back_to_the_bare_slug(): void
    {
        $renderer = new MarkdownRenderer([]);

        $html = $renderer->render('{{term:nicht-vorhanden}}');

        $this->assertStringContainsString('nicht-vorhanden', $html);
        $this->assertStringNotContainsString('glossary-term', $html);
    }

    public function test_it_marks_mermaid_blocks_for_the_frontend(): void
    {
        $renderer = new MarkdownRenderer;

        $html = $renderer->render("```mermaid\ngraph TD; A-->B;\n```");

        $this->assertStringContainsString('<pre class="mermaid">', $html);
        $this->assertStringNotContainsString('language-mermaid', $html);
    }

    public function test_it_allows_raw_html_for_selbstcheck_details_blocks(): void
    {
        $renderer = new MarkdownRenderer;

        $html = $renderer->render("<details>\n<summary>Frage?</summary>\n\nAntwort.\n</details>");

        $this->assertStringContainsString('<details>', $html);
        $this->assertStringContainsString('<summary>Frage?</summary>', $html);
    }
}

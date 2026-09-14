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

    public function test_it_renders_a_dollar_prefixed_block_as_a_console_with_a_command_only_copy_button(): void
    {
        $renderer = new MarkdownRenderer;

        $html = $renderer->render("```\n\$ echoscu -v -aec ORTHANC 127.0.0.1 4242\nI: Received Echo Response (Success)\n```");

        $this->assertStringContainsString('class="lesson-console"', $html);
        $this->assertStringContainsString('lesson-line-prompt', $html);
        $this->assertStringContainsString('lesson-line-output', $html);
        // Der Copy-Button kopiert den lauffaehigen Befehl ohne Prompt-Zeichen,
        // damit er direkt in eine Shell eingefuegt werden kann.
        $this->assertStringContainsString('data-copy="echoscu -v -aec ORTHANC 127.0.0.1 4242"', $html);
    }

    public function test_it_renders_a_powershell_prompt_as_a_console_block_too(): void
    {
        $renderer = new MarkdownRenderer;

        $html = $renderer->render("```\nPS> Get-ChildItem *.dcm\n```");

        $this->assertStringContainsString('class="lesson-console"', $html);
        $this->assertStringContainsString('data-copy="Get-ChildItem *.dcm"', $html);
    }

    public function test_it_renders_a_plain_output_block_as_terminal_without_a_copy_button(): void
    {
        $renderer = new MarkdownRenderer;

        $html = $renderer->render("```\nI: Association Accepted\nI: Received Echo Response (Success)\n```");

        $this->assertStringContainsString('class="lesson-terminal"', $html);
        $this->assertStringNotContainsString('lesson-copy-btn', $html);
    }

    public function test_it_renders_a_language_tagged_block_as_code_with_a_copy_button(): void
    {
        $renderer = new MarkdownRenderer;

        $html = $renderer->render("```python\nprint('hi')\n```");

        $this->assertStringContainsString('class="lesson-code" data-lang="python"', $html);
        $this->assertStringContainsString('lesson-copy-btn', $html);
        $this->assertStringContainsString('language-python', $html);
    }

    public function test_it_renders_a_kein_beispiel_block_as_a_diagram_without_a_copy_button(): void
    {
        $renderer = new MarkdownRenderer;

        $html = $renderer->render("<!-- kein-beispiel -->\n```\n\$ scu ---> scp\n```");

        $this->assertStringContainsString('class="lesson-diagram"', $html);
        $this->assertStringNotContainsString('lesson-copy-btn', $html);
        $this->assertStringNotContainsString('kein-beispiel', $html);
    }
}

<?php

namespace Tests\Unit\Content;

use App\Content\ContentRepository;
use App\Content\NodeSections;
use Tests\TestCase;

class NodeSectionsTest extends TestCase
{
    public function test_it_splits_briefing_hints_and_write_up(): void
    {
        $body = <<<'MD'
        ## Briefing

        Text der Einfuehrung.

        ---

        ## Hints

        ### h1

        Erster Hinweis.

        ### h2

        Zweiter Hinweis.

        ---

        ## Write-up

        Die Loesung.
        MD;

        $sections = NodeSections::parse($body);

        $this->assertSame('Text der Einfuehrung.', $sections['briefing']);
        $this->assertSame(['h1' => 'Erster Hinweis.', 'h2' => 'Zweiter Hinweis.'], $sections['hints']);
        $this->assertSame('Die Loesung.', $sections['write_up']);
    }

    public function test_missing_sections_yield_empty_defaults(): void
    {
        $sections = NodeSections::parse('Kein Abschnitt hier.');

        $this->assertSame('', $sections['briefing']);
        $this->assertSame([], $sections['hints']);
        $this->assertSame('', $sections['write_up']);
    }

    public function test_it_splits_the_real_silent_ct_write_up(): void
    {
        $content = new ContentRepository(base_path('../../content'));
        $node = $content->nodes()['silent-ct'];

        $sections = NodeSections::parse($node['body']);

        $this->assertStringContainsString('Bring die Thorax-Studie ins Archiv', $sections['briefing']);
        $this->assertArrayHasKey('h1', $sections['hints']);
        $this->assertArrayHasKey('h2', $sections['hints']);
        $this->assertArrayHasKey('h3', $sections['hints']);
        $this->assertStringContainsString('Called AE Title Not Recognized', $sections['hints']['h2']);
        $this->assertStringContainsString('Der Weg', $sections['write_up']);
        $this->assertStringContainsString('Was du mitnimmst', $sections['write_up']);
    }
}

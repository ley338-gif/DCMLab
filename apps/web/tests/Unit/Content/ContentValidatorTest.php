<?php

namespace Tests\Unit\Content;

use App\Content\ContentIssue;
use App\Content\ContentValidator;
use Tests\TestCase;

/**
 * ADR 0071/0073, W1-DoD: "Der Service ist ohne Dateisystem gegen ein Array
 * aufrufbar und liefert dieselben Befunde." Kein ContentRepository, keine
 * Datenbank, keine temporaeren Dateien -- reine Arrays rein, ContentIssue[]
 * raus. Das eigentliche Verhalten (alle Regelkategorien) bleibt in
 * tests/Feature/Content/ContentValidateTest.php abgedeckt, das unveraendert
 * gegen das Artisan-Command laeuft.
 */
class ContentValidatorTest extends TestCase
{
    public function test_it_validates_against_plain_arrays_without_any_filesystem_access(): void
    {
        $lessons = [
            '1.0' => [
                'id' => '1.0',
                'meta' => ['objectives_count' => 0, 'requires' => [], 'tools' => []],
                'meta_file' => 'lessons/1.0/meta.yml',
                'meta_raw' => "objectives_count: 0\nrequires: []\ntools: []\n",
                'md_file' => 'lessons/1.0/de.md',
                'md_raw' => null,
                'frontmatter' => null,
                'body' => null,
                'body_start_line' => 1,
            ],
        ];

        $issues = (new ContentValidator)->validate(
            themenfelder: [],
            tracks: [],
            achievements: [],
            lessons: $lessons,
            nodes: [],
            exams: [],
            tools: [],
            toolsRaw: null,
            glossary: [],
            datasets: [],
            skills: [],
        );

        $this->assertCount(1, $issues);
        $this->assertSame('lessons/1.0/meta.yml', $issues[0]->file);
        $this->assertStringContainsString('meta.yml ohne de.md', $issues[0]->message);
    }

    public function test_it_returns_no_issues_for_a_structurally_complete_minimal_set(): void
    {
        $issues = (new ContentValidator)->validate(
            themenfelder: [],
            tracks: [],
            achievements: [],
            lessons: [],
            nodes: [],
            exams: [],
            tools: [],
            toolsRaw: null,
            glossary: [],
            datasets: [],
            skills: [],
        );

        $this->assertSame([], $issues);
    }

    public function test_repeated_calls_do_not_leak_issues_between_invocations(): void
    {
        $validator = new ContentValidator;
        $broken = [
            '1.0' => [
                'id' => '1.0',
                'meta' => ['objectives_count' => 0, 'requires' => [], 'tools' => []],
                'meta_file' => 'lessons/1.0/meta.yml',
                'meta_raw' => '',
                'md_file' => 'lessons/1.0/de.md',
                'md_raw' => null,
                'frontmatter' => null,
                'body' => null,
                'body_start_line' => 1,
            ],
        ];

        $first = $validator->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $broken, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );
        $second = $validator->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: [], nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertNotEmpty($first);
        $this->assertSame([], $second);
    }

    // -----------------------------------------------------------------
    // CMS-7d.3: dieselben drei Regeln (Leseanleitung, Glossarbegriffe,
    // Werkzeug-Nutzung), aber gegen ein RichContentDocument statt gegen
    // rohen Markdown-Text -- ein Lesson-/Node-Eintrag mit `rich_content`
    // hat keinen Body mehr, gegen den die alten Regexe laufen koennten.
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $richContent
     * @param  array<string, mixed>  $metaOverrides
     * @return array<string, array<string, mixed>>
     */
    private function lessonWithRichContent(array $richContent, array $metaOverrides = []): array
    {
        return [
            '1.0' => [
                'id' => '1.0',
                'meta' => array_merge(['objectives_count' => 0, 'requires' => [], 'tools' => []], $metaOverrides),
                'meta_file' => 'lessons/1.0/meta.yml',
                'meta_raw' => '',
                'md_file' => 'lessons/1.0/de.md',
                'md_raw' => null,
                'frontmatter' => null,
                'body' => null,
                'body_start_line' => 1,
                'rich_content' => $richContent,
            ],
        ];
    }

    /**
     * @param  list<ContentIssue>  $issues
     * @return list<string>
     */
    private function messages(array $issues): array
    {
        return array_map(fn ($issue) => $issue->message, $issues);
    }

    public function test_rich_content_lesson_without_any_code_block_fails(): void
    {
        $lessons = $this->lessonWithRichContent([
            'type' => 'doc', 'version' => 1,
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Nur Text.']]]],
        ]);

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'keinen einzigen Codeblock')));
    }

    public function test_rich_content_code_block_without_leseanleitung_fails(): void
    {
        $lessons = $this->lessonWithRichContent([
            'type' => 'doc', 'version' => 1,
            'content' => [
                ['type' => 'code_block', 'attrs' => ['variant' => 'terminal'], 'text' => '$ dcmdump datei.dcm'],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Kein fester Wortlaut.']]],
            ],
        ]);

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Was du daran abliest')));
    }

    public function test_rich_content_code_block_with_leseanleitung_in_the_next_block_passes(): void
    {
        $lessons = $this->lessonWithRichContent([
            'type' => 'doc', 'version' => 1,
            'content' => [
                ['type' => 'code_block', 'attrs' => ['variant' => 'terminal'], 'text' => '$ dcmdump datei.dcm'],
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => 'Was du daran abliest:', 'marks' => [['type' => 'bold']]],
                    ['type' => 'text', 'text' => ' Test.'],
                ]],
            ],
        ]);

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Was du daran abliest')));
    }

    public function test_rich_content_diagram_code_block_is_excluded_from_the_leseanleitung_rule(): void
    {
        $lessons = $this->lessonWithRichContent([
            'type' => 'doc', 'version' => 1,
            'content' => [
                ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => 'A -> B'],
            ],
        ]);

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Was du daran abliest') || str_contains($m, 'Codeblock')));
    }

    public function test_rich_content_unknown_glossary_term_fails(): void
    {
        $lessons = $this->lessonWithRichContent([
            'type' => 'doc', 'version' => 1,
            'content' => [
                ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => 'x'],
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'glossary_term', 'attrs' => ['slug' => 'nicht-vorhanden']],
                ]],
            ],
        ]);

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: ['dicom' => []], datasets: [], skills: [],
        );

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, '{{term:nicht-vorhanden}} existiert nicht')));
    }

    public function test_rich_content_glossary_term_nested_inside_a_callout_is_found(): void
    {
        $lessons = $this->lessonWithRichContent([
            'type' => 'doc', 'version' => 1,
            'content' => [
                ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => 'x'],
                ['type' => 'callout', 'attrs' => ['kind' => 'info'], 'content' => [
                    ['type' => 'paragraph', 'content' => [
                        ['type' => 'glossary_term', 'attrs' => ['slug' => 'nicht-vorhanden']],
                    ]],
                ]],
            ],
        ]);

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, '{{term:nicht-vorhanden}} existiert nicht')));
    }

    public function test_rich_content_tool_used_but_not_declared_fails(): void
    {
        $lessons = $this->lessonWithRichContent(
            [
                'type' => 'doc', 'version' => 1,
                'content' => [
                    ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => '$ dcmdump datei.dcm'],
                ],
            ],
            ['tools' => []],
        );

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: ['dcmdump' => ['purpose' => 'x']], toolsRaw: '', glossary: [], datasets: [], skills: [],
        );

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Werkzeug "dcmdump" wird benutzt')));
    }

    public function test_rich_content_node_envelope_counts_sections_together_for_the_code_block_rule(): void
    {
        $nodes = [
            'sample' => [
                'slug' => 'sample',
                'def' => null,
                'def_file' => 'nodes/sample/node.yml',
                'def_raw' => null,
                'md_file' => 'nodes/sample/de.md',
                'md_raw' => null,
                'frontmatter' => null,
                'body' => null,
                'body_start_line' => 1,
                'rich_content' => [
                    'type' => 'node_content',
                    'version' => 1,
                    'briefing' => ['type' => 'doc', 'version' => 1, 'content' => [
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Briefing.']]],
                    ]],
                    'hints' => [
                        'h1' => ['type' => 'doc', 'version' => 1, 'content' => [
                            ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => 'x'],
                        ]],
                    ],
                    'write_up' => ['type' => 'doc', 'version' => 1, 'content' => []],
                ],
            ],
        ];

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: [], nodes: $nodes, exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertFalse(
            collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'keinen einzigen Codeblock')),
            'der Codeblock im Hint sollte fuer die gesamte Node zaehlen',
        );
    }
}

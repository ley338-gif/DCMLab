<?php

namespace Tests\Unit\Content;

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
}

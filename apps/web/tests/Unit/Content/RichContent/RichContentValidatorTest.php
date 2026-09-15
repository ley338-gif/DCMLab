<?php

namespace Tests\Unit\Content\RichContent;

use App\Content\RichContent\RichContentValidator;
use Tests\TestCase;

class RichContentValidatorTest extends TestCase
{
    public function test_a_minimal_valid_document_has_no_issues(): void
    {
        $doc = [
            'type' => 'doc',
            'version' => 1,
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hallo.']]],
            ],
        ];

        $this->assertSame([], (new RichContentValidator)->validate($doc));
    }

    public function test_it_rejects_a_document_that_is_not_an_array(): void
    {
        $this->assertNotSame([], (new RichContentValidator)->validate('nicht ein array'));
    }

    public function test_it_rejects_the_wrong_top_level_type(): void
    {
        $issues = (new RichContentValidator)->validate(['type' => 'not-a-doc', 'version' => 1, 'content' => []]);

        $this->assertTrue(collect($issues)->contains(fn ($issue) => str_contains($issue, 'doc.type')));
    }

    public function test_it_rejects_an_unsupported_version(): void
    {
        $issues = (new RichContentValidator)->validate(['type' => 'doc', 'version' => 2, 'content' => []]);

        $this->assertTrue(collect($issues)->contains(fn ($issue) => str_contains($issue, 'doc.version')));
    }

    public function test_it_rejects_an_unknown_block_type(): void
    {
        $issues = (new RichContentValidator)->validate([
            'type' => 'doc', 'version' => 1,
            'content' => [['type' => 'video', 'content' => []]],
        ]);

        $this->assertTrue(collect($issues)->contains(fn ($issue) => str_contains($issue, 'unbekannter Block-Typ')));
    }

    public function test_it_rejects_a_heading_without_a_valid_level(): void
    {
        $issues = (new RichContentValidator)->validate([
            'type' => 'doc', 'version' => 1,
            'content' => [['type' => 'heading', 'attrs' => ['level' => 9], 'content' => [['type' => 'text', 'text' => 'X']]]],
        ]);

        $this->assertTrue(collect($issues)->contains(fn ($issue) => str_contains($issue, 'attrs.level')));
    }

    public function test_it_accepts_every_valid_heading_level(): void
    {
        foreach (range(1, 6) as $level) {
            $doc = [
                'type' => 'doc', 'version' => 1,
                'content' => [['type' => 'heading', 'attrs' => ['level' => $level], 'content' => [['type' => 'text', 'text' => 'X']]]],
            ];

            $this->assertSame([], (new RichContentValidator)->validate($doc), "level {$level} sollte gueltig sein");
        }
    }

    public function test_it_rejects_a_code_block_with_an_unknown_variant(): void
    {
        $issues = (new RichContentValidator)->validate([
            'type' => 'doc', 'version' => 1,
            'content' => [['type' => 'code_block', 'attrs' => ['variant' => 'unbekannt'], 'text' => 'x']],
        ]);

        $this->assertTrue(collect($issues)->contains(fn ($issue) => str_contains($issue, 'attrs.variant')));
    }

    public function test_it_accepts_every_code_block_variant(): void
    {
        foreach (['code', 'console', 'terminal', 'diagram', 'mermaid'] as $variant) {
            $doc = [
                'type' => 'doc', 'version' => 1,
                'content' => [['type' => 'code_block', 'attrs' => ['variant' => $variant], 'text' => 'x']],
            ];

            $this->assertSame([], (new RichContentValidator)->validate($doc), "variant {$variant} sollte gueltig sein");
        }
    }

    public function test_it_rejects_an_empty_text_node(): void
    {
        $issues = (new RichContentValidator)->validate([
            'type' => 'doc', 'version' => 1,
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '']]]],
        ]);

        $this->assertTrue(collect($issues)->contains(fn ($issue) => str_contains($issue, '.text')));
    }

    public function test_it_rejects_an_unknown_mark_type(): void
    {
        $issues = (new RichContentValidator)->validate([
            'type' => 'doc', 'version' => 1,
            'content' => [['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'X', 'marks' => [['type' => 'blink']]],
            ]]],
        ]);

        $this->assertTrue(collect($issues)->contains(fn ($issue) => str_contains($issue, 'unbekannter Mark-Typ')));
    }

    public function test_it_rejects_a_link_mark_without_an_href(): void
    {
        $issues = (new RichContentValidator)->validate([
            'type' => 'doc', 'version' => 1,
            'content' => [['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'X', 'marks' => [['type' => 'link', 'attrs' => []]]],
            ]]],
        ]);

        $this->assertTrue(collect($issues)->contains(fn ($issue) => str_contains($issue, 'attrs.href')));
    }

    public function test_it_accepts_a_valid_glossary_term(): void
    {
        $doc = [
            'type' => 'doc', 'version' => 1,
            'content' => [['type' => 'paragraph', 'content' => [
                ['type' => 'glossary_term', 'attrs' => ['slug' => 'dicom']],
            ]]],
        ];

        $this->assertSame([], (new RichContentValidator)->validate($doc));
    }

    public function test_it_validates_nested_lists_and_reports_the_full_path(): void
    {
        $issues = (new RichContentValidator)->validate([
            'type' => 'doc', 'version' => 1,
            'content' => [['type' => 'bullet_list', 'content' => [
                ['type' => 'list_item', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '']]],
                ]],
            ]]],
        ]);

        $this->assertSame(
            ['doc.content[0].content[0].content[0].content[0].text: muss ein nicht-leerer String sein'],
            $issues,
        );
    }

    public function test_it_validates_a_table(): void
    {
        $doc = [
            'type' => 'doc', 'version' => 1,
            'content' => [['type' => 'table', 'content' => [
                ['type' => 'table_row', 'content' => [
                    ['type' => 'table_cell', 'attrs' => ['header' => true], 'content' => [
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Tag']]],
                    ]],
                ]],
            ]]],
        ];

        $this->assertSame([], (new RichContentValidator)->validate($doc));
    }

    public function test_it_rejects_a_document_with_content_that_is_not_a_list(): void
    {
        $issues = (new RichContentValidator)->validate(['type' => 'doc', 'version' => 1, 'content' => 'nope']);

        $this->assertSame(['doc.content: muss eine Liste sein'], $issues);
    }
}

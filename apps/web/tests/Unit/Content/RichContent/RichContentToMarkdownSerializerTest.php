<?php

namespace Tests\Unit\Content\RichContent;

use App\Content\FrontMatter;
use App\Content\NodeSections;
use App\Content\QuizContent;
use App\Content\RichContent\MarkdownToRichContentConverter;
use App\Content\RichContent\NodePayloadNormalizer;
use App\Content\RichContent\RichContentToMarkdownSerializer;
use App\Content\RichContent\UnrepresentableRichContentException;
use App\Content\RichContent\UnsupportedRichContentVersionException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ADR 0122, Phase 1: Kerneigenschaft ist der Round-Trip auf
 * Rich-Content-Ebene -- `convert(serialize(convert(md))) == convert(md)` --
 * fuer jede Lektion und jede Node im Bestand, plus gezielte Faelle je
 * Blocktyp und die bewussten Abbrueche.
 */
class RichContentToMarkdownSerializerTest extends TestCase
{
    private function convert(string $markdown): array
    {
        return (new MarkdownToRichContentConverter)->convert($markdown);
    }

    private function serialize(array $document): string
    {
        return (new RichContentToMarkdownSerializer)->serialize($document);
    }

    private function doc(array ...$blocks): array
    {
        return ['type' => 'doc', 'version' => 1, 'content' => $blocks];
    }

    private function assertRoundTrip(array $document): void
    {
        $this->assertSame(
            RichContentToMarkdownSerializer::canonical($document),
            RichContentToMarkdownSerializer::canonical($this->convert($this->serialize($document))),
        );
    }

    private function assertMarkdownRoundTrip(string $markdown, ?string $expected = null): void
    {
        $document = $this->convert($markdown);
        $serialized = $this->serialize($document);

        $this->assertSame($expected ?? $markdown, $serialized);
        $this->assertRoundTrip($document);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function lessonFiles(): iterable
    {
        foreach (glob(dirname(__DIR__, 6).'/content/lessons/*/de.md') ?: [] as $file) {
            yield basename(dirname($file)) => [$file];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nodeFiles(): iterable
    {
        foreach (glob(dirname(__DIR__, 6).'/content/nodes/*/de.md') ?: [] as $file) {
            yield basename(dirname($file)) => [$file];
        }
    }

    #[DataProvider('lessonFiles')]
    public function test_every_lesson_in_the_corpus_round_trips(string $file): void
    {
        $body = FrontMatter::parse((string) file_get_contents($file))['body'];
        $split = QuizContent::splitBody($body);
        // Dieselbe Prosa-Extraktion wie LessonActivity::legacyProse().
        $prose = trim($split['before']."\n\n".$split['after']);

        $this->assertRoundTrip($this->convert($prose));
    }

    #[DataProvider('nodeFiles')]
    public function test_every_node_in_the_corpus_round_trips(string $file): void
    {
        $body = FrontMatter::parse((string) file_get_contents($file))['body'];
        $normalizer = new NodePayloadNormalizer;
        $envelope = $normalizer->normalize(['body' => $body])['rich_content'];

        $markdown = (new RichContentToMarkdownSerializer)->serializeNodeContent($envelope, array_keys($envelope['hints']));

        $this->assertSame(
            RichContentToMarkdownSerializer::canonical($envelope),
            RichContentToMarkdownSerializer::canonical($normalizer->normalize(['body' => $markdown])['rich_content']),
        );
        $this->assertSame(array_keys(NodeSections::parse($body)['hints']), array_keys(NodeSections::parse($markdown)['hints']));
    }

    public function test_the_corpus_fixture_directories_are_not_empty(): void
    {
        $this->assertGreaterThan(50, iterator_count(self::lessonFiles()));
        $this->assertGreaterThan(30, iterator_count(self::nodeFiles()));
    }

    public function test_paragraphs_headings_and_rules_keep_their_markdown(): void
    {
        $this->assertMarkdownRoundTrip("## Überschrift mit `code`\n\nEin Absatz mit **fett**, *kursiv* und [Link](https://example.org).\nZweite Zeile.\n\n---\n\n### Ebene 3");
    }

    public function test_nested_marks_keep_their_order(): void
    {
        $this->assertRoundTrip($this->doc(['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'beides', 'marks' => [['type' => 'bold'], ['type' => 'italic']]],
            ['type' => 'text', 'text' => ' und '],
            ['type' => 'text', 'text' => 'andersrum', 'marks' => [['type' => 'italic'], ['type' => 'bold']]],
            ['type' => 'text', 'text' => ' und '],
            ['type' => 'text', 'text' => 'Code im Link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.org/a_(b)']], ['type' => 'code']]],
        ]]));
    }

    public function test_glossary_terms_are_written_as_term_references(): void
    {
        $this->assertMarkdownRoundTrip('Eine {{term:association}} vor dem {{term:c-echo}}.');
        $this->assertMarkdownRoundTrip('**Fett mit {{term:scu}} mittendrin**');
    }

    public function test_markdown_significant_text_is_escaped(): void
    {
        $this->assertRoundTrip($this->doc(
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '# kein Heading * kein [Link] <b>kein HTML</b> &amp; {{nur Klammern}} _x_ ~y~ snake_case https://example.org a@b.de']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => '1. keine Liste'],
                ['type' => 'hard_break'],
                ['type' => 'text', 'text' => '- auch nicht'],
                ['type' => 'hard_break'],
                ['type' => 'text', 'text' => '> kein Zitat'],
                ['type' => 'hard_break'],
                ['type' => 'text', 'text' => '==='],
            ]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Endet auf #']]],
        ));
    }

    public function test_lists_including_nesting_and_adjacent_lists(): void
    {
        $this->assertMarkdownRoundTrip("- eins\n- zwei\n  - verschachtelt\n\n1. erstens\n2. zweitens");

        $this->assertRoundTrip($this->doc(
            ['type' => 'bullet_list', 'content' => [['type' => 'list_item', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'a']]]]]]],
            ['type' => 'bullet_list', 'content' => [['type' => 'list_item', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'b']]]]]]],
            ['type' => 'ordered_list', 'content' => [['type' => 'list_item', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'c']]]]]]],
            ['type' => 'ordered_list', 'content' => [['type' => 'list_item', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'd']]]]]]],
        ));
    }

    public function test_blockquote(): void
    {
        $this->assertMarkdownRoundTrip("> Zitat\n>\n> - mit Liste");
    }

    public function test_every_code_block_variant_the_converter_knows(): void
    {
        $this->assertMarkdownRoundTrip("<!-- kein-beispiel -->\n```\nA ─ B\n```");
        $this->assertMarkdownRoundTrip("```mermaid\ngraph TD\n```");
        $this->assertMarkdownRoundTrip("```http\nGET / HTTP/1.1\n```");
        $this->assertMarkdownRoundTrip("```\n\$ echoscu localhost 104\n```");
        $this->assertMarkdownRoundTrip("```\nnur Ausgabe\n```");
        $this->assertMarkdownRoundTrip("````\nenthaelt ```\n````");

        // `terminal` mit Prompt-Zeile geht nur als eingerueckter Codeblock.
        $this->assertRoundTrip($this->doc(['type' => 'code_block', 'attrs' => ['variant' => 'terminal'], 'text' => "\$ ls\nausgabe"]));
    }

    public function test_tables_escape_pipes_and_keep_empty_header_cells(): void
    {
        $this->assertMarkdownRoundTrip("| | **A** |\n|---|---|\n| `a\\|b` | {{term:vr}} |");
    }

    public function test_self_checks(): void
    {
        $this->assertMarkdownRoundTrip("<details>\n<summary>Frage mit `code`?</summary>\n\nAntwort.\n</details>");
        $this->assertMarkdownRoundTrip("<details>\n<summary>Mit Liste?</summary>\n\n- a\n- b\n\n</details>");
    }

    public function test_heading_text_survives_so_the_heading_slug_stays_stable(): void
    {
        $markdown = '## Suchanker nach Fehlerdomäne';

        $this->assertStringContainsString('Suchanker nach Fehlerdomäne', $this->serialize($this->convert($markdown)));
        $this->assertMarkdownRoundTrip($markdown);
    }

    public function test_node_content_uses_the_hint_order_from_the_metadata(): void
    {
        $envelope = [
            'type' => 'node_content',
            'version' => 1,
            'briefing' => $this->convert('Lage.'),
            'hints' => ['h10' => $this->convert('Zehn.'), 'h2' => $this->convert('Zwei.')],
            'write_up' => $this->convert("### Der Weg\n\nSo."),
        ];

        $markdown = (new RichContentToMarkdownSerializer)->serializeNodeContent($envelope, ['h2', 'h10']);

        $this->assertSame("## Briefing\n\nLage.\n\n## Hints\n\n### h2\n\nZwei.\n\n### h10\n\nZehn.\n\n## Write-up\n\n### Der Weg\n\nSo.", $markdown);
        $this->assertSame(['h2', 'h10'], array_keys(NodeSections::parse($markdown)['hints']));
    }

    public function test_a_node_section_that_would_split_on_reread_is_rejected(): void
    {
        $this->expectException(UnrepresentableRichContentException::class);

        (new RichContentToMarkdownSerializer)->serializeNodeContent([
            'type' => 'node_content', 'version' => 1,
            'briefing' => $this->convert('## Zweites Briefing'),
            'hints' => [], 'write_up' => $this->convert('x'),
        ]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unrepresentableBlocks(): iterable
    {
        yield 'callout' => [['type' => 'callout', 'attrs' => ['kind' => 'info'], 'content' => []]];
        yield 'dicom_tag_table' => [['type' => 'dicom_tag_table', 'content' => []]];
        yield 'dicom_dump' => [['type' => 'code_block', 'attrs' => ['variant' => 'dicom_dump'], 'text' => '(0010,0010) PN [X]']];
        yield 'unbekannter Typ' => [['type' => 'video']];
        yield 'console ohne Prompt' => [['type' => 'code_block', 'attrs' => ['variant' => 'console'], 'text' => 'nur Ausgabe']];
        yield 'code mit Sprache mermaid' => [['type' => 'code_block', 'attrs' => ['variant' => 'code', 'language' => 'mermaid'], 'text' => 'x']];
        yield 'Tabelle ohne Kopfzeile' => [['type' => 'table', 'content' => [['type' => 'table_row', 'content' => [
            ['type' => 'table_cell', 'attrs' => ['header' => false], 'content' => [['type' => 'paragraph', 'content' => []]]],
        ]]]]];
        yield 'unbekannte Markierung' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'underline']]]]]];
        yield 'woertliches {{term:x}}' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'siehe {{term:x}}']]]];
        yield 'Fettdruck direkt vor Buchstabe nach Satzzeichen (Lektion 2.1)' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Was du daran abliest:', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => 'Fünf eigene Schritte'],
        ]]];
        yield 'unbekannter Inline-Typ' => [['type' => 'paragraph', 'content' => [['type' => 'image']]]];
    }

    #[DataProvider('unrepresentableBlocks')]
    public function test_unrepresentable_content_throws_instead_of_being_dropped(array $block): void
    {
        $this->expectException(UnrepresentableRichContentException::class);

        $this->serialize($this->doc($block));
    }

    public function test_an_unknown_document_version_throws(): void
    {
        $this->expectException(UnsupportedRichContentVersionException::class);

        $this->serialize(['type' => 'doc', 'version' => 2, 'content' => []]);
    }

    public function test_an_empty_paragraph_is_the_only_thing_left_out(): void
    {
        $this->assertSame('a', $this->serialize($this->doc(
            ['type' => 'paragraph', 'content' => []],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'a']]],
        )));
    }

    public function test_key_order_of_the_input_does_not_matter(): void
    {
        // Postgres-jsonb liefert Objektschluessel umsortiert zurueck.
        $reordered = ['content' => [['content' => [['text' => 'x', 'marks' => [['attrs' => ['href' => 'https://a.de'], 'type' => 'link']], 'type' => 'text']], 'type' => 'paragraph']], 'type' => 'doc', 'version' => 1];

        $this->assertSame('[x](https://a.de)', $this->serialize($reordered));
    }
}

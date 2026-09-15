<?php

namespace Tests\Unit\Content\RichContent;

use App\Content\FrontMatter;
use App\Content\RichContent\MarkdownToRichContentConverter;
use App\Content\RichContent\RichContentValidator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MarkdownToRichContentConverterTest extends TestCase
{
    private function convert(string $markdown): array
    {
        return (new MarkdownToRichContentConverter)->convert($markdown);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blocks(string $markdown): array
    {
        return $this->convert($markdown)['content'];
    }

    public function test_it_produces_a_valid_document_shell(): void
    {
        $doc = $this->convert('Hallo.');

        $this->assertSame('doc', $doc['type']);
        $this->assertSame(1, $doc['version']);
        $this->assertIsArray($doc['content']);
    }

    public function test_it_converts_a_paragraph(): void
    {
        $blocks = $this->blocks('Ein einfacher Absatz.');

        $this->assertSame([
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ein einfacher Absatz.']]],
        ], $blocks);
    }

    public function test_it_converts_a_heading_with_its_level(): void
    {
        $blocks = $this->blocks('### Ein Titel');

        $this->assertSame('heading', $blocks[0]['type']);
        $this->assertSame(3, $blocks[0]['attrs']['level']);
        $this->assertSame('Ein Titel', $blocks[0]['content'][0]['text']);
    }

    public function test_it_converts_bold_and_italic_marks(): void
    {
        $blocks = $this->blocks('**fett** und *kursiv*');

        $this->assertSame([['type' => 'bold']], $blocks[0]['content'][0]['marks']);
        $this->assertSame([['type' => 'italic']], $blocks[0]['content'][2]['marks']);
    }

    public function test_it_converts_inline_code_as_a_mark(): void
    {
        $blocks = $this->blocks('Nutze `dcmdump` dafuer.');

        $codeNode = collect($blocks[0]['content'])->firstWhere('text', 'dcmdump');
        $this->assertSame([['type' => 'code']], $codeNode['marks']);
    }

    public function test_it_converts_a_link(): void
    {
        $blocks = $this->blocks('[DCMLab](https://example.test)');

        $this->assertSame([['type' => 'link', 'attrs' => ['href' => 'https://example.test']]], $blocks[0]['content'][0]['marks']);
        $this->assertSame('DCMLab', $blocks[0]['content'][0]['text']);
    }

    public function test_it_converts_a_bullet_list(): void
    {
        $blocks = $this->blocks("- Eins\n- Zwei");

        $this->assertSame('bullet_list', $blocks[0]['type']);
        $this->assertCount(2, $blocks[0]['content']);
        $this->assertSame('list_item', $blocks[0]['content'][0]['type']);
    }

    public function test_it_converts_an_ordered_list(): void
    {
        $blocks = $this->blocks("1. Eins\n2. Zwei");

        $this->assertSame('ordered_list', $blocks[0]['type']);
    }

    public function test_it_converts_a_blockquote(): void
    {
        $blocks = $this->blocks('> Ein Zitat.');

        $this->assertSame('blockquote', $blocks[0]['type']);
        $this->assertSame('paragraph', $blocks[0]['content'][0]['type']);
    }

    public function test_it_converts_a_fenced_code_block_with_a_language_to_the_code_variant(): void
    {
        $blocks = $this->blocks("```python\nprint(1)\n```");

        $this->assertSame('code_block', $blocks[0]['type']);
        $this->assertSame('code', $blocks[0]['attrs']['variant']);
        $this->assertSame('python', $blocks[0]['attrs']['language']);
        $this->assertSame('print(1)', $blocks[0]['text']);
    }

    public function test_it_converts_a_mermaid_fenced_code_block(): void
    {
        $blocks = $this->blocks("```mermaid\ngraph TD;\n```");

        $this->assertSame('mermaid', $blocks[0]['attrs']['variant']);
    }

    public function test_it_converts_an_unlabeled_code_block_with_a_prompt_line_to_console(): void
    {
        $blocks = $this->blocks("```\n\$ echoscu foo\nErfolgreich.\n```");

        $this->assertSame('console', $blocks[0]['attrs']['variant']);
    }

    public function test_it_converts_an_unlabeled_code_block_without_a_prompt_line_to_terminal(): void
    {
        $blocks = $this->blocks("```\nNur Ausgabe, kein Befehl.\n```");

        $this->assertSame('terminal', $blocks[0]['attrs']['variant']);
    }

    public function test_it_converts_a_kein_beispiel_marked_code_block_to_diagram(): void
    {
        $blocks = $this->blocks("<!-- kein-beispiel -->\n```\nA -> B\n```");

        $this->assertSame('diagram', $blocks[0]['attrs']['variant']);
    }

    public function test_it_splits_glossary_terms_out_of_plain_text(): void
    {
        $blocks = $this->blocks('Siehe {{term:scu}} und {{term:scp}}.');

        $types = array_column($blocks[0]['content'], 'type');
        $this->assertSame(['text', 'glossary_term', 'text', 'glossary_term', 'text'], $types);
        $this->assertSame('scu', $blocks[0]['content'][1]['attrs']['slug']);
        $this->assertSame('scp', $blocks[0]['content'][3]['attrs']['slug']);
    }

    public function test_it_converts_a_gfm_table_with_a_header_row(): void
    {
        $blocks = $this->blocks("| Tag | Wert |\n|---|---|\n| (0008,0060) | CT |");

        $this->assertSame('table', $blocks[0]['type']);
        $this->assertTrue($blocks[0]['content'][0]['content'][0]['attrs']['header']);
        $this->assertFalse($blocks[0]['content'][1]['content'][0]['attrs']['header']);
        $this->assertSame('Tag', $blocks[0]['content'][0]['content'][0]['content'][0]['content'][0]['text']);
    }

    public function test_every_converted_document_in_this_test_class_is_schema_valid(): void
    {
        $samples = [
            '## Titel',
            "- Eins\n- Zwei",
            '> Zitat mit **fett**.',
            "```python\nprint(1)\n```",
            "<!-- kein-beispiel -->\n```\nA -> B\n```",
            "| A | B |\n|---|---|\n| 1 | 2 |",
            'Ein Link zu {{term:dicom}} und [mehr](https://example.test).',
        ];

        $validator = new RichContentValidator;

        foreach ($samples as $sample) {
            $this->assertSame([], $validator->validate($this->convert($sample)), "sollte valide sein: {$sample}");
        }
    }

    /**
     * Kein Anspruch auf verlustfreie Konvertierung des GESAMTEN
     * Lektions-Bodys (u. a. `<details>`-Selbstcheck-Bloecke sind bewusst
     * noch nicht abgedeckt, siehe Klassendoc von
     * MarkdownToRichContentConverter) -- dieser Test beweist nur, dass ein
     * echter, komplexer Bestand nicht abstuerzt und ein valides Dokument
     * ergibt, und dass die zentralen Konstrukte (Tabelle, kein-beispiel-
     * Diagramm, annotierter Code-Block, Glossarbegriff) tatsaechlich
     * ankommen.
     */
    public function test_it_converts_a_real_lesson_body_into_a_valid_document(): void
    {
        $raw = File::get(config('content.path').'/lessons/1.0/de.md');
        $body = FrontMatter::parse($raw)['body'];

        $doc = $this->convert($body);

        $this->assertSame([], (new RichContentValidator)->validate($doc));

        $types = collect($doc['content'])->pluck('type');
        $this->assertTrue($types->contains('table'), 'sollte die Tabelle am Lektionsende enthalten');
        $this->assertTrue($types->contains('heading'), 'sollte Ueberschriften enthalten');

        $codeBlocks = collect($doc['content'])->where('type', 'code_block');
        $this->assertTrue($codeBlocks->pluck('attrs.variant')->contains('diagram'), 'sollte den kein-beispiel-Block als diagram erkennen');
        $this->assertTrue($codeBlocks->pluck('attrs.variant')->contains('code'), 'sollte den python-Block als code erkennen');

        $glossaryTerms = collect($doc['content'])
            ->pluck('content')
            ->flatten(1)
            ->where('type', 'glossary_term')
            ->pluck('attrs.slug');
        $this->assertTrue($glossaryTerms->contains('scu'));
        $this->assertTrue($glossaryTerms->contains('scp'));
    }
}

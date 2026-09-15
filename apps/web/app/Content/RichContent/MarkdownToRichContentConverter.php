<?php

namespace App\Content\RichContent;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;

/**
 * Konvertiert bestehenden Lesson-/Node-Fliesstext (Markdown) in ein
 * Rich-Content-Dokument nach dem Schema aus ADR 0111 (CMS-7a) -- der
 * "Legacy-Konverter", der noetig ist, bevor irgendein bestehender Body
 * (42 Lektionen, 17 Nodes) verlustfrei ins neue Modell umziehen kann.
 *
 * Arbeitet auf dem echten CommonMark-AST (`MarkdownParser::parse()`,
 * dieselbe Umgebung wie `MarkdownRenderer`, siehe `MarkdownEnvironmentFactory`)
 * statt auf dem gerenderten HTML oder eigenem Regex-Parsing -- ein Baum, den
 * eine ausgereifte Bibliothek schon korrekt aufgebaut hat, ist die
 * verlaesslichere Quelle als ein zweiter, selbstgebauter Parser.
 *
 * Erkennt neben dem `kein-beispiel`-Marker auch ein zweites, aus mehreren
 * Geschwister-Bloecken bestehendes Muster: `<details><summary>Frage?
 * </summary>` (ein eigener HtmlBlock, da CommonMark die beiden Zeilen ohne
 * Leerzeile dazwischen zusammenfasst) gefolgt vom eigentlichen Inhalt und
 * einem abschliessenden `</details>`-HtmlBlock wird zu einem `self_check`-
 * Knoten (ADR 0112) -- ein Lernbaustein, kein beliebiges `raw_html`.
 *
 * Bewusst (noch) nicht abgedeckt, weil im echten Bestand nicht vorkommend
 * (Stand ADR 0111/0112): Bilder, horizontale Trennlinien (`---` als
 * eigener Block, nicht am Dokumentende) und jedes andere eingebettete
 * rohe HTML -- ein solcher Knoten wird beim Konvertieren stillschweigend
 * uebersprungen, nicht als Fehler gemeldet.
 */
final class MarkdownToRichContentConverter
{
    private readonly MarkdownParser $parser;

    public function __construct()
    {
        $this->parser = new MarkdownParser(MarkdownEnvironmentFactory::make());
    }

    /**
     * @return array{type: "doc", version: 1, content: list<array<string, mixed>>}
     */
    public function convert(string $markdown): array
    {
        $document = $this->parser->parse($markdown);

        return [
            'type' => 'doc',
            'version' => 1,
            'content' => $this->convertBlocks($document->children()),
        ];
    }

    /**
     * Index-basiert statt eines einfachen `foreach`, weil `self_check`
     * (ADR 0112) sich ueber mehrere Geschwister-Knoten erstreckt -- die
     * Erkennung muss beim Start-Marker nach vorne schauen koennen, statt
     * jeden Knoten isoliert zu betrachten.
     *
     * @param  iterable<Node>  $nodes
     * @return list<array<string, mixed>>
     */
    private function convertBlocks(iterable $nodes): array
    {
        $nodeList = is_array($nodes) ? array_values($nodes) : iterator_to_array($nodes, false);
        $count = count($nodeList);
        $blocks = [];
        $index = 0;

        while ($index < $count) {
            $summary = $this->selfCheckSummary($nodeList[$index]);

            if ($summary !== null) {
                [$content, $consumed] = $this->convertSelfCheckBody($nodeList, $index + 1);
                $blocks[] = ['type' => 'self_check', 'attrs' => ['summary' => $summary], 'content' => $content];
                $index += 1 + $consumed;

                continue;
            }

            $block = $this->convertBlock($nodeList[$index]);

            if ($block !== null) {
                $blocks[] = $block;
            }

            $index++;
        }

        return $blocks;
    }

    /**
     * Verarbeitet Geschwister-Knoten ab `$startIndex` als `self_check`-
     * Inhalt, bis der abschliessende `</details>`-HtmlBlock kommt (der
     * selbst nicht Teil des Inhalts wird) oder die Liste endet (kein
     * schliessendes Tag gefunden -- dann bewusst der Rest als Inhalt statt
     * alles zu verwerfen).
     *
     * @param  list<Node>  $nodeList
     * @return array{0: list<array<string, mixed>>, 1: int} Inhalt und Anzahl konsumierter Knoten (inklusive Schlusstag)
     */
    private function convertSelfCheckBody(array $nodeList, int $startIndex): array
    {
        $content = [];
        $count = count($nodeList);
        $index = $startIndex;

        while ($index < $count) {
            if ($this->isClosingDetailsTag($nodeList[$index])) {
                return [$content, $index - $startIndex + 1];
            }

            $block = $this->convertBlock($nodeList[$index]);

            if ($block !== null) {
                $content[] = $block;
            }

            $index++;
        }

        return [$content, $index - $startIndex];
    }

    /**
     * Erkennt den Start eines `self_check` (ADR 0112): CommonMark fasst
     * `<details>` und die direkt folgende `<summary>...</summary>`-Zeile
     * (keine Leerzeile dazwischen) zu einem einzigen HtmlBlock zusammen --
     * dessen Text ist roh, keine Markdown-Formatierung darin wird aufgeloest.
     */
    private function selfCheckSummary(Node $node): ?string
    {
        if (! $node instanceof HtmlBlock || ! str_starts_with(ltrim($node->getLiteral()), '<details')) {
            return null;
        }

        if (preg_match('/<summary>(.*?)<\/summary>/su', $node->getLiteral(), $match) !== 1) {
            return null;
        }

        return trim($match[1]);
    }

    private function isClosingDetailsTag(Node $node): bool
    {
        return $node instanceof HtmlBlock && preg_match('/^<\/details\s*>$/i', trim($node->getLiteral())) === 1;
    }

    /**
     * @return array<string, mixed>|null null, wenn dieser Knotentyp (noch)
     *                                   keine Entsprechung im Schema hat
     */
    private function convertBlock(Node $node): ?array
    {
        return match (true) {
            $node instanceof Paragraph => ['type' => 'paragraph', 'content' => $this->convertInlines($node->children())],
            $node instanceof Heading => ['type' => 'heading', 'attrs' => ['level' => $node->getLevel()], 'content' => $this->convertInlines($node->children())],
            $node instanceof BlockQuote => ['type' => 'blockquote', 'content' => $this->convertBlocks($node->children())],
            $node instanceof ListBlock => $this->convertList($node),
            $node instanceof FencedCode => $this->convertFencedCode($node),
            $node instanceof IndentedCode => ['type' => 'code_block', 'attrs' => ['variant' => 'terminal'], 'text' => rtrim($node->getLiteral(), "\n")],
            $node instanceof Table => $this->convertTable($node),
            // HtmlBlock ist an dieser Stelle entweder der "kein-beispiel"-
            // Marker (von convertFencedCode() der folgenden FencedCode
            // zugeordnet), ein self_check-Start/-Ende (von convertBlocks()
            // bereits konsumiert, bevor convertBlock() ueberhaupt aufgerufen
            // wird) oder anderes rohes HTML (siehe Klassendoc) -- in jedem
            // Fall hier nichts mehr zu tun.
            $node instanceof HtmlBlock => null,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function convertList(ListBlock $node): array
    {
        $items = [];

        foreach ($node->children() as $item) {
            if ($item instanceof ListItem) {
                $items[] = ['type' => 'list_item', 'content' => $this->convertBlocks($item->children())];
            }
        }

        return [
            'type' => $node->getListData()->type === ListBlock::TYPE_ORDERED ? 'ordered_list' : 'bullet_list',
            'content' => $items,
        ];
    }

    /**
     * Variante wie `MarkdownRenderer::markMermaidBlocks()`/
     * `markCommandBlocks()`: `<!-- kein-beispiel -->` direkt davor ->
     * Diagramm, Sprache `mermaid` -> Mermaid, andere Sprache -> annotierter
     * Code-Block, sonst nach Prompt-Zeilen ("$ "/"PS> ") entscheiden.
     *
     * @return array<string, mixed>
     */
    private function convertFencedCode(FencedCode $node): array
    {
        $text = rtrim($node->getLiteral(), "\n");
        $firstWord = $node->getInfoWords()[0] ?? '';
        $language = $firstWord !== '' ? $firstWord : null;

        if ($this->isKeinBeispielMarker($node->previous())) {
            return ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => $text];
        }

        if ($language === 'mermaid') {
            return ['type' => 'code_block', 'attrs' => ['variant' => 'mermaid'], 'text' => $text];
        }

        if ($language !== null) {
            return ['type' => 'code_block', 'attrs' => ['variant' => 'code', 'language' => $language], 'text' => $text];
        }

        $isConsole = collect(explode("\n", $text))->contains(
            fn (string $line): bool => preg_match('/^\s*(\$\s|PS>\s)/', $line) === 1,
        );

        return ['type' => 'code_block', 'attrs' => ['variant' => $isConsole ? 'console' : 'terminal'], 'text' => $text];
    }

    private function isKeinBeispielMarker(?Node $node): bool
    {
        return $node instanceof HtmlBlock && str_contains($node->getLiteral(), 'kein-beispiel');
    }

    /**
     * @return array<string, mixed>
     */
    private function convertTable(Table $node): array
    {
        $rows = [];

        foreach ($node->children() as $section) {
            if (! $section instanceof TableSection) {
                continue;
            }

            foreach ($section->children() as $row) {
                if ($row instanceof TableRow) {
                    $rows[] = $this->convertTableRow($row, $section->isHead());
                }
            }
        }

        return ['type' => 'table', 'content' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    private function convertTableRow(TableRow $row, bool $isHead): array
    {
        $cells = [];

        foreach ($row->children() as $cell) {
            if ($cell instanceof TableCell) {
                $cells[] = [
                    'type' => 'table_cell',
                    'attrs' => ['header' => $isHead],
                    'content' => [['type' => 'paragraph', 'content' => $this->convertInlines($cell->children())]],
                ];
            }
        }

        return ['type' => 'table_row', 'content' => $cells];
    }

    /**
     * @param  iterable<Node>  $nodes
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, mixed>>
     */
    private function convertInlines(iterable $nodes, array $marks = []): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $result = [...$result, ...$this->convertInline($node, $marks)];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, mixed>>
     */
    private function convertInline(Node $node, array $marks): array
    {
        return match (true) {
            $node instanceof Text => $this->convertText($node->getLiteral(), $marks),
            $node instanceof Code => [['type' => 'text', 'text' => $node->getLiteral(), 'marks' => [...$marks, ['type' => 'code']]]],
            $node instanceof Emphasis => $this->convertInlines($node->children(), [...$marks, ['type' => 'italic']]),
            $node instanceof Strong => $this->convertInlines($node->children(), [...$marks, ['type' => 'bold']]),
            $node instanceof Link => $this->convertInlines($node->children(), [...$marks, ['type' => 'link', 'attrs' => ['href' => $node->getUrl()]]]),
            $node instanceof Newline => [['type' => 'hard_break']],
            $node instanceof HtmlInline => $this->convertText($node->getLiteral(), $marks),
            default => [],
        };
    }

    /**
     * Loest `{{term:x}}` (kein gueltiges Markdown-Konstrukt, kommt als
     * reiner Text-Literal an, siehe `MarkdownRenderer`-Klassendoc) in
     * eigene `glossary_term`-Knoten auf, statt es als Text stehen zu
     * lassen -- der einzige Fall, in dem ein einzelner Text-Knoten in
     * mehrere Inline-Knoten aufgespalten wird.
     *
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, mixed>>
     */
    private function convertText(string $literal, array $marks): array
    {
        $parts = preg_split('/(\{\{term:[a-z0-9\-]+\}\})/', $literal, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false || count($parts) === 1) {
            return $literal === '' ? [] : [['type' => 'text', 'text' => $literal, ...($marks !== [] ? ['marks' => $marks] : [])]];
        }

        $nodes = [];

        foreach ($parts as $part) {
            if (preg_match('/^\{\{term:([a-z0-9\-]+)\}\}$/', $part, $match) === 1) {
                $nodes[] = ['type' => 'glossary_term', 'attrs' => ['slug' => $match[1]]];
            } else {
                $nodes[] = ['type' => 'text', 'text' => $part, ...($marks !== [] ? ['marks' => $marks] : [])];
            }
        }

        return $nodes;
    }
}

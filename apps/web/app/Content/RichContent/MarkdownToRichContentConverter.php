<?php

namespace App\Content\RichContent;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Block\AbstractBlock;
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
 * Horizontale Trennlinien (`---`/`***`/`___` als eigener Block) werden zu
 * `horizontal_rule` (ADR 0116, CMS-7d.1) -- `rich-content:audit` hat
 * gezeigt, dass sie im echten Bestand durchaus vorkommen (Lektion 1.0,
 * neun Mal, als visueller Abschnittstrenner), anders als in ADR
 * 0111/0112 angenommen.
 *
 * Bewusst (noch) nicht abgedeckt, weil im echten Bestand nicht vorkommend
 * (Stand ADR 0111/0112/0116): Bilder und jedes andere eingebettete rohe
 * HTML -- ein solcher Knoten wird beim Konvertieren stillschweigend
 * uebersprungen, nicht als Fehler gemeldet.
 *
 * `callout`, `dicom_tag_table` und `code_block.attrs.variant = dicom_dump`
 * (ADR 0114, CMS-7c) erkennt dieser Konverter bewusst nicht aus
 * bestehendem Markdown -- es gibt im echten Bestand kein zuverlaessiges
 * Signal, das einen generischen Absatz/Codeblock/eine generische Tabelle
 * von einem DCMLab-spezifischen Block unterscheidbar machen wuerde (anders
 * als `<!-- kein-beispiel -->` oder eine Sprachannotation). Diese drei
 * Typen sind reine Autoren-Konstrukte fuer den Editor (CMS-7c), keine
 * Legacy-Migrationsziele.
 *
 * Was hier "stillschweigend uebersprungen" heisst, verwirft `convert()`
 * nach wie vor -- fuer `rich-content:audit` (CMS-7d.1), das genau diese
 * Faelle blockierend statt still melden muss, protokolliert die Instanz
 * jeden uebersprungenen Knoten zusaetzlich, abrufbar ueber
 * `skippedNodes()` nach dem `convert()`-Aufruf.
 */
final class MarkdownToRichContentConverter
{
    private readonly MarkdownParser $parser;

    /**
     * @var list<array{type: string, line: int|null, snippet: string}>
     */
    private array $skips = [];

    public function __construct()
    {
        $this->parser = new MarkdownParser(MarkdownEnvironmentFactory::make());
    }

    /**
     * @return array{type: "doc", version: 1, content: list<array<string, mixed>>}
     */
    public function convert(string $markdown): array
    {
        $this->skips = [];
        $document = $this->parser->parse($markdown);

        return [
            'type' => 'doc',
            'version' => 1,
            'content' => $this->convertBlocks($document->children()),
        ];
    }

    /**
     * Konstrukte, die der letzte `convert()`-Aufruf nicht abbilden konnte --
     * unbekanntes/nicht modelliertes rohes HTML, Bilder und jeder andere
     * unbehandelte Knotentyp (horizontale Trennlinien zaehlen seit ADR 0116
     * NICHT mehr dazu, die werden zu `horizontal_rule`). Der Konverter
     * selbst verwirft sie weiterhin (Verhalten unveraendert); dies ist nur
     * die Sichtbarkeit dafuer, die `rich-content:audit` braucht, um mit
     * exakter Fundstelle zu blockieren statt Inhalt unbemerkt zu verlieren.
     *
     * @return list<array{type: string, line: int|null, snippet: string}>
     */
    public function skippedNodes(): array
    {
        return $this->skips;
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
            // zugeordnet -- kein Verlust, kein Skip) oder anderes rohes HTML
            // (ein self_check-Start/-Ende ist von convertBlocks() bereits
            // konsumiert, bevor convertBlock() ueberhaupt aufgerufen wird,
            // taucht also hier nie auf).
            $node instanceof HtmlBlock => $this->isKeinBeispielMarker($node)
                ? null
                : $this->recordBlockSkip('unbekanntes_html', $node, $node->getLiteral()),
            $node instanceof ThematicBreak => ['type' => 'horizontal_rule'],
            $node instanceof AbstractBlock => $this->recordBlockSkip('unbekannter_block', $node, $node::class),
            default => null,
        };
    }

    /**
     * Hilfsfunktion fuer die `match`-Zweige oben, die einen Knoten
     * uebergehen (Rueckgabe `null`, unveraendertes Konverter-Verhalten),
     * aber zusaetzlich protokollieren, *was* uebergangen wurde.
     */
    private function recordBlockSkip(string $type, AbstractBlock $node, string $snippet): null
    {
        $this->skips[] = [
            'type' => $type,
            'line' => $node->getStartLine(),
            'snippet' => mb_substr(trim($snippet), 0, 200),
        ];

        return null;
    }

    /**
     * @return list<array<string, mixed>> immer leer -- Hilfsfunktion fuer
     *                                    `convertInline()`s uebersprungene Faelle (z. B. Bilder),
     *                                    die anders als Bloecke keine eigene Zeilennummer tragen;
     *                                    die Zeile des naechsten umschliessenden Blocks dient als
     *                                    Naeherung.
     */
    private function recordInlineSkip(string $type, Node $node, string $snippet): array
    {
        $this->skips[] = [
            'type' => $type,
            'line' => $this->enclosingBlockLine($node),
            'snippet' => mb_substr(trim($snippet), 0, 200),
        ];

        return [];
    }

    private function enclosingBlockLine(Node $node): ?int
    {
        $current = $node->parent();

        while ($current !== null && ! $current instanceof AbstractBlock) {
            $current = $current->parent();
        }

        return $current?->getStartLine();
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
            $node instanceof Image => $this->recordInlineSkip('bild', $node, $node->getUrl()),
            default => $this->recordInlineSkip('unbekannter_inline_knoten', $node, $node::class),
        };
    }

    /**
     * Loest `{{term:x}}` (kein gueltiges Markdown-Konstrukt, kommt als
     * reiner Text-Literal an, siehe `MarkdownRenderer`-Klassendoc) in
     * eigene `glossary_term`-Knoten auf, statt es als Text stehen zu
     * lassen -- der einzige Fall, in dem ein einzelner Text-Knoten in
     * mehrere Inline-Knoten aufgespalten wird.
     *
     * Frueher gab es hier eine `count($parts) === 1`-Abkuerzung, die "kein
     * Treffer" annahm, sobald nur ein Teil zurueckkam -- das gilt aber auch,
     * wenn der GESAMTE Literal-Text aus genau einem Glossarbegriff besteht
     * (z. B. eine Tabellenzelle, deren Inhalt nur `{{term:x}}` ist, siehe
     * `rich-content:audit`/CMS-7d.1, das dies gegen echten Bestand aufgedeckt
     * hat). Die Schleife unten behandelt beide Faelle einheitlich: jeder Teil
     * wird einzeln gegen das Glossar-Muster geprueft, unabhaengig davon, wie
     * viele Teile `preg_split()` geliefert hat.
     *
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, mixed>>
     */
    private function convertText(string $literal, array $marks): array
    {
        if ($literal === '') {
            return [];
        }

        $parts = preg_split('/(\{\{term:[a-z0-9\-]+\}\})/', $literal, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return [['type' => 'text', 'text' => $literal, ...($marks !== [] ? ['marks' => $marks] : [])]];
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

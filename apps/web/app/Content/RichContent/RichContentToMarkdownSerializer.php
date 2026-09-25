<?php

namespace App\Content\RichContent;

/**
 * Rueckweg zu `MarkdownToRichContentConverter` (ADR 0122, Phase 1):
 * serialisiert ein DCMLab-v1-Dokument (ADR 0111/0114/0116) zurueck in das
 * Markdown-Format aus `docs/content-schema.md` -- Grundlage fuer
 * `content:export`.
 *
 * Kerneigenschaft ist der Round-Trip auf Rich-Content-Ebene:
 *
 *   convert(serialize(doc)) == doc
 *
 * fuer jedes Dokument, das der Konverter selbst erzeugen kann. Textgleichheit
 * auf Markdown-Ebene wird angestrebt (dieselben Konventionen wie der Bestand:
 * `**`/`*`, `-`-Listen, `1.`-Nummerierung, `<details>`-Selbstchecks, `---`),
 * ist aber nicht garantiert -- der Konverter vergisst z. B. die
 * Startnummer geordneter Listen, Tabellenausrichtungen und den Unterschied
 * zwischen weichem und hartem Zeilenumbruch.
 *
 * Nie still verworfen: ein Knoten, den das Markdown-Format nicht verlustfrei
 * tragen kann, wirft `UnrepresentableRichContentException`. Das betrifft
 * unbekannte Typen, die DCMLab-Bloecke ohne Markdown-Syntax (`callout`,
 * `dicom_tag_table`, `code_block{variant: dicom_dump}` -- Syntax ist eine
 * offene Betreiberfrage, ADR 0122) und Varianten, die der Konverter beim
 * Zuruecklesen anders klassifizieren wuerde (z. B. `console` ohne
 * Prompt-Zeile). Einzige bewusste Ausnahme: ein leerer Absatz ist kein
 * Inhalt und entfaellt.
 */
final class RichContentToMarkdownSerializer
{
    private const PROMPT_LINE = '/^\s*(\$\s|PS>\s)/m';

    private const INDENTED_CODE = 'indented_code';

    /**
     * @param  array<string, mixed>  $document
     */
    public function serialize(array $document): string
    {
        if (($document['type'] ?? null) !== 'doc') {
            throw UnrepresentableRichContentException::at('doc', 'kein "doc"-Dokument');
        }

        if (($document['version'] ?? null) !== 1) {
            throw UnsupportedRichContentVersionException::forVersion($document['version'] ?? null);
        }

        $markdown = $this->blocks($this->listOf($document['content'] ?? [], 'doc'), 'doc');
        $this->assertRoundTrip($document, $markdown);

        return $markdown;
    }

    /**
     * Inhaltsgleichheit zweier Dokumente so, wie sie ein Round-Trip ueber
     * Markdown ueberhaupt unterscheiden kann (Schluesselreihenfolge,
     * benachbarte Texte, leere Absaetze egal). `content:export` (ADR 0122)
     * schreibt eine Datei nur, wenn ihr Inhalt so vom DB-Stand abweicht --
     * nie wegen rein kosmetischer Markdown-Unterschiede.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public function equivalent(array $a, array $b): bool
    {
        return self::canonical($this->comparable($a)) === self::canonical($this->comparable($b));
    }

    /**
     * Letzte Sicherung hinter allen Einzelregeln: das Ergebnis wird mit
     * demselben Konverter zurueckgelesen, den `content:sync`/die Normalizer
     * benutzen. Weicht es ab, ist der Export verlustbehaftet -- z. B. eine
     * Hervorhebung, die direkt an Satzzeichen und Buchstaben grenzt
     * (`**abliest:**Fuenf` ist nach CommonMarks Flanking-Regeln kein
     * Fettdruck), was Studio erzeugen kann, Markdown aber nicht ausdruecken.
     *
     * @param  array<string, mixed>  $document
     */
    private function assertRoundTrip(array $document, string $markdown): void
    {
        $expected = self::canonical($this->comparable($document))['content'] ?? [];
        $actual = self::canonical((new MarkdownToRichContentConverter)->convert($markdown))['content'];

        if ($expected === $actual) {
            return;
        }

        foreach ($expected as $index => $block) {
            if (($actual[$index] ?? null) !== $block) {
                throw UnrepresentableRichContentException::at(
                    "doc.content[{$index}]",
                    'wuerde beim Zuruecklesen abweichen (z. B. Hervorhebung ohne Leerraum direkt vor/nach Satzzeichen)',
                );
            }
        }

        throw UnrepresentableRichContentException::at('doc', 'wuerde beim Zuruecklesen zusaetzliche Bloecke erzeugen');
    }

    /**
     * Das Dokument so, wie der Konverter es bestenfalls zuruecklesen kann:
     * benachbarte Texte mit gleichen Marks verschmolzen, leere Absaetze
     * (ausser in Tabellenzellen) entfernt -- dieselben zwei Normalisierungen,
     * die der Serialisierer selbst vornimmt.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function comparable(array $node): array
    {
        if (! is_array($node['content'] ?? null)) {
            return $node;
        }

        $type = $node['type'] ?? null;
        $content = $node['content'];

        if (in_array($type, ['paragraph', 'heading'], true)) {
            $node['content'] = $this->mergeAdjacentText($this->listOf($content, (string) $type));

            return $node;
        }

        if ($type !== 'table_cell') {
            $content = array_values(array_filter(
                $content,
                fn (mixed $child): bool => ! (is_array($child) && ($child['type'] ?? null) === 'paragraph' && ($child['content'] ?? []) === []),
            ));
        }

        $node['content'] = array_map(fn (mixed $child): mixed => is_array($child) ? $this->comparable($child) : $child, $content);

        return $node;
    }

    /**
     * Setzt einen `node_content`-Umschlag (ADR 0115) wieder zum
     * `NodeSections`-Format zusammen: `## Briefing`, `## Hints` mit
     * `### <id>` je Hint, `## Write-up` -- ohne `---`-Trenner (32 von 34 Nodes im Bestand).
     * Die Hint-Reihenfolge folgt `$hintOrder` (die `hints`-Metadaten der
     * Node, id/cost) -- `jsonb` sortiert Objektschluessel um, die Reihenfolge
     * im Umschlag selbst ist deshalb nicht verlaesslich.
     *
     * @param  array<string, mixed>  $envelope
     * @param  list<string>  $hintOrder
     */
    public function serializeNodeContent(array $envelope, array $hintOrder = []): string
    {
        if (($envelope['type'] ?? null) !== 'node_content') {
            throw UnrepresentableRichContentException::at('node_content', 'kein "node_content"-Umschlag');
        }

        if (($envelope['version'] ?? null) !== 1) {
            throw UnsupportedRichContentVersionException::forVersion($envelope['version'] ?? null);
        }

        $hints = is_array($envelope['hints'] ?? null) ? $envelope['hints'] : [];
        $ids = array_values(array_unique([
            ...array_values(array_filter($hintOrder, fn (string $id): bool => array_key_exists($id, $hints))),
            ...array_map('strval', array_keys($hints)),
        ]));

        $parts = ['## Briefing', $this->nodeSection($envelope['briefing'] ?? null, 'briefing', '/^##\s/m'), '## Hints'];

        foreach ($ids as $id) {
            if (preg_match('/^\S(.*\S)?$/', $id) !== 1) {
                throw UnrepresentableRichContentException::at("hints.{$id}", 'Hint-Id ist keine gueltige Ueberschrift');
            }

            $parts[] = "### {$id}";
            $parts[] = $this->nodeSection($hints[$id], "hints.{$id}", '/^###?\s/m');
        }

        $parts[] = '## Write-up';
        $parts[] = $this->nodeSection($envelope['write_up'] ?? null, 'write_up', '/^##\s/m');

        return implode("\n\n", array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    /**
     * `NodeSections::parse()` findet die Abschnitte ueber `## `-/`### `-
     * Zeilen -- eine solche Zeile IM Abschnitt wuerde ihn beim Zuruecklesen
     * zerschneiden.
     */
    private function nodeSection(mixed $document, string $path, string $forbiddenLine): string
    {
        if (! is_array($document)) {
            throw UnrepresentableRichContentException::at($path, 'Abschnitt fehlt');
        }

        $markdown = $this->serialize($document);

        if (preg_match($forbiddenLine, $markdown) === 1) {
            throw UnrepresentableRichContentException::at($path, 'enthaelt eine Ueberschrift, die die Node-Abschnitte zerschneiden wuerde');
        }

        return $markdown;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function blocks(array $blocks, string $path, bool $inListItem = false): string
    {
        $out = '';
        $previousType = null;
        $bulletMarker = '-';
        $orderedDelimiter = '.';

        foreach ($blocks as $index => $block) {
            $blockPath = "{$path}.content[{$index}]";
            $type = $block['type'] ?? null;

            if ($type === 'paragraph' && $this->listOf($block['content'] ?? [], $blockPath) === []) {
                continue;
            }

            // Zwei direkt aufeinanderfolgende Listen desselben Typs wuerde
            // CommonMark zu EINER Liste verschmelzen -- ein anderer Marker
            // haelt sie getrennt.
            if ($type === 'bullet_list') {
                $bulletMarker = $previousType === 'bullet_list' && $bulletMarker === '-' ? '*' : '-';
            }

            if ($type === 'ordered_list') {
                $orderedDelimiter = $previousType === 'ordered_list' && $orderedDelimiter === '.' ? ')' : '.';
            }

            $markdown = $this->block($block, $blockPath, $bulletMarker, $orderedDelimiter);

            if ($markdown === self::INDENTED_CODE) {
                if (in_array($previousType, ['bullet_list', 'ordered_list'], true)) {
                    throw UnrepresentableRichContentException::at($blockPath, 'terminal-Block mit Prompt-Zeilen direkt nach einer Liste');
                }

                $markdown = $this->indentedCode((string) ($block['text'] ?? ''), $blockPath);
            }

            // Unterliste direkt unter dem Absatz eines Listenpunkts ohne
            // Leerzeile, wie im Bestand (eine Liste darf einen Absatz
            // unterbrechen, geordnete Listen beginnen hier immer bei 1).
            $tight = $inListItem && $previousType === 'paragraph' && in_array($type, ['bullet_list', 'ordered_list'], true);
            $out .= ($out === '' ? '' : ($tight ? "\n" : "\n\n")).$markdown;
            $previousType = $type;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function block(array $block, string $path, string $bulletMarker, string $orderedDelimiter): string
    {
        return match ($block['type'] ?? null) {
            'paragraph' => $this->paragraph($this->listOf($block['content'] ?? [], $path), $path),
            'heading' => $this->heading($block, $path),
            'bullet_list' => $this->listBlock($block, $path, fn (int $i): string => $bulletMarker.' '),
            'ordered_list' => $this->listBlock($block, $path, fn (int $i): string => ($i + 1).$orderedDelimiter.' '),
            'blockquote' => $this->prefixLines($this->blocks($this->listOf($block['content'] ?? [], $path), $path), '> ', '>'),
            'code_block' => $this->codeBlock($block, $path),
            'table' => $this->table($block, $path),
            'self_check' => $this->selfCheck($block, $path),
            'horizontal_rule' => '---',
            'callout', 'dicom_tag_table' => throw UnrepresentableRichContentException::at($path, "DCMLab-Block \"{$block['type']}\" hat (noch) keine Markdown-Syntax (ADR 0114/0122)"),
            default => throw UnrepresentableRichContentException::at($path, 'unbekannter Blocktyp "'.$this->shown($block['type'] ?? null).'"'),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $inlines
     */
    private function paragraph(array $inlines, string $path): string
    {
        $lines = explode("\n", $this->inlines($inlines, $path));

        return implode("\n", array_map(fn (string $line): string => $this->escapeLineStart($line), $lines));
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function heading(array $block, string $path): string
    {
        $level = $block['attrs']['level'] ?? null;

        if (! is_int($level) || $level < 1 || $level > 6) {
            throw UnrepresentableRichContentException::at($path, 'Ueberschriftenebene ausserhalb 1-6');
        }

        $text = $this->inlines($this->listOf($block['content'] ?? [], $path), $path);

        if (str_contains($text, "\n")) {
            throw UnrepresentableRichContentException::at($path, 'Zeilenumbruch in einer Ueberschrift');
        }

        // Eine abschliessende #-Folge wuerde als optionale Schlusssequenz
        // der ATX-Ueberschrift verschluckt.
        $text = preg_replace('/(^|\s)(#+)$/', '$1\\\\$2', $text) ?? $text;

        return str_repeat('#', $level).($text !== '' ? ' '.$text : '');
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  callable(int): string  $marker
     */
    private function listBlock(array $block, string $path, callable $marker): string
    {
        $items = [];

        foreach ($this->listOf($block['content'] ?? [], $path) as $index => $item) {
            $itemPath = "{$path}.content[{$index}]";

            if (($item['type'] ?? null) !== 'list_item') {
                throw UnrepresentableRichContentException::at($itemPath, 'Listenelement ist kein "list_item"');
            }

            $prefix = $marker($index);
            $content = $this->blocks($this->listOf($item['content'] ?? [], $itemPath), $itemPath, true);

            if (str_starts_with($content, '    ')) {
                throw UnrepresentableRichContentException::at($itemPath, 'Listenelement beginnt mit einem eingerueckten Codeblock');
            }

            $items[] = $content === ''
                ? rtrim($prefix)
                : $this->prefixLines($content, $prefix, '', str_repeat(' ', strlen($prefix)));
        }

        if ($items === []) {
            throw UnrepresentableRichContentException::at($path, 'leere Liste');
        }

        return implode("\n", $items);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function codeBlock(array $block, string $path): string
    {
        $text = $block['text'] ?? null;

        if (! is_string($text)) {
            throw UnrepresentableRichContentException::at($path, 'Codeblock ohne Text');
        }

        $variant = $block['attrs']['variant'] ?? null;
        $hasPrompt = preg_match(self::PROMPT_LINE, $text) === 1;

        return match ($variant) {
            'diagram' => "<!-- kein-beispiel -->\n".$this->fence($text, ''),
            'mermaid' => $this->fence($text, 'mermaid'),
            'code' => $this->fence($text, $this->language($block['attrs']['language'] ?? null, $path)),
            'console' => $hasPrompt
                ? $this->fence($text, '')
                : throw UnrepresentableRichContentException::at($path, 'console-Block ohne "$ "/"PS> "-Zeile wuerde als terminal zurueckgelesen'),
            // Ein Fence ohne Prompt-Zeile liest der Konverter als
            // `terminal`, einer mit Prompt-Zeile als `console` -- nur ein
            // eingerueckter Codeblock ist immer `terminal`.
            'terminal' => $hasPrompt ? self::INDENTED_CODE : $this->fence($text, ''),
            'dicom_dump' => throw UnrepresentableRichContentException::at($path, 'code_block-Variante "dicom_dump" hat (noch) keine Markdown-Syntax (ADR 0114/0122)'),
            default => throw UnrepresentableRichContentException::at($path, 'unbekannte code_block-Variante "'.$this->shown($variant).'"'),
        };
    }

    private function language(mixed $language, string $path): string
    {
        if (! is_string($language) || preg_match('/^[^\s`]+$/', $language) !== 1) {
            throw UnrepresentableRichContentException::at($path, 'code-Block ohne gueltige Sprachangabe');
        }

        if ($language === 'mermaid') {
            throw UnrepresentableRichContentException::at($path, 'code-Block mit Sprache "mermaid" wuerde als mermaid-Variante zurueckgelesen');
        }

        return $language;
    }

    private function fence(string $text, string $info): string
    {
        preg_match_all('/`+/', $text, $runs);
        $longest = max([0, ...array_map('strlen', $runs[0])]);
        $fence = str_repeat('`', max(3, $longest + 1));

        return $fence.$info."\n".($text !== '' ? $text."\n" : '').$fence;
    }

    private function indentedCode(string $text, string $path): string
    {
        if (trim($text) === '' || str_starts_with($text, "\n")) {
            throw UnrepresentableRichContentException::at($path, 'terminal-Block beginnt mit einer Leerzeile');
        }

        return implode("\n", array_map(
            fn (string $line): string => $line === '' ? '' : '    '.$line,
            explode("\n", $text),
        ));
    }

    /**
     * GFM-Tabelle: genau eine Kopfzeile, danach nur Datenzeilen gleicher
     * Breite -- alles andere liest der Konverter anders zurueck.
     *
     * @param  array<string, mixed>  $block
     */
    private function table(array $block, string $path): string
    {
        $rows = $this->listOf($block['content'] ?? [], $path);

        if ($rows === []) {
            throw UnrepresentableRichContentException::at($path, 'leere Tabelle');
        }

        $lines = [];
        $width = null;

        foreach ($rows as $rowIndex => $row) {
            $rowPath = "{$path}.content[{$rowIndex}]";
            $cells = $this->listOf($row['content'] ?? [], $rowPath);
            $width ??= count($cells);

            if (($row['type'] ?? null) !== 'table_row' || $cells === [] || count($cells) !== $width) {
                throw UnrepresentableRichContentException::at($rowPath, 'Tabellenzeile ungleicher Breite oder ohne Zellen');
            }

            $rendered = [];

            foreach ($cells as $cellIndex => $cell) {
                $cellPath = "{$rowPath}.content[{$cellIndex}]";
                $isHeader = ($cell['attrs']['header'] ?? false) === true;

                if (($cell['type'] ?? null) !== 'table_cell' || $isHeader !== ($rowIndex === 0)) {
                    throw UnrepresentableRichContentException::at($cellPath, 'nur genau die erste Tabellenzeile darf (und muss) Kopfzeile sein');
                }

                $rendered[] = $this->tableCell($cell, $cellPath);
            }

            $lines[] = '|'.implode('|', array_map(fn (string $cell): string => $cell === '' ? ' ' : " {$cell} ", $rendered)).'|';

            if ($rowIndex === 0) {
                $lines[] = '|'.str_repeat('---|', $width);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $cell
     */
    private function tableCell(array $cell, string $path): string
    {
        $content = $this->listOf($cell['content'] ?? [], $path);

        if (count($content) !== 1 || ($content[0]['type'] ?? null) !== 'paragraph') {
            throw UnrepresentableRichContentException::at($path, 'Tabellenzelle enthaelt nicht genau einen Absatz');
        }

        $text = $this->inlines($this->listOf($content[0]['content'] ?? [], $path), $path);

        if (str_contains($text, "\n")) {
            throw UnrepresentableRichContentException::at($path, 'Zeilenumbruch in einer Tabellenzelle');
        }

        // Auch innerhalb eines Code-Spans verlangt GFM `\|` -- der
        // Tabellenparser trennt Zellen vor dem Inline-Parsing.
        return str_replace('|', '\\|', $text);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function selfCheck(array $block, string $path): string
    {
        $summary = $block['attrs']['summary'] ?? null;

        if (! is_string($summary) || trim($summary) === '' || str_contains($summary, "\n") || stripos($summary, '</summary>') !== false) {
            throw UnrepresentableRichContentException::at($path, 'self_check ohne einzeilige summary');
        }

        $content = $this->listOf($block['content'] ?? [], $path);
        $body = $this->blocks($content, $path);
        $head = "<details>\n<summary>".trim($summary).'</summary>';

        if ($body === '') {
            return $head."\n\n</details>";
        }

        // Wie im Bestand: `</details>` direkt unter dem letzten Absatz (ein
        // HTML-Block Typ 6 unterbricht einen Absatz); nach jedem anderen
        // Block mit Leerzeile, damit er nicht in Liste/Zitat hineinrutscht.
        $lastType = $content[array_key_last($content)]['type'] ?? null;

        return $head."\n\n".$body.($lastType === 'paragraph' ? "\n" : "\n\n").'</details>';
    }

    /**
     * Inline-Folge -> Markdown. Aufeinanderfolgende Knoten mit derselben
     * aeussersten Markierung werden zu EINEM Delimiter-Lauf gebuendelt
     * (`**a {{term:x}} b**` statt `**a **{{term:x}}** b**`), sonst wuerden
     * CommonMarks Flanking-Regeln den Lauf zerbrechen.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    private function inlines(array $nodes, string $path, ?string $parentDelimiter = null): string
    {
        $nodes = $this->mergeAdjacentText($nodes);
        $count = count($nodes);
        $out = '';
        $index = 0;

        while ($index < $count) {
            $node = $nodes[$index];
            $nodePath = "{$path}.content[{$index}]";

            switch ($node['type'] ?? null) {
                case 'glossary_term':
                    $slug = $node['attrs']['slug'] ?? null;

                    if (! is_string($slug) || preg_match('/^[a-z0-9\-]+$/', $slug) !== 1) {
                        throw UnrepresentableRichContentException::at($nodePath, 'Glossarverweis ohne gueltigen Slug');
                    }

                    $out .= "{{term:{$slug}}}";
                    $index++;

                    continue 2;

                case 'hard_break':
                    $out .= "\n";
                    $index++;

                    continue 2;

                case 'text':
                    break;

                default:
                    throw UnrepresentableRichContentException::at($nodePath, 'unbekannter Inline-Typ "'.$this->shown($node['type'] ?? null).'"');
            }

            $marks = $this->marksOf($node, $nodePath);

            // Der Konverter loest `{{term:x}}` im bereits entescapeten
            // Text-Literal auf -- ein Backslash hilft nicht, woertlicher
            // Text in dieser Form ist in Markdown nicht darstellbar.
            if (preg_match('/\{\{term:[a-z0-9\-]+\}\}/', (string) $node['text']) === 1) {
                throw UnrepresentableRichContentException::at($nodePath, 'Text enthaelt woertlich "{{term:...}}"');
            }

            if ($marks === []) {
                $out .= $this->escapeText((string) $node['text']);
                $index++;

                continue;
            }

            $outer = $marks[0];
            $end = $this->runEnd($nodes, $index, $outer);
            $inner = array_map(fn (array $n): array => $this->withoutOuterMark($n), array_slice($nodes, $index, $end - $index + 1));
            $touchesParent = $parentDelimiter !== null && ($index === 0 || $end === $count - 1);

            $out .= $this->wrap($outer, $inner, $nodePath, $touchesParent ? $parentDelimiter : null);
            $index = $end + 1;
        }

        return $out;
    }

    /**
     * Letzter Index des Laufs ab `$start`, der dieselbe aeusserste Markierung
     * traegt. Glossarverweise und Zeilenumbrueche (tragen selbst nie Marks,
     * der Konverter laesst sie beim Zuruecklesen innerhalb einer Hervorhebung
     * ebenfalls markenlos) gehoeren dazu, solange danach noch ein Knoten mit
     * derselben Markierung folgt.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $outer
     */
    private function runEnd(array $nodes, int $start, array $outer): int
    {
        $end = $start;

        for ($i = $start + 1; $i < count($nodes); $i++) {
            $type = $nodes[$i]['type'] ?? null;

            if ($type === 'text') {
                $marks = $nodes[$i]['marks'] ?? [];

                if (is_array($marks) && $marks !== [] && $this->same($marks[0], $outer)) {
                    $end = $i;

                    continue;
                }

                break;
            }

            if (! in_array($type, ['glossary_term', 'hard_break'], true) || ($outer['type'] ?? null) === 'code') {
                break;
            }
        }

        return $end;
    }

    /**
     * @param  array<string, mixed>  $mark
     * @param  list<array<string, mixed>>  $inner
     */
    private function wrap(array $mark, array $inner, string $path, ?string $parentDelimiter): string
    {
        switch ($mark['type'] ?? null) {
            case 'code':
                if (count($inner) !== 1 || ($inner[0]['marks'] ?? []) !== []) {
                    throw UnrepresentableRichContentException::at($path, 'code-Markierung ist nicht die innerste Markierung');
                }

                return $this->codeSpan((string) $inner[0]['text']);

            case 'bold':
            case 'italic':
                // `***x***` liest CommonMark als em(strong(x)) -- ein
                // anderes Delimiter-Zeichen an der Beruehrungsstelle mit der
                // umschliessenden Hervorhebung haelt die Reihenfolge der
                // Marks stabil.
                $char = $parentDelimiter === '*' ? '_' : '*';
                $delimiter = $mark['type'] === 'bold' ? $char.$char : $char;
                $text = $this->inlines($inner, $path, $char);

                if (trim($text) === '') {
                    throw UnrepresentableRichContentException::at($path, 'leere oder reine Leerraum-Hervorhebung');
                }

                // Leerraum am Rand darf nicht innerhalb der Delimiter
                // stehen, sonst sind sie nicht mehr links-/rechtsbuendig.
                $core = trim($text);
                $leading = substr($text, 0, strlen($text) - strlen(ltrim($text)));
                $trailing = substr($text, strlen(rtrim($text)));

                return $leading.$delimiter.$core.$delimiter.$trailing;

            case 'link':
                $href = $mark['attrs']['href'] ?? null;

                if (! is_string($href) || $href === '' || preg_match('/[<>
]/', $href) === 1) {
                    throw UnrepresentableRichContentException::at($path, 'Link ohne darstellbares Ziel');
                }

                $destination = preg_match('/[\s()]/', $href) === 1 ? "<{$href}>" : $href;

                return '['.$this->inlines($inner, $path).']('.$destination.')';
        }

        throw UnrepresentableRichContentException::at($path, 'unbekannte Markierung "'.$this->shown($mark['type'] ?? null).'"');
    }

    private function codeSpan(string $text): string
    {
        if ($text === '') {
            throw UnrepresentableRichContentException::at('code', 'leerer Code-Span');
        }

        preg_match_all('/`+/', $text, $runs);
        $lengths = array_map('strlen', $runs[0]);
        $ticks = 1;

        while (in_array($ticks, $lengths, true)) {
            $ticks++;
        }

        $fence = str_repeat('`', $ticks);
        // CommonMark entfernt genau ein Leerzeichen an beiden Enden, wenn
        // der Inhalt mit Backtick beginnt/endet oder beidseitig Leerzeichen
        // hat -- in diesen Faellen explizit auffuellen.
        $pad = str_starts_with($text, '`') || str_ends_with($text, '`')
            || (str_starts_with($text, ' ') && str_ends_with($text, ' ') && trim($text) !== '');

        return $pad ? "{$fence} {$text} {$fence}" : $fence.$text.$fence;
    }

    /**
     * Minimal statt pauschal: nur Zeichen, die im Fliesstext tatsaechlich
     * etwas ausloesen koennen, damit exportierte Dateien lesbar bleiben.
     */
    private function escapeText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = preg_replace('/([*`\[~])/', '\\\\$1', $text) ?? $text;
        // `_` wirkt nur an Wortgrenzen (intraword-Unterstriche wie in
        // snake_case sind nie Emphasis-Delimiter).
        $text = preg_replace('/(?<![\p{L}\p{N}])_|_(?![\p{L}\p{N}])/u', '\\\\_', $text) ?? $text;
        // Rohes HTML / Autolinks (`<tag`, `</tag`, `<!--`, `<?`, `<scheme:`).
        $text = preg_replace('/<(?=[A-Za-z\/!?])/', '\\\\<', $text) ?? $text;
        // Zeichenreferenzen (`&amp;`, `&#123;`).
        $text = preg_replace('/&(?=#?[A-Za-z0-9]+;)/', '\\\\&', $text) ?? $text;
        // GFM-Autolinks (www., http(s)://, ftp://, E-Mail).
        $text = preg_replace('/\b(https?|ftp):\/\//i', '$1\\://', $text) ?? $text;
        $text = preg_replace('/\bwww\./i', 'www\\.', $text) ?? $text;

        return preg_replace('/([A-Za-z0-9._+\-]+)@([A-Za-z0-9\-]+\.)/', '$1\\@$2', $text) ?? $text;
    }

    /**
     * Zeilenanfaenge, an denen ein Absatz sonst als anderer Block
     * (Ueberschrift, Liste, Zitat, Trennlinie, Setext-Unterstreichung,
     * Codefence, HTML-Block) gelesen wuerde.
     */
    private function escapeLineStart(string $line): string
    {
        if (preg_match('/^(#{1,6})(\s|$)/', $line) === 1
            || preg_match('/^([-+](\s|$)|>|-+\s*$|-\s*-\s*-)/', $line) === 1
            || preg_match('/^=+\s*$/', $line) === 1) {
            return '\\'.$line;
        }

        if (preg_match('/^(\d{1,9})([.)])(\s|$)/', $line, $match) === 1) {
            return $match[1].'\\'.substr($line, strlen($match[1]));
        }

        return $line;
    }

    private function prefixLines(string $text, string $first, string $emptyLine, ?string $rest = null): string
    {
        $rest ??= $first;
        $lines = explode("\n", $text);

        foreach ($lines as $i => $line) {
            $prefix = $i === 0 ? $first : $rest;
            $lines[$i] = $line === '' ? ($emptyLine !== '' ? $emptyLine : '') : $prefix.$line;
        }

        return implode("\n", $lines);
    }

    /**
     * Benachbarte Text-Knoten mit identischen Marks liest der Konverter
     * ohnehin als EINEN Knoten zurueck (CommonMark verschmilzt sie).
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function mergeAdjacentText(array $nodes): array
    {
        $merged = [];

        foreach ($nodes as $node) {
            $last = $merged === [] ? null : $merged[count($merged) - 1];

            if ($last !== null && ($node['type'] ?? null) === 'text' && ($last['type'] ?? null) === 'text'
                && $this->same($last['marks'] ?? [], $node['marks'] ?? [])) {
                $merged[count($merged) - 1]['text'] = ($last['text'] ?? '').($node['text'] ?? '');

                continue;
            }

            $merged[] = $node;
        }

        return array_values(array_filter(
            $merged,
            fn (array $node): bool => ($node['type'] ?? null) !== 'text' || ($node['text'] ?? '') !== '',
        ));
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    private function marksOf(array $node, string $path): array
    {
        if (! is_string($node['text'] ?? null)) {
            throw UnrepresentableRichContentException::at($path, 'Text-Knoten ohne Text');
        }

        return $this->listOf($node['marks'] ?? [], $path);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function withoutOuterMark(array $node): array
    {
        if (($node['type'] ?? null) !== 'text') {
            return $node;
        }

        $marks = array_slice($node['marks'] ?? [], 1);
        unset($node['marks']);

        return $marks === [] ? $node : [...$node, 'marks' => $marks];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listOf(mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw UnrepresentableRichContentException::at($path, 'erwartete eine Liste von Knoten');
        }

        foreach ($value as $item) {
            if (! is_array($item)) {
                throw UnrepresentableRichContentException::at($path, 'Knoten ist kein Objekt');
            }
        }

        /** @var list<array<string, mixed>> $value */
        return $value;
    }

    /**
     * Schluesselreihenfolge-unabhaengiger Vergleich -- Postgres-`jsonb`
     * sortiert Objektschluessel um (ADR 0122, Phase 0).
     */
    private function same(mixed $a, mixed $b): bool
    {
        return self::canonical($a) === self::canonical($b);
    }

    public static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }

    private function shown(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : get_debug_type($value);
    }
}

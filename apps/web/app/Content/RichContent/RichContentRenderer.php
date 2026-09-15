<?php

namespace App\Content\RichContent;

use App\Content\HeadingSlug;

/**
 * Rendert ein validiertes Rich-Content-Dokument (ADR 0111, CMS-7a) zu HTML --
 * das strukturelle Gegenstueck zu `MarkdownRenderer`, das denselben Markup-
 * Vertrag erzeugt (dieselben CSS-Klassen fuer Code-/Konsolen-/Terminal-/
 * Diagramm-Bloecke und Glossar-Begriffe), damit das bestehende Frontend-CSS
 * unveraendert weiterfunktioniert, sobald ein Editor (CMS-7b) tatsaechlich
 * Rich-Content-Dokumente statt Markdown erzeugt.
 *
 * Erwartet ein bereits gegen `RichContentValidator` geprueftes Dokument --
 * wirft keine eigenen Fehler fuer ein ungueltiges Dokument, sondern laesst
 * einen fehlenden/falschen Wert bewusst mit einem freundlichen Fallback
 * durchlaufen (siehe einzelne render*-Methoden).
 */
final class RichContentRenderer
{
    /** @var array<int, string> */
    private array $headingSlugs = [];

    private int $headingIndex = 0;

    /**
     * @param  array<string, array<string, mixed>>  $glossary
     */
    public function __construct(
        private readonly array $glossary = [],
    ) {}

    /**
     * @param  array<string, mixed>  $doc
     */
    public function render(array $doc): string
    {
        $blocks = is_array($doc['content'] ?? null) ? $doc['content'] : [];

        $this->headingSlugs = HeadingSlug::uniqueSlugs($this->collectHeadingTexts($blocks));
        $this->headingIndex = 0;

        return implode('', array_map(fn (mixed $block): string => $this->renderBlock($block), $blocks));
    }

    /**
     * Sucht rekursiv nach `heading`-Knoten -- auch verschachtelt in einer
     * Liste/einem Blockquote/einer Tabellenzelle -- in Dokumentreihenfolge,
     * damit `HeadingSlug::uniqueSlugs()` dieselbe Reihenfolge sieht wie
     * `renderHeading()` beim tatsaechlichen Rendern.
     *
     * @param  array<int|string, mixed>  $nodes
     * @return array<int, string>
     */
    private function collectHeadingTexts(array $nodes): array
    {
        $texts = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['type'] ?? null) === 'heading') {
                $texts[] = $this->flattenText($node['content'] ?? []);
            }

            if (is_array($node['content'] ?? null)) {
                $texts = [...$texts, ...$this->collectHeadingTexts($node['content'])];
            }
        }

        return $texts;
    }

    private function renderBlock(mixed $node): string
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            return '';
        }

        return match ($node['type']) {
            'paragraph' => '<p>'.$this->renderInlineContent($node).'</p>',
            'heading' => $this->renderHeading($node),
            'bullet_list' => '<ul>'.$this->renderListItems($node).'</ul>',
            'ordered_list' => '<ol>'.$this->renderListItems($node).'</ol>',
            'blockquote' => '<blockquote>'.$this->renderBlockContent($node).'</blockquote>',
            'code_block' => $this->renderCodeBlock($node),
            'table' => $this->renderTable($node),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function renderHeading(array $node): string
    {
        $level = is_int($node['attrs']['level'] ?? null) ? $node['attrs']['level'] : 2;
        $slug = $this->headingSlugs[$this->headingIndex] ?? null;
        $this->headingIndex++;

        $idAttr = $slug !== null ? sprintf(' id="%s"', e($slug)) : '';

        return sprintf('<h%1$d%2$s>%3$s</h%1$d>', $level, $idAttr, $this->renderInlineContent($node));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function renderListItems(array $node): string
    {
        $items = is_array($node['content'] ?? null) ? $node['content'] : [];

        return implode('', array_map(
            fn (mixed $item): string => '<li>'.(is_array($item) ? $this->renderBlockContent($item) : '').'</li>',
            $items,
        ));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function renderTable(array $node): string
    {
        $rows = is_array($node['content'] ?? null) ? $node['content'] : [];

        return '<table><tbody>'.implode('', array_map(
            fn (mixed $row): string => is_array($row) ? $this->renderTableRow($row) : '',
            $rows,
        )).'</tbody></table>';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function renderTableRow(array $row): string
    {
        $cells = is_array($row['content'] ?? null) ? $row['content'] : [];

        return '<tr>'.implode('', array_map(
            fn (mixed $cell): string => is_array($cell) ? $this->renderTableCell($cell) : '',
            $cells,
        )).'</tr>';
    }

    /**
     * @param  array<string, mixed>  $cell
     */
    private function renderTableCell(array $cell): string
    {
        $tag = ($cell['attrs']['header'] ?? false) === true ? 'th' : 'td';

        return "<{$tag}>".$this->renderBlockContent($cell)."</{$tag}>";
    }

    /**
     * Fuer Bloecke, deren `content` selbst wieder Bloecke sind (list_item,
     * blockquote, table_cell) statt Inline-Content.
     *
     * @param  array<string, mixed>  $node
     */
    private function renderBlockContent(array $node): string
    {
        $children = is_array($node['content'] ?? null) ? $node['content'] : [];

        return implode('', array_map(fn (mixed $child): string => $this->renderBlock($child), $children));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function renderInlineContent(array $node): string
    {
        $children = is_array($node['content'] ?? null) ? $node['content'] : [];

        return implode('', array_map(fn (mixed $inline): string => $this->renderInline($inline), $children));
    }

    private function renderInline(mixed $node): string
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            return '';
        }

        return match ($node['type']) {
            'text' => $this->renderText($node),
            'glossary_term' => $this->renderGlossaryTerm($node),
            'hard_break' => '<br>',
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function renderText(array $node): string
    {
        $text = e(is_string($node['text'] ?? null) ? $node['text'] : '');
        $marks = is_array($node['marks'] ?? null) ? $node['marks'] : [];

        foreach ($marks as $mark) {
            if (! is_array($mark) || ! is_string($mark['type'] ?? null)) {
                continue;
            }

            $text = match ($mark['type']) {
                'bold' => "<strong>{$text}</strong>",
                'italic' => "<em>{$text}</em>",
                'code' => "<code>{$text}</code>",
                'link' => sprintf('<a href="%s">%s</a>', e((string) ($mark['attrs']['href'] ?? '')), $text),
                default => $text,
            };
        }

        return $text;
    }

    /**
     * Dasselbe Markup wie `MarkdownRenderer::resolveTerms()` -- ein Rich-
     * Content-Dokument traegt den Begriff strukturiert (`attrs.slug`) statt
     * als `{{term:x}}`-Text, das Ergebnis-HTML ist bewusst identisch.
     *
     * @param  array<string, mixed>  $node
     */
    private function renderGlossaryTerm(array $node): string
    {
        $slug = is_string($node['attrs']['slug'] ?? null) ? $node['attrs']['slug'] : '';
        $entry = $this->glossary[$slug] ?? null;

        if ($entry === null) {
            return e($slug);
        }

        $term = e($entry['term'] ?? $slug);
        $tooltip = e(trim(($entry['expansion'] ?? '').' — '.($entry['short'] ?? '')));

        return sprintf(
            '<span class="glossary-term" data-term="%s" title="%s">%s</span>',
            e($slug),
            $tooltip,
            $term,
        );
    }

    /**
     * Dieselben fuenf Varianten wie `MarkdownRenderer::markMermaidBlocks()`/
     * `markCommandBlocks()`, hier direkt aus `attrs.variant` statt aus einer
     * Heuristik auf dem gerenderten HTML -- derselbe visuelle Vertrag
     * (Klassen, Copy-Buttons).
     *
     * @param  array<string, mixed>  $node
     */
    private function renderCodeBlock(array $node): string
    {
        $variant = is_string($node['attrs']['variant'] ?? null) ? $node['attrs']['variant'] : 'terminal';
        $language = is_string($node['attrs']['language'] ?? null) ? $node['attrs']['language'] : null;
        $rawText = is_string($node['text'] ?? null) ? $node['text'] : '';
        $lines = explode("\n", rtrim($rawText, "\n"));

        return match ($variant) {
            'diagram' => sprintf('<div class="lesson-diagram"><pre><code>%s</code></pre></div>', e($rawText)),
            'mermaid' => sprintf('<pre class="mermaid">%s</pre>', e($rawText)),
            'code' => sprintf(
                '<div class="lesson-code" data-lang="%1$s"><div class="lesson-block-bar"><span class="lesson-block-label">%1$s</span>%2$s</div><pre><code class="language-%1$s">%3$s</code></pre></div>',
                e($language ?? ''),
                $this->copyButton($rawText, __('Code kopieren')),
                e($rawText),
            ),
            'console' => $this->renderConsoleBlock($lines),
            default => $this->renderTerminalBlock($lines),
        };
    }

    /**
     * @param  list<string>  $lines
     */
    private function renderConsoleBlock(array $lines): string
    {
        $promptLines = [];
        $renderedLines = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*(\$\s|PS>\s)/', $line) === 1) {
                $promptLines[] = preg_replace('/^\s*(\$\s|PS>\s)/', '', $line);
                $renderedLines[] = sprintf('<span class="lesson-line lesson-line-prompt">%s</span>', e($line));
            } else {
                $renderedLines[] = sprintf('<span class="lesson-line lesson-line-output">%s</span>', e($line));
            }
        }

        $copyButton = $promptLines !== []
            ? $this->copyButton(implode("\n", $promptLines), __('Befehl kopieren'))
            : '';

        return sprintf(
            '<div class="lesson-console"><div class="lesson-block-bar">%s</div><pre><code>%s</code></pre></div>',
            $copyButton,
            implode("\n", $renderedLines),
        );
    }

    /**
     * @param  list<string>  $lines
     */
    private function renderTerminalBlock(array $lines): string
    {
        $renderedLines = array_map(
            fn (string $line): string => sprintf('<span class="lesson-line lesson-line-output">%s</span>', e($line)),
            $lines,
        );

        return sprintf('<div class="lesson-terminal"><pre><code>%s</code></pre></div>', implode("\n", $renderedLines));
    }

    private function copyButton(string $textToCopy, string $label): string
    {
        return sprintf(
            '<button type="button" class="lesson-copy-btn" data-copy="%s" aria-label="%s"><span class="lesson-copy-btn-label">%s</span></button>',
            e($textToCopy),
            e($label),
            e(__('Kopieren')),
        );
    }

    /**
     * @param  array<int|string, mixed>  $content
     */
    private function flattenText(array $content): string
    {
        $parts = [];

        foreach ($content as $node) {
            if (is_array($node) && ($node['type'] ?? null) === 'text' && is_string($node['text'] ?? null)) {
                $parts[] = $node['text'];
            }
        }

        return implode('', $parts);
    }
}

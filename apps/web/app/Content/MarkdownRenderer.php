<?php

namespace App\Content;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Rendert Lektions-/Node-Markdown zu HTML (Abschnitt 10, P1): loest
 * {{term:x}} zu einem Glossar-Tooltip auf und markiert Mermaid-Codebloecke
 * so, dass das Frontend sie an mermaid.js uebergeben kann.
 *
 * Bewusst kein AST-Plugin fuer {{term:x}}: die Syntax ist kein gueltiges
 * Markdown-Konstrukt, CommonMark laesst sie als reinen Text durch. Ein
 * String-Replace auf dem fertigen HTML ist hier einfacher und robuster als
 * ein eigener Inline-Parser.
 */
final class MarkdownRenderer
{
    private readonly MarkdownConverter $converter;

    /**
     * @param  array<string, array<string, mixed>>  $glossary
     */
    public function __construct(
        private readonly array $glossary = [],
    ) {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $markdown): string
    {
        $html = (string) $this->converter->convert($markdown);
        $html = $this->addHeadingAnchors($markdown, $html);
        $html = $this->resolveTerms($html);
        $html = $this->markMermaidBlocks($html);
        $html = $this->markCommandBlocks($html);

        return $html;
    }

    /**
     * Setzt id="<slug>" auf jedes gerenderte ##/###-Heading, damit
     * Rueckverweise ("Nachlesen: 1.7 — ...") wirklich irgendwo landen. Die
     * Slugs kommen aus HeadingSlug, derselben Klasse, die ContentValidate
     * zum Pruefen von exam.yml's review.anchor benutzt -- beide muessen fuer
     * dieselbe Ueberschrift denselben Slug ausrechnen.
     */
    private function addHeadingAnchors(string $markdown, string $html): string
    {
        $headings = HeadingSlug::headingsIn($markdown);

        if ($headings === []) {
            return $html;
        }

        $slugs = HeadingSlug::uniqueSlugs($headings);
        $index = 0;

        return preg_replace_callback(
            '/<h([23])>/',
            function (array $match) use (&$index, $slugs): string {
                $slug = $slugs[$index] ?? null;
                $index++;

                return $slug !== null ? sprintf('<h%s id="%s">', $match[1], $slug) : $match[0];
            },
            $html,
        ) ?? $html;
    }

    private function resolveTerms(string $html): string
    {
        return preg_replace_callback(
            '/\{\{term:([a-z0-9\-]+)\}\}/',
            function (array $match): string {
                $slug = $match[1];
                $entry = $this->glossary[$slug] ?? null;

                if ($entry === null) {
                    // content:validate haette das schon melden muessen --
                    // im Zweifel den Slug sichtbar lassen statt HTML zu brechen.
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
            },
            $html,
        );
    }

    private function markMermaidBlocks(string $html): string
    {
        return preg_replace(
            '#<pre><code class="language-mermaid">(.*?)</code></pre>#s',
            '<pre class="mermaid">$1</pre>',
            $html,
        );
    }

    /**
     * Klassifiziert die uebrigen Codebloecke rein visuell -- ohne dass
     * Autoren dafuer neue Markdown-Syntax lernen muessen, weil die
     * Beispielregel (docs/content-schema.md Abschnitt 0) ohnehin schon
     * jeden Befehl als "$ …"/"PS> …"-Zeile schreibt:
     *
     * - `<!-- kein-beispiel -->` direkt davor (bereits bestehende Markierung
     *   fuer reine Diagramme) -> ruhiger Diagramm-Rahmen, kein Copy-Button
     * - Sprache annotiert (```python` etc., nicht mermaid) -> Code-Block mit
     *   Sprachlabel und Copy-Button
     * - erste/eine Zeile beginnt mit "$ " oder "PS> " -> Konsolen-Block:
     *   Promptzeilen hervorgehoben, Copy-Button kopiert nur die Befehle
     * - alles andere -> reiner Output-Block (Terminal-Optik)
     */
    private function markCommandBlocks(string $html): string
    {
        return preg_replace_callback(
            '#(<!--\s*kein-beispiel\s*-->\s*)?<pre><code(?: class="language-([a-z0-9]+)")?>(.*?)</code></pre>#s',
            function (array $match): string {
                $isDiagram = $match[1] !== '';
                $language = $match[2] !== '' ? $match[2] : null;
                $escapedInner = $match[3];
                $rawText = html_entity_decode($escapedInner, ENT_QUOTES | ENT_HTML5);

                if ($isDiagram) {
                    return sprintf('<div class="lesson-diagram"><pre><code>%s</code></pre></div>', $escapedInner);
                }

                if ($language !== null) {
                    return $this->renderCodeBlock($language, $escapedInner, $rawText);
                }

                $lines = explode("\n", rtrim($rawText, "\n"));
                $isConsole = collect($lines)->contains(
                    fn (string $line): bool => preg_match('/^\s*(\$\s|PS>\s)/', $line) === 1,
                );

                return $isConsole
                    ? $this->renderConsoleBlock($lines)
                    : $this->renderTerminalBlock($rawText);
            },
            $html,
        ) ?? $html;
    }

    private function renderCodeBlock(string $language, string $escapedInner, string $rawText): string
    {
        return sprintf(
            '<div class="lesson-code" data-lang="%1$s"><div class="lesson-block-bar"><span class="lesson-block-label">%1$s</span>%2$s</div><pre><code class="language-%1$s">%3$s</code></pre></div>',
            e($language),
            $this->copyButton($rawText, __('Code kopieren')),
            $escapedInner,
        );
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

    private function renderTerminalBlock(string $rawText): string
    {
        $lines = explode("\n", rtrim($rawText, "\n"));
        $renderedLines = array_map(
            fn (string $line): string => sprintf('<span class="lesson-line lesson-line-output">%s</span>', e($line)),
            $lines,
        );

        return sprintf(
            '<div class="lesson-terminal"><pre><code>%s</code></pre></div>',
            implode("\n", $renderedLines),
        );
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
}

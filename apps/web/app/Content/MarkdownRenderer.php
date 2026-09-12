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
        $html = $this->resolveTerms($html);
        $html = $this->markMermaidBlocks($html);

        return $html;
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
}

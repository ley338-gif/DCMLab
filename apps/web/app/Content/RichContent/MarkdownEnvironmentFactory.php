<?php

namespace App\Content\RichContent;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;

/**
 * Eine einzige Stelle fuer die CommonMark-Umgebung, die sowohl
 * `MarkdownRenderer` (Markdown -> HTML) als auch
 * `MarkdownToRichContentConverter` (Markdown -> Rich-Content-JSON, ADR 0111)
 * verwenden -- beide muessen exakt dieselben Erweiterungen (insbesondere die
 * GFM-Tabellen) aktiviert haben, sonst koennten sie fuer denselben Text
 * unterschiedliche Strukturen sehen.
 */
final class MarkdownEnvironmentFactory
{
    public static function make(): EnvironmentInterface
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);

        return $environment;
    }
}

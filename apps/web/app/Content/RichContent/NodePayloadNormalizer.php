<?php

namespace App\Content\RichContent;

use App\Content\NodeSections;

/**
 * Node-Gegenstueck zu `LessonPayloadNormalizer` (CMS-7d.3, ADR 0118). Ein
 * Legacy-`body`-Payload wird per `NodeSections::parse()` in Briefing/
 * Hints (pro Hint-Id)/Write-up zerlegt und zum `node_content`-Umschlag aus
 * ADR 0115 zusammengesetzt -- derselbe Umschlag, den `rich-content:migrate`
 * (CMS-7d.2) fuer den Erst-Backfill gebaut hat.
 */
final class NodePayloadNormalizer
{
    public function __construct(
        private readonly MarkdownToRichContentConverter $converter = new MarkdownToRichContentConverter,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        if (array_key_exists('rich_content', $payload)) {
            return $payload;
        }

        if (! is_string($payload['body'] ?? null)) {
            return $payload;
        }

        $sections = NodeSections::parse($payload['body']);

        $hints = [];

        foreach ($sections['hints'] as $id => $text) {
            $hints[$id] = $this->converter->convert($text);
        }

        $payload['rich_content'] = [
            'type' => 'node_content',
            'version' => 1,
            'briefing' => $this->converter->convert($sections['briefing']),
            'hints' => $hints,
            'write_up' => $this->converter->convert($sections['write_up']),
        ];
        unset($payload['body']);

        return $payload;
    }
}

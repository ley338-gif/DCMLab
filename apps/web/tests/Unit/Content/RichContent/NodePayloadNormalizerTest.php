<?php

namespace Tests\Unit\Content\RichContent;

use App\Content\RichContent\NodePayloadNormalizer;
use Tests\TestCase;

class NodePayloadNormalizerTest extends TestCase
{
    public function test_it_leaves_a_payload_with_rich_content_untouched(): void
    {
        $payload = ['title' => 'Test', 'rich_content' => ['type' => 'node_content', 'version' => 1]];

        $this->assertSame($payload, (new NodePayloadNormalizer)->normalize($payload));
    }

    public function test_it_converts_a_legacy_body_payload_to_the_node_content_envelope(): void
    {
        $payload = [
            'title' => 'Test',
            'body' => "## Briefing\n\nBriefing-Text.\n\n## Hints\n\n### h1\n\nHint-Text.\n\n## Write-up\n\nWrite-up-Text.\n",
        ];

        $normalized = (new NodePayloadNormalizer)->normalize($payload);

        $this->assertArrayNotHasKey('body', $normalized);
        $envelope = $normalized['rich_content'];
        $this->assertSame('node_content', $envelope['type']);
        $this->assertSame(1, $envelope['version']);
        $this->assertSame('Briefing-Text.', $envelope['briefing']['content'][0]['content'][0]['text']);
        $this->assertSame('Hint-Text.', $envelope['hints']['h1']['content'][0]['content'][0]['text']);
        $this->assertSame('Write-up-Text.', $envelope['write_up']['content'][0]['content'][0]['text']);
    }

    public function test_it_leaves_a_payload_without_body_or_rich_content_untouched(): void
    {
        $payload = ['title' => 'Test'];

        $this->assertSame($payload, (new NodePayloadNormalizer)->normalize($payload));
    }
}

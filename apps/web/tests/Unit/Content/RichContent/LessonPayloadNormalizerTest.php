<?php

namespace Tests\Unit\Content\RichContent;

use App\Content\RichContent\LessonPayloadNormalizer;
use Tests\TestCase;

class LessonPayloadNormalizerTest extends TestCase
{
    public function test_it_leaves_a_payload_with_rich_content_untouched(): void
    {
        $payload = ['title' => 'Test', 'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []]];

        $this->assertSame($payload, (new LessonPayloadNormalizer)->normalize($payload));
    }

    public function test_it_converts_a_legacy_body_payload_to_rich_content(): void
    {
        $payload = ['title' => 'Test', 'body' => "## Intro\n\nEin Absatz."];

        $normalized = (new LessonPayloadNormalizer)->normalize($payload);

        $this->assertArrayNotHasKey('body', $normalized);
        $this->assertSame('Test', $normalized['title']);
        $this->assertSame('doc', $normalized['rich_content']['type']);
        $this->assertSame('Ein Absatz.', $normalized['rich_content']['content'][1]['content'][0]['text']);
    }

    public function test_it_leaves_a_payload_without_body_or_rich_content_untouched(): void
    {
        $payload = ['title' => 'Test'];

        $this->assertSame($payload, (new LessonPayloadNormalizer)->normalize($payload));
    }
}

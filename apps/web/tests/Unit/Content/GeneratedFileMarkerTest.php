<?php

namespace Tests\Unit\Content;

use App\Content\GeneratedFileMarker;
use Tests\TestCase;

class GeneratedFileMarkerTest extends TestCase
{
    public function test_it_prepends_a_yaml_comment_for_yml_files(): void
    {
        $result = GeneratedFileMarker::apply('nodes/foo/node.yml', "slug: foo\n");

        $this->assertStringStartsWith('# '.GeneratedFileMarker::TEXT."\n", $result);
        $this->assertStringContainsString("slug: foo\n", $result);
    }

    public function test_applying_it_twice_to_yaml_does_not_duplicate_the_marker(): void
    {
        $once = GeneratedFileMarker::apply('nodes/foo/node.yml', "slug: foo\n");
        $twice = GeneratedFileMarker::apply('nodes/foo/node.yml', $once);

        $this->assertSame($once, $twice);
        $this->assertSame(1, substr_count($twice, GeneratedFileMarker::TEXT));
    }

    public function test_it_inserts_an_html_comment_right_after_frontmatter_for_markdown_files(): void
    {
        $original = "---\ntitle: Foo\n---\n\nText.\n";

        $result = GeneratedFileMarker::apply('lessons/1.0/de.md', $original);

        $this->assertStringContainsString("---\ntitle: Foo\n---\n\n<!-- ".GeneratedFileMarker::TEXT." -->\n\nText.\n", $result);
    }

    public function test_applying_it_twice_to_markdown_does_not_duplicate_the_marker(): void
    {
        $original = "---\ntitle: Foo\n---\n\nText.\n";

        $once = GeneratedFileMarker::apply('lessons/1.0/de.md', $original);
        $twice = GeneratedFileMarker::apply('lessons/1.0/de.md', $once);

        $this->assertSame($once, $twice);
        $this->assertSame(1, substr_count($twice, GeneratedFileMarker::TEXT));
    }

    public function test_markdown_without_frontmatter_gets_the_marker_prepended(): void
    {
        $result = GeneratedFileMarker::apply('exams/foo/de.md', "Text ohne Frontmatter.\n");

        $this->assertStringStartsWith('<!-- '.GeneratedFileMarker::TEXT.' -->', $result);
    }
}

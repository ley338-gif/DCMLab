<?php

namespace Tests\Unit\Content;

use App\Content\FrontMatter;
use App\Content\LessonMetaGenerator;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * ADR 0080/0081 (W6.2): das Gegenstueck zu den meta.yml-/Frontmatter-Feldern,
 * die App\Content\ContentRepository schon liest.
 */
class LessonMetaGeneratorTest extends TestCase
{
    public function test_regenerate_meta_replaces_only_the_named_scalar_and_list_fields(): void
    {
        $metaRaw = "id: \"1.0\"\ntrack: fundamente\nlevel: einsteiger\nduration_minutes: 5\nrequires: []\ntools: [dcmdump]\nglossary_terms: [dicom]\nstatus: draft\n";

        $regenerated = LessonMetaGenerator::regenerateMeta($metaRaw, [
            'level' => 'aufbau',
            'duration_minutes' => 12,
            'tools' => ['dcmdump', 'echoscu'],
            'requires' => ['1.0'],
            'glossary_terms' => ['dicom', 'instance'],
        ]);
        $parsed = Yaml::parse($regenerated);

        $this->assertSame('aufbau', $parsed['level']);
        $this->assertSame(12, $parsed['duration_minutes']);
        $this->assertSame(['dcmdump', 'echoscu'], $parsed['tools']);
        $this->assertSame(['1.0'], $parsed['requires']);
        $this->assertSame(['dicom', 'instance'], $parsed['glossary_terms']);
        $this->assertSame('fundamente', $parsed['track']);
        $this->assertSame('draft', $parsed['status']);
    }

    public function test_regenerate_meta_ignores_fields_not_present_in_the_input(): void
    {
        $metaRaw = "id: \"1.0\"\nlevel: einsteiger\nrequires: []\n";

        $regenerated = LessonMetaGenerator::regenerateMeta($metaRaw, ['level' => 'fortgeschritten']);
        $parsed = Yaml::parse($regenerated);

        $this->assertSame('fortgeschritten', $parsed['level']);
        $this->assertSame([], $parsed['requires']);
    }

    public function test_regenerate_meta_preserves_comments_and_untouched_fields(): void
    {
        $metaRaw = "id: \"1.0\"\n\n# Ein wichtiger Kommentar.\nlevel: einsteiger\nstatus: draft\n";

        $regenerated = LessonMetaGenerator::regenerateMeta($metaRaw, ['level' => 'aufbau']);

        $this->assertStringContainsString('# Ein wichtiger Kommentar.', $regenerated);
        $this->assertStringContainsString('status: draft', $regenerated);
    }

    public function test_regenerate_front_matter_replaces_title_and_teaser_only(): void
    {
        $mdRaw = "---\ntitle: Alter Titel\nteaser: Alter Teaser\nobjectives:\n  - Eins\n---\n\n## Intro\n\nText.\n";

        $regenerated = LessonMetaGenerator::regenerateFrontMatter($mdRaw, [
            'title' => 'Neuer Titel',
            'teaser' => 'Neuer Teaser, mit Komma',
        ]);
        $frontMatter = FrontMatter::parse($regenerated);

        $this->assertSame('Neuer Titel', $frontMatter['attributes']['title']);
        $this->assertSame('Neuer Teaser, mit Komma', $frontMatter['attributes']['teaser']);
        $this->assertSame(['Eins'], $frontMatter['attributes']['objectives']);
        $this->assertStringContainsString('## Intro', $regenerated);
        $this->assertStringContainsString('Text.', $regenerated);
    }

    public function test_regenerate_front_matter_leaves_content_without_front_matter_unchanged(): void
    {
        $mdRaw = "## Intro\n\nText ohne Frontmatter.\n";

        $regenerated = LessonMetaGenerator::regenerateFrontMatter($mdRaw, ['title' => 'Neu']);

        $this->assertSame($mdRaw, $regenerated);
    }

    public function test_matches_the_real_lesson_1_1_meta_shape_on_a_no_op_round_trip(): void
    {
        $metaRaw = file_get_contents(base_path('../../content/lessons/1.1/meta.yml'));

        if ($metaRaw === false) {
            $this->markTestSkipped('content/lessons/1.1 nicht gefunden.');
        }

        $meta = Yaml::parse($metaRaw);

        $regenerated = LessonMetaGenerator::regenerateMeta($metaRaw, [
            'level' => $meta['level'],
            'duration_minutes' => $meta['duration_minutes'],
            'tools' => $meta['tools'],
            'requires' => $meta['requires'],
            'glossary_terms' => $meta['glossary_terms'],
        ]);
        $reparsed = Yaml::parse($regenerated);

        $this->assertSame($meta['level'], $reparsed['level']);
        $this->assertSame($meta['duration_minutes'], $reparsed['duration_minutes']);
        $this->assertSame($meta['tools'], $reparsed['tools']);
        $this->assertSame($meta['requires'], $reparsed['requires']);
        $this->assertSame($meta['glossary_terms'], $reparsed['glossary_terms']);
        $this->assertSame($meta['quiz'], $reparsed['quiz']);
    }

    public function test_regenerate_sandbox_writes_dataset_only_when_required(): void
    {
        $metaRaw = "id: \"1.0\"\nsandbox:\n  required: true\n  dataset: old-set\nrelated_node:\n  node: null\n  optional: true\n";

        $regenerated = LessonMetaGenerator::regenerateSandbox($metaRaw, ['required' => false]);
        $parsed = Yaml::parse($regenerated);

        $this->assertFalse($parsed['sandbox']['required']);
        $this->assertArrayNotHasKey('dataset', $parsed['sandbox']);
        $this->assertSame(['node' => null, 'optional' => true], $parsed['related_node'], 'related_node darf unangetastet bleiben.');
    }

    public function test_regenerate_sandbox_includes_dataset_and_note_when_present(): void
    {
        $metaRaw = "id: \"1.0\"\nsandbox:\n  required: false\n";

        $regenerated = LessonMetaGenerator::regenerateSandbox($metaRaw, [
            'required' => true,
            'dataset' => 'ct-thorax-60',
            'note' => 'Zwei Serien',
        ]);
        $parsed = Yaml::parse($regenerated);

        $this->assertTrue($parsed['sandbox']['required']);
        $this->assertSame('ct-thorax-60', $parsed['sandbox']['dataset']);
        $this->assertSame('Zwei Serien', $parsed['sandbox']['note']);
    }

    public function test_regenerate_lab_writes_node_as_literal_null_when_absent(): void
    {
        $metaRaw = "id: \"1.0\"\nrelated_node:\n  node: first-contact\n  optional: false\n";

        $regenerated = LessonMetaGenerator::regenerateRelatedNode($metaRaw, ['node' => null, 'optional' => true]);
        $parsed = Yaml::parse($regenerated);

        $this->assertNull($parsed['related_node']['node']);
        $this->assertTrue($parsed['related_node']['optional']);
    }

    public function test_regenerate_lab_writes_a_new_node_slug(): void
    {
        $metaRaw = "id: \"1.0\"\nrelated_node:\n  node: null\n  optional: true\n";

        $regenerated = LessonMetaGenerator::regenerateRelatedNode($metaRaw, ['node' => 'neue-node', 'optional' => false]);
        $parsed = Yaml::parse($regenerated);

        $this->assertSame('neue-node', $parsed['related_node']['node']);
        $this->assertFalse($parsed['related_node']['optional']);
    }

    public function test_regenerate_front_matter_replaces_the_objectives_list(): void
    {
        $mdRaw = "---\ntitle: X\nobjectives:\n  - Alt eins\n  - Alt zwei\n---\n\n## Intro\n\nText.\n";

        $regenerated = LessonMetaGenerator::regenerateFrontMatter($mdRaw, [
            'objectives' => ['Neu eins', 'Neu zwei', 'Neu drei'],
        ]);
        $frontMatter = FrontMatter::parse($regenerated);

        $this->assertSame(['Neu eins', 'Neu zwei', 'Neu drei'], $frontMatter['attributes']['objectives']);
        $this->assertStringContainsString('## Intro', $regenerated);
        $this->assertStringContainsString('Text.', $regenerated);
    }

    public function test_matches_the_real_lesson_1_0_sandbox_lab_objectives_shape_on_a_no_op_round_trip(): void
    {
        $metaRaw = file_get_contents(base_path('../../content/lessons/1.0/meta.yml'));
        $mdRaw = file_get_contents(base_path('../../content/lessons/1.0/de.md'));

        if ($metaRaw === false || $mdRaw === false) {
            $this->markTestSkipped('content/lessons/1.0 nicht gefunden.');
        }

        $meta = Yaml::parse($metaRaw);
        $frontMatter = FrontMatter::parse($mdRaw);

        $regeneratedMeta = LessonMetaGenerator::regenerateRelatedNode(
            LessonMetaGenerator::regenerateSandbox($metaRaw, $meta['sandbox']),
            $meta['related_node'],
        );
        $regeneratedMd = LessonMetaGenerator::regenerateFrontMatter($mdRaw, [
            'objectives' => $frontMatter['attributes']['objectives'],
        ]);

        $reparsedMeta = Yaml::parse($regeneratedMeta);
        $reparsedFrontMatter = FrontMatter::parse($regeneratedMd);

        $this->assertSame($meta['sandbox'], $reparsedMeta['sandbox']);
        $this->assertSame($meta['related_node'], $reparsedMeta['related_node']);
        $this->assertSame($frontMatter['attributes']['objectives'], $reparsedFrontMatter['attributes']['objectives']);
    }
}

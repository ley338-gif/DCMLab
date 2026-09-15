<?php

namespace Tests\Unit\Content;

use App\Content\ExamMetaGenerator;
use App\Content\FrontMatter;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * ADR 0082 (W6.3): das Gegenstueck zu den exam.yml-/Frontmatter-Feldern, die
 * App\Content\ContentRepository schon liest. Der `questions:`-Pool bleibt
 * unangetastet -- er ist nicht Teil dieses Generators, siehe ExamActivity.
 */
class ExamMetaGeneratorTest extends TestCase
{
    public function test_regenerate_meta_replaces_only_the_named_settings_fields(): void
    {
        $metaRaw = "track: fundamente\ntitle_key: exam.fundamente.title\npass_percent: 80\ndraw: 24\nduration_minutes: 25\nshuffle: true\nquestions:\n  - id: f01\n    type: single\n    answer: 0\n    lesson: \"1.0\"\n    tags: [netzwerk]\n";

        $regenerated = ExamMetaGenerator::regenerateMeta($metaRaw, [
            'pass_percent' => 85,
            'draw' => 20,
            'duration_minutes' => 30,
            'shuffle' => false,
            'min_per_lesson' => 3,
        ]);
        $parsed = Yaml::parse($regenerated);

        $this->assertSame(85, $parsed['pass_percent']);
        $this->assertSame(20, $parsed['draw']);
        $this->assertSame(30, $parsed['duration_minutes']);
        $this->assertFalse($parsed['shuffle']);
        $this->assertSame(3, $parsed['min_per_lesson']);
        $this->assertSame('fundamente', $parsed['track']);
        $this->assertCount(1, $parsed['questions']);
        $this->assertSame('f01', $parsed['questions'][0]['id']);
    }

    public function test_regenerate_meta_ignores_fields_not_present_in_the_input(): void
    {
        $metaRaw = "track: fundamente\npass_percent: 80\ndraw: 24\n";

        $regenerated = ExamMetaGenerator::regenerateMeta($metaRaw, ['pass_percent' => 90]);
        $parsed = Yaml::parse($regenerated);

        $this->assertSame(90, $parsed['pass_percent']);
        $this->assertSame(24, $parsed['draw']);
    }

    public function test_regenerate_meta_preserves_comments_and_the_question_pool(): void
    {
        $metaRaw = "track: fundamente\n\n# Ein wichtiger Kommentar.\npass_percent: 80\nquestions:\n  - id: f01\n    type: single\n";

        $regenerated = ExamMetaGenerator::regenerateMeta($metaRaw, ['pass_percent' => 90]);

        $this->assertStringContainsString('# Ein wichtiger Kommentar.', $regenerated);
        $this->assertStringContainsString('questions:', $regenerated);
        $this->assertStringContainsString('id: f01', $regenerated);
    }

    public function test_regenerate_front_matter_replaces_title_and_intro_only(): void
    {
        $mdRaw = "---\ntitle: Alter Titel\nintro: Alter Intro\n---\n\n### f01 — Frage?\n\n1. Ja\n2. Nein\n";

        $regenerated = ExamMetaGenerator::regenerateFrontMatter($mdRaw, [
            'title' => 'Neuer Titel',
            'intro' => 'Neuer Intro, mit Komma',
        ]);
        $frontMatter = FrontMatter::parse($regenerated);

        $this->assertSame('Neuer Titel', $frontMatter['attributes']['title']);
        $this->assertSame('Neuer Intro, mit Komma', $frontMatter['attributes']['intro']);
        $this->assertStringContainsString('### f01 — Frage?', $regenerated);
    }

    public function test_regenerate_front_matter_leaves_content_without_front_matter_unchanged(): void
    {
        $mdRaw = "### f01 — Frage ohne Frontmatter\n";

        $regenerated = ExamMetaGenerator::regenerateFrontMatter($mdRaw, ['title' => 'Neu']);

        $this->assertSame($mdRaw, $regenerated);
    }

    public function test_matches_the_real_fundamente_exam_shape_on_a_no_op_round_trip(): void
    {
        $metaRaw = file_get_contents(base_path('../../content/exams/fundamente/exam.yml'));

        if ($metaRaw === false) {
            $this->markTestSkipped('content/exams/fundamente nicht gefunden.');
        }

        $meta = Yaml::parse($metaRaw);

        $regenerated = ExamMetaGenerator::regenerateMeta($metaRaw, [
            'pass_percent' => $meta['pass_percent'],
            'draw' => $meta['draw'],
            'duration_minutes' => $meta['duration_minutes'],
            'shuffle' => $meta['shuffle'],
        ]);
        $reparsed = Yaml::parse($regenerated);

        $this->assertSame($meta['pass_percent'], $reparsed['pass_percent']);
        $this->assertSame($meta['draw'], $reparsed['draw']);
        $this->assertSame($meta['duration_minutes'], $reparsed['duration_minutes']);
        $this->assertSame($meta['shuffle'], $reparsed['shuffle']);
        $this->assertSame($meta['questions'], $reparsed['questions']);
    }
}

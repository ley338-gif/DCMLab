<?php

namespace Tests\Unit\Content;

use App\Content\ExamContent;
use App\Content\ExamQuestionGenerator;
use App\Content\MarkdownRenderer;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * ADR 0090 (W6.3): das Gegenstueck zu ExamContent::parseQuestions() -- der
 * gesamte Fragenpool wird ersetzt (keine Einzelkarte wie beim Lektions-
 * Quiz), `ref`-Fragen bekommen keinen eigenen de.md-Block.
 */
class ExamQuestionGeneratorTest extends TestCase
{
    public function test_regenerate_meta_writes_a_full_question_with_a_single_review_target(): void
    {
        $metaRaw = "track: fundamente\nquestions:\n  - id: old\n    type: single\n    answer: 0\n    lesson: \"1.0\"\n    review: { lesson: \"1.0\", anchor: \"x\" }\n    difficulty: 1\n    tags: []\n";

        $regenerated = ExamQuestionGenerator::regenerateMeta($metaRaw, [
            [
                'id' => 'f01', 'is_ref' => false, 'type' => 'single', 'answer' => 1,
                'lesson' => '1.0', 'review' => [['lesson' => '1.0', 'anchor' => 'intro']],
                'difficulty' => 2, 'tags' => ['netzwerk'],
            ],
        ]);
        $parsed = Yaml::parse($regenerated);

        $this->assertSame('fundamente', $parsed['track'], 'andere Felder muessen erhalten bleiben.');
        $this->assertCount(1, $parsed['questions']);
        $question = $parsed['questions'][0];
        $this->assertSame('f01', $question['id']);
        $this->assertSame('single', $question['type']);
        $this->assertSame(1, $question['answer']);
        $this->assertSame('1.0', $question['lesson']);
        $this->assertSame(['lesson' => '1.0', 'anchor' => 'intro'], $question['review']);
        $this->assertSame(2, $question['difficulty']);
        $this->assertSame(['netzwerk'], $question['tags']);
    }

    public function test_regenerate_meta_writes_a_ref_question(): void
    {
        $metaRaw = "track: fundamente\nquestions:\n  - id: old\n    type: single\n    answer: 0\n    lesson: \"1.0\"\n    review: { lesson: \"1.0\", anchor: \"x\" }\n    difficulty: 1\n    tags: []\n";

        $regenerated = ExamQuestionGenerator::regenerateMeta($metaRaw, [
            [
                'id' => 'f01', 'is_ref' => true, 'ref_lesson' => '1.0', 'ref_question' => 'q1',
                'lesson' => '1.0', 'review' => [['lesson' => '1.0', 'anchor' => 'intro']],
                'difficulty' => 1, 'tags' => [],
            ],
        ]);
        $parsed = Yaml::parse($regenerated);
        $question = $parsed['questions'][0];

        $this->assertSame(['lesson' => '1.0', 'question' => 'q1'], $question['ref']);
        $this->assertArrayNotHasKey('type', $question);
        $this->assertArrayNotHasKey('answer', $question);
    }

    public function test_regenerate_meta_writes_multiple_review_targets_as_a_list(): void
    {
        $metaRaw = "track: fundamente\nquestions:\n  - id: old\n    type: single\n    answer: 0\n    lesson: cross\n    review: { lesson: \"1.0\", anchor: \"x\" }\n    difficulty: 1\n    tags: []\n";

        $regenerated = ExamQuestionGenerator::regenerateMeta($metaRaw, [
            [
                'id' => 'f37', 'is_ref' => false, 'type' => 'single', 'answer' => 0,
                'lesson' => 'cross',
                'review' => [
                    ['lesson' => '1.5', 'anchor' => 'a'],
                    ['lesson' => '1.7', 'anchor' => 'b'],
                ],
                'difficulty' => 3, 'tags' => [],
            ],
        ]);
        $parsed = Yaml::parse($regenerated);

        $this->assertSame([
            ['lesson' => '1.5', 'anchor' => 'a'],
            ['lesson' => '1.7', 'anchor' => 'b'],
        ], $parsed['questions'][0]['review']);
    }

    public function test_regenerate_meta_preserves_other_top_level_fields_and_comments(): void
    {
        $metaRaw = "track: fundamente\n\n# Kommentar.\npass_percent: 80\nquestions:\n  - id: old\n    type: single\n    answer: 0\n    lesson: \"1.0\"\n    review: { lesson: \"1.0\", anchor: \"x\" }\n    difficulty: 1\n    tags: []\nshuffle: true\n";

        $regenerated = ExamQuestionGenerator::regenerateMeta($metaRaw, [
            [
                'id' => 'f01', 'is_ref' => false, 'type' => 'input', 'answer' => 'test',
                'lesson' => '1.0', 'review' => [['lesson' => '1.0', 'anchor' => 'intro']],
                'difficulty' => 1, 'tags' => [],
            ],
        ]);

        $this->assertStringContainsString('# Kommentar.', $regenerated);
        $this->assertStringContainsString('pass_percent: 80', $regenerated);
        $this->assertStringContainsString('shuffle: true', $regenerated);
    }

    public function test_regenerate_body_renders_single_multi_truefalse_input_blocks_and_skips_refs(): void
    {
        $body = ExamQuestionGenerator::regenerateBody([
            ['id' => 'f01', 'is_ref' => false, 'type' => 'single', 'question' => 'Frage eins?', 'options' => ['A', 'B'], 'explanation' => 'Erklärung eins.'],
            ['id' => 'f02', 'is_ref' => false, 'type' => 'multi', 'question' => 'Frage zwei?', 'options' => ['A', 'B', 'C'], 'explanation' => 'Erklärung zwei.'],
            ['id' => 'f03', 'is_ref' => false, 'type' => 'truefalse', 'question' => 'Aussage drei.', 'explanation' => 'Erklärung drei.'],
            ['id' => 'f04', 'is_ref' => false, 'type' => 'input', 'question' => 'Frage vier?', 'explanation' => 'Erklärung vier.'],
            ['id' => 'f05', 'is_ref' => true, 'ref_lesson' => '1.0', 'ref_question' => 'q1'],
        ]);

        $this->assertStringContainsString('### f01 — Frage eins?', $body);
        $this->assertStringContainsString("1. A\n2. B", $body);
        $this->assertStringContainsString('### f02 — Frage zwei? *(Mehrfachauswahl)*', $body);
        $this->assertStringContainsString('### f03 — Aussage drei.', $body);
        $this->assertStringContainsString('**Richtig / Falsch**', $body);
        $this->assertStringContainsString('### f04 — Frage vier? *(Freitext)*', $body);
        $this->assertStringNotContainsString('f05', $body, 'ref-Fragen bekommen keinen eigenen Block.');

        $renderer = new MarkdownRenderer([]);
        $blocks = ExamContent::blocksFor($body);
        $this->assertCount(4, $blocks);
        $this->assertSame(['A', 'B'], ExamContent::extractOptions($blocks['f01']['body']));
        unset($renderer);
    }

    public function test_matches_the_real_fundamente_exam_pool_on_a_no_op_round_trip(): void
    {
        $metaRaw = file_get_contents(base_path('../../content/exams/fundamente/exam.yml'));
        $mdRaw = file_get_contents(base_path('../../content/exams/fundamente/de.md'));

        if ($metaRaw === false || $mdRaw === false) {
            $this->markTestSkipped('content/exams/fundamente nicht gefunden.');
        }

        $lessonsRaw = [];
        foreach (['1.0', '1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8'] as $lessonId) {
            $lessonMeta = file_get_contents(base_path("../../content/lessons/{$lessonId}/meta.yml"));
            $lessonMd = file_get_contents(base_path("../../content/lessons/{$lessonId}/de.md"));

            if ($lessonMeta === false || $lessonMd === false) {
                $this->markTestSkipped("content/lessons/{$lessonId} nicht gefunden.");
            }

            $lessonsRaw[$lessonId] = [
                'meta' => Yaml::parse($lessonMeta),
                'md_raw' => $lessonMd,
            ];
        }

        $meta = Yaml::parse($metaRaw);
        $renderer = new MarkdownRenderer([]);
        $blocks = ExamContent::blocksFor(preg_replace('/^---.*?---\r?\n/s', '', $mdRaw) ?? '');

        $draftQuestions = [];

        foreach ($meta['questions'] as $entry) {
            $ref = $entry['ref'] ?? null;
            $targets = ExamContent::reviewTargets($entry);

            if ($ref !== null) {
                $draftQuestions[] = [
                    'id' => $entry['id'], 'is_ref' => true,
                    'ref_lesson' => $ref['lesson'], 'ref_question' => $ref['question'],
                    'lesson' => $entry['lesson'], 'review' => $targets,
                    'difficulty' => $entry['difficulty'], 'tags' => $entry['tags'] ?? [],
                ];

                continue;
            }

            $block = $blocks[$entry['id']];
            $type = $entry['type'];
            $options = $type === 'truefalse' ? [] : ExamContent::extractOptions($block['body']);
            $explanation = ExamContent::explanationFor(
                preg_replace('/^---.*?---\r?\n/s', '', $mdRaw) ?? '',
                $entry['id'],
                $renderer,
            );
            preg_match('/\*\*Erklärung:\*\*\s*(.+)$/mu', $block['body'], $rawExplanationMatch);

            $draftQuestions[] = [
                'id' => $entry['id'], 'is_ref' => false, 'type' => $type,
                'question' => $block['question'], 'options' => $options,
                'answer' => $entry['answer'], 'explanation' => trim($rawExplanationMatch[1] ?? ''),
                'lesson' => $entry['lesson'], 'review' => $targets,
                'difficulty' => $entry['difficulty'], 'tags' => $entry['tags'] ?? [],
            ];
        }

        $regeneratedMeta = ExamQuestionGenerator::regenerateMeta($metaRaw, $draftQuestions);
        $reparsedMeta = Yaml::parse($regeneratedMeta);

        $this->assertSame($meta['questions'], $reparsedMeta['questions']);
    }
}

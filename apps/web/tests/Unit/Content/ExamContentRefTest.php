<?php

namespace Tests\Unit\Content;

use App\Content\ExamContent;
use App\Content\MarkdownRenderer;
use Tests\TestCase;

/**
 * Fragenbank (ADR 0071/0079, W5-DoD): "Dieselbe Frage laesst sich in einem
 * Lektionsquiz und im Pruefungspool verwenden, ohne sie zweimal zu
 * pflegen." Ein `ref`-Eintrag in exam.yml's `questions` braucht kein
 * eigenes type/answer/de.md-Abschnitt -- alles kommt aus dem `quiz:`-Block
 * der referenzierten Lektion.
 */
class ExamContentRefTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private function lessons(): array
    {
        return [
            '1.0' => [
                'id' => '1.0',
                'meta' => [
                    'quiz' => [
                        ['id' => 'q1', 'type' => 'single', 'answer' => 1],
                    ],
                ],
                'md_raw' => "---\ntitle: Test\n---\n\n## Quiz\n\n**q1 — Was stimmt?**\n1. Falsch\n2. Richtig\n",
                'body' => "## Quiz\n\n**q1 — Was stimmt?**\n1. Falsch\n2. Richtig\n",
            ],
        ];
    }

    private function renderer(): MarkdownRenderer
    {
        return new MarkdownRenderer([]);
    }

    public function test_a_ref_entry_is_resolved_without_its_own_exam_markdown_block(): void
    {
        $examMeta = [
            ['id' => 'f01', 'ref' => ['lesson' => '1.0', 'question' => 'q1']],
        ];

        // Kein "### f01 — ..."-Abschnitt im exam-de.md -- die Frage kommt
        // vollstaendig aus der Lektion.
        $questions = ExamContent::parseQuestions('', $examMeta, $this->renderer(), $this->lessons());

        $this->assertCount(1, $questions);
        $this->assertSame('f01', $questions[0]['id']);
        $this->assertSame('single', $questions[0]['type']);
        $this->assertStringContainsString('Was stimmt?', $questions[0]['question_html']);
        $this->assertSame(['Falsch', 'Richtig'], $questions[0]['options_html']);
    }

    public function test_answer_for_a_ref_entry_comes_from_the_lessons_quiz_meta(): void
    {
        $examMeta = [
            ['id' => 'f01', 'ref' => ['lesson' => '1.0', 'question' => 'q1']],
        ];

        $answer = ExamContent::answerFor($examMeta, 'f01', $this->lessons());

        $this->assertSame(1, $answer);
    }

    public function test_type_for_a_ref_entry_comes_from_the_lessons_quiz_meta(): void
    {
        $entry = ['id' => 'f01', 'ref' => ['lesson' => '1.0', 'question' => 'q1']];

        $this->assertSame('single', ExamContent::typeFor($entry, $this->lessons()));
    }

    public function test_a_ref_to_an_unknown_lesson_resolves_to_no_question(): void
    {
        $examMeta = [
            ['id' => 'f01', 'ref' => ['lesson' => 'does-not-exist', 'question' => 'q1']],
        ];

        $questions = ExamContent::parseQuestions('', $examMeta, $this->renderer(), $this->lessons());

        $this->assertSame([], $questions);
    }

    public function test_a_ref_to_an_unknown_question_id_resolves_to_no_question(): void
    {
        $examMeta = [
            ['id' => 'f01', 'ref' => ['lesson' => '1.0', 'question' => 'q99']],
        ];

        $questions = ExamContent::parseQuestions('', $examMeta, $this->renderer(), $this->lessons());

        $this->assertSame([], $questions);
    }

    public function test_self_contained_entries_still_work_unchanged_alongside_refs(): void
    {
        $examMeta = [
            ['id' => 'f01', 'ref' => ['lesson' => '1.0', 'question' => 'q1']],
            ['id' => 'f02', 'type' => 'single', 'answer' => 0],
        ];
        $mdRaw = "### f02 — Eigene Frage?\n\n1. Ja\n2. Nein\n";

        $questions = ExamContent::parseQuestions($mdRaw, $examMeta, $this->renderer(), $this->lessons());

        $this->assertSame(['f01', 'f02'], array_column($questions, 'id'));
        $this->assertSame('single', $questions[1]['type']);
        $this->assertStringContainsString('Eigene Frage?', $questions[1]['question_html']);
    }
}

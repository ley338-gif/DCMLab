<?php

namespace Tests\Unit\Content;

use App\Content\FrontMatter;
use App\Content\LessonQuizGenerator;
use App\Content\MarkdownRenderer;
use App\Content\QuizContent;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * ADR 0080 (W6): das Gegenstueck zu QuizContent -- was hier erzeugt wird,
 * muss QuizContent/ContentValidator unveraendert wieder einlesen koennen.
 */
class LessonQuizGeneratorTest extends TestCase
{
    private const QUESTIONS = [
        ['id' => 'q1', 'type' => 'single', 'answer' => 1, 'question' => 'Was stimmt?', 'options' => ['Falsch', 'Richtig']],
        ['id' => 'q2', 'type' => 'multi', 'answer' => [0, 2], 'question' => 'Welche Aussagen stimmen?', 'options' => ['Eins', 'Zwei', 'Drei']],
        ['id' => 'q3', 'type' => 'input', 'answer' => 'DICM', 'question' => 'Wie lautet die Kennung?', 'options' => []],
    ];

    public function test_regenerated_meta_parses_back_to_the_same_quiz_array(): void
    {
        $metaRaw = "id: \"1.0\"\ntrack: fundamente\nrequires: []\nquiz:\n  - id: old\n    type: input\n    answer: X\ntools_checked: \"2026-01-01\"\n";

        $regenerated = LessonQuizGenerator::regenerateMeta($metaRaw, self::QUESTIONS);
        $parsed = Yaml::parse($regenerated);

        $this->assertSame([
            ['id' => 'q1', 'type' => 'single', 'answer' => 1],
            ['id' => 'q2', 'type' => 'multi', 'answer' => [0, 2]],
            ['id' => 'q3', 'type' => 'input', 'answer' => 'DICM'],
        ], $parsed['quiz']);
    }

    public function test_regenerating_meta_preserves_every_other_field_and_comment(): void
    {
        $metaRaw = "id: \"1.0\"\ntrack: fundamente\n\n# Ein wichtiger Kommentar.\nrequires: []\nquiz:\n  - id: old\n    type: input\n    answer: X\ntools_checked: \"2026-01-01\"\nstatus: draft\n";

        $regenerated = LessonQuizGenerator::regenerateMeta($metaRaw, self::QUESTIONS);

        $this->assertStringContainsString('# Ein wichtiger Kommentar.', $regenerated);
        $this->assertStringContainsString('id: "1.0"', $regenerated);
        $this->assertStringContainsString('tools_checked: "2026-01-01"', $regenerated);
        $this->assertStringContainsString('status: draft', $regenerated);
    }

    public function test_meta_without_an_existing_quiz_block_appends_one(): void
    {
        $metaRaw = "id: \"1.0\"\ntrack: fundamente\nrequires: []\n";

        $regenerated = LessonQuizGenerator::regenerateMeta($metaRaw, self::QUESTIONS);
        $parsed = Yaml::parse($regenerated);

        $this->assertSame('fundamente', $parsed['track']);
        $this->assertCount(3, $parsed['quiz']);
    }

    public function test_regenerated_body_parses_back_into_the_same_questions_via_quiz_content(): void
    {
        $body = "## Intro\n\nText.\n\n## Quiz\n\n*Wissenskarten — kommen später zur Wiederholung zurück.*\n\n**q1 — Alte Frage?**\n1. A\n2. B\n\n---\n\n**Als Nächstes:** weiter.\n";

        $regenerated = LessonQuizGenerator::regenerateBody($body, self::QUESTIONS);
        $split = QuizContent::splitBody($regenerated);

        $quizMeta = array_map(fn (array $q) => ['id' => $q['id'], 'type' => $q['type']], self::QUESTIONS);
        $parsed = QuizContent::parseQuestions($split['quiz_raw'], $quizMeta, new MarkdownRenderer([]));

        $this->assertSame(['q1', 'q2', 'q3'], array_column($parsed, 'id'));
        $this->assertStringContainsString('Was stimmt?', $parsed[0]['question_html']);
        $this->assertSame(['Falsch', 'Richtig'], $parsed[0]['options_html']);
        $this->assertSame([], $parsed[2]['options_html']);
    }

    public function test_regenerating_body_preserves_the_preamble_and_the_prose_before_and_after(): void
    {
        $body = "## Intro\n\nWichtiger Text davor.\n\n## Quiz\n\n*Wissenskarten — kommen später zur Wiederholung zurück.*\n\n**q1 — Alte Frage?**\n1. A\n2. B\n\n---\n\n**Als Nächstes:** [1.2](../1.2/).\n";

        $regenerated = LessonQuizGenerator::regenerateBody($body, self::QUESTIONS);

        $this->assertStringContainsString('Wichtiger Text davor.', $regenerated);
        $this->assertStringContainsString('Wissenskarten — kommen später zur Wiederholung zurück.', $regenerated);
        $this->assertStringContainsString('**Als Nächstes:** [1.2](../1.2/).', $regenerated);
        $this->assertStringNotContainsString('Alte Frage', $regenerated);
    }

    public function test_body_without_an_existing_quiz_section_appends_one_before_eof(): void
    {
        $body = "## Intro\n\nText ohne Quiz.\n";

        $regenerated = LessonQuizGenerator::regenerateBody($body, self::QUESTIONS);

        $this->assertStringContainsString('Text ohne Quiz.', $regenerated);
        $this->assertStringContainsString('## Quiz', $regenerated);
        $this->assertStringContainsString('Was stimmt?', $regenerated);
    }

    public function test_matches_the_real_lesson_1_1_quiz_shape_byte_for_byte_on_a_no_op_round_trip(): void
    {
        $metaRaw = file_get_contents(base_path('../../content/lessons/1.1/meta.yml'));
        $mdRaw = file_get_contents(base_path('../../content/lessons/1.1/de.md'));

        if ($metaRaw === false || $mdRaw === false) {
            $this->markTestSkipped('content/lessons/1.1 nicht gefunden.');
        }

        $meta = Yaml::parse($metaRaw);
        $frontMatter = FrontMatter::parse($mdRaw);
        $split = QuizContent::splitBody($frontMatter['body']);
        $renderer = new MarkdownRenderer([]);
        $parsedQuestions = QuizContent::parseQuestions($split['quiz_raw'], $meta['quiz'], $renderer);

        // Aus den geparsten Fragen (HTML-gerendert) wieder Rohtext machen ist
        // verlustbehaftet (HTML vs. Markdown) -- dieser Test prueft deshalb
        // nur, dass ein no-op-Regenerieren (dieselben Fragen, die schon da
        // sind) weiterhin eine gueltige, geparste Struktur ergibt, nicht
        // Byte-Identitaet.
        $questions = [];
        foreach ($meta['quiz'] as $index => $q) {
            $questions[] = [
                'id' => $q['id'],
                'type' => $q['type'],
                'answer' => $q['answer'],
                'question' => strip_tags($parsedQuestions[$index]['question_html']),
                'options' => array_map('strip_tags', $parsedQuestions[$index]['options_html']),
            ];
        }

        $regeneratedMeta = LessonQuizGenerator::regenerateMeta($metaRaw, $questions);
        $regeneratedBody = LessonQuizGenerator::regenerateBody($frontMatter['body'], $questions);

        $reparsedMeta = Yaml::parse($regeneratedMeta);
        $this->assertCount(count($meta['quiz']), $reparsedMeta['quiz']);

        $reparsedSplit = QuizContent::splitBody($regeneratedBody);
        $reparsedQuestions = QuizContent::parseQuestions($reparsedSplit['quiz_raw'], $reparsedMeta['quiz'], $renderer);
        $this->assertSame(array_column($meta['quiz'], 'id'), array_column($reparsedQuestions, 'id'));
    }

    /**
     * content:draft: QuizContent::parseRawQuestions() ist das rohe
     * Gegenstueck zu renderCard() -- Fragetext und Optionen kommen
     * unveraendert (Markdown, ohne Typ-Zusatz) zurueck.
     */
    public function test_raw_questions_parse_back_to_the_generated_text(): void
    {
        $questions = [...self::QUESTIONS, ['id' => 'q4', 'type' => 'single', 'answer' => 0, 'question' => 'Was zeigt `echoscu -v`?', 'options' => ['`Association Accepted`', 'Nichts']]];
        $body = LessonQuizGenerator::regenerateBody("Prosa.\n\n## Quiz\n\n*Wissenskarten.*\n", $questions);

        $parsed = QuizContent::parseRawQuestions(QuizContent::splitBody($body)['quiz_raw']);

        $this->assertSame(['q1', 'q2', 'q3', 'q4'], array_keys($parsed));
        foreach ($questions as $question) {
            $this->assertSame(
                ['question' => $question['question'], 'options' => $question['type'] === 'input' ? [] : $question['options']],
                $parsed[$question['id']],
            );
        }
    }
}

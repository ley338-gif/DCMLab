<?php

namespace App\Content;

/**
 * Loest den Fragenpool einer Track-Abschlusspruefung aus `exams/<track>/de.md`
 * heraus und verbindet ihn mit den Antworten aus `exam.yml` (P10.60). Wie bei
 * QuizContent gilt: Fragetext, Optionen und Erklaerung sind Prosa und bleiben
 * in de.md, `exam.yml` traegt nur Schluessel (id/type/answer/lesson/review/
 * difficulty/tags) -- beides wird live pro Request gelesen, nichts wird in
 * die DB synchronisiert.
 *
 * Format, das `de.md` real benutzt (bewusst anders als die `**qN —**`-Karten
 * der Lektionen: hier ist es eine echte Markdown-Ueberschrift, damit
 * MarkdownRenderer/HeadingSlug eine Anker-ID daraus machen koennen):
 *
 *   ### f01 — Fragetext
 *
 *   1. Option eins
 *   2. Option zwei
 *
 *   **Erklärung:** ...
 *
 *   ### f14 — Aussage. *(Mehrfachauswahl)*
 *
 *   **Richtig / Falsch**
 *
 *   **Erklärung:** ...
 */
final class ExamContent
{
    /**
     * @param  array<int, array<string, mixed>>  $examMeta
     * @return array<int, array{id: string, type: string, question_html: string, options_html: array<int, string>}>
     */
    public static function parseQuestions(string $mdRaw, array $examMeta, MarkdownRenderer $renderer): array
    {
        $typeById = [];
        foreach ($examMeta as $entry) {
            $typeById[(string) ($entry['id'] ?? '')] = (string) ($entry['type'] ?? 'single');
        }

        $blocks = self::splitIntoBlocks($mdRaw);
        $questions = [];

        foreach ($blocks as $id => $block) {
            $type = $typeById[$id] ?? 'single';
            $options = $type === 'truefalse' ? [] : self::extractOptions($block['body']);

            $questions[] = [
                'id' => $id,
                'type' => $type,
                'question_html' => self::renderInline($block['question'], $renderer),
                'options_html' => array_map(
                    fn (string $option): string => self::renderInline($option, $renderer),
                    $options,
                ),
            ];
        }

        return $questions;
    }

    /**
     * Liest die richtige Antwort ausschliesslich serverseitig aus exam.yml --
     * darf nie an den Client gehen, bevor eine Antwort bewertet wurde.
     *
     * @param  array<int, array<string, mixed>>  $examMeta
     */
    public static function answerFor(array $examMeta, string $questionId): mixed
    {
        foreach ($examMeta as $entry) {
            if ((string) ($entry['id'] ?? '') === $questionId) {
                return $entry['answer'] ?? null;
            }
        }

        return null;
    }

    /**
     * `review` ist im Schema entweder ein einzelnes {lesson,anchor}-Objekt
     * oder eine Liste davon (bei `cross`-Fragen mit mehreren Zielen) --
     * diese Methode normalisiert beides auf eine Liste.
     *
     * @param  array<string, mixed>  $entry  ein Eintrag aus exam.yml's `questions`
     * @return list<array{lesson: string, anchor: string}>
     */
    public static function reviewTargets(array $entry): array
    {
        $review = $entry['review'] ?? [];

        if (isset($review['lesson'])) {
            return [['lesson' => (string) $review['lesson'], 'anchor' => (string) $review['anchor']]];
        }

        return array_values(array_map(
            fn (array $target): array => ['lesson' => (string) $target['lesson'], 'anchor' => (string) $target['anchor']],
            $review,
        ));
    }

    /**
     * Wird erst nach der Bewertung ausgeliefert -- niemals vorab.
     */
    public static function explanationFor(string $mdRaw, string $questionId, MarkdownRenderer $renderer): ?string
    {
        $blocks = self::splitIntoBlocks($mdRaw);
        $block = $blocks[$questionId] ?? null;

        if ($block === null) {
            return null;
        }

        if (! preg_match('/^\*\*Erklärung:\*\*\s*(.+(?:\n(?!\s*$).+)*)/mu', $block['body'], $match)) {
            return null;
        }

        return trim($renderer->render(trim($match[1])));
    }

    /**
     * Oeffentlicher Zugriff auf die Block-Zerlegung fuer ContentValidate --
     * derselbe Parser wie beim eigentlichen Rendern, damit Validierung und
     * Ausgabe nie auseinanderlaufen.
     *
     * @return array<string, array{question: string, body: string}> keyed by "f<nn>"
     */
    public static function blocksFor(string $mdRaw): array
    {
        return self::splitIntoBlocks($mdRaw);
    }

    /**
     * @return array<string, array{question: string, body: string}> keyed by "f<nn>"
     */
    private static function splitIntoBlocks(string $mdRaw): array
    {
        $lines = preg_split('/\R/', $mdRaw) ?: [];
        $blocks = [];
        $currentId = null;
        $currentQuestion = '';
        $currentBody = [];

        foreach ($lines as $line) {
            if (preg_match('/^###\s+(f\d+)\s*—\s*(.+?)\s*$/u', $line, $match)) {
                if ($currentId !== null) {
                    $blocks[$currentId] = ['question' => $currentQuestion, 'body' => implode("\n", $currentBody)];
                }

                $currentId = $match[1];
                $currentQuestion = $match[2];
                $currentBody = [];

                continue;
            }

            if ($currentId !== null) {
                $currentBody[] = $line;
            }
        }

        if ($currentId !== null) {
            $blocks[$currentId] = ['question' => $currentQuestion, 'body' => implode("\n", $currentBody)];
        }

        return $blocks;
    }

    /**
     * @return list<string>
     */
    public static function extractOptions(string $body): array
    {
        $options = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if (preg_match('/^\d+\.\s+(.+)$/', $line, $match)) {
                $options[] = $match[1];
            }
        }

        return $options;
    }

    private static function renderInline(string $markdown, MarkdownRenderer $renderer): string
    {
        $html = trim($renderer->render($markdown));

        return preg_replace('#^<p>(.*)</p>$#s', '$1', $html) ?? $html;
    }
}

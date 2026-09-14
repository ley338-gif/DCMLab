<?php

namespace App\Content;

/**
 * Loest den `## Quiz`-Abschnitt einer Lektion aus ihrem Markdown-Body heraus
 * (Abschnitt 4.7, "quiz: # Wissenskarten fuer Spaced Repetition") und
 * verbindet ihn mit den Antworten aus meta.yml. Wie bei NodeSections gilt:
 * Fragetext und Optionen sind Prosa und bleiben in de.md, meta.yml traegt nur
 * Schluessel (id/type/answer) -- diese Klasse liest beides live pro Request,
 * es wird nichts in die DB synchronisiert.
 *
 * Struktur, die in allen bestehenden Lektionen (1.0-1.8) real vorkommt:
 *
 *   ## Quiz
 *
 *   **q1 — Fragetext**
 *   1. Option eins
 *   2. Option zwei
 *
 *   **q2 — Fragetext** *(Mehrfachauswahl)*
 *   1. ...
 *
 *   **q3 — Fragetext** *(Freitext)*
 *
 *   ---
 *
 *   **Als Nächstes:** ...
 */
final class QuizContent
{
    /**
     * Trennt den Body in "vor dem Quiz" (weiterhin normal als Prosa
     * gerendert), den rohen Quiz-Abschnitt (strukturiert geparst statt als
     * HTML ausgegeben) und "nach dem Quiz" (Navigation/Fussnote, bleibt
     * ebenfalls Prosa). Ohne `## Quiz`-Ueberschrift bleibt alles in `before`.
     *
     * @return array{before: string, quiz_raw: string, after: string}
     */
    public static function splitBody(string $body): array
    {
        $lines = preg_split('/\R/', $body) ?: [];
        $quizStart = null;
        $quizEnd = null;

        foreach ($lines as $index => $line) {
            if ($quizStart === null) {
                if (preg_match('/^##\s+Quiz\s*$/', $line)) {
                    $quizStart = $index;
                }

                continue;
            }

            if (trim($line) === '---') {
                $quizEnd = $index;
                break;
            }
        }

        if ($quizStart === null) {
            return ['before' => $body, 'quiz_raw' => '', 'after' => ''];
        }

        $quizEnd ??= count($lines);

        return [
            'before' => implode("\n", array_slice($lines, 0, $quizStart)),
            'quiz_raw' => implode("\n", array_slice($lines, $quizStart, $quizEnd - $quizStart)),
            'after' => implode("\n", array_slice($lines, $quizEnd)),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $quizMeta
     * @return array<int, array{id: string, type: string, question_html: string, options_html: array<int, string>}>
     */
    public static function parseQuestions(string $quizRaw, array $quizMeta, MarkdownRenderer $renderer): array
    {
        $typeById = [];
        foreach ($quizMeta as $entry) {
            $typeById[(string) ($entry['id'] ?? '')] = (string) ($entry['type'] ?? 'single');
        }

        $lines = preg_split('/\R/', $quizRaw) ?: [];
        $order = [];
        /** @var array<string, string> $questionText */
        $questionText = [];
        /** @var array<string, list<string>> $questionOptions */
        $questionOptions = [];
        $currentId = null;

        foreach ($lines as $line) {
            if (preg_match('/^\*\*(q\d+)\s*—\s*(.+?)\*\*/u', $line, $match)) {
                $currentId = $match[1];
                $order[] = $currentId;
                $questionText[$currentId] = $match[2];
                $questionOptions[$currentId] = [];

                continue;
            }

            if ($currentId !== null && preg_match('/^\d+\.\s+(.+)$/', $line, $match)) {
                $questionOptions[$currentId][] = $match[1];
            }
        }

        return array_map(fn (string $id): array => [
            'id' => $id,
            'type' => $typeById[$id] ?? 'single',
            'question_html' => self::renderInline($questionText[$id], $renderer),
            'options_html' => array_map(
                fn (string $option): string => self::renderInline($option, $renderer),
                $questionOptions[$id],
            ),
        ], $order);
    }

    /**
     * Liest die richtige Antwort ausschliesslich serverseitig aus meta.yml --
     * darf nie an den Client gehen, bevor eine Antwort bewertet wurde.
     *
     * @param  array<int, array<string, mixed>>  $quizMeta
     */
    public static function answerFor(array $quizMeta, string $questionId): mixed
    {
        foreach ($quizMeta as $entry) {
            if ((string) ($entry['id'] ?? '') === $questionId) {
                return $entry['answer'] ?? null;
            }
        }

        return null;
    }

    private static function renderInline(string $markdown, MarkdownRenderer $renderer): string
    {
        $html = trim($renderer->render($markdown));

        // render() umschliesst Block-Inhalt mit <p>...</p> -- fuer eine
        // einzelne Zeile (Fragetext, eine Option) wird das gestrippt, damit
        // sie sauber in ein Label/Legend passt, ohne einen Block-Umbruch.
        return preg_replace('#^<p>(.*)</p>$#s', '$1', $html) ?? $html;
    }
}

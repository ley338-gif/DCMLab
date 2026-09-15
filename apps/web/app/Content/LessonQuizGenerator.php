<?php

namespace App\Content;

use Symfony\Component\Yaml\Yaml;

/**
 * Erzeugt den `quiz:`-Block in `meta.yml` und den `## Quiz`-Abschnitt in
 * `de.md` aus einer strukturierten Fragenliste (ADR 0080, W6): das
 * Gegenstueck zu `QuizContent`, das dieselben Fragen wieder einliest.
 * Chirurgisch statt vollstaendig neu zu dumpen -- ein Yaml::dump() des
 * ganzen meta.yml wuerde jeden Kommentar in der Datei loeschen (siehe
 * ContentBuilder::writeHash() fuer denselben Grundsatz beim Flag-Hash).
 *
 * @phpstan-type QuizQuestionDraft array{id: string, type: string, answer: mixed, question: string, options: list<string>}
 */
final class LessonQuizGenerator
{
    /**
     * @param  list<array{id: string, type: string, answer: mixed, question: string, options: list<string>}>  $questions
     */
    public static function regenerateMeta(string $metaRaw, array $questions): string
    {
        $lines = ['quiz:'];

        foreach ($questions as $question) {
            $lines[] = "  - id: {$question['id']}";
            $lines[] = "    type: {$question['type']}";
            $lines[] = '    answer: '.self::dumpAnswer($question['answer']);
        }

        $newBlock = implode("\n", $lines)."\n";

        // \r?\n statt \n: manche Dateien im Bestand haben CRLF-Zeilenenden
        // (z. B. content/lessons/1.1/meta.yml) -- ohne das matcht "quiz:\n"
        // nicht gegen "quiz:\r\n" und der alte Block bleibt stehen, was zu
        // einem doppelten quiz:-Schluessel fuehrt.
        if (preg_match('/^quiz:\r?\n(?:[ \t].*\r?\n|\r?\n)*/m', $metaRaw) === 1) {
            return preg_replace('/^quiz:\r?\n(?:[ \t].*\r?\n|\r?\n)*/m', $newBlock, $metaRaw, 1);
        }

        return rtrim($metaRaw, "\r\n")."\n\n".$newBlock;
    }

    /**
     * `answer` ist je nach Fragetyp ein Index (single), eine Liste von
     * Indizes (multi) oder ein exakter String (input) -- `Yaml::dump()`
     * uebernimmt nur das korrekte Quoting eines einzelnen Skalars, die
     * Zeile selbst bauen wir von Hand, damit sie wie im echten Bestand auf
     * einer Zeile steht (`answer: [0, 2]`, nicht mehrzeilig).
     */
    private static function dumpAnswer(mixed $answer): string
    {
        if (is_array($answer)) {
            return '['.implode(', ', array_map(self::dumpAnswer(...), $answer)).']';
        }

        if (is_bool($answer) || is_int($answer)) {
            return Yaml::dump($answer);
        }

        return trim(Yaml::dump((string) $answer));
    }

    /**
     * @param  list<array{id: string, type: string, answer: mixed, question: string, options: list<string>}>  $questions
     */
    public static function regenerateBody(string $body, array $questions): string
    {
        $split = QuizContent::splitBody($body);

        if ($split['quiz_raw'] === '') {
            return rtrim($body, "\n")."\n\n".self::regenerateQuizRaw('## Quiz', $questions);
        }

        return $split['before'].self::regenerateQuizRaw($split['quiz_raw'], $questions).$split['after'];
    }

    /**
     * @param  list<array{id: string, type: string, answer: mixed, question: string, options: list<string>}>  $questions
     */
    private static function regenerateQuizRaw(string $currentQuizRaw, array $questions): string
    {
        $preamble = self::extractPreamble($currentQuizRaw);

        $parts = ['## Quiz'];

        if ($preamble !== '') {
            $parts[] = $preamble;
        }

        $parts[] = implode("\n\n", array_map(self::renderCard(...), $questions));

        return implode("\n\n", $parts)."\n\n";
    }

    /**
     * Freitext direkt unter der `## Quiz`-Ueberschrift, vor der ersten
     * Karte (z. B. "*Wissenskarten — kommen spaeter zur Wiederholung
     * zurueck.*") -- bleibt beim Regenerieren erhalten, weil er nicht aus
     * den Fragen selbst kommt.
     */
    private static function extractPreamble(string $quizRaw): string
    {
        $lines = preg_split('/\R/', $quizRaw) ?: [];
        $preambleLines = [];

        foreach (array_slice($lines, 1) as $line) {
            if (preg_match('/^\*\*q\d+\s*—/u', $line)) {
                break;
            }

            $preambleLines[] = $line;
        }

        return trim(implode("\n", $preambleLines));
    }

    /**
     * @param  array{id: string, type: string, answer: mixed, question: string, options: list<string>}  $question
     */
    private static function renderCard(array $question): string
    {
        $suffix = match ($question['type']) {
            'multi' => ' *(Mehrfachauswahl)*',
            'input' => ' *(Freitext)*',
            default => '',
        };

        $lines = ["**{$question['id']} — {$question['question']}**{$suffix}"];

        if ($question['type'] !== 'input') {
            foreach ($question['options'] as $index => $option) {
                $lines[] = ($index + 1).". {$option}";
            }
        }

        return implode("\n", $lines);
    }
}

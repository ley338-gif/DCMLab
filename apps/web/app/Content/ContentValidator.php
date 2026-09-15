<?php

namespace App\Content;

use App\Achievements\AchievementRegistry;
use Illuminate\Support\Carbon;

/**
 * Das Regelwissen aus dem frueheren `content:validate`-Command (ADR 0071),
 * jetzt als Service: ohne Dateisystemzugriff gegen bereits geladene
 * ContentRepository-Arrays aufrufbar, damit CI (`ContentValidate`-Command)
 * und der Autoren-Editor (ADR 0071, W6) denselben Regelsatz benutzen statt
 * einer zweiten, schwaecheren Pruefung im Formular.
 */
final class ContentValidator
{
    /** @var ContentIssue[] */
    private array $issues = [];

    /**
     * @param  array<int, array<string, mixed>>  $themenfelder
     * @param  array<int, array<string, mixed>>  $tracks
     * @param  array<int, array<string, mixed>>  $achievements
     * @param  array<string, array<string, mixed>>  $lessons
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  array<string, array<string, mixed>>  $exams
     * @param  array<string, array<string, mixed>>  $tools
     * @param  array<string, array<string, mixed>>  $glossary
     * @param  array<string, array<string, mixed>>  $datasets
     * @param  array<int, array<string, mixed>>  $skills
     * @return list<ContentIssue>
     */
    public function validate(
        array $themenfelder,
        array $tracks,
        array $achievements,
        array $lessons,
        array $nodes,
        array $exams,
        array $tools,
        ?string $toolsRaw,
        array $glossary,
        array $datasets,
        array $skills,
    ): array {
        $this->issues = [];

        $this->checkAchievements($achievements, $nodes, $tracks);
        $this->checkTrackThemenfelder($tracks, $themenfelder);

        foreach ($lessons as $id => $lesson) {
            $this->checkLessonStructure($id, $lesson, $lessons);
            $this->checkExampleRule($lesson['md_file'], $lesson['md_raw'], $lesson['body'], $lesson['body_start_line']);
            $this->checkTerms($lesson['md_file'], $lesson['body'] ?? '', $lesson['body_start_line'], $glossary);

            if ($lesson['meta'] !== null) {
                $this->checkLessonTools($lesson, $tools);
                $this->checkDatasetReference(
                    $lesson['meta_file'],
                    $lesson['meta_raw'] ?? '',
                    data_get($lesson['meta'], 'sandbox.dataset'),
                    $datasets,
                );
            }
        }

        foreach ($nodes as $slug => $node) {
            $this->checkNodeStructure($slug, $node, $lessons);
            $this->checkExampleRule($node['md_file'], $node['md_raw'], $node['body'], $node['body_start_line']);
            $this->checkTerms($node['md_file'], $node['body'] ?? '', $node['body_start_line'], $glossary);

            if ($node['def'] !== null) {
                $this->checkNodeTools($node, $tools);
                $this->checkDatasetReference(
                    $node['def_file'],
                    $node['def_raw'] ?? '',
                    data_get($node['def'], 'environment.dataset'),
                    $datasets,
                );
                $this->checkPlaceholders($node);
                $this->checkFlagFormat($node);
                $this->checkNodeAchievements($node);
                $this->checkNodeThemenfeld($node, $themenfelder);

                if (data_get($node['def'], 'interaction') === 'scenario') {
                    $this->checkScenarioStructure($node);
                }
            }
        }

        $this->checkToolPurposes($toolsRaw, $tools);

        foreach ($exams as $trackSlug => $exam) {
            $this->checkExamStructure($trackSlug, $exam, $lessons, $skills);
        }

        return array_values($this->issues);
    }

    // ---------------------------------------------------------------
    // Struktur
    // ---------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $lesson
     * @param  array<string, array<string, mixed>>  $allLessons
     */
    private function checkLessonStructure(string $id, array $lesson, array $allLessons): void
    {
        if ($lesson['meta'] !== null && $lesson['md_raw'] === null) {
            $this->issue($lesson['meta_file'], null, "Lektion {$id}: meta.yml ohne de.md");
        }

        if ($lesson['md_raw'] !== null && $lesson['meta'] === null) {
            $this->issue($lesson['md_file'], null, "Lektion {$id}: de.md ohne meta.yml");
        }

        if ($lesson['meta'] === null) {
            return;
        }

        $meta = $lesson['meta'];
        $metaFile = $lesson['meta_file'];
        $metaRaw = $lesson['meta_raw'] ?? '';

        // objectives_count muss zur Anzahl der objectives im Frontmatter passen.
        $expected = $meta['objectives_count'] ?? null;
        $actual = is_array($lesson['frontmatter']['objectives'] ?? null)
            ? count($lesson['frontmatter']['objectives'])
            : 0;

        if ($expected !== null && $expected !== $actual) {
            $this->issue(
                $metaFile,
                LineFinder::firstLineContaining($metaRaw, 'objectives_count'),
                "objectives_count ist {$expected}, de.md hat {$actual} objectives im Frontmatter",
            );
        }

        // requires muss auf existierende Lektions-IDs zeigen.
        foreach (($meta['requires'] ?? []) as $requiredId) {
            if (! array_key_exists((string) $requiredId, $allLessons)) {
                $this->issue(
                    $metaFile,
                    LineFinder::firstLineContaining($metaRaw, 'requires'),
                    "requires verweist auf unbekannte Lektion \"{$requiredId}\"",
                );
            }
        }

        $this->checkQuizStructure($metaFile, $metaRaw, $lesson['md_file'], $lesson['md_raw'], $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function checkQuizStructure(string $metaFile, string $metaRaw, string $mdFile, ?string $mdRaw, array $meta): void
    {
        $quiz = $meta['quiz'] ?? [];

        if ($quiz === []) {
            return;
        }

        $mdRaw ??= '';
        preg_match_all('/^\*\*(q\d+)\s*—/mu', $mdRaw, $matches);
        $presentIds = $matches[1];

        foreach ($quiz as $entry) {
            $id = (string) ($entry['id'] ?? '');
            $type = (string) ($entry['type'] ?? '');
            $answer = $entry['answer'] ?? null;

            if (! in_array($id, $presentIds, true)) {
                $this->issue(
                    $mdFile,
                    null,
                    "Quiz-Frage \"{$id}\" aus meta.yml hat keinen \"**{$id} — ...**\"-Abschnitt in de.md",
                );

                continue;
            }

            $optionCount = $this->countQuizOptions($mdRaw, $id);

            match ($type) {
                'single' => $this->checkQuizIndexAnswer($metaFile, $metaRaw, $id, $answer, $optionCount),
                'multi' => $this->checkQuizMultiAnswer($metaFile, $metaRaw, $id, $answer, $optionCount),
                'input' => $this->checkQuizInputAnswer($metaFile, $metaRaw, $id, $answer),
                default => $this->issue(
                    $metaFile,
                    LineFinder::firstLineContaining($metaRaw, $id),
                    "Quiz-Frage \"{$id}\": unbekannter type \"{$type}\" (erlaubt: single, multi, input)",
                ),
            };
        }
    }

    private function checkQuizIndexAnswer(string $metaFile, string $metaRaw, string $id, mixed $answer, int $optionCount): void
    {
        if (! is_int($answer) || $answer < 0 || $answer >= $optionCount) {
            $this->issue(
                $metaFile,
                LineFinder::firstLineContaining($metaRaw, $id),
                "Quiz-Frage \"{$id}\": answer-Index liegt ausserhalb der {$optionCount} vorhandenen Optionen",
            );
        }
    }

    private function checkQuizMultiAnswer(string $metaFile, string $metaRaw, string $id, mixed $answer, int $optionCount): void
    {
        if (! is_array($answer) || $answer === []) {
            $this->issue(
                $metaFile,
                LineFinder::firstLineContaining($metaRaw, $id),
                "Quiz-Frage \"{$id}\": answer muss bei type multi eine nicht-leere Liste sein",
            );

            return;
        }

        foreach ($answer as $index) {
            if (! is_int($index) || $index < 0 || $index >= $optionCount) {
                $this->issue(
                    $metaFile,
                    LineFinder::firstLineContaining($metaRaw, $id),
                    "Quiz-Frage \"{$id}\": answer-Index liegt ausserhalb der {$optionCount} vorhandenen Optionen",
                );

                return;
            }
        }
    }

    private function checkQuizInputAnswer(string $metaFile, string $metaRaw, string $id, mixed $answer): void
    {
        if (! is_string($answer) || trim($answer) === '') {
            $this->issue(
                $metaFile,
                LineFinder::firstLineContaining($metaRaw, $id),
                "Quiz-Frage \"{$id}\": answer muss bei type input ein nicht-leerer String sein",
            );
        }
    }

    private function countQuizOptions(string $mdRaw, string $questionId): int
    {
        $lines = preg_split('/\R/', $mdRaw) ?: [];
        $collecting = false;
        $count = 0;

        foreach ($lines as $line) {
            if (preg_match('/^\*\*(q\d+)\s*—/mu', $line, $match)) {
                if ($collecting) {
                    break;
                }

                $collecting = $match[1] === $questionId;

                continue;
            }

            if ($collecting && preg_match('/^\d+\.\s+/', $line)) {
                $count++;
            }
        }

        return $count;
    }

    // ---------------------------------------------------------------
    // Track-Abschlusspruefung (P10.60)
    // ---------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $exam
     * @param  array<string, array<string, mixed>>  $allLessons
     * @param  array<int, array<string, mixed>>  $skills
     */
    private function checkExamStructure(string $trackSlug, array $exam, array $allLessons, array $skills): void
    {
        if ($exam['meta'] !== null && $exam['md_raw'] === null) {
            $this->issue($exam['meta_file'], null, "Pruefung {$trackSlug}: exam.yml ohne de.md");
        }

        if ($exam['md_raw'] !== null && $exam['meta'] === null) {
            $this->issue($exam['md_file'], null, "Pruefung {$trackSlug}: de.md ohne exam.yml");
        }

        if ($exam['meta'] === null) {
            return;
        }

        $meta = $exam['meta'];
        $metaFile = $exam['meta_file'];
        $metaRaw = $exam['meta_raw'] ?? '';
        $mdRaw = $exam['md_raw'] ?? '';
        $blocks = ExamContent::blocksFor($mdRaw);
        /** @var array<int, array<string, mixed>> $questions */
        $questions = $meta['questions'] ?? [];
        $skillSlugs = array_map(fn (array $skill): string => (string) $skill['slug'], $skills);
        $trackLessonIds = array_keys(array_filter(
            $allLessons,
            fn (array $lesson) => ($lesson['meta']['track'] ?? null) === $trackSlug,
        ));

        $passPercent = $meta['pass_percent'] ?? null;
        if (! is_int($passPercent) || $passPercent < 50 || $passPercent > 100) {
            $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, 'pass_percent'), 'pass_percent muss zwischen 50 und 100 liegen');
        }

        $seenIds = [];
        $typeCounts = ['single' => 0, 'multi' => 0, 'truefalse' => 0, 'input' => 0];
        $difficulty3Count = 0;
        $countsByLesson = [];

        foreach ($questions as $entry) {
            $id = (string) ($entry['id'] ?? '');

            if (! preg_match('/^f\d+$/', $id)) {
                // Die "### fNN — ..."-Ueberschrift in de.md wird von
                // ExamContent::splitIntoBlocks() nur bei diesem Muster
                // erkannt -- eine abweichende id verschmilzt sonst
                // stillschweigend mit dem vorherigen Block (doppelte
                // Erklaerung dort, fehlender Abschnitt hier), statt eines
                // klaren Fehlers.
                $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, $id), "Pruefungsfrage \"{$id}\": id muss dem Muster \"fNN\" folgen (z. B. f41)");
            }

            if (isset($seenIds[$id])) {
                $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, $id), "Pruefungsfrage \"{$id}\" ist mehrfach vergeben");
            }
            $seenIds[$id] = true;

            $lesson = (string) ($entry['lesson'] ?? '');
            $countsByLesson[$lesson] = ($countsByLesson[$lesson] ?? 0) + 1;

            if ($lesson !== 'cross' && ! in_array($lesson, $trackLessonIds, true)) {
                $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, $id), "Pruefungsfrage \"{$id}\": lesson \"{$lesson}\" gehoert nicht zu Track \"{$trackSlug}\"");
            }

            $difficulty = $entry['difficulty'] ?? null;
            if (! in_array($difficulty, [1, 2, 3], true)) {
                $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, $id), "Pruefungsfrage \"{$id}\": difficulty muss 1, 2 oder 3 sein");
            } elseif ($difficulty === 3) {
                $difficulty3Count++;
            }

            $tags = $entry['tags'] ?? [];
            if (! is_array($tags) || count($tags) > 2) {
                $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, $id), "Pruefungsfrage \"{$id}\": tags hoechstens zwei");
            }
            foreach ((array) $tags as $tag) {
                if (! in_array((string) $tag, $skillSlugs, true)) {
                    $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, $id), "Pruefungsfrage \"{$id}\": tag \"{$tag}\" existiert nicht in skills.yml");
                }
            }

            $this->checkExamReview($metaFile, $metaRaw, $id, $entry, $allLessons, $trackLessonIds);

            $ref = $entry['ref'] ?? null;

            if ($ref !== null) {
                $optionCount = $this->checkExamQuestionRef($metaFile, $metaRaw, $id, $ref, $allLessons);

                if ($optionCount === null) {
                    continue;
                }

                $block = $blocks[$id] ?? null;

                if ($block !== null) {
                    $this->checkExamExplanation($exam['md_file'], $id, $block);
                }

                $answer = QuizContent::answerFor($allLessons[$ref['lesson']]['meta']['quiz'] ?? [], (string) $ref['question']);
                $type = ExamContent::typeFor($entry, $allLessons);
            } else {
                $block = $blocks[$id] ?? null;

                if ($block === null) {
                    $this->issue($exam['md_file'], null, "Pruefungsfrage \"{$id}\" aus exam.yml hat keinen \"### {$id} — ...\"-Abschnitt in de.md");

                    continue;
                }

                $this->checkExamExplanation($exam['md_file'], $id, $block);

                $type = (string) ($entry['type'] ?? '');
                $optionCount = count(ExamContent::extractOptions($block['body']));
                $answer = $entry['answer'] ?? null;
            }

            if (array_key_exists($type, $typeCounts)) {
                $typeCounts[$type]++;
            }

            match ($type) {
                'single' => $this->checkQuizIndexAnswer($metaFile, $metaRaw, $id, $answer, $optionCount),
                'multi' => $this->checkQuizMultiAnswer($metaFile, $metaRaw, $id, $answer, $optionCount),
                'truefalse' => $this->checkExamTrueFalseAnswer($metaFile, $metaRaw, $id, $answer),
                'input' => $this->checkQuizInputAnswer($metaFile, $metaRaw, $id, $answer),
                default => $this->issue(
                    $metaFile,
                    LineFinder::firstLineContaining($metaRaw, $id),
                    "Pruefungsfrage \"{$id}\": unbekannter type \"{$type}\" (erlaubt: single, multi, truefalse, input)",
                ),
            };
        }

        $poolSize = count($questions);
        $draw = $meta['draw'] ?? null;

        if (! is_int($draw) || $draw > $poolSize) {
            $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, 'draw'), "draw ({$draw}) darf die Poolgroesse ({$poolSize}) nicht ueberschreiten");
        }

        // Sonderfall Troubleshooting (P10.64, siehe P10-Prompt "Anschluss"):
        // dort ist praktisch jede Frage cross, weil jede Stoerung mehrere
        // Lektionen beruehrt -- die feste 4-Fragen-Quote je Lektion waere
        // dort nicht erfuellbar. exam.yml darf die Quote deshalb ueber
        // min_per_lesson bewusst absenken; ohne das Feld gilt weiterhin 4.
        $minPerLesson = $meta['min_per_lesson'] ?? 4;

        if (! is_int($minPerLesson) || $minPerLesson < 0) {
            $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, 'min_per_lesson'), 'min_per_lesson muss eine nicht-negative Ganzzahl sein');
            $minPerLesson = 4;
        }

        foreach ($trackLessonIds as $lessonId) {
            if (($countsByLesson[$lessonId] ?? 0) < $minPerLesson) {
                $this->issue($metaFile, null, "Pruefung {$trackSlug}: Lektion \"{$lessonId}\" hat weniger als {$minPerLesson} Poolfragen");
            }
        }

        if (($countsByLesson['cross'] ?? 0) < 4) {
            $this->issue($metaFile, null, "Pruefung {$trackSlug}: weniger als 4 cross-Fragen im Pool");
        }

        if ($poolSize > 0 && ($difficulty3Count / $poolSize) < 0.25) {
            $this->issue($metaFile, null, "Pruefung {$trackSlug}: weniger als 25% der Poolfragen haben difficulty 3");
        }

        if ($poolSize > 0) {
            $this->checkExamTypeShare($metaFile, $trackSlug, 'single', $typeCounts['single'], $poolSize, 35, 40);
            $this->checkExamTypeShare($metaFile, $trackSlug, 'multi', $typeCounts['multi'], $poolSize, 20, 25);
            $this->checkExamTypeShare($metaFile, $trackSlug, 'truefalse', $typeCounts['truefalse'], $poolSize, 20, 25);
            $this->checkExamTypeShare($metaFile, $trackSlug, 'input', $typeCounts['input'], $poolSize, 10, 15);
        }
    }

    private function checkExamTypeShare(string $metaFile, string $trackSlug, string $type, int $count, int $poolSize, float $minPercent, float $maxPercent): void
    {
        $percent = ($count / $poolSize) * 100;

        if ($percent < $minPercent - 0.01 || $percent > $maxPercent + 0.01) {
            $this->issue(
                $metaFile,
                null,
                sprintf('Pruefung %s: Anteil type "%s" ist %.1f%%, erwartet %d-%d%%', $trackSlug, $type, $percent, $minPercent, $maxPercent),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, array<string, mixed>>  $allLessons
     * @param  array<int, string>  $trackLessonIds
     */
    private function checkExamReview(string $metaFile, string $metaRaw, string $id, array $entry, array $allLessons, array $trackLessonIds): void
    {
        $review = $entry['review'] ?? null;

        if ($review === null || $review === []) {
            $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, $id), "Pruefungsfrage \"{$id}\": review fehlt");

            return;
        }

        $targets = ExamContent::reviewTargets($entry);

        foreach ($targets as $target) {
            $targetLesson = $allLessons[$target['lesson']] ?? null;

            if ($targetLesson === null) {
                $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, $id), "Pruefungsfrage \"{$id}\": review verweist auf unbekannte Lektion \"{$target['lesson']}\"");

                continue;
            }

            $headings = HeadingSlug::headingsIn((string) ($targetLesson['md_raw'] ?? ''));
            $validSlugs = HeadingSlug::uniqueSlugs($headings);

            if (! in_array($target['anchor'], $validSlugs, true)) {
                $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, $id), "Pruefungsfrage \"{$id}\": anchor \"{$target['anchor']}\" ist keine Ueberschrift in Lektion \"{$target['lesson']}\"");
            }
        }
    }

    /**
     * Fragenbank (ADR 0071/0079, W5): prueft, dass `ref.lesson` existiert
     * und `ref.question` eine echte Frage in deren `quiz:`-Block ist, und
     * liefert die Optionsanzahl aus der Lektion selbst zurueck -- dieselbe
     * Zaehlung wie fuer die Lektion, damit `answer`-Grenzen (Index-Bereich
     * bei single/multi) nicht ein zweites Mal, abweichend gepflegt werden.
     * `null` bedeutet: nicht aufloesbar, bereits als Issue vermerkt.
     *
     * @param  array<string, mixed>  $ref
     * @param  array<string, array<string, mixed>>  $allLessons
     */
    private function checkExamQuestionRef(string $metaFile, string $metaRaw, string $id, array $ref, array $allLessons): ?int
    {
        $lessonId = (string) ($ref['lesson'] ?? '');
        $questionId = (string) ($ref['question'] ?? '');
        $lesson = $allLessons[$lessonId] ?? null;

        if ($lesson === null) {
            $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, $id), "Pruefungsfrage \"{$id}\": ref.lesson verweist auf unbekannte Lektion \"{$lessonId}\"");

            return null;
        }

        $exists = false;
        foreach ($lesson['meta']['quiz'] ?? [] as $candidate) {
            if ((string) ($candidate['id'] ?? '') === $questionId) {
                $exists = true;
                break;
            }
        }

        if (! $exists) {
            $this->issue($metaFile, LineFinder::firstLineContaining($metaRaw, $id), "Pruefungsfrage \"{$id}\": ref.question \"{$questionId}\" existiert nicht im quiz-Block von Lektion \"{$lessonId}\"");

            return null;
        }

        return $this->countQuizOptions((string) ($lesson['md_raw'] ?? ''), $questionId);
    }

    /**
     * @param  array{question: string, body: string}  $block
     */
    private function checkExamExplanation(string $mdFile, string $id, array $block): void
    {
        $explanationCount = preg_match_all('/^\*\*Erklärung:\*\*/mu', $block['body']);

        if ($explanationCount !== 1) {
            $this->issue($mdFile, null, "Pruefungsfrage \"{$id}\": braucht genau eine \"**Erklärung:**\"-Zeile ({$explanationCount} gefunden)");
        }
    }

    private function checkExamTrueFalseAnswer(string $metaFile, string $metaRaw, string $id, mixed $answer): void
    {
        if (! is_bool($answer)) {
            $this->issue(
                $metaFile,
                LineFinder::firstLineContaining($metaRaw, $id),
                "Pruefungsfrage \"{$id}\": answer muss bei type truefalse ein Boolean sein",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<string, mixed>>  $allLessons
     */
    private function checkNodeStructure(string $slug, array $node, array $allLessons): void
    {
        if ($node['def'] !== null && $node['md_raw'] === null) {
            $this->issue($node['def_file'], null, "Node {$slug}: node.yml ohne de.md");
        }

        if ($node['md_raw'] !== null && $node['def'] === null) {
            $this->issue($node['md_file'], null, "Node {$slug}: de.md ohne node.yml");
        }

        if ($node['def'] === null) {
            return;
        }

        $def = $node['def'];
        $defFile = $node['def_file'];
        $defRaw = $node['def_raw'] ?? '';

        foreach (($def['related_lessons'] ?? []) as $lessonId) {
            if (! array_key_exists((string) $lessonId, $allLessons)) {
                $this->issue(
                    $defFile,
                    LineFinder::firstLineContaining($defRaw, 'related_lessons'),
                    "related_lessons verweist auf unbekannte Lektion \"{$lessonId}\"",
                );
            }
        }

        // Jede Hint-ID aus node.yml braucht einen ### h<n>-Abschnitt in de.md.
        $definedHintIds = array_map(fn (array $hint) => (string) $hint['id'], $def['hints'] ?? []);
        $mdRaw = $node['md_raw'] ?? '';
        preg_match_all('/^###\s+(h\d+)\s*$/mi', $mdRaw, $matches);
        $presentHintIds = array_map('strtolower', $matches[1]);

        foreach ($definedHintIds as $hintId) {
            if (! in_array(strtolower($hintId), $presentHintIds, true)) {
                $this->issue(
                    $node['md_file'],
                    null,
                    "Hint \"{$hintId}\" aus node.yml hat keinen \"### {$hintId}\"-Abschnitt in de.md",
                );
            }
        }
    }

    /**
     * `interaction: scenario` (Abschnitt 6j): prueft den Entscheidungsbaum
     * strukturell -- Referenzen aufloesbar, jeder Terminalschritt hat ein
     * gueltiges outcome, mindestens ein Erfolgspfad hat ein reveal.
     *
     * @param  array<string, mixed>  $node
     */
    private function checkScenarioStructure(array $node): void
    {
        $defFile = $node['def_file'];
        $defRaw = $node['def_raw'] ?? '';
        $steps = data_get($node['def'], 'scenario.steps', []);
        $start = data_get($node['def'], 'scenario.start');

        if ($start === null || ! array_key_exists((string) $start, $steps)) {
            $this->issue($defFile, LineFinder::firstLineContaining($defRaw, 'scenario'), 'scenario.start fehlt oder verweist auf keinen Schritt in scenario.steps');

            return;
        }

        $hasCorrectOutcome = false;

        foreach ($steps as $stepId => $step) {
            if ($step['terminal'] ?? false) {
                $outcome = $step['outcome'] ?? null;

                if (! in_array($outcome, ['correct', 'wrong'], true)) {
                    $this->issue($defFile, LineFinder::firstLineContaining($defRaw, (string) $stepId), "scenario.steps.{$stepId}: terminal ohne gueltiges outcome (correct|wrong)");
                }

                if ($outcome === 'correct') {
                    if (empty($step['reveal'])) {
                        $this->issue($defFile, LineFinder::firstLineContaining($defRaw, (string) $stepId), "scenario.steps.{$stepId}: outcome correct ohne reveal");
                    } else {
                        $hasCorrectOutcome = true;
                    }
                }

                continue;
            }

            $options = $step['options'] ?? [];

            if ($options === []) {
                $this->issue($defFile, LineFinder::firstLineContaining($defRaw, (string) $stepId), "scenario.steps.{$stepId}: weder terminal noch options");

                continue;
            }

            foreach ($options as $option) {
                foreach (['id', 'label', 'next'] as $field) {
                    if (! isset($option[$field])) {
                        $this->issue($defFile, LineFinder::firstLineContaining($defRaw, (string) $stepId), "scenario.steps.{$stepId}: Option ohne Feld \"{$field}\"");
                    }
                }

                $next = $option['next'] ?? null;

                if ($next !== null && ! array_key_exists((string) $next, $steps)) {
                    $this->issue($defFile, LineFinder::firstLineContaining($defRaw, (string) $stepId), "scenario.steps.{$stepId}: option.next \"{$next}\" verweist auf keinen Schritt");
                }
            }
        }

        if (! $hasCorrectOutcome) {
            $this->issue($defFile, LineFinder::firstLineContaining($defRaw, 'scenario'), 'scenario.steps hat keinen Terminalschritt mit outcome correct und reveal');
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function checkFlagFormat(array $node): void
    {
        // Es gibt keinen festen Flag-Praefix im Projekt (Flags sind reale
        // DICOM-Werte wie eine SeriesDescription). Prüfbar ist aber das
        // Format des Hash-Feldes: beginnt es nicht mit "sha256:", steht dort
        // vermutlich der Flag-Klartext statt seines Hash.
        $hash = data_get($node['def'], 'flag.hash');

        if ($hash !== null && ! str_starts_with((string) $hash, 'sha256:')) {
            $this->issue(
                $node['def_file'],
                LineFinder::firstLineContaining($node['def_raw'] ?? '', 'hash'),
                'flag.hash beginnt nicht mit "sha256:" — sieht nach Flag-Klartext statt Hash aus',
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $tracks
     * @param  array<int, array<string, mixed>>  $themenfelder
     */
    private function checkTrackThemenfelder(array $tracks, array $themenfelder): void
    {
        $knownSlugs = array_column($themenfelder, 'slug');

        foreach ($tracks as $track) {
            $themenfeldSlug = $track['themenfeld'] ?? null;

            if (! in_array($themenfeldSlug, $knownSlugs, true)) {
                $this->issue(
                    $track['_file'],
                    LineFinder::firstLineContaining($track['_raw'], 'slug: '.$track['slug']),
                    "Track \"{$track['slug']}\" referenziert unbekanntes Themenfeld \"{$themenfeldSlug}\"",
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $themenfelder
     */
    private function checkNodeThemenfeld(array $node, array $themenfelder): void
    {
        $knownSlugs = array_column($themenfelder, 'slug');
        $themenfeldSlug = $node['def']['themenfeld'] ?? 'dicom';

        if (! in_array($themenfeldSlug, $knownSlugs, true)) {
            $this->issue(
                $node['def_file'],
                LineFinder::firstLineContaining($node['def_raw'] ?? '', 'themenfeld'),
                "Node referenziert unbekanntes Themenfeld \"{$themenfeldSlug}\"",
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $achievements
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $tracks
     */
    private function checkAchievements(array $achievements, array $nodes, array $tracks): void
    {
        $seenSlugs = [];
        $trackSlugs = array_column($tracks, 'slug');

        foreach ($achievements as $achievement) {
            $file = $achievement['_file'];
            $raw = $achievement['_raw'];
            $slug = $achievement['slug'] ?? null;
            $line = LineFinder::firstLineContaining($raw, 'slug: '.$slug);

            foreach (['slug', 'name', 'description', 'image', 'category', 'points', 'is_hidden', 'sort_order'] as $field) {
                if (! array_key_exists($field, $achievement)) {
                    $this->issue($file, $line, "Achievement ohne Pflichtfeld \"{$field}\"");
                }
            }

            if ($slug !== null) {
                if (isset($seenSlugs[$slug])) {
                    $this->issue($file, $line, "Achievement-Slug \"{$slug}\" ist nicht eindeutig");
                }

                $seenSlugs[$slug] = true;
            }

            $this->checkAchievementUnlockWhen($file, $line, $slug, $achievement['unlock_when'] ?? null, $nodes, $trackSlugs);
        }
    }

    /**
     * Deklaratives Ausloesekriterium (ADR 0077, W4): dieselben Typen, die
     * App\Achievements\AchievementUnlockEvaluator kennt. Ein Achievement
     * ohne `unlock_when` ist gueltig (z. B. sandbox-starter, dessen
     * Ausloeser kein Abschluss ist und deshalb hartkodiert bleibt).
     *
     * @param  array<string, mixed>|null  $unlockWhen
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  array<int, string>  $trackSlugs
     */
    private function checkAchievementUnlockWhen(string $file, ?int $line, ?string $slug, ?array $unlockWhen, array $nodes, array $trackSlugs): void
    {
        if ($unlockWhen === null) {
            return;
        }

        $type = $unlockWhen['type'] ?? null;

        if (! in_array($type, ['activity_completed', 'track_passed', 'first_solve'], true)) {
            $this->issue($file, $line, "Achievement \"{$slug}\": unlock_when.type \"{$type}\" ist unbekannt (erlaubt: activity_completed, track_passed, first_solve)");

            return;
        }

        if ($type === 'track_passed') {
            $track = $unlockWhen['track'] ?? null;

            if (! in_array($track, $trackSlugs, true)) {
                $this->issue($file, $line, "Achievement \"{$slug}\": unlock_when.track verweist auf unbekannten Track \"{$track}\"");
            }
        }

        if ($type === 'activity_completed' && ($unlockWhen['activity_type'] ?? null) === 'node' && isset($unlockWhen['key'])) {
            $key = (string) $unlockWhen['key'];

            if (! array_key_exists($key, $nodes)) {
                $this->issue($file, $line, "Achievement \"{$slug}\": unlock_when.key verweist auf unbekannte Node \"{$key}\"");
            }
        }
    }

    /**
     * Optionales `achievements:`-Feld (Achievement-System, Abschnitt 7):
     * jeder deklarierte Slug muss in der zentralen Registry existieren,
     * damit eine Node nicht auf ein nie vergebenes Achievement verweist.
     *
     * @param  array<string, mixed>  $node
     */
    private function checkNodeAchievements(array $node): void
    {
        $declared = data_get($node['def'], 'achievements', []);
        $knownSlugs = AchievementRegistry::slugs();

        foreach ($declared as $slug) {
            if (! in_array((string) $slug, $knownSlugs, true)) {
                $this->issue(
                    $node['def_file'],
                    LineFinder::firstLineContaining($node['def_raw'] ?? '', 'achievements'),
                    "achievements verweist auf unbekanntes Achievement \"{$slug}\"",
                );
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $datasets
     */
    private function checkDatasetReference(string $file, string $raw, ?string $datasetSlug, array $datasets): void
    {
        if ($datasetSlug === null) {
            return;
        }

        if (! array_key_exists($datasetSlug, $datasets)) {
            $this->issue(
                $file,
                LineFinder::firstLineContaining($raw, 'dataset'),
                "Datensatz \"{$datasetSlug}\" existiert nicht in datasets.yml",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function checkPlaceholders(array $node): void
    {
        $placeholders = data_get($node['def'], 'environment.placeholders', []);
        $templates = data_get($node['def'], 'environment.templates', []);

        $commands = implode("\n", array_map(fn (array $t) => $t['command'] ?? '', $templates));

        foreach ($placeholders as $placeholder) {
            if (! str_contains($commands, (string) $placeholder)) {
                $this->issue(
                    $node['def_file'],
                    LineFinder::firstLineContaining($node['def_raw'] ?? '', 'placeholders'),
                    "Platzhalter \"{$placeholder}\" kommt in keinem templates-Eintrag vor",
                );
            }
        }
    }

    // ---------------------------------------------------------------
    // Beispielregel
    // ---------------------------------------------------------------

    private function checkExampleRule(string $file, ?string $raw, ?string $body, int $bodyStartLine): void
    {
        if ($raw === null || $body === null) {
            return;
        }

        $blocks = $this->extractCodeBlocks($body, $bodyStartLine);

        if ($blocks === []) {
            $this->issue($file, null, 'enthaelt keinen einzigen Codeblock');

            return;
        }

        $lines = preg_split('/\R/', $raw) ?: [];

        foreach ($blocks as $block) {
            if ($block['excluded']) {
                continue;
            }

            $found = false;
            for ($i = $block['end']; $i < min($block['end'] + 3, count($lines)); $i++) {
                if (str_contains($lines[$i] ?? '', '**Was du daran abliest:**')) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $this->issue(
                    $file,
                    $block['end'] + 1,
                    'Codeblock ohne "**Was du daran abliest:**" innerhalb von drei Zeilen (oder <!-- kein-beispiel --> vergessen)',
                );
            }
        }
    }

    /**
     * @return array<int, array{start:int,end:int,lines:string[],excluded:bool}>
     */
    private function extractCodeBlocks(string $body, int $bodyStartLine): array
    {
        $lines = preg_split('/\R/', $body) ?: [];
        $blocks = [];
        $inBlock = false;
        $start = null;
        $blockLines = [];
        $excluded = false;

        foreach ($lines as $index => $line) {
            $absoluteLine = $bodyStartLine + $index;

            if (! $inBlock && preg_match('/^```/', $line)) {
                $inBlock = true;
                $start = $absoluteLine;
                $blockLines = [];

                $excluded = false;
                for ($back = $index - 1; $back >= max(0, $index - 2); $back--) {
                    $prev = trim($lines[$back]);
                    if ($prev === '') {
                        continue;
                    }
                    $excluded = str_contains($prev, '<!-- kein-beispiel -->');
                    break;
                }

                continue;
            }

            if ($inBlock && preg_match('/^```/', $line)) {
                $blocks[] = ['start' => $start, 'end' => $absoluteLine, 'lines' => $blockLines, 'excluded' => $excluded];
                $inBlock = false;

                continue;
            }

            if ($inBlock) {
                $blockLines[] = $line;
            }
        }

        return $blocks;
    }

    // ---------------------------------------------------------------
    // Fachbegriffe
    // ---------------------------------------------------------------

    /**
     * @param  array<string, array<string, mixed>>  $glossary
     */
    private function checkTerms(string $file, string $body, int $bodyStartLine, array $glossary): void
    {
        $lines = preg_split('/\R/', $body) ?: [];

        foreach ($lines as $index => $line) {
            if (! preg_match_all('/\{\{term:([a-z0-9\-]+)\}\}/', $line, $matches)) {
                continue;
            }

            foreach ($matches[1] as $term) {
                if (! array_key_exists($term, $glossary)) {
                    $this->issue(
                        $file,
                        $bodyStartLine + $index,
                        "{{term:{$term}}} existiert nicht in glossary/de.yml",
                    );
                }
            }
        }
    }

    // ---------------------------------------------------------------
    // Werkzeuge
    // ---------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $lesson
     * @param  array<string, array<string, mixed>>  $tools
     */
    private function checkLessonTools(array $lesson, array $tools): void
    {
        $declared = $lesson['meta']['tools'] ?? [];

        // 1.0 ist die Uebersichtslektion und darf die Vier-Werkzeuge-Grenze
        // bewusst sprengen (siehe Kommentar in content/lessons/1.0/meta.yml).
        $exempt = (bool) ($lesson['meta']['exempt_tool_limit'] ?? false);

        $this->checkToolsDeclaration($lesson['meta_file'], $lesson['meta_raw'] ?? '', $declared, $tools, $exempt);
        $this->checkToolsChecked($lesson['meta_file'], $lesson['meta_raw'] ?? '', $lesson['meta'], $declared);
        $this->checkToolInverse($lesson['md_file'], $lesson['body'] ?? '', $lesson['body_start_line'], $declared, $tools);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<string, mixed>>  $tools
     */
    private function checkNodeTools(array $node, array $tools): void
    {
        $declared = data_get($node['def'], 'environment.tools', []);

        $this->checkToolsDeclaration($node['def_file'], $node['def_raw'] ?? '', $declared, $tools);
        $this->checkToolInverse($node['md_file'], $node['body'] ?? '', $node['body_start_line'], $declared, $tools);
    }

    /**
     * @param  array<int, string>  $declared
     * @param  array<string, array<string, mixed>>  $tools
     */
    private function checkToolsDeclaration(string $file, string $raw, array $declared, array $tools, bool $exemptFromLimit = false): void
    {
        if (! $exemptFromLimit && count($declared) > 4) {
            $this->issue(
                $file,
                LineFinder::firstLineContaining($raw, 'tools'),
                'mehr als vier Werkzeuge deklariert ('.count($declared).')',
            );
        }

        foreach ($declared as $slug) {
            if (! array_key_exists($slug, $tools)) {
                $this->issue(
                    $file,
                    LineFinder::firstLineContaining($raw, 'tools'),
                    "Werkzeug \"{$slug}\" existiert nicht in tools/de.yml",
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @param  array<int, string>  $declared
     */
    private function checkToolsChecked(string $file, string $raw, ?array $meta, array $declared): void
    {
        if ($declared === []) {
            return;
        }

        $checked = $meta['tools_checked'] ?? null;

        if ($checked === null) {
            $this->issue($file, null, 'tools_checked fehlt, obwohl tools nicht leer ist');

            return;
        }

        $date = Carbon::parse((string) $checked);

        if ($date->lt(Carbon::now()->subMonths(12))) {
            $this->issue(
                $file,
                LineFinder::firstLineContaining($raw, 'tools_checked'),
                "tools_checked ({$checked}) ist aelter als 12 Monate",
            );
        }
    }

    /**
     * @param  array<int, string>  $declared
     * @param  array<string, array<string, mixed>>  $tools
     */
    private function checkToolInverse(string $file, string $body, int $bodyStartLine, array $declared, array $tools): void
    {
        $blocks = $this->extractCodeBlocks($body, $bodyStartLine);

        foreach ($blocks as $block) {
            foreach ($block['lines'] as $offset => $line) {
                if (! preg_match('/^\$\s+(\S+)/', $line, $match)) {
                    continue;
                }

                $word = $match[1];

                if (array_key_exists($word, $tools) && ! in_array($word, $declared, true)) {
                    $this->issue(
                        $file,
                        $block['start'] + 1 + $offset,
                        "Werkzeug \"{$word}\" wird benutzt, ist aber nicht in tools deklariert",
                    );
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $tools
     */
    private function checkToolPurposes(?string $raw, array $tools): void
    {
        if ($raw === null) {
            return;
        }

        foreach ($tools as $slug => $definition) {
            $purpose = $definition['purpose'] ?? '';

            if (str_contains($purpose, "\n") || mb_strlen($purpose) > 90) {
                $this->issue(
                    'tools/de.yml',
                    LineFinder::firstLineContaining($raw, $slug.':'),
                    "purpose von \"{$slug}\" ist nicht einzeilig oder laenger als 90 Zeichen",
                );
            }
        }
    }

    private function issue(string $file, ?int $line, string $message): void
    {
        $this->issues[] = new ContentIssue($file, $line, $message);
    }
}

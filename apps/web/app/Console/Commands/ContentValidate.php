<?php

namespace App\Console\Commands;

use App\Content\ContentIssue;
use App\Content\ContentRepository;
use App\Content\LineFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Prueft content/ gegen das verbindliche Schema (Auftrag Abschnitt 4.7).
 * Liest nur, schreibt nichts -- Gegenstueck ist content:sync.
 */
class ContentValidate extends Command
{
    protected $signature = 'content:validate';

    protected $description = 'Prueft content/ gegen Struktur-, Beispiel- und Werkzeugregeln (Abschnitt 4.7)';

    /** @var ContentIssue[] */
    private array $issues = [];

    public function handle(ContentRepository $content): int
    {
        $lessons = $content->lessons();
        $nodes = $content->nodes();
        $tools = $content->tools();
        $glossary = $content->glossary();
        $datasets = $content->datasets();

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
            }
        }

        $this->checkToolPurposes($content->toolsRaw(), $tools);

        if ($this->issues === []) {
            $this->info(sprintf(
                'content:validate — keine Verstoesse (%d Lektionen, %d Nodes, %d Werkzeuge, %d Glossarbegriffe geprueft).',
                count($lessons),
                count($nodes),
                count($tools),
                count($glossary),
            ));

            return self::SUCCESS;
        }

        foreach ($this->issues as $issue) {
            $this->error((string) $issue);
        }

        $this->line('');
        $this->error(sprintf('%d Verstoss(e) gefunden.', count($this->issues)));

        return self::FAILURE;
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

<?php

namespace App\Console\Commands;

use App\Content\ContentRepository;
use App\Content\ContentValidator;
use Illuminate\Console\Command;

/**
 * Prueft content/ gegen das verbindliche Schema (Auftrag Abschnitt 4.7).
 * Liest nur, schreibt nichts -- Gegenstueck ist content:sync.
 *
 * Duenner Aufruf um ContentValidator (ADR 0071/0073, W1): das Regelwissen
 * selbst lebt dort, damit CI und der Autoren-Editor (W6) denselben Regelsatz
 * benutzen. Dieses Command laedt nur content/ und gibt die gefundenen
 * ContentIssue-Eintraege aus.
 */
class ContentValidate extends Command
{
    protected $signature = 'content:validate';

    protected $description = 'Prueft content/ gegen Struktur-, Beispiel- und Werkzeugregeln (Abschnitt 4.7)';

    public function handle(ContentRepository $content, ContentValidator $validator): int
    {
        $lessons = $content->lessons();
        $nodes = $content->nodes();
        $tools = $content->tools();
        $glossary = $content->glossary();
        $datasets = $content->datasets();
        $skills = $content->skills();
        $exams = $content->exams();
        $achievements = $content->achievements();
        $themenfelder = $content->themenfelder();
        $tracks = $content->tracks();

        $issues = $validator->validate(
            themenfelder: $themenfelder,
            tracks: $tracks,
            achievements: $achievements,
            lessons: $lessons,
            nodes: $nodes,
            exams: $exams,
            tools: $tools,
            toolsRaw: $content->toolsRaw(),
            glossary: $glossary,
            datasets: $datasets,
            skills: $skills,
        );

        if ($issues === []) {
            $this->info(sprintf(
                'content:validate — keine Verstoesse (%d Lektionen, %d Nodes, %d Werkzeuge, %d Glossarbegriffe, %d Pruefungen geprueft).',
                count($lessons),
                count($nodes),
                count($tools),
                count($glossary),
                count($exams),
            ));

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->error((string) $issue);
        }

        $this->line('');
        $this->error(sprintf('%d Verstoss(e) gefunden.', count($issues)));

        return self::FAILURE;
    }
}

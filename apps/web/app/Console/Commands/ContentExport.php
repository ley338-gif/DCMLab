<?php

namespace App\Console\Commands;

use App\Content\ContentExporter;
use App\Content\ContentRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Gegenstueck zu `content:sync` (ADR 0122, Phase 2): schreibt den
 * veroeffentlichten DB-Stand nach `content/**` zurueck -- nur die Dateien
 * und Felder, die tatsaechlich abweichen, im bestehenden Format (dieselben
 * chirurgischen Generatoren wie der fruehere Studio-Schreibpfad). Mit
 * `--check` wird nichts geschrieben; Exit-Code 1, sobald `content/` vom
 * DB-Stand abweicht oder eine Ressource nicht exportierbar ist -- die
 * Grundlage fuer den Drift-Check.
 *
 * `content/` ist im `app`-Container read-only gemountet. Ein schreibender
 * Lauf braucht deshalb ein beschreibbares `--path` (siehe `make
 * content-export`, das dafuer einen eigenen Container mit rw-Mount startet).
 */
class ContentExport extends Command
{
    protected $signature = 'content:export
        {--only= : Kommagetrennte Auswahl aus lessons,nodes,tracks (Default: alle)}
        {--id= : Nur diese eine Ressource (Lesson-ID, Node-Slug bzw. Track-Slug)}
        {--check : Nichts schreiben; Exit-Code 1, wenn content/ vom DB-Stand abweicht}
        {--path= : Zielverzeichnis (Default: config(\'content.path\'))}';

    protected $description = 'Schreibt den veroeffentlichten DB-Stand deterministisch nach content/ (ADR 0122)';

    public function handle(ContentExporter $exporter): int
    {
        $scopes = $this->scopes();

        if ($scopes === null) {
            return self::FAILURE;
        }

        $path = rtrim((string) ($this->option('path') ?: config('content.path')), '/\\');

        if (! is_dir($path)) {
            $this->error("Zielverzeichnis \"{$path}\" existiert nicht.");

            return self::FAILURE;
        }

        $id = $this->option('id');
        $result = $exporter->export(new ContentRepository($path), $scopes, is_string($id) && $id !== '' ? $id : null);
        $check = (bool) $this->option('check');

        foreach ($result->files() as $file) {
            $this->line(sprintf('%s %s  [%s]', $check ? 'weicht ab:' : 'geschrieben:', $file['path'], implode(', ', $file['fields'])));

            if (! $check) {
                $this->writeAtomically("{$path}/{$file['path']}", $file['contents']);
            }
        }

        foreach ($result->errors() as $error) {
            $this->error("{$error['resource']}: {$error['message']}");
        }

        if ($result->isClean()) {
            $this->info('content/ entspricht dem veroeffentlichten DB-Stand ('.implode(', ', $scopes).').');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line(sprintf(
            '%d Datei(en) %s, %d Ressource(n) nicht exportierbar.',
            count($result->files()),
            $check ? 'weichen vom DB-Stand ab' : 'geschrieben',
            count($result->errors()),
        ));

        return $check || $result->errors() !== [] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>|null
     */
    private function scopes(): ?array
    {
        $only = $this->option('only');

        if (! is_string($only) || $only === '') {
            return ContentExporter::SCOPES;
        }

        $requested = array_values(array_unique(array_filter(array_map('trim', explode(',', $only)))));
        $unknown = array_diff($requested, ContentExporter::SCOPES);

        if ($unknown !== []) {
            $this->error(
                'Nicht exportierbar: '.implode(', ', $unknown).'. Erlaubt: '.implode(', ', ContentExporter::SCOPES).'. '
                .'Das Quiz ist Teil von "lessons"; Pruefungen, Achievements und Glossar werden weiterhin in content/ '
                .'selbst gepflegt (ADR 0122).',
            );

            return null;
        }

        return $requested;
    }

    private function writeAtomically(string $finalPath, string $contents): void
    {
        $tmpPath = $finalPath.'.tmp-'.bin2hex(random_bytes(4));

        File::ensureDirectoryExists(dirname($finalPath));
        File::put($tmpPath, $contents);
        File::move($tmpPath, $finalPath);
    }
}

<?php

namespace App\Console\Commands;

use App\Content\LabDeploymentExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Lab-Content-Lifecycle-Audit, PR #152: schreibt ein deterministisches
 * `deploy/labs/<slug>.json` je veroeffentlichtem Lab -- reiner Lesevorgang
 * gegen die DB, siehe `LabDeploymentExporter`. Gegenstueck: `labs:import`.
 */
class LabsExport extends Command
{
    protected $signature = 'labs:export {slug? : Nur dieses eine Lab exportieren, statt alle veroeffentlichten}';

    protected $description = 'Exportiert veroeffentlichte Labs als deploy/labs/<slug>.json-Artefakte';

    public function handle(LabDeploymentExporter $exporter): int
    {
        $slug = $this->argument('slug');
        $labs = $exporter->exportable($slug);

        if ($labs->isEmpty()) {
            $this->components->error($slug === null
                ? 'Kein veroeffentlichtes Lab gefunden.'
                : "Kein veroeffentlichtes Lab mit Slug \"{$slug}\" gefunden.");

            return self::FAILURE;
        }

        $directory = rtrim((string) config('services.labs_deploy.path'), '/');
        File::ensureDirectoryExists($directory);

        foreach ($labs as $lab) {
            $path = "{$directory}/{$lab->slug}.json";
            File::put($path, $exporter->encode($exporter->toArtifact($lab)));
            $this->components->info("Exportiert: {$path}");
        }

        return self::SUCCESS;
    }
}

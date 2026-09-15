<?php

namespace Database\Seeders;

use App\Models\SandboxTemplate;
use Illuminate\Database\Seeder;

/**
 * Idempotent ueber `updateOrCreate(slug, ...)`, analog zu AchievementSeeder.
 * services/sandbox baut heute genau eine Laufzeitumgebung (Orthanc+Toolbox-
 * Containerpaar, docker_ops.py) -- entsprechend genau eine freigegebene
 * Vorlage, bis ein zweiter Runtime-Provider oder ein zweites Container-Paar
 * hinzukommt (ADR 0096).
 */
class SandboxTemplateSeeder extends Seeder
{
    public function run(): void
    {
        SandboxTemplate::query()->updateOrCreate(
            ['slug' => 'dicom-basic-tools'],
            [
                'name' => 'DICOM Basic Tools',
                'description' => 'Orthanc-SCP mit DICOM-Werkzeugen (Toolbox-Container), isoliertes Netz ohne Egress (services/sandbox).',
                'runtime_provider' => 'docker',
                'status' => 'published',
            ],
        );
    }
}

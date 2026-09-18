<?php

namespace App\Console\Commands;

use App\Content\LabDeploymentImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;
use RuntimeException;

/**
 * Lab-Content-Lifecycle-Audit, PR #152: liest `deploy/labs/<slug>.json`-
 * Artefakte und wendet sie ueber `LabDeploymentImporter` an. Ohne Argument
 * werden alle Artefakte im Verzeichnis verarbeitet -- ein ungueltiges
 * bricht NUR seinen eigenen Import ab (siehe `LabDeploymentImporter`), die
 * uebrigen werden trotzdem verarbeitet; der Exitcode bleibt aber FAILURE,
 * sobald mindestens ein Artefakt gescheitert ist.
 */
class LabsImport extends Command
{
    protected $signature = 'labs:import {slug? : Nur dieses eine Lab importieren, statt alle Artefakte}';

    protected $description = 'Importiert deploy/labs/<slug>.json-Artefakte (idempotent, veraendert nie Nutzerfortschritt)';

    public function handle(LabDeploymentImporter $importer): int
    {
        $directory = rtrim((string) config('services.labs_deploy.path'), '/');
        $slug = $this->argument('slug');

        if ($slug !== null) {
            $files = ["{$directory}/{$slug}.json"];
        } else {
            $files = File::isDirectory($directory) ? File::glob("{$directory}/*.json") : [];
        }

        if ($files === []) {
            $this->components->error($slug === null
                ? "Keine Import-Artefakte in \"{$directory}\" gefunden."
                : "Artefakt fuer \"{$slug}\" nicht gefunden in \"{$directory}\".");

            return self::FAILURE;
        }

        $failed = false;

        foreach ($files as $file) {
            if (! File::exists($file)) {
                $this->components->error("Nicht gefunden: {$file}");
                $failed = true;

                continue;
            }

            try {
                $artifact = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);

                if (! is_array($artifact)) {
                    throw new RuntimeException("Ungueltiges Lab-Artefakt in {$file}: JSON-Wurzel ist kein Objekt.");
                }

                // Betreiber-Vorgabe: bei explizitem `labs:import <slug>` muss
                // das Artefakt tatsaechlich diesen Slug deklarieren -- sonst
                // koennte ein falsch benannter/verschobener/kopierter
                // Artefakt-Dateiname ueberraschend ein anderes Lab deployen.
                if ($slug !== null && ($artifact['slug'] ?? null) !== $slug) {
                    $declared = is_string($artifact['slug'] ?? null) ? $artifact['slug'] : 'unbekannt';

                    throw new RuntimeException(
                        "Artefakt \"{$file}\" deklariert slug \"{$declared}\", erwartet \"{$slug}\" (Dateiname).",
                    );
                }

                $importer->import($artifact);
                $this->components->info("Importiert: {$file}");
            } catch (JsonException $e) {
                $this->components->error("Ungueltiges JSON in {$file}: {$e->getMessage()}");
                $failed = true;
            } catch (RuntimeException $e) {
                $this->components->error($e->getMessage());
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

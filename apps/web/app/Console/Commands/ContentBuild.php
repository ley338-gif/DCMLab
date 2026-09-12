<?php

namespace App\Console\Commands;

use App\Content\ContentRepository;
use App\Content\FlagNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Erzeugt den Flag-Hash einer Node aus den (synthetischen) Testdaten
 * (Abschnitt 5.2): Der Klartext steht nie im Repo, nur `sha256(normalisierter
 * Wert)` landet in `node.yml: flag.hash`. Schreibt nur die `hash:`-Zeile,
 * der Rest von node.yml (inklusive Kommentare) bleibt unangetastet.
 */
class ContentBuild extends Command
{
    protected $signature = 'content:build';

    protected $description = 'Erzeugt Flag-Hashes aus den Testdaten und schreibt sie in node.yml (Abschnitt 5.2)';

    public function handle(ContentRepository $content): int
    {
        $datasets = $content->datasets();
        $built = 0;
        $unchanged = 0;

        foreach ($content->nodes() as $slug => $node) {
            $def = $node['def'];

            if ($def === null || ! isset($def['flag'])) {
                continue;
            }

            $flag = $def['flag'];
            $sourceTag = $flag['source_tag'] ?? null;
            $caseSensitive = (bool) ($flag['case_sensitive'] ?? false);

            if ($sourceTag === null) {
                $this->error("Node {$slug}: flag.source_tag fehlt — uebersprungen.");

                continue;
            }

            $datasetSlug = data_get($def, 'environment.dataset');
            $dataset = $datasets[$datasetSlug] ?? null;

            if ($dataset === null) {
                $this->error("Node {$slug}: Datensatz \"{$datasetSlug}\" nicht in datasets.yml gefunden.");

                return self::FAILURE;
            }

            try {
                $plaintext = $this->resolveTagValue($sourceTag, $dataset);
            } catch (\RuntimeException $e) {
                $this->error("Node {$slug}: {$e->getMessage()}");

                return self::FAILURE;
            }

            $newHash = 'sha256:'.FlagNormalizer::hash($plaintext, $caseSensitive);

            if ($this->writeHash($content, $node['def_file'], $node['def_raw'] ?? '', $newHash)) {
                $built++;
                $this->info("Node {$slug}: Flag-Hash geschrieben.");
            } else {
                $unchanged++;
            }
        }

        $this->info("content:build — {$built} Hash(es) geschrieben, {$unchanged} bereits aktuell.");

        return self::SUCCESS;
    }

    /**
     * Bildet ein DICOM-Tag aus flag.source_tag auf den Datensatz-Wert ab, aus
     * dem der Flag-Klartext stammt. Neue Tags brauchen einen neuen Fall hier
     * -- lieber ein klarer Fehler als eine stillschweigend falsche Wahl.
     *
     * @param  array<string, mixed>  $dataset
     */
    private function resolveTagValue(string $sourceTag, array $dataset): string
    {
        return match ($sourceTag) {
            '0008,103E' => $this->resolveSeriesDescription($dataset), // Series Description
            default => throw new \RuntimeException(
                "kein Resolver fuer flag.source_tag \"{$sourceTag}\" — content:build muss erweitert werden.",
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $dataset
     */
    private function resolveSeriesDescription(array $dataset): string
    {
        $series = $dataset['series'] ?? [];

        if (is_string($series)) {
            return $series;
        }

        if (is_array($series) && count($series) === 1) {
            return (string) reset($series);
        }

        throw new \RuntimeException(
            'Datensatz hat nicht genau eine Serie — welche davon der Flag ist, muss von Hand entschieden werden.',
        );
    }

    /**
     * Schreibt die `hash:`-Zeile innerhalb des flag-Blocks; lässt die Datei
     * unangetastet, wenn der Hash schon stimmt. Gibt zurueck, ob geschrieben wurde.
     */
    private function writeHash(ContentRepository $content, string $relativeFile, string $raw, string $newHash): bool
    {
        $pattern = '/(flag:.*?hash:\s*")sha256:[^"]*(")/s';

        $updated = preg_replace_callback($pattern, function (array $matches) use ($newHash): string {
            return $matches[1].$newHash.$matches[2];
        }, $raw, 1, $count);

        if ($count === 0) {
            $this->error("{$relativeFile}: keine flag.hash-Zeile gefunden.");

            return false;
        }

        if ($updated === $raw) {
            return false;
        }

        File::put($content->basePath().'/'.$relativeFile, $updated);

        return true;
    }
}

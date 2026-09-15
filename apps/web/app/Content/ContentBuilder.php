<?php

namespace App\Content;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Erzeugt den Flag-Hash einer Node aus den (synthetischen) Testdaten
 * (Abschnitt 5.2), als Service (ADR 0071/0074, W2): laeuft sowohl ueber das
 * `content:build`-Command als auch automatisch im Schreibweg von
 * `ContentWriter`, nachdem eine Node veroeffentlicht wurde. Der Klartext
 * steht nie im Repo, nur `sha256(normalisierter Wert)` landet in
 * `node.yml: flag.hash`. Schreibt nur die `hash:`-Zeile, der Rest von
 * node.yml (inklusive Kommentare) bleibt unangetastet.
 */
final class ContentBuilder
{
    /**
     * @throws RuntimeException wenn ein flag.source_tag nicht aufloesbar ist
     */
    public function build(ContentRepository $content): ContentBuildResult
    {
        $datasets = $content->datasets();
        $built = 0;
        $unchanged = 0;
        $notices = [];

        foreach ($content->nodes() as $slug => $node) {
            $def = $node['def'];

            if ($def === null || ! isset($def['flag'])) {
                continue;
            }

            $flag = $def['flag'];
            $sourceTag = $flag['source_tag'] ?? null;
            $caseSensitive = (bool) ($flag['case_sensitive'] ?? false);

            if ($sourceTag === null) {
                $notices[] = "Node {$slug}: flag.source_tag fehlt — uebersprungen.";

                continue;
            }

            try {
                $plaintext = $this->resolveTagValue($sourceTag, $def, $datasets);
            } catch (RuntimeException $e) {
                throw new RuntimeException("Node {$slug}: {$e->getMessage()}", previous: $e);
            }

            $newHash = 'sha256:'.FlagNormalizer::hash($plaintext, $caseSensitive);
            [$wrote, $notice] = $this->writeHash($content, $node['def_file'], $node['def_raw'] ?? '', $newHash);

            if ($notice !== null) {
                $notices[] = $notice;
            }

            if ($wrote) {
                $built++;
            } else {
                $unchanged++;
            }
        }

        return new ContentBuildResult($built, $unchanged, $notices);
    }

    /**
     * Bildet flag.source_tag auf den Flag-Klartext ab. Neue source_tags
     * brauchen einen neuen Fall hier -- lieber ein klarer Fehler als eine
     * stillschweigend falsche Wahl. "scenario" (Abschnitt 6j) braucht
     * kein Dataset -- der Klartext steckt im Entscheidungsbaum selbst --
     * deshalb wird das Dataset erst NACH dieser Weiche aufgeloest.
     *
     * @param  array<string, mixed>  $def
     * @param  array<string, array<string, mixed>>  $datasets
     */
    private function resolveTagValue(string $sourceTag, array $def, array $datasets): string
    {
        if ($sourceTag === 'scenario') {
            return $this->resolveScenarioReveal($def);
        }

        $datasetSlug = data_get($def, 'environment.dataset');
        $dataset = $datasetSlug !== null ? ($datasets[$datasetSlug] ?? null) : null;

        if ($dataset === null) {
            throw new RuntimeException("Datensatz \"{$datasetSlug}\" nicht in datasets.yml gefunden.");
        }

        return match ($sourceTag) {
            '0008,103E' => $this->resolveSeriesDescription($dataset), // Series Description
            default => throw new RuntimeException(
                "kein Resolver fuer flag.source_tag \"{$sourceTag}\" — content:build muss erweitert werden.",
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private function resolveScenarioReveal(array $def): string
    {
        foreach (data_get($def, 'scenario.steps', []) as $step) {
            if (($step['terminal'] ?? false) && ($step['outcome'] ?? null) === 'correct') {
                return (string) ($step['reveal']
                    ?? throw new RuntimeException('terminal step mit outcome "correct" hat kein reveal-Feld'));
            }
        }

        throw new RuntimeException('kein terminal step mit outcome "correct" in scenario.steps gefunden');
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

        throw new RuntimeException(
            'Datensatz hat nicht genau eine Serie — welche davon der Flag ist, muss von Hand entschieden werden.',
        );
    }

    /**
     * Schreibt die `hash:`-Zeile innerhalb des flag-Blocks; lässt die Datei
     * unangetastet, wenn der Hash schon stimmt. Gibt zurueck, ob geschrieben
     * wurde, plus einen nicht-fatalen Hinweis, falls keine flag.hash-Zeile
     * gefunden wurde (soft-fail wie im urspruenglichen Command: die Node wird
     * uebersprungen, nicht der ganze Lauf abgebrochen).
     *
     * @return array{0: bool, 1: ?string}
     */
    private function writeHash(ContentRepository $content, string $relativeFile, string $raw, string $newHash): array
    {
        $pattern = '/(flag:.*?hash:\s*")sha256:[^"]*(")/s';

        $updated = preg_replace_callback($pattern, function (array $matches) use ($newHash): string {
            return $matches[1].$newHash.$matches[2];
        }, $raw, 1, $count);

        if ($count === 0) {
            return [false, "{$relativeFile}: keine flag.hash-Zeile gefunden."];
        }

        if ($updated === $raw) {
            return [false, null];
        }

        File::put($content->basePath().'/'.$relativeFile, $updated);

        return [true, null];
    }
}

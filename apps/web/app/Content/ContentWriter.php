<?php

namespace App\Content;

use App\Activities\ActivityContract;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Schreibt eine freigegebene Aktivitaet nach content/** (ADR 0071/0072,
 * W2): Validierung ist Vorbedingung (keine zweite, schwaechere Pruefung),
 * dann atomar schreiben, dann `content:sync` (DB-Index), dann
 * `ContentBuilder` (Flag-Hashes) und Cache-Invalidierung bei Engine und
 * Orchestrator.
 *
 * `serialize()` liefert heute noch den unveraenderten Ist-Zustand (ADR
 * 0073) -- sobald W3 echte Entwuerfe einfuehrt, aendert sich daran fuer
 * diese Klasse nichts, sie bleibt agnostisch gegenueber der Herkunft der
 * Dateiinhalte.
 */
final readonly class ContentWriter
{
    public function __construct(
        private ContentRepository $content,
        private ContentBuilder $builder,
        private CacheInvalidatorContract $cache,
    ) {}

    /**
     * @return list<ContentIssue> Nicht leer, wenn NICHTS geschrieben wurde.
     */
    public function write(ActivityContract $activity): array
    {
        $issues = $activity->validate();

        if ($issues !== []) {
            return $issues;
        }

        $this->writeAtomically($activity->serialize());

        Artisan::call('content:sync');
        $this->builder->build($this->content);
        $this->cache->invalidate();

        return [];
    }

    /**
     * @param  list<array{path: string, contents: string}>  $files
     */
    private function writeAtomically(array $files): void
    {
        $pending = [];

        foreach ($files as $file) {
            $finalPath = $this->content->basePath().'/'.$file['path'];
            $tmpPath = $finalPath.'.tmp-'.bin2hex(random_bytes(4));
            $contents = GeneratedFileMarker::apply($file['path'], $file['contents']);

            File::ensureDirectoryExists(dirname($finalPath));
            File::put($tmpPath, $contents);
            $pending[] = [$tmpPath, $finalPath];
        }

        // Erst wenn jede Datei vollstaendig als Temp-Datei geschrieben ist,
        // in einem zweiten Durchgang an ihren endgueltigen Platz verschieben
        // -- ein Fehler beim Schreiben einer spaeteren Datei laesst so keine
        // teilweise aktualisierte Aktivitaet zurueck.
        foreach ($pending as [$tmpPath, $finalPath]) {
            File::move($tmpPath, $finalPath);
        }
    }
}

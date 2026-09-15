<?php

namespace App\Console\Commands;

use App\Content\ContentBuilder;
use App\Content\ContentRepository;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Erzeugt den Flag-Hash einer Node aus den (synthetischen) Testdaten
 * (Abschnitt 5.2). Duenner Aufruf um ContentBuilder (ADR 0071/0074, W2): das
 * Regelwissen selbst lebt dort, damit dieses Command und ContentWriter
 * (Schreibweg des Autoren-Editors) denselben Mechanismus benutzen.
 */
class ContentBuild extends Command
{
    protected $signature = 'content:build';

    protected $description = 'Erzeugt Flag-Hashes aus den Testdaten und schreibt sie in node.yml (Abschnitt 5.2)';

    public function handle(ContentRepository $content, ContentBuilder $builder): int
    {
        try {
            $result = $builder->build($content);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result->notices as $notice) {
            $this->error($notice);
        }

        $this->info("content:build — {$result->built} Hash(es) geschrieben, {$result->unchanged} bereits aktuell.");

        return self::SUCCESS;
    }
}

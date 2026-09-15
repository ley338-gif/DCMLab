<?php

namespace App\Content;

/**
 * Ergebnis eines ContentBuilder::build()-Laufs (ADR 0071/0074, W2). `notices`
 * sind nicht-fatale Hinweise (z. B. eine uebersprungene Node ohne
 * flag.source_tag) -- ein fataler Fehler (unbekannter Resolver, Datensatz
 * nicht gefunden, ...) wird stattdessen als Exception geworfen, damit der
 * Aufrufer (Command oder ContentWriter) ihn nicht versehentlich ignoriert.
 */
final readonly class ContentBuildResult
{
    /**
     * @param  list<string>  $notices
     */
    public function __construct(
        public int $built,
        public int $unchanged,
        public array $notices = [],
    ) {}
}

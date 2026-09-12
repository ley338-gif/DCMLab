<?php

namespace App\Content;

/**
 * Ein einzelner Validierungsfehler mit Datei und, wo ermittelbar, Zeilennummer
 * (Abschnitt 4.7: "Der Befehl gibt Fehler mit Datei und Zeilennummer aus").
 */
final class ContentIssue
{
    public function __construct(
        public readonly string $file,
        public readonly ?int $line,
        public readonly string $message,
    ) {}

    public function __toString(): string
    {
        $location = $this->line !== null ? "{$this->file}:{$this->line}" : $this->file;

        return "{$location} — {$this->message}";
    }
}

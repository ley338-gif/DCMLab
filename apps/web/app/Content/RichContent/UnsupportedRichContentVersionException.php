<?php

namespace App\Content\RichContent;

use RuntimeException;

/**
 * `RichContentRenderer` bekommt ein Dokument mit einer anderen `version`
 * als der, die es kennt -- Betreiberentscheidung (ADR 0112): still
 * weiterzurendern waere falsch, sobald echte Dokumente in der DB liegen
 * und das Schema irgendwann auf Version 2 wechselt. Ein `version: 1`-Leser
 * darf ein `version: 2`-Dokument nicht "irgendwie" darstellen -- das
 * verlangt eine explizite Migration (v1 -> v2), keinen stillen Fallback.
 */
final class UnsupportedRichContentVersionException extends RuntimeException
{
    public static function forVersion(mixed $version): self
    {
        $shown = is_scalar($version) ? (string) $version : get_debug_type($version);

        return new self("Rich-Content-Dokumentversion \"{$shown}\" wird nicht unterstuetzt (erwartet: 1).");
    }
}

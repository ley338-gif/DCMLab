<?php

namespace App\Content\RichContent;

use RuntimeException;

/**
 * `RichContentToMarkdownSerializer` bekommt einen Knoten, den das
 * Markdown-Format aus `docs/content-schema.md` nicht verlustfrei tragen kann
 * -- ein unbekannter Blocktyp, ein DCMLab-Block ohne Markdown-Syntax
 * (`callout`, `dicom_tag_table`, `code_block{variant: dicom_dump}`, ADR 0114)
 * oder eine Konstellation, die `MarkdownToRichContentConverter` beim
 * Zuruecklesen anders deuten wuerde. Analog zu
 * `UnsupportedRichContentVersionException`: lieber laut abbrechen als einen
 * Export schreiben, der beim naechsten `content:sync` still Inhalt verliert
 * (ADR 0122, Phase 1).
 */
final class UnrepresentableRichContentException extends RuntimeException
{
    public static function at(string $path, string $reason): self
    {
        return new self("Rich Content bei \"{$path}\" ist nicht als Markdown darstellbar: {$reason}");
    }
}

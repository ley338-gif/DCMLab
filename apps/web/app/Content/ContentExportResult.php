<?php

namespace App\Content;

/**
 * Ergebnis von `ContentExporter::export()` (ADR 0122): nur Dateien, deren
 * Inhalt vom DB-Stand abweicht, jeweils mit den abweichenden Feldern, plus
 * Ressourcen, die nicht exportiert werden konnten. Ein leeres Ergebnis
 * heisst: `content/` entspricht dem veroeffentlichten DB-Stand.
 */
final class ContentExportResult
{
    /**
     * @var list<array{path: string, contents: string, fields: list<string>}>
     */
    private array $files = [];

    /**
     * @var list<array{resource: string, message: string}>
     */
    private array $errors = [];

    /**
     * @param  list<string>  $fields  leer = Datei stimmt, wird nicht aufgenommen
     */
    public function file(string $path, string $contents, array $fields): void
    {
        if ($fields !== []) {
            $this->files[] = ['path' => $path, 'contents' => $contents, 'fields' => $fields];
        }
    }

    public function error(string $resource, string $message): void
    {
        $this->errors[] = ['resource' => $resource, 'message' => $message];
    }

    /**
     * @return list<array{path: string, contents: string, fields: list<string>}>
     */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * @return list<array{resource: string, message: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function isClean(): bool
    {
        return $this->files === [] && $this->errors === [];
    }
}

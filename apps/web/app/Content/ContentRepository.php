<?php

namespace App\Content;

use Symfony\Component\Yaml\Yaml;

/**
 * Liest content/ ein (Abschnitt 4). Kennt nur das Dateisystem, keine
 * Datenbank -- das ist der Unterschied zu App\Models\{Track,Lesson,Node}:
 * die Modelle sind ein Index UEBER das, was dieses Repository liest
 * (Abschnitt 7), niemals der Speicherort selbst.
 */
final class ContentRepository
{
    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function themenfelder(): array
    {
        $file = 'themenfelder.yml';
        $raw = $this->readIfExists($file);

        if ($raw === null) {
            return [];
        }

        $parsed = Yaml::parse($raw) ?? [];

        return array_map(
            fn (array $themenfeld, int|string $index): array => $themenfeld + ['_file' => $file, '_raw' => $raw, '_index' => $index],
            $parsed,
            array_keys($parsed),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tracks(): array
    {
        $file = 'tracks.yml';
        $raw = $this->readIfExists($file);

        if ($raw === null) {
            return [];
        }

        $parsed = Yaml::parse($raw) ?? [];

        return array_map(
            fn (array $track, int|string $index): array => $track + ['_file' => $file, '_raw' => $raw, '_index' => $index],
            $parsed,
            array_keys($parsed),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function achievements(): array
    {
        $file = 'achievements.yml';
        $raw = $this->readIfExists($file);

        if ($raw === null) {
            return [];
        }

        $parsed = Yaml::parse($raw) ?? [];

        return array_map(
            fn (array $achievement, int|string $index): array => $achievement + ['_file' => $file, '_raw' => $raw, '_index' => $index],
            $parsed,
            array_keys($parsed),
        );
    }

    /**
     * @return array<string, array<string, mixed>> keyed by lesson id (z. B. "1.5")
     */
    public function lessons(): array
    {
        $lessons = [];

        foreach ($this->directories('lessons') as $dir) {
            $id = basename($dir);
            $metaFile = "lessons/{$id}/meta.yml";
            $mdFile = "lessons/{$id}/de.md";

            $metaRaw = $this->readIfExists($metaFile);
            $mdRaw = $this->readIfExists($mdFile);

            $meta = $metaRaw !== null ? (Yaml::parse($metaRaw) ?? []) : null;
            $frontMatter = $mdRaw !== null ? FrontMatter::parse($mdRaw) : null;

            $lessons[$id] = [
                'id' => $id,
                'meta' => $meta,
                'meta_file' => $metaFile,
                'meta_raw' => $metaRaw,
                'md_file' => $mdFile,
                'md_raw' => $mdRaw,
                'frontmatter' => $frontMatter['attributes'] ?? null,
                'body' => $frontMatter['body'] ?? null,
                'body_start_line' => $frontMatter['bodyStartLine'] ?? 1,
            ];
        }

        return $lessons;
    }

    /**
     * @return array<string, array<string, mixed>> keyed by node slug
     */
    public function nodes(): array
    {
        $nodes = [];

        foreach ($this->directories('nodes') as $dir) {
            $slug = basename($dir);
            $defFile = "nodes/{$slug}/node.yml";
            $mdFile = "nodes/{$slug}/de.md";

            $defRaw = $this->readIfExists($defFile);
            $mdRaw = $this->readIfExists($mdFile);

            $def = $defRaw !== null ? (Yaml::parse($defRaw) ?? []) : null;
            $frontMatter = $mdRaw !== null ? FrontMatter::parse($mdRaw) : null;

            $nodes[$slug] = [
                'slug' => $slug,
                'def' => $def,
                'def_file' => $defFile,
                'def_raw' => $defRaw,
                'md_file' => $mdFile,
                'md_raw' => $mdRaw,
                'frontmatter' => $frontMatter['attributes'] ?? null,
                'body' => $frontMatter['body'] ?? null,
                'body_start_line' => $frontMatter['bodyStartLine'] ?? 1,
            ];
        }

        return $nodes;
    }

    /**
     * @return array<string, array<string, mixed>> keyed by Werkzeug-Slug
     */
    public function tools(): array
    {
        $file = 'tools/de.yml';
        $raw = $this->readIfExists($file);

        return $raw !== null ? (Yaml::parse($raw) ?? []) : [];
    }

    public function toolsRaw(): ?string
    {
        return $this->readIfExists('tools/de.yml');
    }

    public function tracksRaw(): ?string
    {
        return $this->readIfExists('tracks.yml');
    }

    /**
     * @return array<string, array<string, mixed>> keyed by Glossar-Slug
     */
    public function glossary(): array
    {
        $file = 'glossary/de.yml';
        $raw = $this->readIfExists($file);

        return $raw !== null ? (Yaml::parse($raw) ?? []) : [];
    }

    /**
     * @return array<string, array<string, mixed>> keyed by Datensatz-Slug
     */
    public function datasets(): array
    {
        $file = 'datasets.yml';
        $raw = $this->readIfExists($file);

        return $raw !== null ? (Yaml::parse($raw) ?? []) : [];
    }

    /**
     * @return array<int, array<string, mixed>> das kontrollierte Skill-Vokabular
     */
    public function skills(): array
    {
        $file = 'skills.yml';
        $raw = $this->readIfExists($file);

        return $raw !== null ? (Yaml::parse($raw) ?? []) : [];
    }

    /**
     * @return array<string, array<string, mixed>> keyed by Track-Slug
     */
    public function exams(): array
    {
        $exams = [];

        foreach ($this->directories('exams') as $dir) {
            $trackSlug = basename($dir);
            $metaFile = "exams/{$trackSlug}/exam.yml";
            $mdFile = "exams/{$trackSlug}/de.md";

            $metaRaw = $this->readIfExists($metaFile);
            $mdRaw = $this->readIfExists($mdFile);

            $meta = $metaRaw !== null ? (Yaml::parse($metaRaw) ?? []) : null;
            $frontMatter = $mdRaw !== null ? FrontMatter::parse($mdRaw) : null;

            $exams[$trackSlug] = [
                'track' => $trackSlug,
                'meta' => $meta,
                'meta_file' => $metaFile,
                'meta_raw' => $metaRaw,
                'md_file' => $mdFile,
                'md_raw' => $mdRaw,
                'frontmatter' => $frontMatter['attributes'] ?? null,
                'body' => $frontMatter['body'] ?? null,
            ];
        }

        return $exams;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * @return string[]
     */
    private function directories(string $relative): array
    {
        $path = $this->basePath.'/'.$relative;

        if (! is_dir($path)) {
            return [];
        }

        $dirs = array_values(array_filter(glob($path.'/*') ?: [], 'is_dir'));
        sort($dirs);

        return $dirs;
    }

    private function readIfExists(string $relative): ?string
    {
        $path = $this->basePath.'/'.$relative;

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }
}

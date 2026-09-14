<?php

namespace App\Content;

/**
 * Erzeugt stabile Anker-Slugs aus Markdown-Ueberschriften -- gemeinsam
 * genutzt von MarkdownRenderer (setzt id="..." auf jedes gerenderte
 * ##/###-Heading) und ContentValidate (prueft exam.yml's review.anchor
 * gegen echte Ueberschriften der Ziel-Lektion). Bewusst ohne zusaetzliches
 * CommonMark-Plugin -- eine geteilte, kleine Funktion reicht, und beide
 * Seiten muessen exakt denselben Slug erzeugen.
 */
final class HeadingSlug
{
    public static function of(string $headingText): string
    {
        $text = strip_tags($headingText);
        $text = strtr($text, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss',
        ]);
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';

        return trim($text, '-');
    }

    /**
     * Weist jeder Ueberschrift einer Liste einen eindeutigen Slug zu --
     * Kollisionen (z. B. zwei "Stolperfallen" in unterschiedlichen Lektionen
     * spielen keine Rolle, aber zwei gleiche Ueberschriften *in derselben*
     * de.md schon) werden mit -2, -3, ... aufgeloest, damit Renderer und
     * Validator sich nie widersprechen.
     *
     * @param  string[]  $headingTexts
     * @return array<int, string> gleiche Reihenfolge wie $headingTexts
     */
    public static function uniqueSlugs(array $headingTexts): array
    {
        $seen = [];
        $slugs = [];

        foreach ($headingTexts as $text) {
            $base = self::of($text);
            $slug = $base;
            $suffix = 2;

            while (isset($seen[$slug])) {
                $slug = "{$base}-{$suffix}";
                $suffix++;
            }

            $seen[$slug] = true;
            $slugs[] = $slug;
        }

        return $slugs;
    }

    /**
     * Extrahiert alle ##/###-Ueberschriften aus rohem Lektions-Markdown, in
     * Dokumentreihenfolge -- dieselbe Quelle, die MarkdownRenderer beim
     * Rendern in IDs verwandelt.
     *
     * @return string[]
     */
    public static function headingsIn(string $markdown): array
    {
        // Erlaubt auch Ueberschriften innerhalb eines Blockquotes
        // ("> ### Stolperfallen", wie in allen Lektionen benutzt) --
        // CommonMark rendert daraus trotzdem ein echtes <h3>, das
        // MarkdownRenderer::addHeadingAnchors() mit einer id versieht.
        preg_match_all('/^(?:>\s*)?#{2,3}\s+(.+?)\s*$/mu', $markdown, $matches);

        return array_map(
            fn (string $heading): string => trim(preg_replace('/[*_`]/', '', $heading) ?? $heading),
            $matches[1],
        );
    }
}

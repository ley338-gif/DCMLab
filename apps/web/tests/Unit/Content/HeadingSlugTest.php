<?php

namespace Tests\Unit\Content;

use App\Content\HeadingSlug;
use Tests\TestCase;

/**
 * Anker-Slug-Erzeugung, geteilt zwischen MarkdownRenderer (setzt id=... auf
 * gerenderte Headings) und ContentValidate (prueft exam.yml's review.anchor
 * dagegen) -- beide muessen fuer dieselbe Ueberschrift denselben Slug
 * ausrechnen, P10.60.
 */
class HeadingSlugTest extends TestCase
{
    public function test_transliterates_umlauts_and_eszett(): void
    {
        $this->assertSame('haelfte-1-das-format', HeadingSlug::of('Hälfte 1: Das Format'));
        $this->assertSame('strassenverkehr', HeadingSlug::of('Straßenverkehr'));
    }

    public function test_strips_punctuation_and_quotes(): void
    {
        $this->assertSame('der-presentation-context', HeadingSlug::of('Der Presentation Context'));
        $this->assertSame('was-eine-transfer-syntax-festlegt', HeadingSlug::of('Was eine Transfer Syntax festlegt'));
        $this->assertSame('die-untersuchung-ist-doppelt-drin', HeadingSlug::of('„Die Untersuchung ist doppelt drin"'));
    }

    public function test_matches_blockquote_headings(): void
    {
        $markdown = "## Im Alltag heißt das\n\nText.\n\n> ### Stolperfallen\n>\n> Text.\n";

        $this->assertSame(['Im Alltag heißt das', 'Stolperfallen'], HeadingSlug::headingsIn($markdown));
    }

    public function test_duplicate_headings_within_one_document_get_unique_slugs(): void
    {
        $slugs = HeadingSlug::uniqueSlugs(['Dein Lab', 'Selbstcheck', 'Dein Lab']);

        $this->assertSame(['dein-lab', 'selbstcheck', 'dein-lab-2'], $slugs);
    }
}

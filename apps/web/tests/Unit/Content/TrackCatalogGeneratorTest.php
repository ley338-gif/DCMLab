<?php

namespace Tests\Unit\Content;

use App\Content\NodeMetaGenerator;
use App\Content\TrackCatalogGenerator;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * ADR 0122 (content:export): feldweiser Ersatz in tracks.yml und die
 * Index-Felder einer node.yml.
 */
class TrackCatalogGeneratorTest extends TestCase
{
    private const RAW = "# Kopfkommentar\n\n- slug: fundamente\n  themenfeld: dicom\n  order: 1\n  title_key: track.fundamente.title\n  level: einsteiger\n  hours: 7\n  status: published\n\n- slug: services\n  themenfeld: dicom\n  order: 2\n  status: planned\n";

    public function test_it_replaces_only_the_named_fields_of_one_entry(): void
    {
        $regenerated = TrackCatalogGenerator::regenerateFields(self::RAW, 'fundamente', ['hours' => 99, 'status' => 'draft']);

        $this->assertSame(str_replace(['hours: 7', 'status: published'], ['hours: 99', 'status: draft'], self::RAW), $regenerated);
    }

    public function test_a_missing_field_is_added_inside_its_block_not_after_the_blank_line(): void
    {
        $regenerated = (string) TrackCatalogGenerator::regenerateFields(self::RAW, 'fundamente', ['teaser_key' => 'x']);

        $this->assertStringContainsString("  status: published\n  teaser_key: x\n\n- slug: services", $regenerated);
        $this->assertSame('x', Yaml::parse($regenerated)[0]['teaser_key']);
        $this->assertSame('planned', Yaml::parse($regenerated)[1]['status']);
    }

    public function test_an_unknown_slug_is_not_invented(): void
    {
        $this->assertNull(TrackCatalogGenerator::regenerateFields(self::RAW, 'nur-in-studio', ['hours' => 1]));
    }

    public function test_it_works_on_the_real_tracks_yml(): void
    {
        $raw = str_replace("\r\n", "\n", (string) file_get_contents(base_path('../../content/tracks.yml')));
        $regenerated = (string) TrackCatalogGenerator::regenerateFields($raw, 'bild', ['level' => 'fortgeschritten']);

        $before = collect(Yaml::parse($raw))->keyBy('slug');
        $after = collect(Yaml::parse($regenerated))->keyBy('slug');
        $this->assertSame('fortgeschritten', $after['bild']['level']);
        $this->assertSame($before->except('bild')->all(), $after->except('bild')->all());
        $this->assertSame(substr_count($raw, "\n"), substr_count($regenerated, "\n"));
    }

    public function test_node_index_fields_leave_environment_and_flag_alone(): void
    {
        $defRaw = "slug: x\ndifficulty: easy\nstatus: draft\n\nenvironment:\n  engine: simulated\n  status: laeuft\n";

        $regenerated = NodeMetaGenerator::regenerateIndex($defRaw, ['status' => 'published', 'themenfeld' => 'hl7']);

        $this->assertSame("slug: x\ndifficulty: easy\nstatus: published\n\nenvironment:\n  engine: simulated\n  status: laeuft\nthemenfeld: hl7", $regenerated);
        $this->assertSame('laeuft', Yaml::parse($regenerated)['environment']['status']);
    }
}

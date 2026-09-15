<?php

namespace Tests\Unit\Content;

use App\Content\AchievementCatalogGenerator;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * ADR 0083 (W6.4): chirurgisches Ersetzen/Anhaengen genau eines Eintrags in
 * der flachen Top-Level-Liste von achievements.yml -- anders als
 * LessonQuizGenerator/ExamMetaGenerator gibt es hier keinen
 * uebergeordneten Schluessel, jeder Eintrag beginnt bei `- slug:`.
 */
class AchievementCatalogGeneratorTest extends TestCase
{
    private const RAW = <<<'YAML'
        # Kommentar am Dateianfang.

        - slug: first-blood
          name: First Blood
          description: Löse deinen ersten Lab- oder Node.
          image: first-blood.png
          category: labs
          rarity: common
          points: 0
          is_hidden: false
          sort_order: 10
          unlock_when:
            type: first_solve
            activity_type: node

        - slug: echo-heard
          name: Echo Heard
          description: Führe dein erstes erfolgreiches C-ECHO durch.
          image: echo-heard.png
          category: dicom
          rarity: common
          points: 0
          is_hidden: false
          sort_order: 30

        YAML;

    public function test_regenerate_entry_replaces_only_the_named_slug(): void
    {
        $regenerated = AchievementCatalogGenerator::regenerateEntry(self::RAW, 'first-blood', [
            'name' => 'Neuer Name',
            'description' => 'Neue Beschreibung',
            'image' => 'first-blood-v2.png',
            'category' => 'labs',
            'points' => 5,
            'is_hidden' => false,
            'sort_order' => 15,
            'unlock_when' => ['type' => 'first_solve', 'activity_type' => 'node'],
        ]);
        $parsed = Yaml::parse($regenerated);

        $this->assertCount(2, $parsed);
        $this->assertSame('Neuer Name', $parsed[0]['name']);
        $this->assertSame(5, $parsed[0]['points']);
        $this->assertSame('echo-heard', $parsed[1]['slug']);
        $this->assertSame('Echo Heard', $parsed[1]['name']);
        $this->assertStringContainsString('# Kommentar am Dateianfang.', $regenerated);
    }

    public function test_regenerate_entry_preserves_the_other_entry_untouched(): void
    {
        $regenerated = AchievementCatalogGenerator::regenerateEntry(self::RAW, 'first-blood', [
            'name' => 'Neuer Name', 'description' => 'X', 'image' => 'x.png', 'category' => 'labs',
            'points' => 0, 'is_hidden' => false, 'sort_order' => 10,
        ]);

        $this->assertStringContainsString("- slug: echo-heard\n  name: Echo Heard", $regenerated);
        $this->assertStringContainsString('sort_order: 30', $regenerated);
    }

    public function test_regenerate_entry_appends_a_new_slug_at_the_end(): void
    {
        $regenerated = AchievementCatalogGenerator::regenerateEntry(self::RAW, 'brand-new', [
            'name' => 'Brandneu', 'description' => 'Beschreibung', 'image' => 'brand-new.png',
            'category' => 'dicom', 'points' => 10, 'is_hidden' => true, 'sort_order' => 999,
        ]);
        $parsed = Yaml::parse($regenerated);

        $this->assertCount(3, $parsed);
        $this->assertSame('brand-new', $parsed[2]['slug']);
        $this->assertTrue($parsed[2]['is_hidden']);
        $this->assertSame('first-blood', $parsed[0]['slug']);
        $this->assertSame('echo-heard', $parsed[1]['slug']);
    }

    public function test_regenerate_entry_appends_to_an_empty_file(): void
    {
        $regenerated = AchievementCatalogGenerator::regenerateEntry('', 'first-one', [
            'name' => 'Erstes', 'description' => 'X', 'image' => 'x.png', 'category' => 'labs',
            'points' => 0, 'is_hidden' => false, 'sort_order' => 10,
        ]);
        $parsed = Yaml::parse($regenerated);

        $this->assertCount(1, $parsed);
        $this->assertSame('first-one', $parsed[0]['slug']);
    }

    public function test_regenerate_entry_omits_unlock_when_and_rarity_when_absent(): void
    {
        $regenerated = AchievementCatalogGenerator::regenerateEntry(self::RAW, 'echo-heard', [
            'name' => 'Echo Heard', 'description' => 'X', 'image' => 'echo-heard.png', 'category' => 'dicom',
            'points' => 0, 'is_hidden' => false, 'sort_order' => 30,
        ]);
        $parsed = Yaml::parse($regenerated);

        $this->assertArrayNotHasKey('unlock_when', $parsed[1]);
        $this->assertArrayNotHasKey('rarity', $parsed[1]);
    }

    public function test_matches_the_real_achievements_catalog_on_a_no_op_round_trip(): void
    {
        $raw = file_get_contents(base_path('../../content/achievements.yml'));

        if ($raw === false) {
            $this->markTestSkipped('content/achievements.yml nicht gefunden.');
        }

        $parsed = Yaml::parse($raw);
        $first = $parsed[0];

        $regenerated = AchievementCatalogGenerator::regenerateEntry($raw, $first['slug'], $first);
        $reparsed = Yaml::parse($regenerated);

        $this->assertSame($first, $reparsed[0]);
        $this->assertCount(count($parsed), $reparsed);
    }
}

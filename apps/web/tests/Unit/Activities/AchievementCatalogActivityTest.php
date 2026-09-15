<?php

namespace Tests\Unit\Activities;

use App\Activities\AchievementCatalogActivity;
use App\Content\ContentRepository;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * ADR 0072/0083 (W6.4): der Achievement-Katalog als sechster, untypischer
 * Aktivitaetstyp -- eine einzige Instanz (`key() === 'catalog'`), die
 * gesamte achievements.yml. Welcher Eintrag bearbeitet wird, steht im
 * Entwurf ($draft['slug']), nicht in der Aktivitaets-Identitaet.
 */
class AchievementCatalogActivityTest extends TestCase
{
    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentDir = storage_path('framework/testing/achievement-activity-'.uniqid());
        File::ensureDirectoryExists($this->contentDir);
        File::put(
            $this->contentDir.'/achievements.yml',
            "- slug: first-blood\n  name: First Blood\n  description: Test.\n  image: first-blood.png\n  category: labs\n  points: 0\n  is_hidden: false\n  sort_order: 10\n",
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_supports_declares_it_authorable_without_completion(): void
    {
        $supports = $this->makeActivity()->supports();

        $this->assertTrue($supports->authorable);
        $this->assertFalse($supports->tracksCompletion);
        $this->assertFalse($supports->isGraded);
        $this->assertTrue($supports->versionable);
        $this->assertTrue($supports->reusable);
    }

    public function test_result_is_always_null(): void
    {
        $activity = $this->makeActivity();

        $this->assertNull($activity->result(User::factory()->make()));
    }

    public function test_serialize_without_a_draft_returns_the_file_unchanged(): void
    {
        $files = $this->makeActivity()->serialize();

        $this->assertCount(1, $files);
        $this->assertSame('achievements.yml', $files[0]['path']);
        $this->assertStringContainsString('slug: first-blood', $files[0]['contents']);
    }

    public function test_serialize_with_a_draft_regenerates_only_the_named_slug(): void
    {
        $files = $this->makeActivity()->serialize([
            'slug' => 'first-blood',
            'name' => 'Neuer Name',
            'description' => 'Neu.',
            'image' => 'first-blood.png',
            'category' => 'labs',
            'points' => 5,
            'is_hidden' => false,
            'sort_order' => 10,
        ]);

        $parsed = Yaml::parse($files[0]['contents']);
        $this->assertSame('Neuer Name', $parsed[0]['name']);
        $this->assertSame(5, $parsed[0]['points']);
    }

    public function test_validate_with_an_invalid_draft_reports_the_issue(): void
    {
        $issues = $this->makeActivity()->validate([
            'slug' => 'first-blood',
            'name' => 'X',
            'description' => 'X',
            'image' => 'x.png',
            'category' => 'labs',
            'points' => 0,
            'is_hidden' => false,
            'sort_order' => 10,
            'unlock_when' => ['type' => 'not_a_real_type'],
        ]);

        $messages = array_map(fn ($issue) => (string) $issue, $issues);
        $this->assertTrue((bool) array_filter($messages, fn (string $m) => str_contains($m, 'unlock_when.type')));
    }

    public function test_validate_without_a_draft_is_unaffected_by_this_change(): void
    {
        $issues = $this->makeActivity()->validate();

        foreach ($issues as $issue) {
            $this->assertSame('achievements.yml', $issue->file);
        }
    }

    private function makeActivity(): AchievementCatalogActivity
    {
        return new AchievementCatalogActivity(new ContentRepository($this->contentDir));
    }
}

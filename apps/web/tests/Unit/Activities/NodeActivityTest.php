<?php

namespace Tests\Unit\Activities;

use App\Activities\NodeActivity;
use App\Content\ContentRepository;
use App\Content\FrontMatter;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class NodeActivityTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentDir = storage_path('framework/testing/node-activity-'.uniqid());

        File::ensureDirectoryExists($this->contentDir.'/nodes/test-node');
        File::put(
            $this->contentDir.'/nodes/test-node/node.yml',
            "slug: test-node\ndifficulty: easy\npoints: 10\ncategory: netzwerk\nskills: [netzwerk]\nrelated_lessons: []\nestimated_minutes: 15\nstatus: draft\n",
        );
        File::put(
            $this->contentDir.'/nodes/test-node/de.md',
            "---\ntitle: Test Node\nscenario_title: Test Szenario\n---\n\nBriefing-Text.\n",
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_supports_declares_grading_and_a_container(): void
    {
        $activity = $this->makeActivity();

        $supports = $activity->supports();

        $this->assertTrue($supports->isGraded);
        $this->assertTrue($supports->needsContainer);
        $this->assertTrue($supports->freelyPlaceable);
        $this->assertSame('container', $supports->runtimeType);
        $this->assertTrue($supports->reusable);
        // Seit ADR 0108 (CMS-6d Teil 2) wertet serialize($draft) den Entwurf
        // tatsaechlich aus, siehe NodeActivity::supports().
        $this->assertTrue($supports->versionable);
    }

    public function test_result_is_null_before_any_attempt_exists(): void
    {
        $activity = $this->makeActivity();
        $user = User::factory()->create();

        $this->assertNull($activity->result($user));
    }

    public function test_result_reflects_a_solved_attempt_with_points_and_skills(): void
    {
        $node = $this->makeNode();
        $activity = new NodeActivity($node, new ContentRepository($this->contentDir));
        $user = User::factory()->create();

        NodeAttempt::create([
            'user_id' => $user->id,
            'node_id' => $node->id,
            'engine_session_id' => (string) Str::uuid(),
            'status' => 'solved',
            'hints_used' => [],
            'points' => 10,
            'started_at' => now()->subMinutes(5),
            'flag_submitted_at' => now(),
        ]);

        $result = $activity->result($user);

        $this->assertNotNull($result);
        $this->assertTrue($result->completed);
        $this->assertSame(10, $result->score);
        $this->assertSame(10, $result->maxScore);
        $this->assertSame(['netzwerk'], $result->skills);
    }

    public function test_validate_delegates_to_the_shared_content_validator_scoped_to_this_node(): void
    {
        $activity = $this->makeActivity();

        $issues = $activity->validate();

        foreach ($issues as $issue) {
            $this->assertStringStartsWith('nodes/test-node/', $issue->file);
        }
    }

    public function test_serialize_returns_the_node_definition_and_markdown_unchanged(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize();

        $this->assertCount(2, $files);
        $this->assertSame('nodes/test-node/node.yml', $files[0]['path']);
        $this->assertStringContainsString('slug: test-node', $files[0]['contents']);
    }

    public function test_serialize_with_a_draft_regenerates_only_the_named_fields(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize([
            'difficulty' => 'medium',
            'points' => 20,
            'category' => 'sicherheit',
            'interaction' => 'terminal',
            'estimated_minutes' => 30,
            'skills' => ['netzwerk', 'sicherheit'],
            'related_lessons' => ['1.1'],
            'title' => 'Neuer Titel',
            'scenario_title' => 'Neues Szenario',
        ]);

        $parsedDef = Yaml::parse($files[0]['contents']);
        $this->assertSame('medium', $parsedDef['difficulty']);
        $this->assertSame(20, $parsedDef['points']);
        $this->assertSame('sicherheit', $parsedDef['category']);
        $this->assertSame(30, $parsedDef['estimated_minutes']);
        $this->assertSame(['netzwerk', 'sicherheit'], $parsedDef['skills']);
        $this->assertSame(['1.1'], $parsedDef['related_lessons']);

        $frontMatter = FrontMatter::parse($files[1]['contents']);
        $this->assertSame('Neuer Titel', $frontMatter['attributes']['title']);
        $this->assertSame('Neues Szenario', $frontMatter['attributes']['scenario_title']);
    }

    public function test_serialize_with_a_body_draft_replaces_only_the_body(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize(['body' => '## Briefing'.PHP_EOL.PHP_EOL.'Neuer Briefing-Text.']);

        $frontMatter = FrontMatter::parse($files[1]['contents']);
        $this->assertSame('Test Node', $frontMatter['attributes']['title'], 'Frontmatter darf unangetastet bleiben.');
        $this->assertStringContainsString('Neuer Briefing-Text.', $files[1]['contents']);
        $this->assertStringNotContainsString('Briefing-Text.'.PHP_EOL, $files[1]['contents']);
    }

    public function test_serialize_with_a_hints_draft_regenerates_only_the_hints_block(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize(['hints' => [['id' => 'h1', 'cost' => 1], ['id' => 'h2', 'cost' => 2]]]);
        $parsedDef = Yaml::parse($files[0]['contents']);

        $this->assertSame([
            ['id' => 'h1', 'cost' => 1],
            ['id' => 'h2', 'cost' => 2],
        ], $parsedDef['hints']);
        $this->assertSame('easy', $parsedDef['difficulty'], 'difficulty darf unangetastet bleiben.');
    }

    public function test_validate_with_a_draft_checks_what_serialize_would_produce_instead_of_the_current_state(): void
    {
        $activity = $this->makeActivity();

        // related_lessons verweist auf eine unbekannte Lektion -- dieselbe
        // Regel wie ContentValidator::checkNodeStructure() im Ist-Zustand.
        $issues = $activity->validate(['related_lessons' => ['9.9']]);

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'unbekannte Lektion'),
        ));
    }

    /**
     * Eine rein in Studio angelegte Node (ADR 0109, CMS-6d Teil 3) hat kein
     * content/nodes/<slug>/-Gegenstueck -- serialize($draft)/validate($draft)
     * muessen trotzdem einen validen Rumpf erzeugen, statt (wie ohne Draft)
     * einfach [] zurueckzugeben.
     */
    public function test_serialize_with_a_draft_builds_a_valid_body_even_without_a_matching_content_file(): void
    {
        $node = Node::factory()->create(['slug' => 'studio-only-node']);
        $activity = new NodeActivity($node, new ContentRepository($this->contentDir));

        $files = $activity->serialize([
            'title' => 'Neue Node', 'scenario_title' => 'Neues Szenario', 'difficulty' => 'easy',
            'points' => 5, 'category' => 'netzwerk', 'estimated_minutes' => 10,
        ]);

        $parsedDef = Yaml::parse($files[0]['contents']);
        $this->assertSame('easy', $parsedDef['difficulty']);
        $this->assertSame(5, $parsedDef['points']);

        $frontMatter = FrontMatter::parse($files[1]['contents']);
        $this->assertSame('Neue Node', $frontMatter['attributes']['title']);
        $this->assertSame('Neues Szenario', $frontMatter['attributes']['scenario_title']);
    }

    public function test_validate_without_a_draft_still_checks_the_current_state(): void
    {
        $activity = $this->makeActivity();

        $withDraft = $activity->validate(null);
        $withoutArgument = $activity->validate();

        $this->assertEquals($withoutArgument, $withDraft);
    }

    public function test_deserialize_normalizes_the_current_fields(): void
    {
        $activity = $this->makeActivity();

        $draft = $activity->deserialize();

        $this->assertSame('test-node', $draft['slug']);
        $this->assertSame('easy', $draft['difficulty']);
        $this->assertSame(10, $draft['points']);
        $this->assertIsString($draft['body']);
        $this->assertSame([], $draft['hints']);
    }

    private function makeActivity(): NodeActivity
    {
        return new NodeActivity($this->makeNode(), new ContentRepository($this->contentDir));
    }

    private function makeNode(): Node
    {
        return Node::factory()->create(['slug' => 'test-node', 'points' => 10, 'skills' => ['netzwerk']]);
    }
}

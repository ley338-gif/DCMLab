<?php

namespace Tests\Unit\Activities;

use App\Activities\NodeActivity;
use App\Content\ContentRepository;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
            "---\ntitle: Test Node\n---\n\nBriefing-Text.\n",
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
        // Kein Node-Editor legt heute einen Entwurf an -- serialize($draft)
        // ignoriert $draft, siehe NodeActivity::supports().
        $this->assertFalse($supports->versionable);
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

    private function makeActivity(): NodeActivity
    {
        return new NodeActivity($this->makeNode(), new ContentRepository($this->contentDir));
    }

    private function makeNode(): Node
    {
        return Node::factory()->create(['slug' => 'test-node', 'points' => 10, 'skills' => ['netzwerk']]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\Themenfeld;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Node-Verwaltung in Studio (ADR 0109, CMS-6d Teil 3): der Editor, den ADR
 * 0107/0108 vorbereitet haben -- store()/duplicate()/archive()/restore()/
 * updateThemenfeld() sind strukturelle Eingriffe (NodePolicy), edit()/
 * update() bearbeiten den Content-Entwurf (ActivityPolicy).
 */
class StudioNodeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_learner_cannot_view_the_node_list(): void
    {
        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/de/studio/nodes')->assertForbidden();
    }

    public function test_an_author_can_view_but_not_manage_nodes(): void
    {
        $author = User::factory()->author()->create();
        Node::factory()->create(['title' => ['de' => 'Silent CT']]);

        $this->actingAs($author)
            ->get('/de/studio/nodes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Studio/Nodes/Index')
                ->where('can_manage', false)
                ->has('nodes', 1)
                ->where('nodes.0.title', 'Silent CT')
            );
    }

    public function test_an_author_cannot_create_a_node(): void
    {
        $themenfeld = Themenfeld::factory()->create();
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->post('/de/studio/nodes', [
                'slug' => 'neue-node', 'title' => 'Neue Node',
                'themenfeld_id' => $themenfeld->id, 'difficulty' => 'easy',
                'category' => 'netzwerk', 'interaction' => 'terminal',
            ])
            ->assertForbidden();

        $this->assertSame(0, Node::query()->count());
    }

    public function test_a_reviewer_can_create_a_node_with_its_activity_atomically(): void
    {
        $themenfeld = Themenfeld::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post('/de/studio/nodes', [
                'slug' => 'dns-mismatch',
                'title' => 'DNS Mismatch',
                'themenfeld_id' => $themenfeld->id,
                'difficulty' => 'medium',
                'category' => 'netzwerk',
                'interaction' => 'terminal',
            ])
            ->assertRedirect('/de/studio/nodes/dns-mismatch');

        $node = Node::query()->where('slug', 'dns-mismatch')->first();
        $this->assertNotNull($node);
        $this->assertSame('draft', $node->status);
        $this->assertSame('DNS Mismatch', $node->title['de']);
        $this->assertSame('', $node->body);

        $activity = Activity::query()->where('type', 'node')->where('key', 'dns-mismatch')->first();
        $this->assertNotNull($activity);
        $this->assertSame('draft', $activity->status);
    }

    public function test_a_duplicate_slug_is_rejected(): void
    {
        Node::factory()->create(['slug' => 'silent-ct']);
        $themenfeld = Themenfeld::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post('/de/studio/nodes', [
                'slug' => 'silent-ct', 'title' => 'Duplikat',
                'themenfeld_id' => $themenfeld->id, 'difficulty' => 'easy',
                'category' => 'netzwerk', 'interaction' => 'terminal',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_can_manage_and_can_publish_reflect_the_same_rules_as_the_activity_policy(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node']);
        $activity = Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);

        $reviewer = User::factory()->reviewer()->create();
        $unassignedAuthor = User::factory()->author()->create();
        $assignedAuthor = User::factory()->author()->create();
        $activity->authorUsers()->attach($assignedAuthor);

        $this->actingAs($reviewer)
            ->get("/de/studio/nodes/{$node->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_manage', true)->where('can_publish', true));

        $this->actingAs($unassignedAuthor)
            ->get("/de/studio/nodes/{$node->slug}")
            ->assertForbidden();

        $this->actingAs($assignedAuthor)
            ->get("/de/studio/nodes/{$node->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_manage', false)->where('can_publish', false));
    }

    public function test_edit_shows_the_current_db_state_when_there_is_no_pending_draft(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node', 'title' => ['de' => 'Alt'], 'body' => 'Alter Text.']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get("/de/studio/nodes/{$node->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('fields.title', 'Alt')
                ->where('fields.rich_content.type', 'node_content')
                ->where('pending_version', null)
            );
    }

    public function test_edit_shows_the_pending_draft_payload_instead_of_the_db_state(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node', 'title' => ['de' => 'Alt']]);
        $activity = Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'draft',
            'payload' => ['title' => 'Entwurfstitel'], 'is_current' => false,
            'created_by' => User::factory()->create()->id,
        ]);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get("/de/studio/nodes/{$node->slug}")
            ->assertInertia(fn ($page) => $page->where('fields.title', 'Entwurfstitel'));
    }

    /**
     * Betreiber-Review vor #126: analog zum Lesson-Fall -- ein VOR dem
     * Cutover angelegter Node-Entwurf traegt noch `payload.body`
     * (NodeSections-Markdown) statt `rich_content`. `edit()` muss ihn
     * ueber denselben Normalizer wie Publish/Restore/Preview uebersetzen,
     * sonst bekaemen die drei neuen Editoren (Briefing/Hints/Write-up)
     * ein Feld, das sie nicht verstehen.
     */
    public function test_a_pre_cutover_draft_with_legacy_body_opens_in_the_new_editor(): void
    {
        Node::factory()->create(['slug' => 'test-node']);
        $activity = Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'draft',
            'payload' => [
                'title' => 'Entwurfstitel', 'scenario_title' => 'Entwurf', 'difficulty' => 'easy',
                'points' => 10, 'category' => 'netzwerk', 'interaction' => 'terminal',
                'estimated_minutes' => 15, 'skills' => [], 'related_lessons' => [],
                'hints' => [['id' => 'h1', 'cost' => 1]],
                'body' => "## Briefing\n\nEntwurfs-Briefing.\n\n## Hints\n\n### h1\n\nEntwurfs-Hinweis.\n\n## Write-up\n\nEntwurfs-Loesung.\n",
            ],
            'is_current' => false, 'created_by' => User::factory()->create()->id,
        ]);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get('/de/studio/nodes/test-node')
            ->assertInertia(fn ($page) => $page
                ->where('fields.title', 'Entwurfstitel')
                ->where('fields.rich_content.type', 'node_content')
                ->where('fields.rich_content.briefing', function ($document) {
                    $texts = collect($document['content'])->pluck('content')->flatten(1)->pluck('text')->filter()->implode(' ');

                    return str_contains($texts, 'Entwurfs-Briefing.');
                })
                ->where('fields.rich_content.hints.h1', function ($document) {
                    $texts = collect($document['content'])->pluck('content')->flatten(1)->pluck('text')->filter()->implode(' ');

                    return str_contains($texts, 'Entwurfs-Hinweis.');
                }),
            );
    }

    /**
     * Betreiber-Review vor #126: nach dem ersten Rich-Content-Publish ist
     * `body` absichtlich stale (ADR 0118, "keine zwei schreibenden
     * Sources of Truth") -- `rich_content` ist die aktuelle Quelle.
     * `duplicate()` kopierte bisher nur `body`, eine danach duplizierte
     * Node haette deshalb veraltete Inhalte bekommen.
     */
    public function test_duplicating_a_node_after_a_rich_content_publish_copies_the_current_rich_content(): void
    {
        Node::factory()->create([
            'slug' => 'silent-ct',
            'body' => 'Veralteter Legacy-Text (vor dem ersten Rich-Content-Publish).',
            'rich_content' => [
                'type' => 'node_content', 'version' => 1,
                'briefing' => ['type' => 'doc', 'version' => 1, 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Aktueller Rich-Content-Text.']]],
                ]],
                'hints' => [],
                'write_up' => ['type' => 'doc', 'version' => 1, 'content' => []],
            ],
        ]);
        Activity::factory()->create(['type' => 'node', 'key' => 'silent-ct']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post('/de/studio/nodes/silent-ct/duplicate')
            ->assertRedirect('/de/studio/nodes/silent-ct-kopie');

        $copy = Node::query()->where('slug', 'silent-ct-kopie')->firstOrFail();
        $this->assertSame(
            'Aktueller Rich-Content-Text.',
            $copy->rich_content['briefing']['content'][0]['content'][0]['text'],
        );
        // body bleibt zusaetzlich als Legacy-Fallback kopiert.
        $this->assertSame('Veralteter Legacy-Text (vor dem ersten Rich-Content-Publish).', $copy->body);
    }

    public function test_saving_a_draft_creates_a_content_version_without_touching_the_node_row(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node', 'title' => ['de' => 'Alt']]);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->patch("/de/studio/nodes/{$node->slug}", $this->validDraftPayload(['title' => 'Neuer Entwurfstitel']))
            ->assertRedirect();

        $this->assertSame('Alt', $node->fresh()->title['de']);
        $this->assertSame(1, ContentVersion::query()->count());
        $this->assertSame('draft', ContentVersion::query()->first()->status);
    }

    public function test_an_unassigned_author_cannot_save_a_draft(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node']);
        $activity = Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->patch("/de/studio/nodes/{$node->slug}", $this->validDraftPayload())
            ->assertForbidden();

        $this->assertSame(0, ContentVersion::query()->count());
    }

    public function test_updating_the_themenfeld_takes_effect_immediately_outside_the_draft_workflow(): void
    {
        $oldThemenfeld = Themenfeld::factory()->create();
        $newThemenfeld = Themenfeld::factory()->create();
        $node = Node::factory()->create(['slug' => 'test-node', 'themenfeld_id' => $oldThemenfeld->id]);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->patch("/de/studio/nodes/{$node->slug}/themenfeld", ['themenfeld_id' => $newThemenfeld->id])
            ->assertRedirect();

        $this->assertSame($newThemenfeld->id, $node->fresh()->themenfeld_id);
    }

    public function test_an_author_cannot_change_the_themenfeld(): void
    {
        $themenfeld = Themenfeld::factory()->create();
        $node = Node::factory()->create(['slug' => 'test-node']);
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->patch("/de/studio/nodes/{$node->slug}/themenfeld", ['themenfeld_id' => $themenfeld->id])
            ->assertForbidden();
    }

    public function test_duplicating_a_node_creates_a_new_slug_and_activity_without_copying_attempts(): void
    {
        $node = Node::factory()->create([
            'slug' => 'silent-ct', 'title' => ['de' => 'Silent CT'], 'body' => 'Originaltext.',
            'points' => 10, 'skills' => ['netzwerk'],
        ]);
        Activity::factory()->create(['type' => 'node', 'key' => 'silent-ct']);
        $user = User::factory()->create();
        NodeAttempt::create([
            'user_id' => $user->id, 'node_id' => $node->id,
            'engine_session_id' => (string) Str::uuid(), 'status' => 'solved',
            'hints_used' => [], 'points' => 10, 'started_at' => now(), 'flag_submitted_at' => now(),
        ]);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post('/de/studio/nodes/silent-ct/duplicate')
            ->assertRedirect('/de/studio/nodes/silent-ct-kopie');

        $copy = Node::query()->where('slug', 'silent-ct-kopie')->first();
        $this->assertNotNull($copy);
        $this->assertSame('draft', $copy->status);
        $this->assertSame('Silent CT (Kopie)', $copy->title['de']);
        $this->assertSame('Originaltext.', $copy->body);
        $this->assertSame(0, NodeAttempt::query()->where('node_id', $copy->id)->count());

        $copyActivity = Activity::query()->where('type', 'node')->where('key', 'silent-ct-kopie')->first();
        $this->assertNotNull($copyActivity);
    }

    public function test_duplicating_twice_produces_distinct_slugs(): void
    {
        Node::factory()->create(['slug' => 'silent-ct']);
        Activity::factory()->create(['type' => 'node', 'key' => 'silent-ct']);
        Node::factory()->create(['slug' => 'silent-ct-kopie']);
        Activity::factory()->create(['type' => 'node', 'key' => 'silent-ct-kopie']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)->post('/de/studio/nodes/silent-ct/duplicate')->assertRedirect('/de/studio/nodes/silent-ct-kopie-2');
    }

    public function test_the_full_archive_lifecycle(): void
    {
        $node = Node::factory()->create(['status' => 'draft']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)->post("/de/studio/nodes/{$node->slug}/archive")->assertRedirect();
        $this->assertSame('archived', $node->fresh()->status);

        $this->actingAs($reviewer)->post("/de/studio/nodes/{$node->slug}/restore")->assertRedirect();
        $this->assertSame('draft', $node->fresh()->status);
    }

    public function test_an_author_cannot_archive_a_node(): void
    {
        $node = Node::factory()->create(['status' => 'draft']);
        $author = User::factory()->author()->create();

        $this->actingAs($author)->post("/de/studio/nodes/{$node->slug}/archive")->assertForbidden();
        $this->assertSame('draft', $node->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validDraftPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Titel', 'scenario_title' => 'Szenario', 'difficulty' => 'easy',
            'points' => 10, 'category' => 'netzwerk', 'interaction' => 'terminal',
            'estimated_minutes' => 15, 'skills' => [], 'related_lessons' => [],
            'hints' => [],
            'rich_content' => [
                'type' => 'node_content', 'version' => 1,
                'briefing' => ['type' => 'doc', 'version' => 1, 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Text.']]],
                ]],
                'hints' => [],
                'write_up' => ['type' => 'doc', 'version' => 1, 'content' => []],
            ],
        ], $overrides);
    }
}

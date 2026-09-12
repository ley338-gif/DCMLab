<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Node-Oberfläche (Abschnitt 10, P5): die Engine selbst (Regelwerk, Zustand)
 * ist in services/engine getestet -- hier geht es nur um die Laravel-Seite:
 * Sitzung anlegen/wiederverwenden, Anfragen durchreichen, node_attempts
 * pflegen, Hint-/Write-up-Text aus de.md rendern.
 */
class NodeControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->contentDir = storage_path('framework/testing/node-content-'.Str::random(12));
        $this->buildContentFixture();
        $this->app->instance(ContentRepository::class, new ContentRepository($this->contentDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        Node::factory()->create(['slug' => 'test-node']);

        $this->get('/de/nodes/test-node')->assertRedirect('/de/login');
    }

    public function test_first_visit_creates_an_engine_session_and_renders_state(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node', 'points' => 10]);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'sess-1', 'state' => $this->baseState()], 201),
            '*/v1/sessions/sess-1/state' => Http::response($this->baseState()),
        ]);

        $response = $this->actingAs($user)->get('/de/nodes/test-node');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Nodes/Show')
            ->where('node.slug', 'test-node')
            ->where('briefing_html', fn (string $html) => str_contains($html, 'Testauftrag'))
            ->where('hints.0.id', 'h1')
            ->where('hints.0.used', false)
            ->where('hints.0.text_html', null)
            ->where('write_up_html', null)
            ->where('state.node_slug', 'test-node'),
        );

        $this->assertDatabaseHas('node_attempts', [
            'user_id' => $user->id,
            'node_id' => $node->id,
            'engine_session_id' => 'sess-1',
            'status' => 'started',
        ]);

        Http::assertSent(fn ($request) => $request->url() === $this->engineUrl('/v1/sessions')
            && $request['node_slug'] === 'test-node');
    }

    public function test_second_visit_reuses_the_existing_session(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node']);
        $user = User::factory()->create();
        NodeAttempt::create([
            'user_id' => $user->id,
            'node_id' => $node->id,
            'engine_session_id' => 'existing-session',
            'status' => 'started',
            'started_at' => now(),
        ]);

        Http::fake(['*/v1/sessions/existing-session/state' => Http::response($this->baseState())]);

        $this->actingAs($user)->get('/de/nodes/test-node')->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v1/sessions')
            && $request->method() === 'POST');
    }

    public function test_exec_proxies_to_the_engine(): void
    {
        [$user] = $this->userWithExistingAttempt();

        Http::fake([
            '*/v1/sessions/existing-session/exec' => Http::response([
                'stdout' => '', 'stderr' => 'command not found', 'exit_code' => 127, 'events' => [],
            ]),
        ]);

        $response = $this->actingAs($user)->postJson('/de/nodes/test-node/exec', [
            'host' => 'workstation',
            'command' => 'rm -rf /',
        ]);

        $response->assertOk()->assertJson(['exit_code' => 127, 'stderr' => 'command not found']);

        Http::assertSent(fn ($request) => $request->url() === $this->engineUrl('/v1/sessions/existing-session/exec')
            && $request['host'] === 'workstation'
            && $request['command'] === 'rm -rf /');
    }

    public function test_hint_endpoint_renders_hint_text_and_syncs_the_attempt(): void
    {
        [$user, , $attempt] = $this->userWithExistingAttempt();

        Http::fake([
            '*/v1/sessions/existing-session/hint' => Http::response(['points' => 9]),
            '*/v1/sessions/existing-session/state' => Http::response(
                [...$this->baseState(), 'hints_used' => ['h1'], 'points' => 9],
            ),
        ]);

        $response = $this->actingAs($user)->postJson('/de/nodes/test-node/hint', ['hint_id' => 'h1']);

        $response->assertOk();
        $this->assertStringContainsString('Erster Hinweis', $response->json('text_html'));

        $attempt->refresh();
        $this->assertSame(['h1'], $attempt->hints_used);
        $this->assertSame(9, $attempt->points);
    }

    public function test_write_up_endpoint_zeroes_points_and_returns_rendered_html(): void
    {
        [$user, , $attempt] = $this->userWithExistingAttempt();

        Http::fake([
            '*/v1/sessions/existing-session/write-up' => Http::response(['points' => 0]),
            '*/v1/sessions/existing-session/state' => Http::response(
                [...$this->baseState(), 'write_up_seen' => true, 'points' => 0],
            ),
        ]);

        $response = $this->actingAs($user)->postJson('/de/nodes/test-node/write-up');

        $response->assertOk();
        $this->assertStringContainsString('Die Lösung', $response->json('write_up_html'));

        $attempt->refresh();
        $this->assertSame(0, $attempt->points);
    }

    public function test_correct_flag_marks_the_attempt_solved(): void
    {
        [$user, , $attempt] = $this->userWithExistingAttempt();

        Http::fake([
            '*/v1/sessions/existing-session/flag' => Http::response(['correct' => true, 'points' => 10]),
            '*/v1/sessions/existing-session/state' => Http::response(
                [...$this->baseState(), 'solved' => true, 'points' => 10],
            ),
        ]);

        $response = $this->actingAs($user)->postJson('/de/nodes/test-node/flag', ['value' => 'Testflag']);

        $response->assertOk()->assertJson(['correct' => true, 'points' => 10]);

        $attempt->refresh();
        $this->assertSame('solved', $attempt->status);
        $this->assertNotNull($attempt->flag_submitted_at);
    }

    public function test_show_renders_the_write_up_automatically_once_solved(): void
    {
        [$user] = $this->userWithExistingAttempt();

        Http::fake([
            '*/v1/sessions/existing-session/state' => Http::response(
                [...$this->baseState(), 'solved' => true, 'write_up_seen' => false],
            ),
        ]);

        $response = $this->actingAs($user)->get('/de/nodes/test-node');

        $response->assertInertia(fn ($page) => $page
            ->where('write_up_html', fn (?string $html) => $html !== null && str_contains($html, 'Die Lösung')),
        );
    }

    public function test_incorrect_flag_does_not_mark_the_attempt_solved(): void
    {
        [$user, , $attempt] = $this->userWithExistingAttempt();

        Http::fake([
            '*/v1/sessions/existing-session/flag' => Http::response(['correct' => false]),
            '*/v1/sessions/existing-session/state' => Http::response($this->baseState()),
        ]);

        $this->actingAs($user)->postJson('/de/nodes/test-node/flag', ['value' => 'falsch'])
            ->assertOk()->assertJson(['correct' => false]);

        $attempt->refresh();
        $this->assertSame('started', $attempt->status);
        $this->assertNull($attempt->flag_submitted_at);
    }

    /**
     * @return array{0: User, 1: Node, 2: NodeAttempt}
     */
    private function userWithExistingAttempt(): array
    {
        $node = Node::factory()->create(['slug' => 'test-node']);
        $user = User::factory()->create();
        $attempt = NodeAttempt::create([
            'user_id' => $user->id,
            'node_id' => $node->id,
            'engine_session_id' => 'existing-session',
            'status' => 'started',
            'started_at' => now(),
        ]);

        return [$user, $node, $attempt];
    }

    private function engineUrl(string $path): string
    {
        return rtrim(config('services.engine.url'), '/').$path;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseState(): array
    {
        return [
            'node_slug' => 'test-node',
            'hosts' => [
                'workstation' => ['ip' => '10.0.0.50', 'role' => 'shell'],
            ],
            'hints_used' => [],
            'write_up_seen' => false,
            'solved' => false,
            'points' => 10,
            'stuck' => false,
            'created_at' => now()->toIso8601String(),
        ];
    }

    private function buildContentFixture(): void
    {
        $nodeYml = <<<'YAML'
        slug: test-node
        difficulty: easy
        points: 10
        category: netzwerk
        skills: [netzwerk]
        related_lessons: []
        estimated_minutes: 10

        environment:
          engine: simulated
          hosts:
            - name: workstation
              ip: 10.0.0.50
              role: shell
          known_calling_aets: []
          tools: [echoscu]
          dataset: test-set
          templates:
            - label_key: tpl.echo
              command: "echoscu -aet WS -aec ZIEL_AE 10.0.0.10 104"
          placeholders: [ZIEL_AE]

        flag:
          type: tag_value
          source_tag: "0008,103E"
          hash: "sha256:0000000000000000000000000000000000000000000000000000000000000000"
          case_sensitive: false

        hints:
          - id: h1
            cost: 1

        stuck_timeout_minutes: 10
        status: draft
        updated: "2026-09-14"
        YAML;

        $nodeMd = <<<'MD'
        ---
        title: Testnode
        scenario_title: Ein Testszenario
        ---

        ## Briefing

        Testauftrag: loese das Raetsel.

        ---

        ## Hints

        ### h1

        Erster Hinweis.

        ---

        ## Write-up

        Die Lösung steht hier.
        MD;

        File::ensureDirectoryExists($this->contentDir.'/nodes/test-node');
        File::ensureDirectoryExists($this->contentDir.'/tools');
        File::ensureDirectoryExists($this->contentDir.'/glossary');
        File::put($this->contentDir.'/nodes/test-node/node.yml', $nodeYml);
        File::put($this->contentDir.'/nodes/test-node/de.md', $nodeMd);
        File::put($this->contentDir.'/datasets.yml', "test-set:\n  series: [\"Test Series\"]\n  file_count: 1\n");
    }
}

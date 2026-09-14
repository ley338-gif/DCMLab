<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Node-Oberfläche fuer `interaction: scenario` (Abschnitt 13, PoC
 * "Datenschutz"): dieselben Laravel-Routen wie bei terminal-Nodes, aber
 * EngineClientResolver muss sie an services/scenario-engine statt
 * services/engine durchreichen -- explizit gegen die scenario_engine-URL
 * gefaked, nie gegen einen gemeinsamen Wildcard, damit ein versehentliches
 * Zurueckfallen auf die DICOM-Engine auffaellt statt zufaellig zu passen.
 */
class NodeControllerScenarioTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->contentDir = storage_path('framework/testing/node-scenario-content-'.Str::random(12));
        $this->buildContentFixture();
        $this->app->instance(ContentRepository::class, new ContentRepository($this->contentDir));
        $this->seed(AchievementSeeder::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_show_creates_a_session_against_the_scenario_engine_not_dicom(): void
    {
        $node = Node::factory()->create(['slug' => 'test-scenario', 'interaction' => 'scenario']);
        $user = User::factory()->create();

        Http::fake([
            $this->scenarioEngineUrl('/v1/sessions') => Http::response(
                ['session_id' => 'sess-1', 'state' => $this->baseState()], 201,
            ),
            $this->scenarioEngineUrl('/v1/sessions/sess-1/state') => Http::response($this->baseState()),
        ]);

        $response = $this->actingAs($user)->get('/de/nodes/test-scenario');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Nodes/Show')
            ->where('node.slug', 'test-scenario')
            ->where('node.interaction', 'scenario')
            ->where('state.scenario.step_id', 'frage'),
        );

        $this->assertDatabaseHas('node_attempts', [
            'user_id' => $user->id,
            'node_id' => $node->id,
            'engine_session_id' => 'sess-1',
        ]);

        Http::assertSent(fn ($request) => $request->url() === $this->scenarioEngineUrl('/v1/sessions'));
        Http::assertNotSent(fn ($request) => $request->url() === $this->engineUrl('/v1/sessions'));
    }

    public function test_choosing_an_option_proxies_to_the_scenario_engine(): void
    {
        [$user] = $this->userWithExistingAttempt();

        Http::fake([
            $this->scenarioEngineUrl('/v1/sessions/existing-session/action') => Http::response([
                'log' => ['Richtige Antwort.'],
                'events' => [],
                'state' => [...$this->baseState(), 'scenario' => [
                    'step_id' => 'erfolg', 'prompt' => 'Richtig.', 'options' => [],
                    'terminal' => true, 'outcome' => 'correct', 'log' => [],
                ]],
            ]),
        ]);

        $response = $this->actingAs($user)->postJson('/de/nodes/test-scenario/action', [
            'host' => 'player',
            'action' => 'richtig',
        ]);

        $response->assertOk();
        $this->assertSame('correct', $response->json('state.scenario.outcome'));

        Http::assertSent(fn ($request) => $request->url()
            === $this->scenarioEngineUrl('/v1/sessions/existing-session/action')
            && $request['action'] === 'richtig');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), $this->engineUrl('')));
    }

    public function test_terminal_node_still_routes_to_the_dicom_engine(): void
    {
        $node = Node::factory()->create(['slug' => 'test-terminal', 'interaction' => 'terminal']);
        $user = User::factory()->create();

        Http::fake([
            $this->engineUrl('/v1/sessions') => Http::response(
                ['session_id' => 'sess-2', 'state' => ['node_slug' => 'test-terminal', 'hosts' => [], 'hints_used' => [], 'write_up_seen' => false, 'solved' => false, 'points' => 0, 'stuck' => false, 'created_at' => now()->toIso8601String()]],
                201,
            ),
            $this->engineUrl('/v1/sessions/sess-2/state') => Http::response(
                ['node_slug' => 'test-terminal', 'hosts' => [], 'hints_used' => [], 'write_up_seen' => false, 'solved' => false, 'points' => 0, 'stuck' => false, 'created_at' => now()->toIso8601String()],
            ),
        ]);

        $this->actingAs($user)->get('/de/nodes/test-terminal')->assertOk();

        Http::assertSent(fn ($request) => $request->url() === $this->engineUrl('/v1/sessions'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), $this->scenarioEngineUrl('')));
    }

    /**
     * @return array{0: User, 1: Node, 2: NodeAttempt}
     */
    private function userWithExistingAttempt(): array
    {
        $node = Node::factory()->create(['slug' => 'test-scenario', 'interaction' => 'scenario']);
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

    private function scenarioEngineUrl(string $path): string
    {
        return rtrim(config('services.scenario_engine.url'), '/').$path;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseState(): array
    {
        return [
            'node_slug' => 'test-scenario',
            'scenario' => [
                'step_id' => 'frage',
                'prompt' => 'Testfrage.',
                'options' => [
                    ['id' => 'falsch', 'label' => 'Falsche Antwort.'],
                    ['id' => 'richtig', 'label' => 'Richtige Antwort.'],
                ],
                'terminal' => false,
                'outcome' => null,
                'log' => [],
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
        slug: test-scenario
        difficulty: easy
        points: 10
        category: security
        interaction: scenario
        skills: [security]
        related_lessons: []
        estimated_minutes: 10

        scenario:
          start: frage
          steps:
            frage:
              prompt: Testfrage.
              options:
                - id: falsch
                  label: Falsche Antwort.
                  next: ende_falsch
                - id: richtig
                  label: Richtige Antwort.
                  next: ende_richtig
            ende_falsch:
              terminal: true
              outcome: wrong
            ende_richtig:
              terminal: true
              outcome: correct
              reveal: test-flag-klartext

        flag:
          type: exact
          source_tag: scenario
          hash: "sha256:0000000000000000000000000000000000000000000000000000000000000000"
          case_sensitive: false

        hints:
          - id: h1
            cost: 1

        stuck_timeout_minutes: 10
        status: draft
        updated: "2026-09-21"
        YAML;

        $nodeMd = <<<'MD'
        ---
        title: Test-Szenario
        scenario_title: Ein Test-Dialog
        ---

        ## Briefing

        Testauftrag: entscheide dich richtig.

        ---

        ## Hints

        ### h1

        Erster Hinweis.

        ---

        ## Write-up

        Die Lösung steht hier.
        MD;

        File::ensureDirectoryExists($this->contentDir.'/nodes/test-scenario');
        File::ensureDirectoryExists($this->contentDir.'/nodes/test-terminal');
        File::ensureDirectoryExists($this->contentDir.'/tools');
        File::ensureDirectoryExists($this->contentDir.'/glossary');
        File::put($this->contentDir.'/nodes/test-scenario/node.yml', $nodeYml);
        File::put($this->contentDir.'/nodes/test-scenario/de.md', $nodeMd);
        File::put(
            $this->contentDir.'/nodes/test-terminal/node.yml',
            "slug: test-terminal\ndifficulty: easy\npoints: 0\ncategory: netzwerk\nskills: []\nrelated_lessons: []\nestimated_minutes: 5\nenvironment:\n  engine: simulated\n  hosts: []\nhints: []\nstuck_timeout_minutes: 10\nstatus: draft\nupdated: \"2026-09-21\"\n",
        );
        File::put(
            $this->contentDir.'/nodes/test-terminal/de.md',
            "---\ntitle: Testnode\nscenario_title: Test\n---\n\n## Briefing\n\nText.\n\n## Write-up\n\nText.\n",
        );
    }
}

<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\AchievementDefinition;
use App\Models\AchievementUnlock;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\NodePreviewSession;
use App\Models\Themenfeld;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $this->seed(AchievementSeeder::class);
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

    /**
     * Seit ADR 0110 (CMS-6d Haertung): eine per Studio angelegte, noch nicht
     * freigegebene Node ist fuer eine normale Lernende gesperrt -- vorher
     * war show() ausschliesslich gegen "archived" geprueft, ein Entwurf war
     * also sofort fuer jeden angemeldeten Nutzer spielbar.
     */
    public function test_a_learner_cannot_open_a_node_that_is_not_yet_published(): void
    {
        Node::factory()->create(['slug' => 'test-node', 'status' => 'draft']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/de/nodes/test-node')->assertNotFound();
    }

    /**
     * "Vorschau" (ADR 0109) ist keine zweite Route -- ein Autor/Reviewer,
     * der die zugehoerige Activity bearbeiten darf, sieht denselben
     * Lerner-Renderer trotzdem, auch bevor die Node freigegeben ist.
     */
    public function test_an_assigned_author_can_preview_a_node_that_is_not_yet_published(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node', 'status' => 'draft']);
        $activity = Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'sess-1', 'state' => $this->baseState()], 201),
            '*/v1/sessions/sess-1/state' => Http::response($this->baseState()),
        ]);

        $this->actingAs($author)->get('/de/nodes/test-node')->assertOk();
    }

    public function test_an_unassigned_author_cannot_preview_a_node_that_is_not_yet_published(): void
    {
        Node::factory()->create(['slug' => 'test-node', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $author = User::factory()->author()->create();

        $this->actingAs($author)->get('/de/nodes/test-node')->assertNotFound();
    }

    public function test_a_reviewer_can_preview_a_node_that_is_not_yet_published(): void
    {
        Node::factory()->create(['slug' => 'test-node', 'status' => 'review']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $reviewer = User::factory()->reviewer()->create();

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'sess-1', 'state' => $this->baseState()], 201),
            '*/v1/sessions/sess-1/state' => Http::response($this->baseState()),
        ]);

        $this->actingAs($reviewer)->get('/de/nodes/test-node')->assertOk();
    }

    public function test_an_authorized_previewer_sees_the_draft_preview_banner_prop(): void
    {
        Node::factory()->create(['slug' => 'test-node', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $admin = User::factory()->administrator()->create();

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'sess-1', 'state' => $this->baseState()], 201),
            '*/v1/sessions/sess-1/state' => Http::response($this->baseState()),
        ]);

        $this->actingAs($admin)->get('/de/nodes/test-node')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('draft_preview', true));
    }

    public function test_a_published_node_never_sets_the_draft_preview_prop(): void
    {
        [$user] = $this->userWithExistingAttempt();

        Http::fake(['*/v1/sessions/existing-session/state' => Http::response($this->baseState())]);

        $this->actingAs($user)->get('/de/nodes/test-node')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('draft_preview', false));
    }

    /**
     * Der eigentliche Sicherheits-Fix (ADR 0110/CMS-6d-Haertung, Abschnitt
     * 4): vor diesem PR pruefte nur `show()` den Draft-Status -- state/exec/
     * config/action/hint/write-up/flag hingen ausschliesslich an
     * `attemptFor()`, das anstandslos eine echte Session fuer JEDEN
     * angemeldeten Nutzer angelegt haette, solange er den Node-Slug kennt.
     * Jeder Unterendpunkt muss denselben `NodePolicy::view()`-Vertrag wie
     * `show()` durchsetzen.
     */
    public function test_a_learner_cannot_use_any_session_endpoint_for_a_node_that_is_not_yet_published(): void
    {
        Node::factory()->create(['slug' => 'test-node', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $learner = User::factory()->create();

        $endpoints = [
            ['get', '/de/nodes/test-node/state', []],
            ['post', '/de/nodes/test-node/exec', ['host' => 'workstation', 'command' => 'ls']],
            ['post', '/de/nodes/test-node/config', ['host' => 'workstation', 'field' => 'x', 'value' => 'y']],
            ['post', '/de/nodes/test-node/action', ['host' => 'workstation', 'action' => 'send_study']],
            ['post', '/de/nodes/test-node/hint', ['hint_id' => 'h1']],
            ['post', '/de/nodes/test-node/write-up', []],
            ['post', '/de/nodes/test-node/flag', ['value' => 'irrelevant']],
        ];

        foreach ($endpoints as [$method, $url, $data]) {
            $response = $this->actingAs($learner)->json($method, $url, $data ?? []);
            $response->assertNotFound();
        }

        // Kein einziger dieser Aufrufe darf irgendeine Session angelegt
        // haben -- weder eine echte (node_attempts) noch eine Vorschau
        // (node_preview_sessions).
        $this->assertSame(0, NodeAttempt::query()->count());
        $this->assertSame(0, NodePreviewSession::query()->count());
    }

    public function test_guests_are_redirected_to_login_for_a_draft_nodes_session_endpoints(): void
    {
        Node::factory()->create(['slug' => 'test-node', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);

        $this->postJson('/de/nodes/test-node/exec', ['host' => 'workstation', 'command' => 'ls'])
            ->assertUnauthorized();
    }

    /**
     * "Erratene Preview-Session-ID": es gibt in dieser Architektur keinen
     * client-seitigen Session-Id-Parameter zu erraten -- jede Session wird
     * serverseitig ausschliesslich ueber (authentifizierter Nutzer, Node)
     * aufgeloest (`attemptFor()`/`previewSessionFor()`). Der eigentlich
     * relevante Nachweis ist deshalb Cross-User-Isolation: ein normaler
     * Lernender, der denselben Draft-Slug kennt, auf den ein Administrator
     * bereits eine aktive Vorschau-Session hat, bekommt trotzdem 404 und
     * beeinflusst dessen Vorschau-Session nicht.
     */
    public function test_a_learner_cannot_reach_another_users_active_preview_session_via_the_same_slug(): void
    {
        Node::factory()->create(['slug' => 'test-node', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $admin = User::factory()->administrator()->create();
        $learner = User::factory()->create();

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'admin-preview', 'state' => $this->baseState()], 201),
            '*/v1/sessions/admin-preview/state' => Http::response($this->baseState()),
        ]);
        $this->actingAs($admin)->get('/de/nodes/test-node')->assertOk();
        $this->assertSame(1, NodePreviewSession::query()->count());

        $this->actingAs($learner)->postJson('/de/nodes/test-node/exec', [
            'host' => 'workstation', 'command' => 'ls',
        ])->assertNotFound();

        // Der Lernende hat weder eine eigene Session bekommen noch die
        // Vorschau-Session des Admins veraendert.
        $this->assertSame(1, NodePreviewSession::query()->count());
        $this->assertSame('admin-preview', NodePreviewSession::query()->sole()->engine_session_id);
    }

    /**
     * War bis zu diesem Fund `Http::assertSentCount(3)` (1x createSession +
     * 2x state) -- brach lokal, sobald ein zusaetzlicher, mit der
     * Session-Wiederverwendung voellig unzusammenhaengender HTTP-Aufruf
     * mitgezaehlt wurde: `Inertia\Ssr\HttpGateway::dispatch()` schickt bei
     * `Vite::isRunningHot()` (d. h. `public/hot` existiert) einen echten
     * `Http::post()` an die im Hot-File hinterlegte Vite-Dev-Server-URL,
     * der ueber `Http::fake()` ebenfalls aufgezeichnet wird. Verifiziert:
     * Der Test ist deterministisch (10x isoliert gruen) sobald kein
     * `public/hot` vorhanden ist, und reproduzierbar auf 4 Anfragen, sobald
     * doch eins existiert -- eine reine Artefakt-Leiche eines lokal
     * gestarteten `npm run dev`, kein CI-Zustand (die Datei ist
     * gitignored, CI startet nie `npm run dev` neben Pest) und kein Fehler
     * in `previewSessionFor()`. Die pauschale Gesamtzaehlung war deshalb
     * ohnehin der falsche Vertrag -- sie pruefte implizit "keine einzige
     * fremde HTTP-Anfrage waehrend des Requests", nicht "keine zweite
     * Session". Ersetzt durch endpunktspezifische Assertions, die exakt
     * den fachlichen Vertrag pruefen und unabhaengig von einer lokal
     * zufaellig aktiven SSR-Anfrage bestehen bleiben.
     */
    public function test_second_preview_visit_reuses_the_existing_preview_session(): void
    {
        Node::factory()->create(['slug' => 'test-node', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $author = User::factory()->author()->create();
        Activity::query()->where('key', 'test-node')->sole()->authorUsers()->attach($author);

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'preview-1', 'state' => $this->baseState()], 201),
            '*/v1/sessions/preview-1/state' => Http::response($this->baseState()),
        ]);
        $this->actingAs($author)->get('/de/nodes/test-node')->assertOk();
        $this->assertSame(1, NodePreviewSession::query()->count());
        $firstPreview = NodePreviewSession::query()->sole();

        $this->actingAs($author)->get('/de/nodes/test-node')->assertOk();

        $this->assertSame(1, NodePreviewSession::query()->count());
        $secondPreview = NodePreviewSession::query()->sole();
        $this->assertSame($firstPreview->id, $secondPreview->id, 'Der zweite Besuch muss dieselbe Preview-Session-Zeile wiederverwenden.');
        $this->assertSame($firstPreview->engine_session_id, $secondPreview->engine_session_id, 'Die engine_session_id darf sich beim zweiten Besuch nicht aendern.');

        // Keine echte NodeAttempt-Zeile: eine nicht veroeffentlichte Node
        // erzeugt per Konstruktion (attemptFor()) ausschliesslich eine
        // NodePreviewSession, nie einen echten Lernfortschritt.
        $this->assertSame(0, NodeAttempt::query()->count());

        $createSessionRequests = Http::recorded(
            fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/v1/sessions'),
        );
        $this->assertCount(1, $createSessionRequests, 'Ueber beide Besuche hinweg darf genau eine Session erzeugt werden -- kein zweites createSession beim Wiederbesuch.');

        $stateRequests = Http::recorded(
            fn ($request) => $request->method() === 'GET' && str_contains($request->url(), '/v1/sessions/preview-1/state'),
        );
        $this->assertCount(2, $stateRequests, 'Jeder Besuch fragt genau einmal den aktuellen Engine-State ab.');
    }

    public function test_index_groups_nodes_by_themenfeld(): void
    {
        $datenschutz = Themenfeld::factory()->create(['slug' => 'datenschutz']);
        Node::factory()->create(['slug' => 'dicom-node']);
        Node::factory()->create(['slug' => 'datenschutz-node', 'themenfeld_id' => $datenschutz->id]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/de/nodes');

        $response->assertInertia(fn ($page) => $page
            ->component('Nodes/Index')
            ->where('nodes', fn (Collection $nodes) => $nodes->firstWhere('slug', 'dicom-node')['themenfeld'] === 'dicom'
                && $nodes->firstWhere('slug', 'datenschutz-node')['themenfeld'] === 'datenschutz'),
        );
    }

    /**
     * Published Content Boundary Hardening: der Node-Katalog ist auch fuer
     * Gaeste ohne jede Authentifizierung erreichbar -- relatedLessonForNodes()
     * loeste die "passende Lektion" bisher unabhaengig von deren
     * Veroeffentlichungsstatus auf, ein echter Titel-Leak ohne Login.
     */
    public function test_the_public_catalog_does_not_leak_a_draft_related_lessons_title(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '9.9', 'status' => 'draft', 'title' => ['de' => 'Geheime Draft-Lektion']]);
        Node::factory()->create(['slug' => 'test-node', 'related_lessons' => ['9.9']]);

        $response = $this->get('/de/nodes');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('nodes', fn (Collection $nodes) => $nodes->firstWhere('slug', 'test-node')['related_lesson'] === null),
        );
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

    /**
     * ADR 0107 (CMS-6d): body/hints kommen bevorzugt aus der DB, nicht mehr
     * live aus content/ -- derselbe Fund/dieselbe Loesung wie bei Lesson
     * (ADR 0101).
     */
    public function test_it_prefers_the_db_body_and_hints_over_the_file(): void
    {
        Node::factory()->create([
            'slug' => 'test-node',
            'points' => 10,
            'body' => "## Briefing\n\nAus der DB, nicht aus der Datei.\n\n## Hints\n\n### h1\n\nDB-Hinweis.\n\n## Write-up\n\nDB-Loesung.",
            'hints' => [['id' => 'h1', 'cost' => 3]],
        ]);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'sess-1', 'state' => $this->baseState()], 201),
            '*/v1/sessions/sess-1/state' => Http::response($this->baseState()),
        ]);

        $this->actingAs($user)
            ->get('/de/nodes/test-node')
            ->assertInertia(fn ($page) => $page
                ->where('briefing_html', fn (string $html) => str_contains($html, 'Aus der DB'))
                ->where('hints.0.id', 'h1'),
            );
    }

    /**
     * CMS-7d.3 (ADR 0118): eine Node mit befuelltem rich_content wird ueber
     * RichContentRenderer gerendert, nicht mehr ueber body/NodeSections/
     * MarkdownRenderer -- der eigentliche Read-Cutover.
     */
    public function test_it_prefers_rich_content_over_the_legacy_body_when_present(): void
    {
        Node::factory()->create([
            'slug' => 'test-node',
            'points' => 10,
            'body' => "## Briefing\n\nVeraltet -- darf NICHT gerendert werden.\n\n## Hints\n\n### h1\n\nVeraltet.\n\n## Write-up\n\nVeraltet.",
            'hints' => [['id' => 'h1', 'cost' => 3]],
            'rich_content' => [
                'type' => 'node_content', 'version' => 1,
                'briefing' => ['type' => 'doc', 'version' => 1, 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Briefing aus rich_content.']]],
                ]],
                'hints' => ['h1' => ['type' => 'doc', 'version' => 1, 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hint aus rich_content.']]],
                ]]],
                'write_up' => ['type' => 'doc', 'version' => 1, 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Write-up aus rich_content.']]],
                ]],
            ],
        ]);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'sess-1', 'state' => $this->baseState()], 201),
            '*/v1/sessions/sess-1/state' => Http::response($this->baseState()),
        ]);

        $this->actingAs($user)
            ->get('/de/nodes/test-node')
            ->assertInertia(fn ($page) => $page
                ->where('briefing_html', fn (string $html) => str_contains($html, 'Briefing aus rich_content.')
                    && ! str_contains($html, 'Veraltet')),
            );
    }

    /**
     * CMS-7d.4 (Betreiber-Review): der 404-Existenzcheck durfte nicht
     * mehr allein von `body !== null` abhaengen -- eine reine
     * Rich-Content-Node (`body` zufaellig `null`, kein produktiver Fall
     * heute, da `StudioNodeController::store()` immer `''` setzt, aber
     * nicht mehr strukturell erzwungen) muss trotzdem sichtbar bleiben.
     */
    public function test_it_is_visible_when_rich_content_is_set_but_body_is_null(): void
    {
        Node::factory()->create([
            'slug' => 'test-node',
            'body' => null,
            'hints' => [],
            'rich_content' => [
                'type' => 'node_content', 'version' => 1,
                'briefing' => ['type' => 'doc', 'version' => 1, 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Nur Rich Content, kein body.']]],
                ]],
                'hints' => [],
                'write_up' => ['type' => 'doc', 'version' => 1, 'content' => []],
            ],
        ]);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'sess-1', 'state' => $this->baseState()], 201),
            '*/v1/sessions/sess-1/state' => Http::response($this->baseState()),
        ]);

        $this->actingAs($user)
            ->get('/de/nodes/test-node')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('briefing_html', fn (string $html) => str_contains($html, 'Nur Rich Content, kein body.')),
            );
    }

    /**
     * CMS-7d.4 Phase 3: der Markdown-Fallback (kein rich_content
     * gesetzt) loggt ein messbares Signal -- Ziel ist, dass dieser
     * Log-Eintrag im produktiven Bestand nie feuert
     * (`rich-content:coverage`).
     */
    public function test_falling_back_to_markdown_logs_a_warning(): void
    {
        Node::factory()->create([
            'slug' => 'test-node',
            'body' => "## Briefing\n\nNur Markdown.\n\n## Hints\n\n## Write-up\n\nText.",
            'hints' => [],
            'rich_content' => null,
        ]);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'sess-1', 'state' => $this->baseState()], 201),
            '*/v1/sessions/sess-1/state' => Http::response($this->baseState()),
        ]);
        Log::spy();

        $this->actingAs($user)->get('/de/nodes/test-node')->assertOk();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context) => $message === 'learner_view.legacy_body_fallback'
                && $context['node_slug'] === 'test-node' && $context['field'] === 'briefing')
            ->once();
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

    public function test_hint_endpoint_prefers_rich_content_over_the_legacy_body(): void
    {
        $node = Node::factory()->create([
            'slug' => 'test-node',
            'body' => "## Briefing\n\nX.\n\n## Hints\n\n### h1\n\nVeraltet.\n\n## Write-up\n\nX.",
            'hints' => [['id' => 'h1', 'cost' => 1]],
            'rich_content' => [
                'type' => 'node_content', 'version' => 1,
                'briefing' => ['type' => 'doc', 'version' => 1, 'content' => []],
                'hints' => ['h1' => ['type' => 'doc', 'version' => 1, 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hint aus rich_content.']]],
                ]]],
                'write_up' => ['type' => 'doc', 'version' => 1, 'content' => []],
            ],
        ]);
        $user = User::factory()->create();
        $attempt = NodeAttempt::create([
            'user_id' => $user->id, 'node_id' => $node->id, 'engine_session_id' => 'existing-session',
            'status' => 'started', 'started_at' => now(),
        ]);

        Http::fake([
            '*/v1/sessions/existing-session/hint' => Http::response(['points' => 9]),
            '*/v1/sessions/existing-session/state' => Http::response(
                [...$this->baseState(), 'hints_used' => ['h1'], 'points' => 9],
            ),
        ]);

        $response = $this->actingAs($user)->postJson('/de/nodes/test-node/hint', ['hint_id' => 'h1']);

        $response->assertOk();
        $this->assertStringContainsString('Hint aus rich_content.', $response->json('text_html'));
        $this->assertStringNotContainsString('Veraltet', $response->json('text_html'));
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

    public function test_correct_flag_recomputes_the_profile(): void
    {
        [$user, $node, $attempt] = $this->userWithExistingAttempt();

        Http::fake([
            '*/v1/sessions/existing-session/flag' => Http::response(['correct' => true, 'points' => 9]),
            '*/v1/sessions/existing-session/state' => Http::response(
                [...$this->baseState(), 'solved' => true, 'points' => 9],
            ),
        ]);

        $this->actingAs($user)->postJson('/de/nodes/test-node/flag', ['value' => 'Testflag'])
            ->assertOk();

        $attempt->refresh();
        $this->assertSame(9, $attempt->points);

        $profile = $user->profile;
        $this->assertNotNull($profile);
        $this->assertSame(9, $profile->points);
        $this->assertSame(['netzwerk' => 9, 'datenmodell' => 0, 'bildgebung' => 0, 'integration' => 0, 'security' => 0], $profile->skill_vector);
    }

    public function test_correct_flag_unlocks_the_personal_first_blood_achievement_once(): void
    {
        [$user, $node, $attempt] = $this->userWithExistingAttempt();
        Activity::factory()->create(['type' => 'node', 'key' => $node->slug]);

        Http::fake([
            '*/v1/sessions/existing-session/flag' => Http::response(['correct' => true, 'points' => 9]),
            '*/v1/sessions/existing-session/state' => Http::response(
                [...$this->baseState(), 'solved' => true, 'points' => 9],
            ),
        ]);

        $response = $this->actingAs($user)->postJson('/de/nodes/test-node/flag', ['value' => 'Testflag']);

        $response->assertOk();
        // "trailblazer" (globaler Wettlauf, ADR 0090b) und "first-blood"
        // (persoenlich, ADR 0077) sind beide activity_completed-Kriterien
        // ohne key -- die erste je geloeste Node schaltet zwangslaeufig
        // beide gleichzeitig frei.
        $this->assertEqualsCanonicalizing(
            ['first-blood', 'trailblazer'],
            array_column($response->json('unlocked_achievements'), 'slug'),
        );
        $this->assertSame(
            1,
            AchievementUnlock::query()
                ->where('user_id', $user->id)
                ->whereRelation('definition', 'slug', 'first-blood')
                ->count(),
        );
        $this->assertSame(
            1,
            AchievementUnlock::query()
                ->where('user_id', $user->id)
                ->whereRelation('definition', 'slug', 'trailblazer')
                ->count(),
        );

        // Ein zweiter geloester Node darf first-blood nicht erneut vergeben,
        // aber sein eigenes, deklarativ an ihn gebundenes Achievement schon
        // (ADR 0077: unlock_when statt node.yml-Feld) -- und "trailblazer"
        // wieder, weil es global-scoped ist (je Node ein eigener Gewinner).
        AchievementDefinition::create([
            'slug' => 'second-node-badge', 'name' => 'Zweiter Node', 'description' => 'Test',
            'image' => 'second-node-badge.png', 'category' => 'test', 'points' => 0,
            'is_hidden' => false, 'sort_order' => 100,
            'unlock_when' => ['type' => 'activity_completed', 'activity_type' => 'node', 'key' => 'test-node-2'],
        ]);
        $secondNode = Node::factory()->create(['slug' => 'test-node-2']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node-2']);
        $secondAttempt = NodeAttempt::create([
            'user_id' => $user->id,
            'node_id' => $secondNode->id,
            'engine_session_id' => 'second-session',
            'status' => 'started',
            'started_at' => now(),
        ]);

        Http::fake([
            '*/v1/sessions/second-session/flag' => Http::response(['correct' => true, 'points' => 5]),
            '*/v1/sessions/second-session/state' => Http::response(
                [...$this->baseState(), 'node_slug' => 'test-node-2', 'solved' => true, 'points' => 5],
            ),
        ]);

        $secondResponse = $this->actingAs($user)->postJson('/de/nodes/test-node-2/flag', ['value' => 'Testflag2']);

        $secondResponse->assertOk();
        $this->assertEqualsCanonicalizing(
            ['second-node-badge', 'trailblazer'],
            array_column($secondResponse->json('unlocked_achievements'), 'slug'),
        );
        $this->assertSame(
            1,
            AchievementUnlock::query()
                ->where('user_id', $user->id)
                ->whereRelation('definition', 'slug', 'first-blood')
                ->count(),
        );
        $this->assertSame(
            2,
            AchievementUnlock::query()
                ->where('user_id', $user->id)
                ->whereRelation('definition', 'slug', 'trailblazer')
                ->count(),
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
     * Phase D.2: ein korrekter Flag bei nicht erfuellten `solve.requires`
     * ist `correct: true, solved: false` -- die Engine-Antwort traegt jetzt
     * ein eigenes `solved`-Feld, das der Controller (nicht mehr `correct`)
     * fuer Attempt-Status/Achievements auswertet.
     */
    public function test_correct_but_incomplete_flag_does_not_mark_the_attempt_solved(): void
    {
        [$user, $node, $attempt] = $this->userWithExistingAttempt();
        Activity::factory()->create(['type' => 'node', 'key' => $node->slug]);

        Http::fake([
            '*/v1/sessions/existing-session/flag' => Http::response([
                'correct' => true, 'solved' => false, 'reason' => 'prerequisites_not_met',
            ]),
            '*/v1/sessions/existing-session/state' => Http::response($this->baseState()),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/de/nodes/test-node/flag', ['value' => 'PACS-TO-DOSE']);

        $response->assertOk()->assertJson([
            'correct' => true, 'solved' => false, 'reason' => 'prerequisites_not_met',
            'unlocked_achievements' => [],
        ]);

        $attempt->refresh();
        $this->assertSame('started', $attempt->status);
        $this->assertNull($attempt->flag_submitted_at);

        $this->assertNull($user->fresh()->profile);
        $this->assertSame(
            0,
            AchievementUnlock::query()->where('user_id', $user->id)->count(),
        );
    }

    /**
     * Der separate Scenario-Engine-Client (services/scenario-engine) kennt
     * kein `solved`-Feld -- der Controller-Fallback `$result['solved'] ??
     * $result['correct']` muss dessen altes Verhalten exakt erhalten, sonst
     * wuerde jeder Scenario-Node (z. B. "dosis-bleibt-liegen") nie wieder
     * als geloest erkannt.
     */
    public function test_legacy_engine_response_without_solved_field_still_solves(): void
    {
        [$user, , $attempt] = $this->userWithExistingAttempt();

        Http::fake([
            '*/v1/sessions/existing-session/flag' => Http::response(['correct' => true, 'points' => 10]),
            '*/v1/sessions/existing-session/state' => Http::response(
                [...$this->baseState(), 'solved' => true, 'points' => 10],
            ),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/de/nodes/test-node/flag', ['value' => 'Testflag']);

        $response->assertOk()->assertJson(['correct' => true, 'solved' => true]);

        $attempt->refresh();
        $this->assertSame('solved', $attempt->status);
        $this->assertNotNull($attempt->flag_submitted_at);
    }

    /**
     * Abschnitt 6 des Auftrags: ein in der Draft-Vorschau "geloester" Node
     * darf keinerlei regulaeren Lernfortschritt erzeugen -- kein
     * `node_attempts`-Datensatz, kein Profil, kein ActivityProgress, kein
     * Achievement. Die Engine selbst darf den simulierten Node intern
     * trotzdem als geloest fuehren (fuer Write-up/UI), nur die persistente
     * Lernstatistik der Webanwendung bleibt unberuehrt.
     */
    public function test_a_correctly_solved_draft_preview_creates_no_persistent_progress(): void
    {
        Node::factory()->create(['slug' => 'test-node', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $admin = User::factory()->administrator()->create();

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'preview-1', 'state' => $this->baseState()], 201),
            '*/v1/sessions/preview-1/state' => Http::response($this->baseState()),
            '*/v1/sessions/preview-1/flag' => Http::response(['correct' => true, 'solved' => true, 'points' => 50]),
        ]);
        // Session zuerst anlegen (wie ein echter Vorschau-Besuch).
        $this->actingAs($admin)->get('/de/nodes/test-node')->assertOk();

        $response = $this->actingAs($admin)
            ->postJson('/de/nodes/test-node/flag', ['value' => 'Testflag']);

        // Die Engine-Antwort selbst bleibt unveraendert -- der Lernende
        // sieht denselben Solve/Write-up-Ablauf wie ein echter Learner.
        $response->assertOk()->assertJson([
            'correct' => true, 'solved' => true, 'unlocked_achievements' => [],
        ]);

        $this->assertSame(0, NodeAttempt::query()->count());
        $this->assertSame(1, NodePreviewSession::query()->count());
        $this->assertSame('solved', NodePreviewSession::query()->sole()->status);
        $this->assertNull($admin->fresh()->profile);
        $this->assertSame(0, AchievementUnlock::query()->count());
        $this->assertSame(0, ActivityProgress::query()->count());
    }

    /**
     * Mehrfaches Loesen/Hinweise/Write-up in der Vorschau darf ebenfalls
     * nichts persistieren -- nicht nur der erste Aufruf.
     */
    public function test_repeated_draft_preview_solves_and_write_up_still_create_no_progress(): void
    {
        Node::factory()->create(['slug' => 'test-node', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $author = User::factory()->author()->create();
        Activity::query()->where('key', 'test-node')->sole()->authorUsers()->attach($author);

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'preview-1', 'state' => $this->baseState()], 201),
            '*/v1/sessions/preview-1/state' => Http::response(
                [...$this->baseState(), 'solved' => true, 'write_up_seen' => true],
            ),
            '*/v1/sessions/preview-1/flag' => Http::response(['correct' => true, 'solved' => true, 'points' => 50]),
            '*/v1/sessions/preview-1/write-up' => Http::response(['points' => 0]),
        ]);
        $this->actingAs($author)->get('/de/nodes/test-node')->assertOk();

        $this->actingAs($author)->postJson('/de/nodes/test-node/flag', ['value' => 'a'])->assertOk();
        $this->actingAs($author)->postJson('/de/nodes/test-node/flag', ['value' => 'b'])->assertOk();
        $this->actingAs($author)->postJson('/de/nodes/test-node/write-up')->assertOk();

        $this->assertSame(0, NodeAttempt::query()->count());
        $this->assertNull($author->fresh()->profile);
        $this->assertSame(0, AchievementUnlock::query()->count());
        $this->assertSame(0, ActivityProgress::query()->count());
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

        $nodeYml2 = <<<'YAML'
        slug: test-node-2
        difficulty: easy
        points: 5
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
          templates: []
          placeholders: []

        flag:
          type: tag_value
          source_tag: "0008,103E"
          hash: "sha256:0000000000000000000000000000000000000000000000000000000000000000"
          case_sensitive: false

        hints: []

        stuck_timeout_minutes: 10
        status: draft
        updated: "2026-09-14"
        YAML;

        $nodeMd2 = <<<'MD'
        ---
        title: Testnode 2
        scenario_title: Ein zweites Testszenario
        ---

        ## Briefing

        Testauftrag 2.

        ---

        ## Write-up

        Die Lösung steht hier.
        MD;

        File::ensureDirectoryExists($this->contentDir.'/nodes/test-node');
        File::ensureDirectoryExists($this->contentDir.'/nodes/test-node-2');
        File::ensureDirectoryExists($this->contentDir.'/tools');
        File::ensureDirectoryExists($this->contentDir.'/glossary');
        File::put($this->contentDir.'/nodes/test-node/node.yml', $nodeYml);
        File::put($this->contentDir.'/nodes/test-node/de.md', $nodeMd);
        File::put($this->contentDir.'/nodes/test-node-2/node.yml', $nodeYml2);
        File::put($this->contentDir.'/nodes/test-node-2/de.md', $nodeMd2);
        File::put($this->contentDir.'/datasets.yml', "test-set:\n  series: [\"Test Series\"]\n  file_count: 1\n");
    }
}

<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\SandboxSession;
use App\Models\SandboxTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * CMS-8a, Abschnitt H: eigenstaendige Learner-Route fuer ein Lab. CMS-8d
 * (dieser Commit) verdrahtet den Runtime-Lifecycle -- Start, Zustand,
 * ein duenner Exec-Proxy, Beenden -- ausschliesslich ownership-sicher ueber
 * den eigenen `LabAttempt` des anfragenden Nutzers, nie ueber eine vom
 * Client mitgegebene Sitzungs-ID. Assertion-Auswertung/Progress-/
 * Profilpunkte-Verdrahtung (in `exec()`) und `show()`s Runtime-/
 * Assertion-Anzeige sind bewusst NICHT Teil dieses Commits, siehe
 * LabController-Klassendoc -- das kommt im naechsten Progress-Commit.
 *
 * Betreiber-Review vor #128: Ansehen (show) und Beginnen (start) sind
 * bewusst getrennte Aktionen -- anders als bei Node legt der reine
 * Seitenaufruf noch KEINEN Attempt an.
 */
class LabControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_lab_is_visible_without_an_attempt(): void
    {
        Lab::factory()->create([
            'slug' => 'c-echo-connectivity',
            'title' => ['de' => 'C-ECHO Connectivity Lab'],
            'scenario_title' => ['de' => 'Verbindung pruefen'],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Pruefe die Verbindung.']]],
            ]],
        ]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Labs/Show')
                ->where('lab.title', 'C-ECHO Connectivity Lab')
                ->where('lab.scenario_title', 'Verbindung pruefen')
                ->where('attempt', null)
                ->where('can_start', true)
                ->where('briefing_html', fn (string $html) => str_contains($html, 'Pruefe die Verbindung.')),
            );

        $this->assertDatabaseCount('lab_attempts', 0);
    }

    public function test_starting_a_lab_creates_an_attempt(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/de/labs/c-echo-connectivity/start')
            ->assertRedirect();

        $this->assertDatabaseHas('lab_attempts', [
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'status' => 'started',
        ]);

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertInertia(fn ($page) => $page->where('attempt.status', 'started'));
    }

    public function test_starting_a_lab_twice_does_not_create_a_second_attempt(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        $this->assertSame(1, LabAttempt::where('user_id', $user->id)->where('activity_id', $activity->id)->count());
    }

    /**
     * Ein zweiter "Lab starten"-Klick darf einen bereits geloesten Attempt
     * nicht zurueck auf "started" setzen -- firstOrCreate() legt nur an,
     * ruehrt einen bestehenden Datensatz nie an.
     */
    public function test_starting_a_lab_does_not_reset_an_already_solved_attempt(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        LabAttempt::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'status' => 'solved',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        $this->assertSame('solved', LabAttempt::where('user_id', $user->id)->where('activity_id', $activity->id)->value('status'));
    }

    public function test_a_draft_lab_is_hidden_from_a_learner(): void
    {
        Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/de/labs/draft-lab')->assertNotFound();
    }

    public function test_starting_a_draft_lab_is_blocked_for_a_learner(): void
    {
        Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/de/labs/draft-lab/start')->assertNotFound();
        $this->assertDatabaseCount('lab_attempts', 0);
    }

    public function test_a_draft_lab_is_visible_to_its_author(): void
    {
        $lab = Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);

        $this->actingAs($author)
            ->get('/de/labs/draft-lab')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_start', false));
    }

    /**
     * Betreiber-Review (zweite Runde): der Blocker aus der ersten
     * #128-Review -- show() erlaubt einem Autor bewusst eine Vorschau
     * eines Drafts, start() darf diese Ausnahme aber NIE teilen, sonst
     * wuerde eine reine Vorschau (ab CMS-8d) eine echte SandboxSession/
     * Quota/TTL ausloesen koennen. Ansehen und Beginnen sind deshalb
     * absichtlich unterschiedlich streng gegatet.
     */
    public function test_an_author_can_preview_a_draft_lab_but_cannot_start_it(): void
    {
        Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);

        $this->actingAs($author)->get('/de/labs/draft-lab')->assertOk();
        $this->actingAs($author)->post('/de/labs/draft-lab/start')->assertNotFound();

        $this->assertDatabaseCount('lab_attempts', 0);
    }

    public function test_starting_a_lab_starts_the_runtime_with_the_attempt_scoped_key(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);

        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start')->assertRedirect();

        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($attempt->current_sandbox_session_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/sandboxes')
            && $request['runtime_key'] === "lab-attempt:{$attempt->id}"
            && $request['dataset_slug'] === 'ct-thorax-60'
            && $request['template_slug'] === 'dicom-basic-tools');
    }

    /**
     * Betreiber-Vorgabe (CMS-8d, Abschnitt B): der konstante
     * lab-attempt:{id}-Key macht einen erneuten Klick von Natur aus sicher
     * wiederholbar -- keine zweite SandboxSession-Zeile.
     */
    public function test_starting_a_lab_twice_reuses_the_same_runtime_session(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);

        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        $this->assertSame(1, SandboxSession::query()->count());
    }

    /**
     * Der Zwischenzustand aus Abschnitt B: status='started' UND
     * current_sandbox_session_id=null bedeutet "Attempt existiert, keine
     * aktive Runtime" -- hier durch einen weichen Fehler ausgeloest, keine
     * neue Statusspalte, kein Absturz, der Attempt wird NIE geloescht.
     */
    public function test_a_soft_runtime_conflict_leaves_the_attempt_started_without_a_session(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['detail' => 'active_runtime_exists'], 409)]);

        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start')->assertRedirect();

        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('started', $attempt->status);
        $this->assertNull($attempt->current_sandbox_session_id);
    }

    /**
     * Betreiber-Vorgabe: `active_runtime_exists`/`quota_exceeded` sind
     * erwartete weiche Fehler, keine Exceptions -- start()s einziger
     * Rueckkanal ist der Session-Flash (Betreiber-Korrektur: `Labs/Show.vue`
     * liest das ueber ein lokales `show()`-Prop statt globalem
     * Flash-Sharing, das kommt aber erst mit der Learner-UI in einem
     * spaeteren Commit -- hier wird nur der Flash selbst geprueft).
     */
    public function test_a_soft_runtime_conflict_flashes_the_error_to_the_session(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['detail' => 'active_runtime_exists'], 409)]);

        $this->actingAs($user)
            ->post('/de/labs/c-echo-connectivity/start')
            ->assertRedirect()
            ->assertSessionHas('runtime_error', 'active_runtime_exists');
    }

    /**
     * Betreiber-Korrektur (CMS-8d): Runtime-Konfiguration kann nach dem
     * Publish wieder ungueltig werden (Template archiviert) -- start()
     * prueft das VOR dem RuntimeSessionService-Aufruf, kein ungefangener
     * 500er, keine Anfrage an services/sandbox.
     */
    public function test_starting_a_lab_with_an_archived_runtime_template_is_rejected_without_calling_the_runtime(): void
    {
        SandboxTemplate::factory()->create(['slug' => 'stale-template', 'status' => 'draft']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'stale-template', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake();

        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start')->assertRedirect();

        Http::assertNothingSent();
        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertNull($attempt->current_sandbox_session_id);
    }

    public function test_starting_a_lab_with_a_missing_dataset_is_rejected_without_calling_the_runtime(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'does-not-exist-anymore']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake();

        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start')->assertRedirect();

        Http::assertNothingSent();
    }

    public function test_runtime_state_reports_the_live_status_of_the_own_attempts_session(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'queued', 'sandbox_id' => 'sb-1', 'queue_position' => 3], 201),
            '*/v1/sandboxes/sb-1' => Http::response(['status' => 'running', 'queue_position' => null]),
        ]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        $this->actingAs($user)
            ->getJson('/de/labs/c-echo-connectivity/runtime')
            ->assertOk()
            ->assertExactJson(['status' => 'running', 'queue_position' => null]);
    }

    public function test_runtime_state_maps_a_gone_sandbox_to_a_404(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        Http::fake(['*/v1/sandboxes/sb-1' => Http::response(['detail' => 'not_found'], 404)]);

        $this->actingAs($user)
            ->getJson('/de/labs/c-echo-connectivity/runtime')
            ->assertStatus(404)
            ->assertJson(['error' => 'sandbox_not_found']);
    }

    /**
     * Ownership-Grundsatz (CMS-8d): keine Route nimmt eine sandbox_id vom
     * Client entgegen -- jeder Endpunkt loest immer den eigenen Attempt des
     * anfragenden Nutzers auf. Ein Nutzer ohne eigenen Attempt fuer dieses
     * Lab bekommt strukturell nie Zugriff auf die Sitzung eines anderen --
     * ueber KEINEN der drei Runtime-Endpunkte.
     */
    public function test_no_runtime_endpoint_ever_leaks_access_without_an_own_attempt(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);

        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);
        $this->actingAs($owner)->post('/de/labs/c-echo-connectivity/start');

        $this->actingAs($intruder)->getJson('/de/labs/c-echo-connectivity/runtime')->assertNotFound();
        $this->actingAs($intruder)->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu'])->assertNotFound();
        $this->actingAs($intruder)->deleteJson('/de/labs/c-echo-connectivity/runtime')->assertNotFound();

        // Der Besitzer selbst ist von alldem unberuehrt -- die Sitzung
        // gehoert weiterhin ihm, kein Seiteneffekt durch die Zugriffe des
        // Eindringlings.
        $this->assertNotNull(LabAttempt::where('user_id', $owner->id)->value('current_sandbox_session_id'));
    }

    public function test_exec_without_an_own_attempt_is_not_found(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu'])
            ->assertNotFound();
    }

    public function test_exec_without_an_active_runtime_returns_a_clear_error_not_a_500(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();
        LabAttempt::create(['user_id' => $user->id, 'activity_id' => $activity->id, 'status' => 'started', 'started_at' => now()]);

        $this->actingAs($user)
            ->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu'])
            ->assertStatus(409);
    }

    /**
     * `exec()` ist in diesem Commit ein duenner Proxy -- reicht die
     * Sandbox-Antwort unveraendert durch, wertet noch nichts aus (das
     * kommt im naechsten Progress-Commit).
     */
    public function test_exec_proxies_the_raw_sandbox_response(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201),
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => 'pong', 'stderr' => '', 'exit_code' => 0]),
        ]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        $this->actingAs($user)
            ->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu 127.0.0.1'])
            ->assertOk()
            ->assertExactJson(['stdout' => 'pong', 'stderr' => '', 'exit_code' => 0]);
    }

    public function test_exec_maps_a_not_ready_sandbox_to_a_409(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        Http::fake(['*/v1/sandboxes/sb-1/exec' => Http::response(['detail' => 'sandbox_not_ready'], 409)]);

        $this->actingAs($user)
            ->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu'])
            ->assertStatus(409)
            ->assertJson(['error' => 'sandbox_not_ready']);
    }

    public function test_destroy_runtime_ends_the_own_active_session(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201),
            '*/v1/sandboxes/sb-1' => Http::response([], 204),
        ]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        $this->actingAs($user)
            ->deleteJson('/de/labs/c-echo-connectivity/runtime')
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertNull($attempt->current_sandbox_session_id);
        $this->assertSame('destroyed', SandboxSession::where('lab_attempt_id', $attempt->id)->value('status'));
    }

    public function test_destroy_runtime_without_an_own_attempt_is_not_found(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->deleteJson('/de/labs/c-echo-connectivity/runtime')
            ->assertNotFound();
    }

    /**
     * Zweite besonders ernst genommene Garantie (Betreiber-Review): nach
     * einem Reap/Destroy muss ein `started`-Attempt sauber wieder startbar
     * sein -- DIESELBE Attempt-ID (kein neuer Lernfortschritt-Datensatz),
     * aber eine ECHTE NEUE SandboxSession (der konstante runtime_key
     * bedeutet Wiederverwendung nur, solange Python die Sitzung noch als
     * aktiv kennt -- nach destroy() ist das nicht mehr der Fall).
     */
    public function test_restarting_after_a_destroy_reuses_the_attempt_but_creates_a_new_session(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::sequence()
                ->push(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)
                ->push(['status' => 'running', 'sandbox_id' => 'sb-2'], 201),
            '*/v1/sandboxes/sb-1' => Http::response([], 204),
        ]);

        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');
        $attemptId = LabAttempt::where('user_id', $user->id)->value('id');
        $firstSessionId = LabAttempt::whereKey($attemptId)->value('current_sandbox_session_id');

        $this->actingAs($user)->deleteJson('/de/labs/c-echo-connectivity/runtime')->assertOk();
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start')->assertRedirect();

        $attempt = LabAttempt::findOrFail($attemptId);
        $this->assertSame($attemptId, $attempt->id);
        $this->assertNotNull($attempt->current_sandbox_session_id);
        $this->assertNotSame($firstSessionId, $attempt->current_sandbox_session_id);
        $this->assertSame(2, SandboxSession::where('lab_attempt_id', $attemptId)->count());
        $this->assertSame(
            'sb-2',
            SandboxSession::whereKey($attempt->current_sandbox_session_id)->value('runtime_instance_id'),
        );
    }
}

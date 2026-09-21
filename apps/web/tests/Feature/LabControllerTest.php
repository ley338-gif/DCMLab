<?php

namespace Tests\Feature;

use App\Content\LabAssertionEvaluator;
use App\Models\AchievementDefinition;
use App\Models\AchievementUnlock;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\Lesson;
use App\Models\LessonElement;
use App\Models\SandboxSession;
use App\Models\SandboxTemplate;
use App\Models\Track;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\ProfileService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * CMS-8a, Abschnitt H: eigenstaendige Learner-Route fuer ein Lab. CMS-8d
 * verdrahtet den Runtime-Lifecycle -- Start, Zustand, Exec, Beenden --
 * ausschliesslich ownership-sicher ueber den eigenen `LabAttempt` des
 * anfragenden Nutzers, nie ueber eine vom Client mitgegebene Sitzungs-ID.
 * Dieser Commit schliesst die Solve-Kette in `exec()` (Assertion-
 * Auswertung, atomarer Abschluss, Progress-/Profilpunkte) vollstaendig
 * ueber Backend-/Feature-Tests bewiesen -- `show()`s Runtime-/Assertion-
 * Anzeige und `Labs/Show.vue` bleiben bewusst einem eigenen, spaeteren
 * UI-Commit vorbehalten.
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
                ->where('runtime', null)
                ->where('assertions', [])
                ->where('runtime_error', null)
                ->where('next_step', ['type' => 'labs_index', 'lesson_id' => null, 'lesson_title' => null])
                ->where('briefing_html', fn (string $html) => str_contains($html, 'Pruefe die Verbindung.')),
            );

        $this->assertDatabaseCount('lab_attempts', 0);
    }

    /**
     * PR #148, Prioritaet 1: der Rueckweg wird ueber dieselbe Lesson-/
     * Track-Aufloesung wie `DashboardHomeService::labsOverview()` ermittelt
     * -- ein Lab, das ueber ein `LessonElement` an eine Lesson gehaengt
     * ist, bekommt `next_step.type === 'lesson'` mit deren Titel, unabhaengig
     * vom Attempt-Status (auch ohne Attempt schon berechnet, siehe
     * `LabController::show()`).
     */
    public function test_show_reports_the_owning_lesson_as_the_next_step(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.6', 'track_id' => $track->id, 'title' => ['de' => 'Erste Verbindung']]);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $activity->id, 'position' => 0]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertInertia(fn ($page) => $page
                ->where('next_step', ['type' => 'lesson', 'lesson_id' => '1.6', 'lesson_title' => 'Erste Verbindung']),
            );
    }

    /**
     * Ein Lab ohne Activity-Zeile (z. B. eine kaputte/veraltete Verknuepfung)
     * bekommt trotzdem den Katalog-Fallback statt eines fehlenden Props --
     * `show()` selbst 404et in diesem Fall zwar schon ueber `assertVisible()`
     * fuer ein Draft-Lab, aber `next_step` darf nie ein Prop-Fehler sein.
     */
    public function test_next_step_falls_back_to_the_labs_catalog_without_a_lesson(): void
    {
        Lab::factory()->create(['slug' => 'unattached-lab']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'unattached-lab']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/labs/unattached-lab')
            ->assertInertia(fn ($page) => $page
                ->where('next_step', ['type' => 'labs_index', 'lesson_id' => null, 'lesson_title' => null]),
            );
    }

    /**
     * Published Content Boundary Hardening: eine ueber `LessonElement`
     * verknuepfte Lesson, die selbst nicht (mehr) veroeffentlicht ist, darf
     * nach dem Loesen eines Labs weder ihren Titel noch ihre `lesson_id`
     * (und damit einen echten Link) preisgeben -- derselbe Katalog-
     * Fallback wie fuer ein Lab ganz ohne Lesson-Bezug.
     */
    public function test_next_step_falls_back_to_the_labs_catalog_when_the_owning_lesson_is_a_draft(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.6', 'track_id' => $track->id, 'status' => 'draft', 'title' => ['de' => 'Geheime Draft-Lektion']]);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $activity->id, 'position' => 0]);

        $user = User::factory()->create();
        LabAttempt::create(['user_id' => $user->id, 'activity_id' => $activity->id, 'status' => 'solved', 'started_at' => now(), 'completed_at' => now()]);

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertInertia(fn ($page) => $page
                ->where('next_step', ['type' => 'labs_index', 'lesson_id' => null, 'lesson_title' => null]),
            );
    }

    /**
     * Gegenprobe: ein zugewiesener Reviewer/Administrator sieht denselben
     * Draft-Rueckweg ueber `LessonPolicy::view()` weiterhin -- keine
     * Regression fuer die autorisierte Vorschau.
     */
    public function test_an_authorized_administrator_still_sees_the_draft_lessons_next_step(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.6', 'track_id' => $track->id, 'status' => 'draft', 'title' => ['de' => 'Geheime Draft-Lektion']]);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $activity->id, 'position' => 0]);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.6']);

        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get('/de/labs/c-echo-connectivity')
            ->assertInertia(fn ($page) => $page
                ->where('next_step', ['type' => 'lesson', 'lesson_id' => '1.6', 'lesson_title' => 'Geheime Draft-Lektion']),
            );
    }

    /**
     * Regressionsschutz: ein geloestes Lab mit einer weiterhin
     * veroeffentlichten Lesson zeigt unveraendert den echten Rueckweg,
     * unabhaengig vom Solve-Status (wie test_show_reports_the_owning_
     * lesson_as_the_next_step oben, hier zusaetzlich mit einem echten
     * geloesten Attempt).
     */
    public function test_solved_lab_with_a_published_lesson_keeps_the_existing_next_step(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.6', 'track_id' => $track->id, 'title' => ['de' => 'Erste Verbindung']]);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $activity->id, 'position' => 0]);

        $user = User::factory()->create();
        LabAttempt::create(['user_id' => $user->id, 'activity_id' => $activity->id, 'status' => 'solved', 'started_at' => now(), 'completed_at' => now()]);

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertInertia(fn ($page) => $page
                ->where('next_step', ['type' => 'lesson', 'lesson_id' => '1.6', 'lesson_title' => 'Erste Verbindung']),
            );
    }

    public function test_show_reports_the_live_runtime_status_and_assertion_checklist(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create([
            'slug' => 'c-echo-connectivity',
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'assertions' => [
                ['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1'],
                ['type' => 'command_executed', 'prefix' => 'storescu 127.0.0.1'],
            ],
        ]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'queued', 'sandbox_id' => 'sb-1', 'queue_position' => 2], 201),
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => 'ok', 'stderr' => '', 'exit_code' => 0]),
            '*/v1/sandboxes/sb-1/events' => Http::response([
                'exec' => [['command' => 'echoscu 127.0.0.1 4242', 'exit_code' => 0, 'timestamp' => 't1', 'stdout_preview' => '']],
                'orthanc' => ['new_instances' => []],
            ]),
        ]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');
        $this->actingAs($user)->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu 127.0.0.1 4242']);

        Http::fake(['*/v1/sandboxes/sb-1' => Http::response(['status' => 'running', 'queue_position' => null])]);

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('attempt.status', 'started')
                ->where('runtime', ['status' => 'running', 'queue_position' => null])
                ->where('assertions', [
                    ['index' => 0, 'type' => 'command_executed', 'passed' => true, 'label' => null, 'progress' => null],
                    ['index' => 1, 'type' => 'command_executed', 'passed' => false, 'label' => null, 'progress' => null],
                ]),
            );
    }

    /**
     * PR #148, "Runtime ended/expired": jede von `state()` beim Polling
     * entdeckte, nicht explizit vom Lernenden beendete Sitzung wird von
     * `RuntimeSessionService::reconcileGone()` als 'reaped' markiert -- im
     * heutigen System (nur CMS-8b's Idle-Timeout-Cleanup raeumt Sitzungen
     * ohne expliziten `destroy()`-Aufruf weg) ist das gleichbedeutend mit
     * "wegen Inaktivitaet beendet", ohne dass Laravel eine eigene
     * Persistenz oder Python eine neue Auskunft braucht.
     */
    public function test_show_reports_the_runtime_as_expired_once_it_was_reaped(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        Http::fake(['*/v1/sandboxes/sb-1' => Http::response(['detail' => 'not_found'], 404)]);

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('runtime', ['status' => 'expired', 'queue_position' => null]));

        $this->assertDatabaseHas('sandbox_sessions', ['runtime_instance_id' => 'sb-1', 'status' => 'reaped']);
    }

    /**
     * Ein vom Lernenden selbst ueber `destroyRuntime()`/`restartRuntime()`
     * explizit beendeter Attempt darf NIE als "expired" erscheinen --
     * `finishSession()` nullt `current_sandbox_session_id` im selben Schritt
     * wie das Setzen von `status='destroyed'`, show()s Live-Check wird fuer
     * diese Sitzung also gar nicht erst erneut aufgerufen (frueher
     * `$sandboxId === null`-Ausstieg in `liveRuntimeStatus()`).
     */
    public function test_show_reports_no_runtime_after_an_explicit_destroy(): void
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
        $this->actingAs($user)->deleteJson('/de/labs/c-echo-connectivity/runtime')->assertOk();

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('runtime', null));

        $this->assertDatabaseHas('sandbox_sessions', ['runtime_instance_id' => 'sb-1', 'status' => 'destroyed']);
    }

    /**
     * Betreiber-Korrektur: ein lokales Prop auf show() statt globalem
     * Flash-Sharing -- start()s Redirect ist der einzige Rueckkanal, die
     * Seite muss ihn nach dem naechsten Aufruf tatsaechlich zeigen.
     */
    public function test_show_surfaces_a_soft_runtime_error_as_a_local_prop(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['detail' => 'active_runtime_exists'], 409)]);

        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start')->assertRedirect();

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('runtime_error', 'active_runtime_exists'));
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
     * Checkliste "Exec erfuellt noch nichts": ein Befehl, der keine der
     * konfigurierten Assertions erfuellt, aendert nichts am Attempt-Status.
     */
    public function test_exec_that_satisfies_no_assertion_leaves_the_attempt_started(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create([
            'slug' => 'c-echo-connectivity',
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1']],
        ]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201),
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => '', 'stderr' => 'not found', 'exit_code' => 127]),
            '*/v1/sandboxes/sb-1/events' => Http::response([
                'exec' => [['command' => 'dcmdump foo.dcm', 'exit_code' => 0, 'timestamp' => 't1', 'stdout_preview' => '']],
                'orthanc' => ['new_instances' => []],
            ]),
        ]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        $this->actingAs($user)
            ->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'dcmdump foo.dcm'])
            ->assertOk()
            ->assertJson(['all_satisfied' => false, 'assertions' => [['index' => 0, 'type' => 'command_executed', 'passed' => false]]]);

        $this->assertSame('started', LabAttempt::where('user_id', $user->id)->value('status'));
        $this->assertSame(0, ActivityProgress::count());
    }

    /**
     * Checkliste "Exec erfuellt Assertion -> assertions_passed wird
     * geschrieben": zwei Assertions, nur die erste wird erfuellt -- der
     * Attempt bleibt `started` (noch nicht ALLE erfuellt), aber der
     * Fortschritt wird bereits persistiert.
     */
    public function test_exec_that_satisfies_one_of_several_assertions_persists_progress_without_solving(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create([
            'slug' => 'c-echo-connectivity',
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'assertions' => [
                ['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1'],
                ['type' => 'command_executed', 'prefix' => 'storescu 127.0.0.1'],
            ],
        ]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201),
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => 'ok', 'stderr' => '', 'exit_code' => 0]),
            '*/v1/sandboxes/sb-1/events' => Http::response([
                'exec' => [['command' => 'echoscu 127.0.0.1 4242', 'exit_code' => 0, 'timestamp' => 't1', 'stdout_preview' => '']],
                'orthanc' => ['new_instances' => []],
            ]),
        ]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        $response = $this->actingAs($user)
            ->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu 127.0.0.1 4242'])
            ->assertOk();

        $response->assertJson(['all_satisfied' => false]);
        $response->assertJson(['assertions' => [
            ['index' => 0, 'type' => 'command_executed', 'passed' => true],
            ['index' => 1, 'type' => 'command_executed', 'passed' => false],
        ]]);

        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('started', $attempt->status);
        $this->assertSame(['command_executed:echoscu 127.0.0.1'], $attempt->assertions_passed);
        $this->assertSame(0, ActivityProgress::count());
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

    /**
     * Der komplette CMS-8d-Weg: Runtime starten, einen Befehl ausfuehren,
     * der die letzte konfigurierte Assertion erfuellt, automatisch geloest
     * werden, denselben Progress-/Achievement-/Punkte-Mechanismus wie
     * Node/Exam auslösen -- und die Antwort darf weder den rohen Prefix
     * noch den internen `type:prefix`-Identifier verraten, nur Typ+Index+
     * erfuellt.
     */
    public function test_exec_that_satisfies_every_assertion_solves_the_attempt_and_records_progress(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create([
            'slug' => 'c-echo-connectivity',
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'points' => 20,
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1']],
        ]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        AchievementDefinition::create([
            'slug' => 'c-echo-badge', 'name' => 'C-ECHO', 'description' => 'Test',
            'image' => 'c-echo-badge.png', 'category' => 'test', 'points' => 0,
            'is_hidden' => false, 'sort_order' => 100,
            'unlock_when' => ['type' => 'activity_completed', 'activity_type' => 'lab', 'key' => 'c-echo-connectivity'],
        ]);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201),
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => 'ok', 'stderr' => '', 'exit_code' => 0]),
            '*/v1/sandboxes/sb-1/events' => Http::response([
                'exec' => [['command' => 'echoscu 127.0.0.1 4242 -aec ORTHANC', 'exit_code' => 0, 'timestamp' => 't1', 'stdout_preview' => '']],
                'orthanc' => ['new_instances' => []],
            ]),
        ]);

        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        $response = $this->actingAs($user)
            ->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu 127.0.0.1 4242 -aec ORTHANC'])
            ->assertOk();

        $response->assertJson(['all_satisfied' => true]);
        $response->assertJson(['assertions' => [['index' => 0, 'type' => 'command_executed', 'passed' => true]]]);
        $this->assertSame(['c-echo-badge'], array_column($response->json('unlocked_achievements'), 'slug'));
        // Betreiber-Vorgabe: weder der rohe Prefix noch der interne
        // "type:prefix"-Identifier duerfen jemals im Response-Body stehen.
        $this->assertStringNotContainsString('127.0.0.1', (string) $response->getContent());
        $this->assertStringNotContainsString('command_executed:', (string) $response->getContent());

        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('solved', $attempt->status);
        $this->assertNotNull($attempt->completed_at);

        $progress = ActivityProgress::where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($progress->completed);
        $this->assertSame(20, $progress->score);
        $this->assertSame(20, $progress->max_score);

        $this->assertSame(
            1,
            AchievementUnlock::query()->where('user_id', $user->id)->whereRelation('definition', 'slug', 'c-echo-badge')->count(),
        );

        $this->assertSame(20, (new ProfileService)->totalPoints($user));

        // Ein zweiter exec()-Aufruf nach dem Loesen wertet nicht erneut aus
        // und vergibt das Achievement nicht ein zweites Mal.
        $second = $this->actingAs($user)
            ->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu 127.0.0.1 4242 -aec ORTHANC'])
            ->assertOk();

        $this->assertSame([], $second->json('unlocked_achievements'));
        $this->assertSame(
            1,
            AchievementUnlock::query()->where('user_id', $user->id)->whereRelation('definition', 'slug', 'c-echo-badge')->count(),
        );
        $this->assertSame(1, ActivityProgress::where('user_id', $user->id)->count());
    }

    /**
     * PR #150 (Assertion-/Grading-Audit): derselbe Solve-/Progress-/
     * Achievement-Weg wie bei `command_executed`, aber die Assertion selbst
     * prueft nicht den ausgefuehrten Befehl, sondern Orthancs eigene
     * Serverwahrheit (`events()['orthanc']['new_instances']`) --
     * werkzeugunabhaengig, kein Sonderfall im Controller noetig.
     */
    public function test_exec_that_satisfies_a_dicom_instance_received_assertion_solves_the_attempt_and_records_progress_idempotently(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create([
            'slug' => 'c-store-verification',
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'points' => 15,
            'assertions' => [[
                'type' => 'dicom_instance_received',
                'patient_id' => '4711',
                'modality' => 'CT',
            ]],
        ]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-store-verification']);
        AchievementDefinition::create([
            'slug' => 'c-store-badge', 'name' => 'C-STORE', 'description' => 'Test',
            'image' => 'c-store-badge.png', 'category' => 'test', 'points' => 0,
            'is_hidden' => false, 'sort_order' => 100,
            'unlock_when' => ['type' => 'activity_completed', 'activity_type' => 'lab', 'key' => 'c-store-verification'],
        ]);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201),
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => 'ok', 'stderr' => '', 'exit_code' => 0]),
            '*/v1/sandboxes/sb-1/events' => Http::response([
                'exec' => [['command' => 'storescu 127.0.0.1 4242 -aec ORTHANC file.dcm', 'exit_code' => 0, 'timestamp' => 't1', 'stdout_preview' => '']],
                'orthanc' => ['new_instances' => [[
                    'instance_id' => 'inst-1',
                    'sop_class' => '1.2.840.10008.5.1.4.1.1.2',
                    'transfer_syntax' => '1.2.840.10008.1.2.1',
                    'patient_id' => '4711',
                    'study_instance_uid' => '1.2.3.4.5',
                    'modality' => 'CT',
                ]]],
            ]),
        ]);

        $this->actingAs($user)->post('/de/labs/c-store-verification/start');

        $response = $this->actingAs($user)
            ->postJson('/de/labs/c-store-verification/exec', ['command' => 'storescu 127.0.0.1 4242 -aec ORTHANC file.dcm'])
            ->assertOk();

        $response->assertJson(['all_satisfied' => true]);
        $response->assertJson(['assertions' => [['index' => 0, 'type' => 'dicom_instance_received', 'passed' => true]]]);
        $this->assertSame(['c-store-badge'], array_column($response->json('unlocked_achievements'), 'slug'));
        // Betreiber-Vorgabe: kein interner Identifier im Response-Body.
        $this->assertStringNotContainsString('dicom_instance_received:', (string) $response->getContent());

        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('solved', $attempt->status);
        $this->assertNotNull($attempt->completed_at);

        $progress = ActivityProgress::where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($progress->completed);
        $this->assertSame(15, $progress->score);
        $this->assertSame(15, $progress->max_score);

        $this->assertSame(
            1,
            AchievementUnlock::query()->where('user_id', $user->id)->whereRelation('definition', 'slug', 'c-store-badge')->count(),
        );
        $this->assertSame(15, (new ProfileService)->totalPoints($user));

        // Zweiter exec()-Aufruf nach dem Loesen: kein doppeltes Punkte-/
        // Achievement-Vergeben (dieselbe Idempotenz-Garantie wie bei
        // command_executed).
        $second = $this->actingAs($user)
            ->postJson('/de/labs/c-store-verification/exec', ['command' => 'storescu 127.0.0.1 4242 -aec ORTHANC file.dcm'])
            ->assertOk();

        $this->assertSame([], $second->json('unlocked_achievements'));
        $this->assertSame(
            1,
            AchievementUnlock::query()->where('user_id', $user->id)->whereRelation('definition', 'slug', 'c-store-badge')->count(),
        );
        $this->assertSame(1, ActivityProgress::where('user_id', $user->id)->count());
    }

    /**
     * PR #153 (Assertion-Label-/Progress-Audit): das eigentliche
     * Realitaets-Szenario -- ein `min_instances: 60`-Lab meldet ehrlichen
     * Fortschritt waehrend des Uebertragens und zeigt nach dem Loesen nur
     * noch Label+Haken, keine Zahl mehr.
     */
    public function test_exec_reports_progress_for_a_dicom_instance_received_assertion_until_it_is_satisfied(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create([
            'slug' => 'c-store-progress',
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'assertions' => [[
                'type' => 'dicom_instance_received',
                'patient_id' => '4711',
                'min_instances' => 60,
                'label' => 'Vollständige CT-Studie übertragen',
            ]],
        ]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-store-progress']);
        $user = User::factory()->create();

        $instances = fn (int $count) => array_map(
            fn (int $i) => ['instance_id' => "inst-{$i}", 'patient_id' => '4711'],
            range(1, $count),
        );

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);
        $this->actingAs($user)->post('/de/labs/c-store-progress/start');

        Http::fake([
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => '', 'stderr' => '', 'exit_code' => 0]),
            '*/v1/sandboxes/sb-1/events' => Http::sequence()
                ->push(['exec' => [], 'orthanc' => ['new_instances' => $instances(30)]])
                ->push(['exec' => [], 'orthanc' => ['new_instances' => $instances(59)]])
                ->push(['exec' => [], 'orthanc' => ['new_instances' => $instances(60)]]),
        ]);

        $atThirty = $this->actingAs($user)
            ->postJson('/de/labs/c-store-progress/exec', ['command' => 'storescu 127.0.0.1 4242 -aec ORTHANC ~/daten/ct-thorax-60/'])
            ->assertOk();
        $atThirty->assertJson(['all_satisfied' => false, 'assertions' => [[
            'index' => 0, 'passed' => false,
            'label' => 'Vollständige CT-Studie übertragen',
            'progress' => ['current' => 30, 'required' => 60, 'unit' => 'instances'],
        ]]]);

        $atFiftyNine = $this->actingAs($user)
            ->postJson('/de/labs/c-store-progress/exec', ['command' => 'true'])
            ->assertOk();
        $atFiftyNine->assertJson(['all_satisfied' => false, 'assertions' => [[
            'index' => 0, 'passed' => false,
            'progress' => ['current' => 59, 'required' => 60, 'unit' => 'instances'],
        ]]]);

        $solved = $this->actingAs($user)
            ->postJson('/de/labs/c-store-progress/exec', ['command' => 'true'])
            ->assertOk();
        $solved->assertJson(['all_satisfied' => true, 'assertions' => [[
            'index' => 0, 'passed' => true,
            'label' => 'Vollständige CT-Studie übertragen',
            'progress' => null,
        ]]]);
    }

    /**
     * Monotonie ueber einen Runtime-Neustart hinweg (Betreiber-Vorgabe):
     * eine bereits bestandene Assertion zeigt nach einem Neustart mit
     * leerer Sandbox weiterhin nur Haken+Label, NIE ein irrefuehrendes
     * "0 von 60".
     */
    public function test_show_never_reports_progress_for_an_already_passed_assertion(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create([
            'slug' => 'c-store-progress',
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'dicom_instance_received', 'patient_id' => '4711', 'min_instances' => 60, 'label' => 'Vollständige CT-Studie übertragen']],
        ]);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-store-progress']);
        $user = User::factory()->create();
        $attempt = LabAttempt::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'status' => 'solved',
            'assertions_passed' => [LabAssertionEvaluator::identifierFor(['type' => 'dicom_instance_received', 'patient_id' => '4711', 'min_instances' => 60])],
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        Http::fake(['*/v1/sandboxes/sb-1' => Http::response(['status' => 'running', 'queue_position' => null])]);
        SandboxSession::factory()->create([
            'user_id' => $user->id,
            'runtime_instance_id' => 'sb-1',
            'lab_attempt_id' => $attempt->id,
        ]);
        $attempt->update(['current_sandbox_session_id' => SandboxSession::where('lab_attempt_id', $attempt->id)->value('id')]);

        $this->actingAs($user)
            ->get('/de/labs/c-store-progress')
            ->assertInertia(fn ($page) => $page->where('assertions', [
                ['index' => 0, 'type' => 'dicom_instance_received', 'passed' => true, 'label' => 'Vollständige CT-Studie übertragen', 'progress' => null],
            ]));
    }

    /**
     * Checkliste "Runtime events() Fehler -> kein teilweise gespeicherter
     * Solve": events() wird bewusst AUSSERHALB der Transaktion aufgerufen
     * (siehe exec()-Klassendoc) -- ein Fehler dort darf die Transaktion
     * also gar nicht erst eroeffnen, kein halb geschriebener Attempt, kein
     * verwaister ActivityProgress-Datensatz.
     */
    public function test_a_gone_sandbox_during_events_leaves_no_partially_saved_solve(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create([
            'slug' => 'c-echo-connectivity',
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1']],
        ]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201),
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => 'ok', 'stderr' => '', 'exit_code' => 0]),
        ]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');
        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();

        // Die Sitzung verschwindet erst zwischen exec() (oben bereits
        // erfolgreich gefaked) und dem nachfolgenden events()-Aufruf.
        Http::fake(['*/v1/sandboxes/sb-1/events' => Http::response(['detail' => 'not_found'], 404)]);

        $this->actingAs($user)
            ->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu 127.0.0.1 4242 -aec ORTHANC'])
            ->assertStatus(404)
            ->assertJson(['error' => 'sandbox_not_found']);

        $attempt->refresh();
        $this->assertSame('started', $attempt->status);
        $this->assertSame([], $attempt->assertions_passed);
        $this->assertSame(0, ActivityProgress::count());
    }

    /**
     * Checkliste "zwei konkurrierende Solve-Pfade koennen nur einen
     * First-Solve erzeugen": `events()` ist ein externer Aufruf AUSSERHALB
     * der Transaktion (siehe exec()-Klassendoc) -- genau in diesem Fenster
     * simuliert dieser Test einen zweiten, "gleichzeitigen" Request, der
     * bereits fertig geloest hat, BEVOR dieser Request seine eigene
     * lockForUpdate()-Transaktion eroeffnet. Der `lockForUpdate()`-
     * Wiederholungscheck muss das erkennen und darf Progress/Achievement
     * kein zweites Mal buchen.
     */
    public function test_a_concurrent_solve_between_events_and_the_lock_is_not_recorded_twice(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create([
            'slug' => 'c-echo-connectivity',
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'points' => 20,
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1']],
        ]);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        AchievementDefinition::create([
            'slug' => 'c-echo-badge', 'name' => 'C-ECHO', 'description' => 'Test',
            'image' => 'c-echo-badge.png', 'category' => 'test', 'points' => 0,
            'is_hidden' => false, 'sort_order' => 100,
            'unlock_when' => ['type' => 'activity_completed', 'activity_type' => 'lab', 'key' => 'c-echo-connectivity'],
        ]);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201),
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => 'ok', 'stderr' => '', 'exit_code' => 0]),
        ]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');
        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();

        Http::fake(['*/v1/sandboxes/sb-1/events' => function () use ($attempt, $activity, $user) {
            // Simuliert einen zweiten Request, der zwischen DIESEM
            // events()-Aufruf (ausserhalb der Transaktion) und dessen
            // eigenem lockForUpdate() bereits vollstaendig fertig geloest
            // hat -- inklusive Progress und Achievement, exakt das, was
            // dessen eigene Transaktion+Nachlauf getan haetten.
            $attempt->forceFill([
                'status' => 'solved',
                'completed_at' => now(),
                'assertions_passed' => ['command_executed:echoscu 127.0.0.1'],
            ])->save();

            ActivityProgress::create([
                'user_id' => $user->id,
                'activity_id' => $activity->id,
                'completed' => true,
                'score' => 20,
                'max_score' => 20,
                'skills' => [],
                'completed_at' => now(),
            ]);

            (new AchievementService)->unlock($user, 'c-echo-badge', [], $activity);

            return Http::response([
                'exec' => [['command' => 'echoscu 127.0.0.1 4242 -aec ORTHANC', 'exit_code' => 0, 'timestamp' => 't1', 'stdout_preview' => '']],
                'orthanc' => ['new_instances' => []],
            ]);
        }]);

        $response = $this->actingAs($user)
            ->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu 127.0.0.1 4242 -aec ORTHANC'])
            ->assertOk();

        // Dieser Request sieht den Attempt bereits als geloest und bucht
        // deshalb selbst kein zweites Mal Progress/Achievement.
        $response->assertJson(['all_satisfied' => true, 'unlocked_achievements' => []]);
        $this->assertSame(1, ActivityProgress::where('user_id', $user->id)->count());
        $this->assertSame(
            1,
            AchievementUnlock::query()->where('user_id', $user->id)->whereRelation('definition', 'slug', 'c-echo-badge')->count(),
        );
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

    /**
     * Haerten (CMS-8d, letzter Commit): bis hierhin fingen liveRuntimeStatus()
     * und runtimeState() ausschliesslich RuntimeGoneException ab -- ein
     * Transport-Fehler oder ein von SandboxClient::mapKnownFailure() nicht
     * abgefangener 5xx liefen als ungefangene Exception bis zum Controller
     * durch und haetten das gesamte Lab-Briefing mit einem 500er zerstoert.
     */
    public function test_show_reports_sandbox_unavailable_on_a_connection_failure(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        Http::fake(['*/v1/sandboxes/sb-1' => fn () => throw new ConnectionException('refused')]);
        $this->mock(ExceptionHandler::class, fn ($mock) => $mock->shouldReceive('report')->once());

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('runtime.status', 'sandbox_unavailable')
                ->where('runtime.queue_position', null),
            );
    }

    public function test_show_reports_sandbox_unavailable_on_an_unmapped_server_error(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        Http::fake(['*/v1/sandboxes/sb-1' => Http::response(['detail' => 'internal'], 500)]);
        $this->mock(ExceptionHandler::class, fn ($mock) => $mock->shouldReceive('report')->once());

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('runtime.status', 'sandbox_unavailable'));
    }

    /**
     * RuntimeNotReadyException ist keine RequestException -- eigener,
     * getrennter Fangzweig, gemappt auf denselben "queued"-Zustand, den
     * state() normalerweise schon per 200 liefert. Kein report() hier,
     * das ist kein Fehlerfall.
     */
    public function test_show_reports_queued_when_state_unexpectedly_reports_not_ready(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        Http::fake(['*/v1/sandboxes/sb-1' => Http::response(['detail' => 'sandbox_not_ready'], 409)]);
        $this->mock(ExceptionHandler::class, fn ($mock) => $mock->shouldNotReceive('report'));

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('runtime.status', 'queued'));
    }

    public function test_runtime_state_reports_sandbox_unavailable_on_a_connection_failure(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        Http::fake(['*/v1/sandboxes/sb-1' => fn () => throw new ConnectionException('refused')]);
        $this->mock(ExceptionHandler::class, fn ($mock) => $mock->shouldReceive('report')->once());

        $this->actingAs($user)
            ->getJson('/de/labs/c-echo-connectivity/runtime')
            ->assertStatus(503)
            ->assertJson(['error' => 'sandbox_unavailable']);
    }

    public function test_exec_reports_sandbox_unavailable_on_a_connection_failure_during_exec(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        Http::fake(['*/v1/sandboxes/sb-1/exec' => fn () => throw new ConnectionException('refused')]);
        $this->mock(ExceptionHandler::class, fn ($mock) => $mock->shouldReceive('report')->once());

        $this->actingAs($user)
            ->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu'])
            ->assertStatus(503)
            ->assertJson(['error' => 'sandbox_unavailable']);

        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('started', $attempt->status);
        $this->assertSame([], $attempt->assertions_passed);
    }

    /**
     * Kein teilweise gespeicherter Solve: events() liegt ausserhalb der
     * Transaktion, ein Transport-Fehler dort darf die Transaktion also gar
     * nicht erst eroeffnen (dieselbe Garantie, die schon fuer den
     * RuntimeGoneException-Fall gilt, jetzt auch fuer diesen Fehlertyp).
     */
    public function test_exec_reports_sandbox_unavailable_on_a_connection_failure_during_events(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create([
            'slug' => 'c-echo-connectivity',
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1']],
        ]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201),
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => 'ok', 'stderr' => '', 'exit_code' => 0]),
        ]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        Http::fake(['*/v1/sandboxes/sb-1/events' => fn () => throw new ConnectionException('refused')]);
        $this->mock(ExceptionHandler::class, fn ($mock) => $mock->shouldReceive('report')->once());

        $this->actingAs($user)
            ->postJson('/de/labs/c-echo-connectivity/exec', ['command' => 'echoscu 127.0.0.1 4242 -aec ORTHANC'])
            ->assertStatus(503)
            ->assertJson(['error' => 'sandbox_unavailable']);

        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('started', $attempt->status);
        $this->assertSame([], $attempt->assertions_passed);
        $this->assertSame(0, ActivityProgress::count());
    }

    public function test_destroy_runtime_reports_sandbox_unavailable_on_a_connection_failure(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');
        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($attempt->current_sandbox_session_id);

        Http::fake(['*/v1/sandboxes/sb-1' => fn () => throw new ConnectionException('refused')]);
        $this->mock(ExceptionHandler::class, fn ($mock) => $mock->shouldReceive('report')->once());

        $this->actingAs($user)
            ->deleteJson('/de/labs/c-echo-connectivity/runtime')
            ->assertStatus(503)
            ->assertJson(['error' => 'sandbox_unavailable']);

        // Betreiber-Vorgabe: ein fehlgeschlagener Destroy-Versuch darf den
        // Komfortzeiger nicht faelschlich loeschen -- die Sitzung ist ja
        // moeglicherweise noch da, nur der Aufruf ist gescheitert.
        $this->assertNotNull($attempt->fresh()->current_sandbox_session_id);
    }

    public function test_starting_a_lab_reports_a_soft_error_on_a_connection_failure(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => fn () => throw new ConnectionException('refused')]);
        $this->mock(ExceptionHandler::class, fn ($mock) => $mock->shouldReceive('report')->once());

        $this->actingAs($user)
            ->post('/de/labs/c-echo-connectivity/start')
            ->assertRedirect()
            ->assertSessionHas('runtime_error', 'sandbox_unavailable');

        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('started', $attempt->status);
        $this->assertNull($attempt->current_sandbox_session_id);
    }
}

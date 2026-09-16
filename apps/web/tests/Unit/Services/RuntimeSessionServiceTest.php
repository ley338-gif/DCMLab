<?php

namespace Tests\Unit\Services;

use App\Models\Activity;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\SandboxSession;
use App\Models\SandboxTemplate;
use App\Models\User;
use App\Services\RuntimeGoneException;
use App\Services\RuntimeRequest;
use App\Services\RuntimeSessionOwnerMismatchException;
use App\Services\RuntimeSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * CMS-8b (Betreiber-Review): zentraler Aufrufer von RuntimeProviderRegistry
 * -- Template-/Provider-Aufloesung, SandboxSession-Persistenz, LabAttempt-
 * Verknuepfung und Gone/Reaped-Reconciliation an EINER Stelle, damit
 * SandboxController (heute) und ein kuenftiger Lab-Runtime-Start (CMS-8d)
 * dieselbe Logik teilen statt sie zu kopieren.
 */
class RuntimeSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_with_a_lab_attempt_links_the_session_and_updates_the_attempt(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $user = User::factory()->create();
        $lab = Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $attempt = LabAttempt::create([
            'user_id' => $user->id, 'activity_id' => $activity->id,
            'status' => 'started', 'started_at' => now(),
        ]);

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);

        $result = $this->service()->start(new RuntimeRequest(
            userId: (string) $user->id,
            datasetSlug: 'ct-thorax-60',
            templateSlug: 'dicom-basic-tools',
            runtimeKey: "lab-attempt:{$attempt->id}",
            activityId: $activity->id,
            labAttemptId: $attempt->id,
        ));

        $session = SandboxSession::query()->where('runtime_instance_id', 'sb-1')->first();
        $this->assertNotNull($session);
        $this->assertSame($attempt->id, $session->lab_attempt_id);
        $this->assertSame($session->id, $result['session_id']);
        $this->assertSame($session->id, $attempt->fresh()->current_sandbox_session_id);
    }

    public function test_start_without_a_lab_attempt_leaves_lab_attempt_id_null(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);

        $this->service()->start(new RuntimeRequest(
            userId: (string) $user->id,
            datasetSlug: 'ct-thorax-60',
            templateSlug: 'dicom-basic-tools',
            runtimeKey: 'sandbox:user:'.$user->id,
        ));

        $session = SandboxSession::query()->where('runtime_instance_id', 'sb-1')->first();
        $this->assertNull($session->lab_attempt_id);
    }

    public function test_start_rejects_an_unknown_or_unpublished_template_slug(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->start(new RuntimeRequest(
            userId: '1',
            datasetSlug: 'ct-thorax-60',
            templateSlug: 'nonexistent',
            runtimeKey: 'sandbox:user:1',
        ));
    }

    public function test_start_returns_the_conflict_error_and_writes_no_session(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        Http::fake(['*/v1/sandboxes' => Http::response(['detail' => 'active_runtime_exists'], 409)]);

        $result = $this->service()->start(new RuntimeRequest(
            userId: '1',
            datasetSlug: 'ct-thorax-60',
            templateSlug: 'dicom-basic-tools',
            runtimeKey: 'lab-attempt:999',
        ));

        $this->assertSame(['error' => 'active_runtime_exists'], $result);
        $this->assertSame(0, SandboxSession::query()->count());
    }

    public function test_start_reuses_the_existing_session_when_python_returns_the_same_runtime_instance_id(): void
    {
        // CMS-8b, Betreiber-Review (drittes Review): Python ist fuer
        // denselben runtime_key idempotent -- ein zweiter start()-Aufruf
        // (z. B. ein erneuter Klick) bekommt dieselbe sandbox_id zurueck.
        // Ohne Wiederverwendung wuerde das eine zweite SandboxSession-Zeile
        // fuer dieselbe Runtime anlegen.
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);

        $request = new RuntimeRequest(
            userId: (string) $user->id,
            datasetSlug: 'ct-thorax-60',
            templateSlug: 'dicom-basic-tools',
            runtimeKey: 'sandbox:user:'.$user->id,
        );

        $first = $this->service()->start($request);
        $second = $this->service()->start($request);

        $this->assertSame(1, SandboxSession::query()->count());
        $this->assertSame($first['session_id'], $second['session_id']);
    }

    public function test_start_throws_when_the_runtime_instance_id_belongs_to_a_different_owner(): void
    {
        // Zweite, Laravel-seitige Verteidigungslinie (Pythons runtime_key
        // ist bereits Owner-scoped, das hier sollte nie eintreten) -- eine
        // falsche Zuordnung waere schlimmer als ein harter Fehler.
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $owner = User::factory()->create();
        SandboxSession::factory()->create([
            'user_id' => $owner->id,
            'runtime_instance_id' => 'sb-1',
            'runtime_provider' => 'docker',
        ]);

        $otherUser = User::factory()->create();
        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);

        $this->expectException(RuntimeSessionOwnerMismatchException::class);

        $this->service()->start(new RuntimeRequest(
            userId: (string) $otherUser->id,
            datasetSlug: 'ct-thorax-60',
            templateSlug: 'dicom-basic-tools',
            runtimeKey: 'sandbox:user:'.$otherUser->id,
        ));
    }

    public function test_state_against_a_gone_session_reconciles_and_clears_the_attempts_current_session(): void
    {
        $user = User::factory()->create();
        $lab = Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $attempt = LabAttempt::create([
            'user_id' => $user->id, 'activity_id' => $activity->id,
            'status' => 'started', 'started_at' => now(),
        ]);
        $session = SandboxSession::factory()->create([
            'runtime_instance_id' => 'sb-1',
            'runtime_provider' => 'docker',
            'lab_attempt_id' => $attempt->id,
        ]);
        $attempt->update(['current_sandbox_session_id' => $session->id]);

        Http::fake(['*/v1/sandboxes/sb-1' => Http::response(['detail' => 'sandbox not found'], 404)]);

        $this->expectException(RuntimeGoneException::class);

        try {
            $this->service()->state('sb-1');
        } finally {
            $session->refresh();
            $this->assertSame('reaped', $session->status);
            $this->assertNotNull($session->finished_at);
            $this->assertNull($attempt->fresh()->current_sandbox_session_id);
        }
    }

    public function test_state_reconciliation_does_not_touch_a_different_current_session(): void
    {
        $user = User::factory()->create();
        $lab = Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $attempt = LabAttempt::create([
            'user_id' => $user->id, 'activity_id' => $activity->id,
            'status' => 'started', 'started_at' => now(),
        ]);
        $goneSession = SandboxSession::factory()->create([
            'runtime_instance_id' => 'sb-old', 'runtime_provider' => 'docker', 'lab_attempt_id' => $attempt->id,
        ]);
        $newerSession = SandboxSession::factory()->create([
            'runtime_instance_id' => 'sb-new', 'runtime_provider' => 'docker', 'lab_attempt_id' => $attempt->id,
        ]);
        $attempt->update(['current_sandbox_session_id' => $newerSession->id]);

        Http::fake(['*/v1/sandboxes/sb-old' => Http::response(['detail' => 'sandbox not found'], 404)]);

        try {
            $this->service()->state('sb-old');
        } catch (RuntimeGoneException) {
        }

        $this->assertSame($newerSession->id, $attempt->fresh()->current_sandbox_session_id);
    }

    private function service(): RuntimeSessionService
    {
        return $this->app->make(RuntimeSessionService::class);
    }
}

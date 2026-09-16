<?php

namespace Tests\Feature;

use App\Models\AchievementUnlock;
use App\Models\Lesson;
use App\Models\SandboxSession;
use App\Models\SandboxTemplate;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Spielwiese-Proxy (Abschnitt 10, P7): Laravel haelt keinen eigenen Zustand
 * ("kein Zustand ueber Sitzungen hinweg") -- diese Tests pruefen nur, dass
 * die Anfragen korrekt an den Orchestrator durchgereicht werden.
 */
class SandboxControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seit ADR 0096 (CMS-2) braucht create() eine freigegebene Vorlage.
        SandboxTemplate::factory()->published()->create(['runtime_provider' => 'docker']);
    }

    public function test_guests_cannot_start_a_sandbox(): void
    {
        $lesson = $this->lessonWithSandbox();

        $this->postJson("/de/lessons/{$lesson->lesson_id}/sandbox")->assertUnauthorized();
    }

    public function test_it_creates_a_sandbox_for_the_lessons_dataset(): void
    {
        $lesson = $this->lessonWithSandbox('ct-thorax-3-slices');
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1', 'queue_position' => null], 201),
        ]);

        $response = $this->actingAs($user)->postJson("/de/lessons/{$lesson->lesson_id}/sandbox");

        $response->assertCreated()->assertJson(['status' => 'running', 'sandbox_id' => 'sb-1']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/sandboxes')
            && $request['user_id'] === (string) $user->id
            && $request['dataset_slug'] === 'ct-thorax-3-slices'
            && $request['template_slug'] === SandboxTemplate::query()->value('slug')
            && $request['runtime_key'] === 'sandbox:user:'.$user->id);
    }

    public function test_it_creates_a_durable_sandbox_session_record_on_success(): void
    {
        $lesson = $this->lessonWithSandbox('ct-thorax-3-slices');
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1', 'queue_position' => null], 201),
        ]);

        $this->actingAs($user)->postJson("/de/lessons/{$lesson->lesson_id}/sandbox")->assertCreated();

        $session = SandboxSession::query()->where('runtime_instance_id', 'sb-1')->first();
        $this->assertNotNull($session);
        $this->assertSame($user->id, $session->user_id);
        $this->assertSame('docker', $session->runtime_provider);
        $this->assertSame('running', $session->status);
        $this->assertNotNull($session->started_at);
    }

    public function test_failed_sandbox_creation_does_not_create_a_session_record(): void
    {
        $lesson = $this->lessonWithSandbox();
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response([], 429)]);

        $this->actingAs($user)->postJson("/de/lessons/{$lesson->lesson_id}/sandbox");

        $this->assertSame(0, SandboxSession::query()->count());
    }

    public function test_exec_touches_the_sessions_last_activity_at_and_resolves_its_provider(): void
    {
        $user = User::factory()->create();
        $session = SandboxSession::factory()->create([
            'runtime_instance_id' => 'sb-1',
            'runtime_provider' => 'docker',
            'last_activity_at' => now()->subHour(),
        ]);

        Http::fake([
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => 'ok', 'stderr' => '', 'exit_code' => 0]),
        ]);

        $this->actingAs($user)->postJson('/de/sandbox/sb-1/exec', ['command' => 'echo hi'])->assertOk();

        $this->assertTrue($session->fresh()->last_activity_at->gt(now()->subMinute()));
    }

    public function test_destroy_marks_the_session_finished(): void
    {
        $user = User::factory()->create();
        $session = SandboxSession::factory()->create([
            'runtime_instance_id' => 'sb-1',
            'runtime_provider' => 'docker',
        ]);

        Http::fake(['*/v1/sandboxes/sb-1' => Http::response('', 204)]);

        $this->actingAs($user)->deleteJson('/de/sandbox/sb-1')->assertOk();

        $session->refresh();
        $this->assertSame('destroyed', $session->status);
        $this->assertNotNull($session->finished_at);
    }

    public function test_successful_sandbox_creation_unlocks_the_sandbox_starter_achievement(): void
    {
        $this->seed(AchievementSeeder::class);
        $lesson = $this->lessonWithSandbox();
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1', 'queue_position' => null], 201),
        ]);

        $response = $this->actingAs($user)->postJson("/de/lessons/{$lesson->lesson_id}/sandbox");

        $response->assertCreated();
        $this->assertSame(
            ['sandbox-starter'],
            array_column($response->json('unlocked_achievements'), 'slug'),
        );
        $this->assertSame(
            1,
            AchievementUnlock::query()
                ->where('user_id', $user->id)
                ->whereRelation('definition', 'slug', 'sandbox-starter')
                ->count(),
        );
    }

    public function test_failed_sandbox_creation_does_not_unlock_any_achievement(): void
    {
        $this->seed(AchievementSeeder::class);
        $lesson = $this->lessonWithSandbox();
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response([], 429)]);

        $this->actingAs($user)->postJson("/de/lessons/{$lesson->lesson_id}/sandbox")->assertStatus(429);

        $this->assertSame(0, AchievementUnlock::query()->where('user_id', $user->id)->count());
    }

    public function test_it_rejects_lessons_without_a_sandbox_dataset(): void
    {
        $lesson = Lesson::factory()->create(['sandbox' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/de/lessons/{$lesson->lesson_id}/sandbox")
            ->assertInvalid(['lesson']);
    }

    public function test_it_rejects_creation_when_no_sandbox_template_is_published(): void
    {
        SandboxTemplate::query()->delete();
        $lesson = $this->lessonWithSandbox();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/de/lessons/{$lesson->lesson_id}/sandbox")
            ->assertInvalid(['lesson']);
    }

    public function test_quota_exceeded_returns_429(): void
    {
        $lesson = $this->lessonWithSandbox();
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response([], 429)]);

        $this->actingAs($user)->postJson("/de/lessons/{$lesson->lesson_id}/sandbox")
            ->assertStatus(429)
            ->assertJson(['error' => 'quota_exceeded']);
    }

    /**
     * CMS-8b, Betreiber-Review: der neue runtime_key-Konflikt (ein anderer
     * Zweck haelt bereits die einzige erlaubte Sitzung des Nutzers) ist ein
     * eigener, vom Kontingent-429 unterscheidbarer Fehlerfall -- 409, keine
     * SandboxSession.
     */
    public function test_active_runtime_conflict_returns_409_and_creates_no_session(): void
    {
        $lesson = $this->lessonWithSandbox();
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['detail' => 'active_runtime_exists'], 409)]);

        $this->actingAs($user)->postJson("/de/lessons/{$lesson->lesson_id}/sandbox")
            ->assertStatus(409)
            ->assertJson(['error' => 'active_runtime_exists']);

        $this->assertSame(0, SandboxSession::query()->count());
    }

    public function test_exec_proxies_to_the_sandbox(): void
    {
        $user = User::factory()->create();

        Http::fake([
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => 'ok', 'stderr' => '', 'exit_code' => 0]),
        ]);

        $this->actingAs($user)->postJson('/de/sandbox/sb-1/exec', ['command' => 'echo hi'])
            ->assertOk()->assertJson(['stdout' => 'ok']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/sandboxes/sb-1/exec')
            && $request['command'] === 'echo hi');
    }

    /**
     * CMS-8b, Betreiber-Review: der 404-Fehlerpfad (Runtime-Seite kennt die
     * Sitzung nicht mehr, z. B. vom Idle-Cleanup-Loop weggeraeumt) muss
     * `sandbox_sessions.status` reconcilen, nicht nur eine 500/Exception
     * nach aussen durchreichen.
     */
    public function test_exec_against_a_gone_sandbox_marks_the_session_reaped_and_returns_404(): void
    {
        $user = User::factory()->create();
        $session = SandboxSession::factory()->create([
            'runtime_instance_id' => 'sb-1',
            'runtime_provider' => 'docker',
        ]);

        Http::fake(['*/v1/sandboxes/sb-1/exec' => Http::response(['detail' => 'sandbox not found'], 404)]);

        $this->actingAs($user)->postJson('/de/sandbox/sb-1/exec', ['command' => 'echo hi'])
            ->assertStatus(404);

        $session->refresh();
        $this->assertSame('reaped', $session->status);
        $this->assertNotNull($session->finished_at);
    }

    /**
     * CMS-8b, Betreiber-Review (drittes Review): eine wartende (queued)
     * Sitzung ist NICHT dasselbe wie eine weggeraeumte -- ein verfrueher
     * exec()-Aufruf darf sie nicht faelschlich als 'reaped' reconcilen.
     */
    public function test_exec_against_a_queued_sandbox_returns_409_without_reconciling(): void
    {
        $user = User::factory()->create();
        $session = SandboxSession::factory()->create([
            'runtime_instance_id' => 'sb-1',
            'runtime_provider' => 'docker',
            'status' => 'queued',
        ]);

        Http::fake(['*/v1/sandboxes/sb-1/exec' => Http::response(['detail' => 'sandbox_not_ready'], 409)]);

        $this->actingAs($user)->postJson('/de/sandbox/sb-1/exec', ['command' => 'echo hi'])
            ->assertStatus(409)
            ->assertJson(['error' => 'sandbox_not_ready']);

        $this->assertSame('queued', $session->fresh()->status);
    }

    /**
     * Der Command wird seit den Exec-Facts dauerhaft in Redis gespeichert
     * (CMS-8b, Betreiber-Review) -- die Laravel-Validierung muss dasselbe
     * Limit wie services/sandbox's Pydantic-Modell durchsetzen.
     */
    public function test_exec_rejects_a_command_over_the_length_limit(): void
    {
        $user = User::factory()->create();
        SandboxSession::factory()->create(['runtime_instance_id' => 'sb-1', 'runtime_provider' => 'docker']);

        $this->actingAs($user)
            ->postJson('/de/sandbox/sb-1/exec', ['command' => str_repeat('x', 4097)])
            ->assertInvalid(['command']);
    }

    public function test_destroy_proxies_to_the_sandbox(): void
    {
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes/sb-1' => Http::response('', 204)]);

        $this->actingAs($user)->deleteJson('/de/sandbox/sb-1')->assertOk()->assertJson(['ok' => true]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/sandboxes/sb-1')
            && $request->method() === 'DELETE');
    }

    private function lessonWithSandbox(string $dataset = 'test-set'): Lesson
    {
        $track = Track::factory()->create();

        return Lesson::factory()->create([
            'track_id' => $track->id,
            'sandbox' => ['required' => true, 'dataset' => $dataset],
        ]);
    }
}

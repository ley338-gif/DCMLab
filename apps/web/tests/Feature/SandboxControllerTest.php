<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Track;
use App\Models\User;
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
            && $request['dataset_slug'] === 'ct-thorax-3-slices');
    }

    public function test_it_rejects_lessons_without_a_sandbox_dataset(): void
    {
        $lesson = Lesson::factory()->create(['sandbox' => null]);
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

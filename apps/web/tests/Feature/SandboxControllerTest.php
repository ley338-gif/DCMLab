<?php

namespace Tests\Feature;

use App\Models\AchievementUnlock;
use App\Models\Lesson;
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
            && $request['dataset_slug'] === 'ct-thorax-3-slices');
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

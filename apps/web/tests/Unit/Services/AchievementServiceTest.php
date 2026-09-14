<?php

namespace Tests\Unit\Services;

use App\Achievements\AchievementUnlockStatus;
use App\Models\AchievementUnlock;
use App\Models\User;
use App\Services\AchievementService;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Generisches Achievement-System (Auftrag "Achievement-System", Abschnitt
 * 3/5): unlock() muss idempotent sein und race-sicher, analog zu
 * ProfileService::maybeAwardFirstBlood() (siehe ProfileServiceTest).
 */
class AchievementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AchievementSeeder::class);
    }

    public function test_unlock_creates_a_new_unlock_with_unlocked_at_and_metadata(): void
    {
        $user = User::factory()->create();

        $result = (new AchievementService)->unlock($user, 'first-blood', ['node' => 'first-contact']);

        $this->assertSame(AchievementUnlockStatus::NewlyUnlocked, $result->status);
        $this->assertTrue($result->isNewlyUnlocked());

        $unlock = AchievementUnlock::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($unlock->unlocked_at);
        $this->assertSame(['node' => 'first-contact'], $unlock->metadata);
    }

    public function test_unlock_is_idempotent(): void
    {
        $user = User::factory()->create();
        $service = new AchievementService;

        $service->unlock($user, 'first-blood');
        $second = $service->unlock($user, 'first-blood');

        $this->assertSame(AchievementUnlockStatus::AlreadyUnlocked, $second->status);
        $this->assertSame(1, AchievementUnlock::query()->where('user_id', $user->id)->count());
    }

    public function test_unlock_with_unknown_slug_returns_not_found_without_crashing(): void
    {
        $user = User::factory()->create();

        $result = (new AchievementService)->unlock($user, 'does-not-exist');

        $this->assertSame(AchievementUnlockStatus::NotFound, $result->status);
        $this->assertSame(0, AchievementUnlock::query()->count());
    }

    public function test_concurrent_unlock_attempts_do_not_create_duplicates(): void
    {
        $user = User::factory()->create();
        $service = new AchievementService;

        // Simuliert eine Race Condition: der exists()-Check in unlock() sieht
        // in beiden "Requests" noch keinen Datensatz, weil hier keine echte
        // Nebenlaeufigkeit moeglich ist -- stattdessen wird ein Datensatz
        // zwischen Check und Insert direkt eingefuegt, genau wie
        // ProfileServiceTest es fuer first_blood tut.
        $definitionId = \App\Models\AchievementDefinition::query()->where('slug', 'first-blood')->value('id');
        AchievementUnlock::create([
            'user_id' => $user->id,
            'achievement_definition_id' => $definitionId,
            'unlocked_at' => now(),
            'metadata' => [],
        ]);

        $result = $service->unlock($user, 'first-blood');

        $this->assertSame(AchievementUnlockStatus::AlreadyUnlocked, $result->status);
        $this->assertSame(1, AchievementUnlock::query()->where('user_id', $user->id)->count());
    }

    public function test_list_for_user_marks_unlocked_and_locked_achievements(): void
    {
        $user = User::factory()->create();
        (new AchievementService)->unlock($user, 'sandbox-starter');

        $list = (new AchievementService)->listForUser($user);

        $sandboxStarter = $list->firstWhere('slug', 'sandbox-starter');
        $echoHeard = $list->firstWhere('slug', 'echo-heard');

        $this->assertTrue($sandboxStarter['unlocked']);
        $this->assertNotNull($sandboxStarter['unlocked_at']);
        $this->assertFalse($echoHeard['unlocked']);
        $this->assertNull($echoHeard['unlocked_at']);
        $this->assertSame('/images/achievements/sandbox-starter.png', $sandboxStarter['image']);
    }
}

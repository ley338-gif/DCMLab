<?php

namespace Tests\Unit\Activities;

use App\Activities\ActivityContract;
use App\Activities\ActivityRegistry;
use App\Activities\ActivityResult;
use App\Activities\ActivitySupports;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR 0072, W0-DoD: "Ein kuenstlicher sechster Aktivitaetstyp laesst sich in
 * einem Test registrieren, anzeigen, abschliessen und bewerten, ohne dass
 * ProfileService, AchievementService, das Dashboard oder die Punkterechnung
 * angefasst wurden." Dieser Test ist der Nachweis: `QuestActivity` existiert
 * nur hier, nirgends in app/, und die Registry kennt sie nicht im Voraus.
 */
class ActivityRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_built_in_content_backed_types_are_registered(): void
    {
        $registry = $this->app->make(ActivityRegistry::class);

        $this->assertEqualsCanonicalizing(['lesson', 'node', 'exam', 'achievement', 'sandbox', 'quiz', 'lab'], $registry->registeredTypes());
    }

    public function test_a_sixth_activity_type_can_be_registered_displayed_completed_and_scored(): void
    {
        $registry = $this->app->make(ActivityRegistry::class);

        $registry->register('quest', fn (Activity $activity) => new QuestActivity($activity));

        $activity = Activity::factory()->create(['type' => 'quest', 'key' => 'first-quest']);
        $user = User::factory()->create();

        $resolved = $registry->resolve($activity);

        $this->assertInstanceOf(QuestActivity::class, $resolved);
        $this->assertSame('quest', $resolved->activityType());
        $this->assertSame('first-quest', $resolved->key());
        $this->assertTrue($resolved->supports()->isGraded);

        // Anzeigen.
        $view = $resolved->learnerView($user);
        $this->assertSame('not_started', $view['status']);

        // Vor dem Abschluss gibt es kein Ergebnis.
        $this->assertNull($resolved->result($user));

        // Abschliessen und bewerten -- rein ueber activity_progress, keine
        // Beruehrung von ProfileService/AchievementService/Dashboard.
        ActivityProgress::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'completed' => true,
            'score' => 7,
            'max_score' => 10,
            'skills' => ['security'],
            'completed_at' => now(),
        ]);

        $result = $resolved->result($user);
        $this->assertNotNull($result);
        $this->assertTrue($result->completed);
        $this->assertSame(7, $result->score);
        $this->assertSame(10, $result->maxScore);
        $this->assertSame(['security'], $result->skills);
    }

    public function test_resolving_an_unregistered_type_fails_clearly(): void
    {
        $registry = new ActivityRegistry;
        $activity = Activity::factory()->make(['type' => 'unbekannt']);

        $this->expectExceptionMessage('Kein Aktivitaetstyp registriert fuer "unbekannt".');

        $registry->resolve($activity);
    }
}

/**
 * Testdouble fuer den W0-DoD-Nachweis oben: ein voellig neuer Aktivitaetstyp,
 * dessen Ergebnis ausschliesslich aus `activity_progress` gelesen wird.
 */
final readonly class QuestActivity implements ActivityContract
{
    public function __construct(private Activity $activity) {}

    public function activityType(): string
    {
        return 'quest';
    }

    public function key(): string
    {
        return $this->activity->key;
    }

    public function supports(): ActivitySupports
    {
        return new ActivitySupports(
            isGraded: true,
            tracksCompletion: true,
            needsContainer: false,
            authorable: false,
            freelyPlaceable: true,
        );
    }

    public function learnerView(User $user): array
    {
        $progress = $this->activity->progress()->where('user_id', $user->id)->first();

        return [
            'type' => 'quest',
            'key' => $this->activity->key,
            'status' => $progress?->completed ? 'completed' : 'not_started',
        ];
    }

    public function authorView(): array
    {
        return [];
    }

    public function validate(?array $draft = null): array
    {
        return [];
    }

    public function serialize(?array $draft = null): array
    {
        return [];
    }

    public function deserialize(): array
    {
        return ['key' => $this->activity->key];
    }

    public function result(User $user): ?ActivityResult
    {
        $progress = $this->activity->progress()->where('user_id', $user->id)->first();

        if ($progress === null) {
            return null;
        }

        return new ActivityResult(
            completed: $progress->completed,
            score: $progress->score,
            maxScore: $progress->max_score,
            skills: $progress->skills,
            completedAt: $progress->completed_at,
        );
    }
}

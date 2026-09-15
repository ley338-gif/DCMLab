<?php

namespace Tests\Unit\Activities;

use App\Activities\SandboxActivity;
use App\Models\Lesson;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spielwiese haengt an ihrer Lektion und hat deshalb keinen eigenen
 * `activities`-Verzeichniseintrag -- siehe SandboxActivity Klassendoc.
 */
class SandboxActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_supports_declares_a_container_without_grading_or_completion(): void
    {
        $activity = $this->makeActivity();

        $supports = $activity->supports();

        $this->assertFalse($supports->isGraded);
        $this->assertFalse($supports->tracksCompletion);
        $this->assertTrue($supports->needsContainer);
        $this->assertFalse($supports->freelyPlaceable);
        $this->assertSame('container', $supports->runtimeType);
        // Kein eigenes Dateiziel -- die Spielwiesen-Konfiguration haengt am
        // content_versions-Datensatz der Lektion, siehe SandboxActivity::supports().
        $this->assertFalse($supports->versionable);
    }

    public function test_learner_view_exposes_the_lessons_dataset(): void
    {
        $activity = $this->makeActivity();
        $user = User::factory()->create();

        $view = $activity->learnerView($user);

        $this->assertSame('sandbox', $view['type']);
        $this->assertSame('ct-thorax-60', $view['dataset']);
    }

    public function test_result_is_always_null_since_laravel_holds_no_sandbox_state(): void
    {
        $activity = $this->makeActivity();
        $user = User::factory()->create();

        $this->assertNull($activity->result($user));
    }

    private function makeActivity(): SandboxActivity
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create([
            'track_id' => $track->id,
            'sandbox' => ['required' => true, 'dataset' => 'ct-thorax-60'],
        ]);

        return new SandboxActivity($lesson);
    }
}

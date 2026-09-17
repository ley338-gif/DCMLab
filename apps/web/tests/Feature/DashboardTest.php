<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Track;
use App\Models\User;
use App\Services\AchievementService;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    /**
     * P10.65: drei Pruefungszustaende je Track auf dem Dashboard --
     * bestanden, verfuegbar (alle Lektionen fertig), noch nicht verfuegbar
     * -- ueber ExamAttemptService::statusForTracks(), derselben Stelle wie
     * TrackController.
     */
    public function test_dashboard_shows_the_three_exam_states_per_track(): void
    {
        $contentDir = storage_path('framework/testing/dashboard-content-'.Str::random(12));
        foreach (['passed', 'available'] as $slug) {
            File::ensureDirectoryExists($contentDir.'/exams/'.$slug);
            File::put($contentDir.'/exams/'.$slug.'/exam.yml', "track: {$slug}\ntitle_key: exam.{$slug}.title\npass_percent: 50\ndraw: 1\nquestions: []\n");
            File::put($contentDir.'/exams/'.$slug.'/de.md', '');
        }

        $user = User::factory()->create();

        $passedTrack = Track::factory()->create(['slug' => 'passed', 'order' => 1, 'title_key' => 'track.passed.title']);
        $lessonDone = Lesson::factory()->create(['track_id' => $passedTrack->id, 'lesson_id' => 'p.1']);
        LessonProgress::create(['user_id' => $user->id, 'lesson_id' => $lessonDone->id, 'status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);
        ExamAttempt::create([
            'user_id' => $user->id,
            'track_id' => $passedTrack->id,
            'status' => 'completed',
            'question_ids' => ['f01'],
            'current_index' => 1,
            'answers' => ['f01' => ['submitted' => 0, 'correct' => true]],
            'score_correct' => 1,
            'score_total' => 1,
            'passed' => true,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);

        $availableTrack = Track::factory()->create(['slug' => 'available', 'order' => 2]);
        $availableLesson = Lesson::factory()->create(['track_id' => $availableTrack->id, 'lesson_id' => 'a.1']);
        LessonProgress::create(['user_id' => $user->id, 'lesson_id' => $availableLesson->id, 'status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);

        $notYetTrack = Track::factory()->create(['slug' => 'not-yet', 'order' => 3]);
        Lesson::factory()->create(['track_id' => $notYetTrack->id, 'lesson_id' => 'n.1']);

        $this->app->instance(ContentRepository::class, new ContentRepository($contentDir));

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tracks.0.exam.passed', true)
            ->where('tracks.0.exam.passed_at', now()->toDateString())
            ->where('tracks.1.exam.passed', false)
            ->where('tracks.1.exam.available', true)
            ->where('tracks.2.exam.passed', false)
            ->where('tracks.2.exam.available', false),
        );

        File::deleteDirectory($contentDir);
    }

    public function test_dashboard_exposes_the_achievement_registry_with_the_users_unlock_state(): void
    {
        $this->seed(AchievementSeeder::class);
        $user = User::factory()->create();
        (new AchievementService)->unlock($user, 'sandbox-starter');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('achievements', 14)
            ->where('achievements', fn ($achievements) => collect($achievements)
                ->firstWhere('slug', 'sandbox-starter')['unlocked'] === true
                && collect($achievements)->firstWhere('slug', 'echo-heard')['unlocked'] === false
            ),
        );
    }
}

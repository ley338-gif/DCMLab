<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\ExamAttempt;
use App\Models\Lab;
use App\Models\Lesson;
use App\Models\LessonElement;
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

    /**
     * Published Content Boundary Hardening (Dashboard "Weiterlernen" und
     * Aktivitaetsverlauf): $recentProgress war bisher unabhaengig vom
     * Status der zugehoerigen Lesson -- ein Lernender mit dem zuletzt
     * beruehrten Fortschritt auf einer inzwischen unveroeffentlichten
     * Lesson haette hier deren echten Titel samt aktivem Link gesehen, und
     * "Weiterlernen" haette auf dieselbe, nun gesperrte Lektion verwiesen.
     */
    public function test_recent_lessons_and_continue_learning_exclude_a_lesson_that_is_now_a_draft(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0', 'title' => ['de' => 'Geheime Draft-Lektion']]);
        $user = User::factory()->create();
        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $lesson->id,
            'status' => 'started', 'started_at' => now(),
        ]);
        $lesson->update(['status' => 'draft']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('recent_lessons', 0)
            ->where('continue_learning', null),
        );
    }

    /**
     * Published Content Boundary Hardening (Track-Fortschrittszaehler):
     * eine Draft-Lesson darf weder den Nenner (lessons_count) noch den
     * Zaehler (completed_lessons_count) der Dashboard-Trackliste
     * beeinflussen.
     */
    public function test_dashboard_track_progress_counters_ignore_draft_lessons(): void
    {
        $track = Track::factory()->create(['status' => 'published']);
        $done = Lesson::factory()->create(['track_id' => $track->id, 'order' => 0]);
        Lesson::factory()->create(['track_id' => $track->id, 'order' => 1, 'status' => 'draft']);

        $user = User::factory()->create();
        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $done->id,
            'status' => 'completed', 'started_at' => now(), 'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('tracks.0.lessons_count', 1)
            ->where('tracks.0.completed_lessons_count', 1),
        );
    }

    /**
     * Dieselbe Regel fuer DashboardHomeService::continueLearning() -- die
     * "Lektion X von Y"-Anzeige der Weiterlernen-Karte darf eine
     * zusaetzliche Draft-Lesson desselben Tracks nicht mitzaehlen.
     */
    public function test_continue_learning_lessons_count_ignores_draft_lessons(): void
    {
        $track = Track::factory()->create(['status' => 'published']);
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'order' => 0, 'lesson_id' => '1.0']);
        Lesson::factory()->create(['track_id' => $track->id, 'order' => 1, 'lesson_id' => '1.1', 'status' => 'draft']);

        $user = User::factory()->create();
        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $lesson->id,
            'status' => 'started', 'started_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('continue_learning.lesson_id', '1.0')
            ->where('continue_learning.lessons_count', 1),
        );
    }

    /**
     * Baseline fuer DashboardHomeService::recommendedNext() Regel 1 (kein
     * bestehender Test deckte diese Regel bislang ab): eine abgeschlossene,
     * weiterhin veroeffentlichte Lesson mit einem noch ungeloesten,
     * verknuepften Lab loest die Lab-Empfehlung wie vorgesehen aus.
     */
    public function test_a_completed_lessons_unsolved_lab_is_recommended(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0']);
        Lab::factory()->create(['slug' => 'echo-lab']);
        $labActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'echo-lab']);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $labActivity->id, 'position' => 0]);

        $user = User::factory()->create();
        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $lesson->id,
            'status' => 'completed', 'started_at' => now()->subDay(), 'completed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('recommended.type', 'lab')
            ->where('recommended.slug', 'echo-lab'),
        );
    }

    /**
     * Published Content Boundary Hardening: historischer Fortschritt auf
     * einer Lesson, die WAEHREND ihrer Veroeffentlichung abgeschlossen und
     * seither wieder auf Draft gesetzt wurde, darf keine Lab-Empfehlung
     * mehr ausloesen -- Gegenprobe zum Test oben, sonst identisches Setup.
     * `labsOverview()` setzt die Verknuepfung fuer diesen Nutzer bereits auf
     * `lesson_id: null`, wodurch Regel 1 den Kandidaten gar nicht erst
     * sieht; da der Track dadurch auch keine veroeffentlichte Lesson mehr
     * hat, greift auch keine der beiden anderen Empfehlungsregeln.
     */
    public function test_historical_progress_on_a_lesson_reverted_to_draft_no_longer_recommends_its_lab(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '1.0']);
        Lab::factory()->create(['slug' => 'echo-lab']);
        $labActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'echo-lab']);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $labActivity->id, 'position' => 0]);

        $user = User::factory()->create();
        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $lesson->id,
            'status' => 'completed', 'started_at' => now()->subDay(), 'completed_at' => now()->subDay(),
        ]);
        $lesson->update(['status' => 'draft']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('recommended', null));
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

<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\AchievementUnlock;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\Lesson;
use App\Models\LessonElement;
use App\Models\LessonProgress;
use App\Models\Node;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Lektionsansicht (Abschnitt 10, P3): gerenderte Werkzeugleiste, NEU-
 * Ableitung, Fortschritt.
 */
class LessonControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->contentDir = storage_path('framework/testing/lesson-content-'.uniqid());
        $this->buildContentFixture();
        $this->app->instance(ContentRepository::class, new ContentRepository($this->contentDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $track = Track::factory()->create(['order' => 1]);
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'order' => 0]);

        $this->get('/de/lessons/1.0')->assertRedirect('/de/login');
    }

    /**
     * Seit ADR 0119 (Lesson-Sichtbarkeit gehaertet, analog ADR 0110 fuer
     * Node): eine noch nicht freigegebene Lektion ist fuer eine normale
     * Lernende gesperrt -- vorher war show() ueberhaupt nicht gegen den
     * Status geprueft, ein Entwurf war also sofort fuer jeden angemeldeten
     * Nutzer per Direktlink sichtbar.
     */
    public function test_a_learner_cannot_open_a_lesson_that_is_not_yet_published(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'status' => 'draft']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/de/lessons/1.0')->assertNotFound();
    }

    /**
     * "Vorschau" (LessonEditorController::preview()) ist keine zweite
     * Route -- ein Autor/Reviewer, der die zugehoerige Activity bearbeiten
     * darf, sieht denselben Lerner-Renderer trotzdem, auch bevor die
     * Lektion freigegeben ist.
     */
    public function test_an_assigned_author_can_preview_a_lesson_that_is_not_yet_published(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'status' => 'draft', 'body' => 'Prosa-Inhalt der Lektion.']);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);

        $this->actingAs($author)->get('/de/lessons/1.0')->assertOk();
    }

    public function test_an_unassigned_author_cannot_preview_a_lesson_that_is_not_yet_published(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $author = User::factory()->author()->create();

        $this->actingAs($author)->get('/de/lessons/1.0')->assertNotFound();
    }

    public function test_a_reviewer_can_preview_a_lesson_that_is_not_yet_published(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'status' => 'review', 'body' => 'Prosa-Inhalt der Lektion.']);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)->get('/de/lessons/1.0')->assertOk();
    }

    public function test_an_authorized_previewer_sees_the_draft_preview_banner_prop(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'status' => 'draft', 'body' => 'Prosa-Inhalt der Lektion.']);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get('/de/lessons/1.0')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('draft_preview', true));
    }

    public function test_a_published_lesson_never_sets_the_draft_preview_prop(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'body' => 'Prosa-Inhalt der Lektion.']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/de/lessons/1.0')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('draft_preview', false));
    }

    /**
     * Der eigentliche Sicherheits-Fix (ADR 0110/0119-Haertung uebertragen
     * auf Lesson): vor diesem PR pruefte nur `show()` den Draft-Status --
     * complete()/reopen() hingen an keinerlei Autorisierung, ein normaler
     * Lernender mit bekannter Draft-Lesson-Id konnte hier direkt einen
     * echten `LessonProgress`-Datensatz anlegen und `ActivityProgressRecorder`
     * ausloesen, ganz ohne vorherigen autorisierten Besuch von `show()`.
     */
    public function test_a_learner_cannot_use_any_lesson_sub_endpoint_for_a_lesson_that_is_not_yet_published(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'status' => 'draft', 'body' => 'Prosa-Inhalt der Lektion.']);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $learner = User::factory()->create();

        $this->actingAs($learner)->postJson('/de/lessons/1.0/complete')->assertNotFound();
        $this->actingAs($learner)->postJson('/de/lessons/1.0/reopen')->assertNotFound();

        $this->assertSame(0, LessonProgress::query()->count());
        $this->assertSame(0, ActivityProgress::query()->count());
    }

    public function test_guests_are_redirected_to_login_for_a_draft_lessons_sub_endpoints(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);

        $this->postJson('/de/lessons/1.0/complete')->assertUnauthorized();
    }

    /**
     * Abschnitt "Fortschritts-Isolation": ein in der Draft-Vorschau
     * "abgeschlossener" Lesson-Besuch darf keinerlei regulaeren
     * Lernfortschritt erzeugen -- kein `LessonProgress` (weder aus dem
     * blossen Ansehen via `trackProgress`, noch aus `complete()`), kein
     * `ActivityProgress`, kein Achievement.
     */
    public function test_a_correctly_completed_draft_preview_creates_no_persistent_progress(): void
    {
        $this->seed(AchievementSeeder::class);
        $track = Track::factory()->create();
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'status' => 'draft', 'body' => 'Prosa-Inhalt der Lektion.']);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $admin = User::factory()->administrator()->create();

        // Erst ansehen (trackProgress-Zweig), dann "erledigt" markieren --
        // beides darf keine Zeile in lesson_progress anlegen.
        $this->actingAs($admin)->get('/de/lessons/1.0')->assertOk();
        $this->actingAs($admin)->postJson('/de/lessons/1.0/complete')->assertRedirect();

        $this->assertSame(0, LessonProgress::query()->count());
        $this->assertSame(0, ActivityProgress::query()->count());
        $this->assertSame(0, AchievementUnlock::query()->count());
    }

    public function test_repeated_draft_preview_visits_and_completions_still_create_no_progress(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'status' => 'draft', 'body' => 'Prosa-Inhalt der Lektion.']);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get('/de/lessons/1.0')->assertOk();
        $this->actingAs($admin)->postJson('/de/lessons/1.0/complete')->assertRedirect();
        $this->actingAs($admin)->get('/de/lessons/1.0')->assertOk();
        $this->actingAs($admin)->postJson('/de/lessons/1.0/reopen')->assertRedirect();
        $this->actingAs($admin)->postJson('/de/lessons/1.0/complete')->assertRedirect();

        $this->assertSame(0, LessonProgress::query()->count());
        $this->assertSame(0, ActivityProgress::query()->count());
    }

    /**
     * Lesson hat keine erratbare Session-/Attempt-ID wie Node -- die
     * relevante Cross-State-Frage ist stattdessen: haengt die Autorisierung
     * WIRKLICH nur am aktuellen Status + der Rolle, unabhaengig von
     * bereits bestehendem, aus einer frueheren Veroeffentlichungsphase
     * stammendem echten Fortschritt desselben Nutzers? Ein Lernender mit
     * echtem, historischem LessonProgress darf nicht ploetzlich wieder
     * Zugriff bekommen, nur weil er die Lektion vorher schon gesehen hatte.
     */
    public function test_a_learner_with_prior_progress_is_still_blocked_once_the_lesson_becomes_a_draft(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'body' => 'Prosa-Inhalt der Lektion.']);
        $learner = User::factory()->create();
        LessonProgress::create([
            'user_id' => $learner->id, 'lesson_id' => $lesson->id,
            'status' => 'completed', 'started_at' => now()->subDay(), 'completed_at' => now()->subDay(),
        ]);

        $lesson->update(['status' => 'draft']);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);

        $this->actingAs($learner)->get('/de/lessons/1.0')->assertNotFound();
        $this->actingAs($learner)->postJson('/de/lessons/1.0/reopen')->assertNotFound();

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $learner->id, 'lesson_id' => $lesson->id, 'status' => 'completed',
        ]);
    }

    public function test_it_renders_the_lesson_with_toolbar_and_marks_new_tools(): void
    {
        $track = Track::factory()->create(['order' => 1]);

        $earlier = Lesson::factory()->create([
            'lesson_id' => '1.0',
            'track_id' => $track->id,
            'order' => 0,
            'tools' => ['dcmdump'],
        ]);

        $lesson = Lesson::factory()->create([
            'lesson_id' => '1.1',
            'track_id' => $track->id,
            'order' => 1,
            'tools' => ['dcmftest', 'dcmdump'],
            'requires' => ['1.0'],
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/de/lessons/1.1');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Lessons/Show')
            ->where('lesson.lesson_id', '1.1')
            ->where('toolbar.tools.0.slug', 'dcmftest')
            ->where('toolbar.tools.0.is_new', true)
            ->where('toolbar.tools.1.slug', 'dcmdump')
            ->where('toolbar.tools.1.is_new', false)
            ->where('toolbar.requires.0.lesson_id', '1.0')
            ->where('progress.status', 'started')
            ->where('progress.is_returning_visit', false),
        );
    }

    public function test_it_shows_the_related_node_when_it_exists(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create([
            'lesson_id' => '1.5',
            'track_id' => $track->id,
            'order' => 0,
            'related_node' => ['node' => 'silent-ct', 'optional' => false],
        ]);
        Node::factory()->create(['slug' => 'silent-ct', 'title' => ['de' => 'Silent CT']]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/lessons/1.5')
            ->assertInertia(fn ($page) => $page
                ->has('elements', 2)
                ->where('elements.0.type', 'content')
                ->where('elements.1.type', 'related_node')
                ->where('elements.1.related_node.slug', 'silent-ct')
                ->where('elements.1.related_node.title', 'Silent CT'),
            );
    }

    public function test_it_omits_the_related_node_when_it_does_not_exist_yet(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create([
            'lesson_id' => '1.1',
            'track_id' => $track->id,
            'order' => 0,
            'related_node' => ['node' => 'first-contact', 'optional' => false],
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/lessons/1.1')
            ->assertInertia(fn ($page) => $page
                ->has('elements', 1)
                ->where('elements.0.type', 'content'),
            );
    }

    /**
     * CMS-7d.3 (ADR 0118): eine Lektion mit befuelltem rich_content wird
     * ueber RichContentRenderer gerendert, nicht mehr ueber body/
     * MarkdownRenderer -- der eigentliche Read-Cutover.
     */
    public function test_it_prefers_rich_content_over_the_legacy_body_when_present(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create([
            'lesson_id' => '1.1',
            'track_id' => $track->id,
            'order' => 0,
            'body' => "## Veraltet\n\nDieser Markdown-Text darf NICHT gerendert werden.",
            'rich_content' => [
                'type' => 'doc', 'version' => 1,
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Aus rich_content gerendert.']]],
                ],
            ],
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/lessons/1.1')
            ->assertInertia(fn ($page) => $page
                ->where('elements.0.type', 'content')
                ->where('elements.0.body_html', fn (string $html) => str_contains($html, 'Aus rich_content gerendert.')
                    && ! str_contains($html, 'Veraltet')),
            );
    }

    /**
     * CMS-7d.4 (Betreiber-Review): der 404-Existenzcheck durfte nicht
     * mehr allein von `body !== null` abhaengen -- eine reine
     * Rich-Content-Lesson (`body` zufaellig `null`) muss trotzdem
     * sichtbar bleiben, und die Quiz-Aufteilung darf dabei nicht an
     * einem `null`-body abstuerzen.
     */
    public function test_it_is_visible_when_rich_content_is_set_but_body_is_null(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create([
            'lesson_id' => '1.1',
            'track_id' => $track->id,
            'order' => 0,
            'body' => null,
            'rich_content' => [
                'type' => 'doc', 'version' => 1,
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Nur Rich Content, kein body.']]],
                ],
            ],
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/lessons/1.1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('elements.0.type', 'content')
                ->where('elements.0.body_html', fn (string $html) => str_contains($html, 'Nur Rich Content, kein body.')),
            );
    }

    /**
     * CMS-7d.4 Phase 3: der Markdown-Fallback (keine rich_content
     * gesetzt) loggt ein messbares Signal -- Ziel ist, dass dieser
     * Log-Eintrag im produktiven Bestand nie feuert
     * (`rich-content:coverage`).
     */
    public function test_falling_back_to_markdown_logs_a_warning(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create([
            'lesson_id' => '1.1',
            'track_id' => $track->id,
            'order' => 0,
            'body' => 'Nur Markdown, kein rich_content.',
            'rich_content' => null,
        ]);

        $user = User::factory()->create();
        Log::spy();

        $this->actingAs($user)->get('/de/lessons/1.1')->assertOk();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context) => $message === 'learner_view.legacy_body_fallback' && $context['lesson_id'] === '1.1')
            ->once();
    }

    /**
     * ADR 0101 (CMS-5a): body/title/teaser/objectives kommen bevorzugt aus
     * der DB, ContentRepository ist nur noch Fallback.
     */
    public function test_it_prefers_the_db_body_title_teaser_and_objectives_over_the_file(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create([
            'lesson_id' => '1.1',
            'track_id' => $track->id,
            'order' => 0,
            'title' => ['de' => 'DB-Titel'],
            'teaser' => ['de' => 'DB-Teaser'],
            'objectives' => ['DB-Lernziel'],
            'body' => "## Aus der DB\n\nDieser Text kommt aus der Datenbank, nicht aus der Datei.",
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/lessons/1.1')
            ->assertInertia(fn ($page) => $page
                ->where('lesson.title', 'DB-Titel')
                ->where('lesson.teaser', 'DB-Teaser')
                ->where('lesson.objectives', ['DB-Lernziel'])
                ->where('elements.0.type', 'content')
                ->where('elements.0.body_html', fn (string $html) => str_contains($html, 'kommt aus der Datenbank')),
            );
    }

    public function test_it_falls_back_to_the_file_when_the_db_body_and_objectives_are_null(): void
    {
        // title/teaser sind in der DB seit ADR 0071 NOT NULL (immer via
        // content:sync gesetzt) -- nur body/objectives sind nullable und
        // haben deshalb einen echten Datei-Fallback zu testen.
        $track = Track::factory()->create();
        Lesson::factory()->create([
            'lesson_id' => '1.1',
            'track_id' => $track->id,
            'order' => 0,
            'objectives' => null,
            'body' => null,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/lessons/1.1')
            ->assertInertia(fn ($page) => $page
                ->where('lesson.objectives', ['Testen'])
                ->where('elements.0.type', 'content')
                ->where('elements.0.body_html', fn (string $html) => str_contains($html, 'ist wichtig')),
            );
    }

    /**
     * ADR 0105 (CMS-6b), Betreiber-Abnahmekriterium: die gespeicherte
     * Reihenfolge in `lesson_elements` bestimmt die tatsaechlich
     * gerenderte Reihenfolge auf der Lern-Seite -- nicht mehr eine fest
     * verdrahtete Body/Sandbox/Lab/Quiz-Abfolge.
     */
    public function test_reordering_lesson_elements_in_the_db_changes_the_rendered_order(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create([
            'lesson_id' => '1.9',
            'track_id' => $track->id,
            'tools' => ['echoscu'], // needs_sandbox: true im echten Bestand
            'sandbox' => ['required' => true, 'dataset' => 'ct-head-01', 'note' => null],
            'related_node' => ['node' => 'silent-ct', 'optional' => false],
            'body' => "Prosa-Inhalt der Lektion.\n\n## Quiz\n\n**q1 — Frage?**\n1. A\n2. B",
            'quiz' => [['id' => 'q1', 'type' => 'single', 'answer' => 0]],
        ]);
        Node::factory()->create(['slug' => 'silent-ct', 'title' => ['de' => 'Silent CT']]);

        $sandboxActivity = Activity::factory()->create(['type' => 'sandbox', 'key' => '1.9']);
        $labActivity = Activity::factory()->create(['type' => 'node', 'key' => 'silent-ct']);
        $quizActivity = Activity::factory()->create(['type' => 'quiz', 'key' => '1.9']);

        // Bewusst NICHT in der kanonischen Reihenfolge (Content/Sandbox/
        // Lab/Quiz), sondern Quiz zuerst, dann Sandbox, dann Lab, dann
        // Content zuletzt -- der Test darf diese Reihenfolge exakt so auf
        // der gerenderten Seite wiederfinden.
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $quizActivity->id, 'position' => 0]);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $sandboxActivity->id, 'position' => 1]);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $labActivity->id, 'position' => 2]);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'content', 'activity_id' => null, 'position' => 3]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/lessons/1.9')
            ->assertInertia(fn ($page) => $page
                ->has('elements', 4)
                ->where('elements.0.type', 'quiz')
                ->where('elements.1.type', 'sandbox')
                ->where('elements.2.type', 'related_node')
                ->where('elements.3.type', 'content'),
            );

        // Jetzt umsortieren: Content zuerst, Quiz zuletzt.
        LessonElement::where('lesson_id', $lesson->id)->where('type', 'content')->update(['position' => 0]);
        LessonElement::where('activity_id', $sandboxActivity->id)->update(['position' => 1]);
        LessonElement::where('activity_id', $labActivity->id)->update(['position' => 2]);
        LessonElement::where('activity_id', $quizActivity->id)->update(['position' => 3]);

        $this->actingAs($user)
            ->get('/de/lessons/1.9')
            ->assertInertia(fn ($page) => $page
                ->where('elements.0.type', 'content')
                ->where('elements.1.type', 'sandbox')
                ->where('elements.2.type', 'related_node')
                ->where('elements.3.type', 'quiz'),
            );
    }

    /**
     * CMS-8a, Abschnitt H: innerhalb einer Lesson zeigt ein Lab-Element nur
     * eine schlanke Launch-/Status-Karte -- "offen" ohne Attempt, "solved"
     * sobald ein LabAttempt fuer den Nutzer geloest ist.
     */
    public function test_it_shows_the_lab_card_open_without_an_attempt(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create([
            'lesson_id' => '1.6', 'track_id' => $track->id, 'body' => 'Prosa-Inhalt der Lektion.',
        ]);
        Lab::factory()->create(['slug' => 'c-echo-lab', 'title' => ['de' => 'C-ECHO Lab'], 'estimated_minutes' => 10]);
        $labActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-lab']);

        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'content', 'activity_id' => null, 'position' => 0]);
        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $labActivity->id, 'position' => 1]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/lessons/1.6')
            ->assertInertia(fn ($page) => $page
                ->has('elements', 2)
                ->where('elements.1.type', 'lab')
                ->where('elements.1.lab.slug', 'c-echo-lab')
                ->where('elements.1.lab.title', 'C-ECHO Lab')
                ->where('elements.1.lab.estimated_minutes', 10)
                ->where('elements.1.lab.status', 'not_started'),
            );
    }

    public function test_it_shows_the_lab_card_as_solved_once_the_attempt_is_solved(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create([
            'lesson_id' => '1.7', 'track_id' => $track->id, 'body' => 'Prosa-Inhalt der Lektion.',
        ]);
        Lab::factory()->create(['slug' => 'c-echo-lab']);
        $labActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-lab']);

        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $labActivity->id, 'position' => 0]);

        $user = User::factory()->create();
        LabAttempt::create([
            'user_id' => $user->id,
            'activity_id' => $labActivity->id,
            'status' => 'solved',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/de/lessons/1.7')
            ->assertInertia(fn ($page) => $page
                ->where('elements.0.type', 'lab')
                ->where('elements.0.lab.status', 'solved'),
            );
    }

    /**
     * Betreiber-Review (zweite Runde): ein Draft/archiviertes Lab darf einem
     * normalen Lernenden nicht weiterhin als aktive Karte (Titel/Dauer)
     * angezeigt werden -- der Klick wuerde ohnehin nur 404 liefern
     * (LabController schuetzt den direkten Aufruf bereits). Das Element
     * bleibt im Ergebnis (kein stilles Verschwinden), aber `lab` ist null.
     */
    public function test_it_hides_a_draft_labs_card_from_a_learner(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create([
            'lesson_id' => '1.8', 'track_id' => $track->id, 'body' => 'Prosa-Inhalt der Lektion.',
        ]);
        Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        $labActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);

        LessonElement::create(['lesson_id' => $lesson->id, 'type' => 'activity', 'activity_id' => $labActivity->id, 'position' => 0]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/lessons/1.8')
            ->assertInertia(fn ($page) => $page
                ->where('elements.0.type', 'lab')
                ->where('elements.0.lab', null),
            );
    }

    public function test_visiting_creates_progress_and_second_visit_is_detected(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.1', 'track_id' => $track->id, 'order' => 0]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/de/lessons/1.1');

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'status' => 'started',
        ]);

        $this->actingAs($user)
            ->get('/de/lessons/1.1')
            ->assertInertia(fn ($page) => $page->where('progress.is_returning_visit', true));

        $this->assertSame(1, LessonProgress::where('user_id', $user->id)->count());
    }

    public function test_marking_a_lesson_complete_and_reopening_it_persists(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.1', 'track_id' => $track->id, 'order' => 0]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/de/lessons/1.1');
        $this->actingAs($user)->post('/de/lessons/1.1/complete')->assertRedirect();

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
        ]);

        // Fortschritt ist an den Nutzer gebunden, nicht an die Session --
        // eine neue "Sitzung" desselben Nutzers sieht denselben Stand.
        $this->flushSession();
        $this->actingAs($user)
            ->get('/de/lessons/1.1')
            ->assertInertia(fn ($page) => $page->where('progress.status', 'completed'));

        $this->actingAs($user)->post('/de/lessons/1.1/reopen')->assertRedirect();

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'status' => 'started',
            'completed_at' => null,
        ]);
    }

    private function buildContentFixture(): void
    {
        $meta = <<<'YAML'
        id: "1.1"
        track: fundamente
        order: 1
        duration_minutes: 8
        level: einsteiger
        objectives_count: 1
        requires: []
        tools: [dcmftest, dcmdump]
        status: draft
        updated: "2026-09-12"
        YAML;

        $markdown = <<<'MD'
        ---
        title: Testlektion
        teaser: Eine Testlektion.
        objectives:
          - Testen
        ---

        ## Intro

        {{term:dicom}} ist wichtig.

        ```
        $ dcmftest datei.dcm
        yes: datei.dcm
        ```

        **Was du daran abliest:** Test.
        MD;

        File::ensureDirectoryExists($this->contentDir.'/lessons/1.1');
        File::ensureDirectoryExists($this->contentDir.'/lessons/1.5');
        File::ensureDirectoryExists($this->contentDir.'/tools');
        File::ensureDirectoryExists($this->contentDir.'/glossary');
        File::put($this->contentDir.'/lessons/1.1/meta.yml', $meta);
        File::put($this->contentDir.'/lessons/1.1/de.md', $markdown);
        File::put($this->contentDir.'/lessons/1.5/meta.yml', str_replace('"1.1"', '"1.5"', $meta));
        File::put($this->contentDir.'/lessons/1.5/de.md', $markdown);

        File::put($this->contentDir.'/tools/de.yml', <<<'YAML'
        dcmftest:
          name: dcmftest
          suite: dcmtk
          kind: datei
          purpose: Prüft, ob eine Datei überhaupt dem DICOM-Dateiformat entspricht.
          example: "dcmftest datei.dcm"
          needs_sandbox: false
        dcmdump:
          name: dcmdump
          suite: dcmtk
          kind: datei
          purpose: Kippt den Inhalt aus.
          example: "dcmdump datei.dcm"
          needs_sandbox: false
        YAML
        );

        File::put($this->contentDir.'/glossary/de.yml', <<<'YAML'
        dicom:
          term: DICOM
          expansion: Digital Imaging and Communications in Medicine
          short: Testbegriff.
          see_also: []
        YAML
        );
    }
}

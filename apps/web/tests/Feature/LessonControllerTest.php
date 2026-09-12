<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Node;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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

    public function test_it_shows_the_lab_node_when_it_exists(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create([
            'lesson_id' => '1.5',
            'track_id' => $track->id,
            'order' => 0,
            'lab' => ['node' => 'silent-ct', 'optional' => false],
        ]);
        Node::factory()->create(['slug' => 'silent-ct', 'title' => ['de' => 'Silent CT']]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/lessons/1.5')
            ->assertInertia(fn ($page) => $page
                ->where('toolbar.lab_node.slug', 'silent-ct')
                ->where('toolbar.lab_node.title', 'Silent CT'),
            );
    }

    public function test_it_omits_the_lab_node_when_it_does_not_exist_yet(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create([
            'lesson_id' => '1.1',
            'track_id' => $track->id,
            'order' => 0,
            'lab' => ['node' => 'first-contact', 'optional' => false],
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/lessons/1.1')
            ->assertInertia(fn ($page) => $page->where('toolbar.lab_node', null));
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

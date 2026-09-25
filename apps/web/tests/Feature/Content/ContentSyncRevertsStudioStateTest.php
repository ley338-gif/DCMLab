<?php

namespace Tests\Feature\Content;

use App\Activities\ActivityRegistry;
use App\Content\ContentPublishingService;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * ADR 0122, Phase 0 (Audit): belegt, dass `content:sync` einen in Studio
 * veroeffentlichten DB-Stand stillschweigend mit dem Stand aus `content/`
 * ueberschreibt -- fuer jede Studio-bearbeitbare Spalte ausser
 * `rich_content`, `track_id`/`order` (Lesson) und `title`/`teaser` (Track).
 *
 * CHARAKTERISIERUNGSTEST: die Assertions nach dem zweiten Sync beschreiben
 * das HEUTIGE, unerwuenschte Verhalten ("Studio-Stand ist weg"), damit
 * `main` gruen bleibt. Phase 3 (Sync-Schutzregel) dreht genau diese
 * Assertions um -- jede Stelle ist mit "PHASE 3: umdrehen" markiert.
 *
 * Laeuft gegen den echten `content/`-Bestand (nur lesend, wie die
 * uebrigen `ContentSyncTest`-Faelle) und ausschliesslich in der Test-DB
 * (phpunit.xml: SQLite `:memory:`, ADR 0069).
 */
class ContentSyncRevertsStudioStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(ContentRepository::class, new ContentRepository(base_path('../../content')));
        $this->assertSame(0, Artisan::call('content:sync'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publishViaStudio(Activity $activity, array $payload): ContentVersion
    {
        $versions = app(ContentVersioningService::class);
        $draft = $versions->createDraft($activity, $payload, User::factory()->author()->create());
        $versions->submitForReview($draft);

        $issues = app(ContentPublishingService::class)->publish($draft->fresh(), User::factory()->reviewer()->create());
        $this->assertSame([], $issues, 'Studio-Publish muss gueltig sein, sonst beweist der Test nichts.');

        return $draft->fresh();
    }

    public function test_sync_reverts_studio_published_lesson_metadata_but_keeps_rich_content(): void
    {
        $activity = Activity::query()->where('type', 'lesson')->where('key', '1.5')->firstOrFail();
        $payload = app(ActivityRegistry::class)->resolve($activity)->deserialize();
        $fileLesson = Lesson::where('lesson_id', '1.5')->firstOrFail();

        $payload['title'] = 'Studio-Titel';
        $payload['teaser'] = 'Studio-Teaser';
        $payload['level'] = 'fortgeschritten';
        $payload['duration_minutes'] = 42;
        $payload['tools'] = [...$payload['tools'], 'dcmdump'];
        $payload['objectives'] = ['Studio-Lernziel A', 'Studio-Lernziel B'];

        $this->publishViaStudio($activity, $payload);

        $published = Lesson::where('lesson_id', '1.5')->firstOrFail();
        $this->assertSame('Studio-Titel', $published->title['de']);
        $this->assertSame(['Studio-Lernziel A', 'Studio-Lernziel B'], $published->objectives);
        $this->assertSame(2, $published->objectives_count);
        $this->assertSame(42, $published->duration_minutes);
        $this->assertNotNull($published->rich_content);
        // Lesson.status ist NICHT Teil des Studio-Payloads -- ein Publish
        // laesst ihn unveraendert (Befund Phase 0, Punkt 2).
        $this->assertSame($fileLesson->status, $published->status);

        $richContentAfterPublish = $published->rich_content;

        Artisan::call('content:sync');

        $afterSync = Lesson::where('lesson_id', '1.5')->firstOrFail();

        // PHASE 3: umdrehen -- heute gewinnt die Datei.
        $this->assertSame($fileLesson->title, $afterSync->title);
        $this->assertSame($fileLesson->teaser, $afterSync->teaser);
        $this->assertSame($fileLesson->level, $afterSync->level);
        $this->assertSame($fileLesson->duration_minutes, $afterSync->duration_minutes);
        $this->assertSame($fileLesson->tools, $afterSync->tools);
        $this->assertSame($fileLesson->objectives, $afterSync->objectives);
        $this->assertSame($fileLesson->objectives_count, $afterSync->objectives_count);
        $this->assertSame($fileLesson->title, Activity::query()->where('type', 'lesson')->where('key', '1.5')->value('title'));

        // Geschuetzt schon heute: rich_content schreibt content:sync nie.
        $this->assertSame($richContentAfterPublish, $afterSync->rich_content);

        // Die Versionshistorie behauptet danach weiterhin den Studio-Stand
        // -- is_current zeigt auf eine Fassung, die nicht mehr live ist.
        $current = ContentVersion::query()->where('activity_id', $activity->id)->where('is_current', true)->firstOrFail();
        $this->assertSame('Studio-Titel', $current->payload['title']);
    }

    public function test_sync_reverts_a_studio_published_quiz(): void
    {
        $activity = Activity::query()->where('type', 'lesson')->where('key', '1.1')->firstOrFail();
        $fileLesson = Lesson::where('lesson_id', '1.1')->firstOrFail();
        $this->assertCount(3, $fileLesson->quiz);

        $this->publishViaStudio($activity, ['quiz' => [[
            'id' => 'q1',
            'type' => 'single',
            'answer' => 0,
            'question' => 'Studio-Frage?',
            'options' => ['Studio-Antwort', 'Falsch'],
        ]]]);

        $published = Lesson::where('lesson_id', '1.1')->firstOrFail();
        $this->assertCount(1, $published->quiz);
        $this->assertStringContainsString('Studio-Frage?', (string) $published->body);

        Artisan::call('content:sync');

        $afterSync = Lesson::where('lesson_id', '1.1')->firstOrFail();

        // PHASE 3: umdrehen -- quiz (Metadaten) UND body (Fragetext) kommen
        // wieder aus meta.yml/de.md.
        $this->assertSame($fileLesson->quiz, $afterSync->quiz);
        $this->assertStringNotContainsString('Studio-Frage?', (string) $afterSync->body);
    }

    public function test_sync_reverts_a_studio_published_node_including_its_status(): void
    {
        // `gefiltert` steht in node.yml auf `status: draft`.
        $activity = Activity::query()->where('type', 'node')->where('key', 'gefiltert')->firstOrFail();
        $fileNode = Node::where('slug', 'gefiltert')->firstOrFail();
        $this->assertSame('draft', $fileNode->status);

        $payload = app(ActivityRegistry::class)->resolve($activity)->deserialize();
        $payload['title'] = 'Studio-Node-Titel';
        $payload['points'] = $fileNode->points + 5;
        $payload['estimated_minutes'] = $fileNode->estimated_minutes + 7;

        $this->publishViaStudio($activity, $payload);

        $published = Node::where('slug', 'gefiltert')->firstOrFail();
        $this->assertSame('published', $published->status);
        $this->assertSame('Studio-Node-Titel', $published->title['de']);
        $richContentAfterPublish = $published->rich_content;

        Artisan::call('content:sync');

        $afterSync = Node::where('slug', 'gefiltert')->firstOrFail();

        // PHASE 3: umdrehen -- die veroeffentlichte Node faellt auf `draft`
        // zurueck und verschwindet damit fuer Lernende.
        $this->assertSame('draft', $afterSync->status);
        $this->assertSame($fileNode->title, $afterSync->title);
        $this->assertSame($fileNode->points, $afterSync->points);
        $this->assertSame($fileNode->estimated_minutes, $afterSync->estimated_minutes);
        $this->assertSame('draft', Activity::query()->where('type', 'node')->where('key', 'gefiltert')->value('status'));

        $this->assertSame($richContentAfterPublish, $afterSync->rich_content);
    }

    public function test_sync_reverts_a_studio_archived_node(): void
    {
        Node::where('slug', 'silent-ct')->firstOrFail()->update(['status' => 'archived']);

        Artisan::call('content:sync');

        // PHASE 3: umdrehen -- StudioNodeController::archive() wird
        // stillschweigend zurueckgenommen.
        $this->assertSame('published', Node::where('slug', 'silent-ct')->value('status'));
    }

    public function test_sync_reverts_studio_track_settings_except_title_and_teaser(): void
    {
        $track = Track::where('slug', 'fundamente')->firstOrFail();
        $file = $track->only(['level', 'hours', 'order', 'status']);

        // Dieselben Spalten, die StudioTrackController::update()/unpublish()
        // setzt -- ohne HTTP-Umweg, es geht nur um das Sync-Verhalten.
        $track->update([
            'title' => ['de' => 'Studio-Tracktitel'],
            'level' => 'fortgeschritten',
            'hours' => 99,
            'order' => 42,
            'status' => 'draft',
        ]);

        Artisan::call('content:sync');

        $afterSync = Track::where('slug', 'fundamente')->firstOrFail();

        // PHASE 3: umdrehen.
        $this->assertSame($file, $afterSync->only(['level', 'hours', 'order', 'status']));
        // Schon heute geschuetzt (syncTracks() schreibt title/teaser nie).
        $this->assertSame('Studio-Tracktitel', $afterSync->title['de']);
    }
}

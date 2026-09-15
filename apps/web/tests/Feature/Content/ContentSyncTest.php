<?php

namespace Tests\Feature\Content;

use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ContentSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_indexes_tracks_lessons_and_nodes(): void
    {
        $dir = base_path('tests/Fixtures/content-real');

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        $exitCode = Artisan::call('content:sync');

        $this->assertSame(0, $exitCode);

        $track = Track::where('slug', 'fundamente')->first();
        $this->assertNotNull($track);

        $lesson15 = Lesson::where('lesson_id', '1.5')->first();
        $this->assertNotNull($lesson15);
        $this->assertSame($track->id, $lesson15->track_id);
        $this->assertSame('SCU und SCP — Rollen, nicht Geräte', $lesson15->title['de']);
        $this->assertSame(['1.1'], $lesson15->requires);

        $lesson11 = Lesson::where('lesson_id', '1.1')->first();
        $this->assertNotNull($lesson11);
    }

    /**
     * ADR 0101 (CMS-5a): body/objectives werden aus derselben Datei
     * gefuellt, die auch title/teaser liefert.
     */
    public function test_it_fills_lesson_body_and_objectives_from_the_same_file(): void
    {
        $dir = base_path('tests/Fixtures/content-real');
        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        $lesson15 = Lesson::where('lesson_id', '1.5')->first();
        $this->assertNotNull($lesson15->body);
        $this->assertStringContainsString('Der Techniker des Modalitätenherstellers', $lesson15->body);
        $this->assertSame(
            [
                'SCU und SCP als Rollen pro Dienst begreifen, nicht als Geräteeigenschaft',
                'Called und Calling AE Title korrekt zuordnen',
                'Die Rollenumkehr bei C-MOVE erklären',
                'Einschätzen, was ein erfolgreiches C-ECHO beweist — und was nicht',
            ],
            $lesson15->objectives,
        );
    }

    public function test_it_also_registers_an_activity_entry_per_lesson(): void
    {
        $dir = base_path('tests/Fixtures/content-real');
        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        $track = Track::where('slug', 'fundamente')->first();
        $lesson15 = Lesson::where('lesson_id', '1.5')->first();

        $activity = Activity::query()->where('type', 'lesson')->where('key', '1.5')->first();
        $this->assertNotNull($activity);
        $this->assertSame($track->id, $activity->track_id);
        $this->assertSame($lesson15->status, $activity->status);
        $this->assertSame($lesson15->title, $activity->title);
        $this->assertSame($lesson15->source_hash, $activity->source_hash);
    }

    public function test_it_registers_a_sandbox_activity_entry_for_a_lesson_with_a_sandbox(): void
    {
        $dir = base_path('tests/Fixtures/content-real');
        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        // Alle drei Fixture-Lektionen haben eine Spielwiese (sandbox.dataset
        // gesetzt) -- siehe tests/Fixtures/content-real/lessons/*/meta.yml.
        $sandbox = Activity::query()->where('type', 'sandbox')->where('key', '1.5')->first();
        $this->assertNotNull($sandbox);
        $lesson15 = Lesson::where('lesson_id', '1.5')->first();
        $this->assertSame($lesson15->track_id, $sandbox->track_id);
        $this->assertSame($lesson15->status, $sandbox->status);
    }

    public function test_it_skips_the_sandbox_activity_entry_for_a_lesson_without_a_sandbox(): void
    {
        $dir = storage_path('framework/testing/sync-'.uniqid());

        File::ensureDirectoryExists($dir.'/lessons/1.0');
        File::put($dir.'/themenfelder.yml', "- slug: dicom\n  order: 1\n  title_key: t\n  status: published\n");
        File::put($dir.'/tracks.yml', "- slug: fundamente\n  themenfeld: dicom\n  order: 1\n  title_key: t\n  level: einsteiger\n  hours: 1\n  status: published\n");
        File::put($dir.'/lessons/1.0/meta.yml', "id: \"1.0\"\ntrack: fundamente\norder: 0\nduration_minutes: 5\nlevel: einsteiger\nobjectives_count: 1\nrequires: []\ntools: []\nglossary_terms: []\nstatus: draft\n");
        File::put($dir.'/lessons/1.0/de.md', "---\ntitle: Test\nteaser: Test\nobjectives:\n  - Eins\n---\n\nText.\n");

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        $this->assertNotNull(Activity::query()->where('type', 'lesson')->where('key', '1.0')->first());
        $this->assertNull(Activity::query()->where('type', 'sandbox')->where('key', '1.0')->first());

        File::deleteDirectory($dir);
    }

    public function test_it_is_idempotent(): void
    {
        $dir = base_path('tests/Fixtures/content-real');
        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');
        $countAfterFirstRun = Lesson::count();

        Artisan::call('content:sync');
        $countAfterSecondRun = Lesson::count();

        $this->assertSame($countAfterFirstRun, $countAfterSecondRun);
    }

    public function test_it_skips_lessons_with_unknown_track_and_warns(): void
    {
        $dir = storage_path('framework/testing/sync-'.uniqid());

        File::ensureDirectoryExists($dir.'/lessons/1.0');
        File::put($dir.'/tracks.yml', "- slug: fundamente\n  order: 1\n  title_key: t\n  level: einsteiger\n  hours: 1\n  status: published\n");
        File::put($dir.'/lessons/1.0/meta.yml', "id: \"1.0\"\ntrack: nicht-vorhanden\norder: 0\nduration_minutes: 5\nlevel: einsteiger\nobjectives_count: 1\nrequires: []\ntools: []\nglossary_terms: []\nstatus: draft\n");
        File::put($dir.'/lessons/1.0/de.md', "---\ntitle: Test\nteaser: Test\nobjectives:\n  - Eins\n---\n\nText.\n");

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        $exitCode = Artisan::call('content:sync');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('unbekannter Track', Artisan::output());
        $this->assertSame(0, Lesson::count());

        File::deleteDirectory($dir);
    }

    /**
     * ADR 0104 (CMS-6a): quiz-Metadaten (id/type/answer) werden aus
     * derselben Datei befuellt wie body/objectives.
     */
    public function test_it_fills_lesson_quiz_metadata_from_real_content(): void
    {
        $dir = base_path('../../content');

        if (! is_dir($dir.'/lessons/1.5')) {
            $this->markTestSkipped('content/lessons/1.5 nicht gefunden.');
        }

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        $lesson15 = Lesson::where('lesson_id', '1.5')->first();
        $this->assertNotNull($lesson15->quiz);
        $this->assertSame('q1', $lesson15->quiz[0]['id']);
        $this->assertSame('single', $lesson15->quiz[0]['type']);
        $this->assertSame(1, $lesson15->quiz[0]['answer']);
    }

    public function test_it_indexes_the_silent_ct_node_from_real_content(): void
    {
        $dir = base_path('../../content');

        if (! is_dir($dir.'/nodes/silent-ct')) {
            $this->markTestSkipped('content/nodes/silent-ct nicht gefunden.');
        }

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        $node = Node::where('slug', 'silent-ct')->first();
        $this->assertNotNull($node);
        $this->assertSame('Silent CT', $node->title['de']);
        $this->assertSame(10, $node->points);

        $activity = Activity::query()->where('type', 'node')->where('key', 'silent-ct')->first();
        $this->assertNotNull($activity);
        $this->assertSame($node->status, $activity->status);
    }

    /**
     * ADR 0107 (CMS-6d): body/hints werden aus derselben Datei befuellt,
     * die auch title/scenario_title liefert.
     */
    public function test_it_fills_node_body_and_hints_from_real_content(): void
    {
        $dir = base_path('../../content');

        if (! is_dir($dir.'/nodes/silent-ct')) {
            $this->markTestSkipped('content/nodes/silent-ct nicht gefunden.');
        }

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        $node = Node::where('slug', 'silent-ct')->first();
        $this->assertNotNull($node->body);
        $this->assertStringContainsString('## Briefing', $node->body);
        $this->assertSame('h1', $node->hints[0]['id']);
        $this->assertSame(1, $node->hints[0]['cost']);
    }

    /**
     * ADR 0105 (CMS-6b): content:sync backfuellt lesson_elements in der
     * kanonischen Reihenfolge (Content, Sandbox, Lab, Quiz -- nur wenn
     * vorhanden). Diese Fixture hat weder ein `nodes/`-Verzeichnis (Lab
     * daher uebersprungen) noch einen `quiz:`-Block in meta.yml fuer 1.5
     * (siehe dortige Datei) -- nur Content und Sandbox werden erwartet.
     */
    public function test_it_backfills_lesson_elements_in_canonical_order_and_is_idempotent(): void
    {
        $dir = base_path('tests/Fixtures/content-real');
        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        $lesson15 = Lesson::where('lesson_id', '1.5')->first();
        $elements = $lesson15->elements()->with('activity')->get();

        $this->assertCount(2, $elements);
        $this->assertSame('content', $elements[0]->type);
        $this->assertSame('sandbox', $elements[1]->activity->type);

        // Erneuter Lauf legt nichts doppelt an und veraendert keine Position.
        Artisan::call('content:sync');
        $this->assertCount(2, $lesson15->elements()->get());
    }

    /**
     * Eine per Studio (CMS-6c, spaeter) umsortierte Reihenfolge darf ein
     * weiterer content:sync-Lauf nicht zuruecksetzen -- syncLessonElements()
     * legt nur fehlende Slots an, ruehrt aber nie eine bestehende Zeile an.
     */
    public function test_it_never_reorders_an_already_backfilled_element(): void
    {
        $dir = base_path('tests/Fixtures/content-real');
        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        $lesson15 = Lesson::where('lesson_id', '1.5')->first();
        $contentElement = $lesson15->elements()->where('type', 'content')->firstOrFail();
        $sandboxElement = $lesson15->elements()->where('type', 'activity')->firstOrFail();

        // Manuell umsortiert, wie es CMS-6c ueber die DB taete.
        $contentElement->update(['position' => 5]);
        $sandboxElement->update(['position' => 1]);

        Artisan::call('content:sync');

        $this->assertSame(5, $contentElement->fresh()->position);
        $this->assertSame(1, $sandboxElement->fresh()->position);
    }

    public function test_it_registers_an_activity_entry_per_track_exam_from_real_content(): void
    {
        $dir = base_path('../../content');

        if (! is_dir($dir.'/exams/fundamente')) {
            $this->markTestSkipped('content/exams/fundamente nicht gefunden.');
        }

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        $track = Track::where('slug', 'fundamente')->first();
        $activity = Activity::query()->where('type', 'exam')->where('key', 'fundamente')->first();

        $this->assertNotNull($activity);
        $this->assertSame($track->id, $activity->track_id);
        $this->assertSame('published', $activity->status);
    }
}

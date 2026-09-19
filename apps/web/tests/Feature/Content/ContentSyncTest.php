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

    /**
     * Studio-Lessons-Umbau (Source-of-Truth-Cutover): track_id/order werden
     * nur beim ALLERERSTEN Sync einer Lesson aus der Datei gesetzt. Sobald
     * die Zeile existiert, ist Studio/DB fuer diese beiden Felder
     * authoritativ -- ein erneuter content:sync (z. B. weil sich sonst
     * etwas an der Lektion geaendert hat) darf eine per Studio
     * vorgenommene Verschiebung/Umsortierung nicht stillschweigend
     * rueckgaengig machen.
     */
    public function test_a_second_sync_does_not_revert_a_studio_reassigned_lesson(): void
    {
        $dir = storage_path('framework/testing/sync-'.uniqid());

        File::ensureDirectoryExists($dir.'/lessons/1.0');
        File::put($dir.'/themenfelder.yml', "- slug: dicom\n  order: 1\n  title_key: t\n  status: published\n");
        File::put($dir.'/tracks.yml', "- slug: fundamente\n  themenfeld: dicom\n  order: 1\n  title_key: t\n  level: einsteiger\n  hours: 1\n  status: published\n- slug: services\n  themenfeld: dicom\n  order: 2\n  title_key: t2\n  level: einsteiger\n  hours: 1\n  status: published\n");
        File::put($dir.'/lessons/1.0/meta.yml', "id: \"1.0\"\ntrack: fundamente\norder: 0\nduration_minutes: 5\nlevel: einsteiger\nobjectives_count: 1\nrequires: []\ntools: []\nglossary_terms: []\nstatus: draft\n");
        File::put($dir.'/lessons/1.0/de.md', "---\ntitle: Test\nteaser: Test\nobjectives:\n  - Eins\n---\n\nText.\n");

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        $fundamente = Track::where('slug', 'fundamente')->firstOrFail();
        $services = Track::where('slug', 'services')->firstOrFail();
        $lesson = Lesson::where('lesson_id', '1.0')->firstOrFail();
        $this->assertSame($fundamente->id, $lesson->track_id);
        $this->assertSame(0, $lesson->order);

        // Simuliert eine echte Studio-Verschiebung
        // (StudioTrackController::moveLesson()), ohne den HTTP-Layer hier
        // erneut zu testen -- die eigentliche Verschiebungslogik hat ihre
        // eigenen Tests in StudioTrackControllerTest.
        $lesson->update(['track_id' => $services->id, 'order' => 5]);
        Activity::query()->where('type', 'lesson')->where('key', '1.0')->update(['track_id' => $services->id, 'order' => 5]);

        Artisan::call('content:sync');

        $lesson->refresh();
        $this->assertSame($services->id, $lesson->track_id, 'track_id darf durch einen erneuten Sync nicht zurueckgesetzt werden.');
        $this->assertSame(5, $lesson->order, 'order darf durch einen erneuten Sync nicht zurueckgesetzt werden.');

        $activity = Activity::query()->where('type', 'lesson')->where('key', '1.0')->firstOrFail();
        $this->assertSame($services->id, $activity->track_id);
        $this->assertSame(5, $activity->order);

        // Alle anderen, weiterhin datei-gefuehrten Felder werden trotzdem
        // ganz normal weiter synchronisiert.
        $this->assertSame('draft', $lesson->status);

        File::deleteDirectory($dir);
    }

    /**
     * Gegenprobe zum Cutover-Test oben: eine Lektion, die zum ERSTEN Mal
     * synchronisiert wird, bekommt track_id/order weiterhin ganz normal aus
     * der Datei -- der Cutover blockiert nur das UEBERSCHREIBEN einer
     * bereits existierenden Zeile, nicht die Erstbefuellung.
     */
    public function test_a_brand_new_lesson_still_gets_seeded_from_the_file(): void
    {
        $dir = storage_path('framework/testing/sync-'.uniqid());

        File::ensureDirectoryExists($dir.'/lessons/1.0');
        File::put($dir.'/themenfelder.yml', "- slug: dicom\n  order: 1\n  title_key: t\n  status: published\n");
        File::put($dir.'/tracks.yml', "- slug: fundamente\n  themenfeld: dicom\n  order: 1\n  title_key: t\n  level: einsteiger\n  hours: 1\n  status: published\n");
        File::put($dir.'/lessons/1.0/meta.yml', "id: \"1.0\"\ntrack: fundamente\norder: 3\nduration_minutes: 5\nlevel: einsteiger\nobjectives_count: 1\nrequires: []\ntools: []\nglossary_terms: []\nstatus: draft\n");
        File::put($dir.'/lessons/1.0/de.md', "---\ntitle: Test\nteaser: Test\nobjectives:\n  - Eins\n---\n\nText.\n");

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        $fundamente = Track::where('slug', 'fundamente')->firstOrFail();
        $lesson = Lesson::where('lesson_id', '1.0')->firstOrFail();
        $this->assertSame($fundamente->id, $lesson->track_id);
        $this->assertSame(3, $lesson->order);

        File::deleteDirectory($dir);
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
     * Guardrail gegen den Fehler, der zur Entdeckung der rich_content-
     * Cutover-Luecke fuehrte (ADR 0118): eine bereits auf `rich_content`
     * umgestellte Lektion zeigt Lernenden diesen Inhalt, nicht `body` --
     * ein content:sync-Lauf schreibt `body` trotzdem weiter unveraendert
     * aus der Datei und muss deshalb warnen, sobald sich der Dateiinhalt
     * seit dem letzten Sync tatsaechlich geaendert hat.
     */
    public function test_it_warns_when_syncing_a_lesson_whose_rich_content_is_already_set_and_the_file_changed(): void
    {
        $dir = storage_path('framework/testing/sync-'.uniqid());

        File::ensureDirectoryExists($dir.'/lessons/1.0');
        File::put($dir.'/themenfelder.yml', "- slug: dicom\n  order: 1\n  title_key: t\n  status: published\n");
        File::put($dir.'/tracks.yml', "- slug: fundamente\n  themenfeld: dicom\n  order: 1\n  title_key: t\n  level: einsteiger\n  hours: 1\n  status: published\n");
        File::put($dir.'/lessons/1.0/meta.yml', "id: \"1.0\"\ntrack: fundamente\norder: 0\nduration_minutes: 5\nlevel: einsteiger\nobjectives_count: 1\nrequires: []\ntools: []\nglossary_terms: []\nstatus: draft\n");
        File::put($dir.'/lessons/1.0/de.md', "---\ntitle: Test\nteaser: Test\nobjectives:\n  - Eins\n---\n\nAlter Text.\n");

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');
        Lesson::where('lesson_id', '1.0')->update([
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
        ]);

        // Datei aendert sich (anderer Body -> anderer source_hash), aber
        // niemand hat rich_content ueber den Studio-Editor nachgezogen.
        File::put($dir.'/lessons/1.0/de.md', "---\ntitle: Test\nteaser: Test\nobjectives:\n  - Eins\n---\n\nNeuer Text.\n");

        Artisan::call('content:sync');

        $this->assertStringContainsString('Lektion 1.0: rich_content ist bereits gesetzt', Artisan::output());

        File::deleteDirectory($dir);
    }

    public function test_it_does_not_warn_when_syncing_a_rich_content_lesson_whose_file_is_unchanged(): void
    {
        $dir = storage_path('framework/testing/sync-'.uniqid());

        File::ensureDirectoryExists($dir.'/lessons/1.0');
        File::put($dir.'/themenfelder.yml', "- slug: dicom\n  order: 1\n  title_key: t\n  status: published\n");
        File::put($dir.'/tracks.yml', "- slug: fundamente\n  themenfeld: dicom\n  order: 1\n  title_key: t\n  level: einsteiger\n  hours: 1\n  status: published\n");
        File::put($dir.'/lessons/1.0/meta.yml', "id: \"1.0\"\ntrack: fundamente\norder: 0\nduration_minutes: 5\nlevel: einsteiger\nobjectives_count: 1\nrequires: []\ntools: []\nglossary_terms: []\nstatus: draft\n");
        File::put($dir.'/lessons/1.0/de.md', "---\ntitle: Test\nteaser: Test\nobjectives:\n  - Eins\n---\n\nUnveraenderter Text.\n");

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');
        Lesson::where('lesson_id', '1.0')->update([
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
        ]);

        Artisan::call('content:sync');

        $this->assertStringNotContainsString('rich_content ist bereits gesetzt', Artisan::output());

        File::deleteDirectory($dir);
    }

    public function test_it_warns_when_syncing_a_node_whose_rich_content_is_already_set_and_the_file_changed(): void
    {
        $dir = storage_path('framework/testing/sync-'.uniqid());

        File::ensureDirectoryExists($dir.'/nodes/test-node');
        File::put($dir.'/themenfelder.yml', "- slug: dicom\n  order: 1\n  title_key: t\n  status: published\n");
        File::put($dir.'/nodes/test-node/node.yml', "slug: test-node\ndifficulty: easy\npoints: 10\ncategory: netzwerk\nskills: []\nrelated_lessons: []\nestimated_minutes: 5\nenvironment:\n  engine: simulated\n  hosts: []\nhints: []\nstuck_timeout_minutes: 10\nstatus: draft\nupdated: \"2026-09-20\"\n");
        File::put($dir.'/nodes/test-node/de.md', "---\ntitle: Test-Node\nscenario_title: Test\n---\n\n## Briefing\n\nAlter Text.\n\n## Write-up\n\nText.\n");

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');
        Node::where('slug', 'test-node')->update([
            'rich_content' => ['type' => 'node_content', 'version' => 1, 'briefing' => ['type' => 'doc', 'version' => 1, 'content' => []], 'hints' => [], 'write_up' => ['type' => 'doc', 'version' => 1, 'content' => []]],
        ]);

        File::put($dir.'/nodes/test-node/de.md', "---\ntitle: Test-Node\nscenario_title: Test\n---\n\n## Briefing\n\nNeuer Text.\n\n## Write-up\n\nText.\n");

        Artisan::call('content:sync');

        $this->assertStringContainsString('Node test-node: rich_content ist bereits gesetzt', Artisan::output());

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
     * Regressionstest fuer PR #159: `letztes-glied-fehlt` hatte einen
     * `scenario:`-Baum ohne explizites `interaction: scenario` im node.yml
     * -- ContentSync defaultete klaglos auf "terminal", wodurch der Node
     * am falschen Engine-Client (services/engine statt
     * services/scenario-engine) gelandet waere. Siehe auch
     * NodeControllerScenarioTest fuer den vollen Ende-zu-Ende-Pfad.
     */
    public function test_it_syncs_a_scenario_node_with_the_declared_interaction_from_real_content(): void
    {
        $dir = base_path('../../content');

        if (! is_dir($dir.'/nodes/letztes-glied-fehlt')) {
            $this->markTestSkipped('content/nodes/letztes-glied-fehlt nicht gefunden.');
        }

        $this->app->instance(ContentRepository::class, new ContentRepository($dir));

        Artisan::call('content:sync');

        $node = Node::where('slug', 'letztes-glied-fehlt')->first();
        $this->assertNotNull($node);
        $this->assertSame('scenario', $node->interaction);
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

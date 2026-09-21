<?php

namespace Tests\Feature\Content;

use App\Content\ContentPublishingService;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lab;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\SandboxTemplate;
use App\Models\Themenfeld;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * CMS-7d.3 Phase 7 (ADR 0118), Betreiber-Abnahmekriterien: deckt genau die
 * beiden Luecken ab, die den `ContentPublishingService` ueberhaupt erst
 * ausgeloest haben (siehe dessen Klassendoc) -- Atomaritaet von
 * apply()+Versionswechsel, und `restoreVersion()`s korrekte Autorenschaft
 * (`created_by`/`reviewed_by` = Ausfuehrender, nicht der historische Autor).
 * `LessonContentPublisherTest`/`NodeContentPublisherTest` decken bereits ab,
 * dass `body`/Datei-Content unangetastet bleiben -- hier geht es um die
 * Transaktions-/Versionsebene, die dort nicht getestet wird.
 */
class ContentPublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Absichtlich OHNE Codeblock -- verletzt "enthaelt keinen einzigen
     * Codeblock" (ContentValidator::checkRichContentExampleRule()), der
     * einzige Zweck dieses Helpers ist ein Dokument zu bauen, das die
     * heutige Validierung ablehnt.
     */
    private function richContent(string $text): array
    {
        return ['type' => 'doc', 'version' => 1, 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        ]];
    }

    /**
     * Wie `richContent()`, aber mit einem Codeblock + direkt folgender
     * "Was du daran abliest:"-Erklaerung -- besteht ContentValidator, der
     * gegebene Text bleibt dabei `content[0]` (fuer Assertions auf den
     * ersten Block).
     */
    private function validRichContent(string $text): array
    {
        return ['type' => 'doc', 'version' => 1, 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
            ['type' => 'code_block', 'attrs' => ['variant' => 'console'], 'text' => 'ok'],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Was du daran abliest: Beispiel.', 'marks' => [['type' => 'bold']]],
            ]],
        ]];
    }

    private function lessonDraftPayload(string $text = 'Neue Prosa.'): array
    {
        return [
            'title' => 'Neu', 'teaser' => 'Neu', 'level' => 'einsteiger', 'duration_minutes' => 5,
            'tools' => [], 'requires' => [], 'glossary_terms' => [], 'objectives' => ['Ziel'],
            'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
            'related_node' => ['node' => null, 'optional' => true],
            'rich_content' => $this->validRichContent($text),
        ];
    }

    public function test_publish_returns_issues_and_changes_nothing_when_the_draft_is_invalid(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'title' => ['de' => 'Alt'], 'body' => 'Alt.']);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $reviewer = User::factory()->reviewer()->create();

        // Ein rich_content ganz ohne Codeblock verletzt "enthaelt keinen
        // einzigen Codeblock" (ContentValidator, checkRichContentExampleRule())
        // -- derselbe Trigger wie in ActivityContentApplierTest, hier aber
        // ueber den vollen ContentPublishingService-Pfad.
        $invalidPayload = $this->lessonDraftPayload();
        $invalidPayload['rich_content'] = $this->richContent('Ohne Codeblock.');

        $version = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => $invalidPayload,
            'is_current' => false, 'created_by' => $reviewer->id,
        ]);

        $issues = app(ContentPublishingService::class)->publish($version, $reviewer);

        $this->assertNotEmpty($issues);
        $this->assertSame('Alt', $lesson->fresh()->title['de']);
        $this->assertSame('review', $version->fresh()->status);
        $this->assertFalse($version->fresh()->is_current);
    }

    /**
     * Der urspruengliche Fund (Klassendoc von `ContentPublishingService`):
     * schlaegt der Versionswechsel NACH dem Anwenden auf die Live-Ressource
     * fehl, darf die Live-Ressource trotzdem nicht veraendert bleiben --
     * beides muss in derselben Transaktion zurueckrollen. `ContentVersioningService`
     * ist `final` (kein Mock moeglich) -- ein Entwurf im falschen Status
     * ('draft' statt 'review') loest denselben Fehlerpfad ganz ohne Mock
     * aus: `ContentVersioningService::publish()` wirft dann selbst, aber
     * erst NACHDEM `ActivityContentApplier::apply()` (derselbe
     * Transaktions-Block) bereits gelaufen ist.
     */
    public function test_publish_rolls_back_the_live_write_when_the_version_flip_fails_afterwards(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'title' => ['de' => 'Alt'], 'body' => 'Alt.']);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $reviewer = User::factory()->reviewer()->create();

        $version = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'draft', 'payload' => $this->lessonDraftPayload(),
            'is_current' => false, 'created_by' => $reviewer->id,
        ]);

        try {
            app(ContentPublishingService::class)->publish($version, $reviewer);
            $this->fail('publish() haette die Exception aus ContentVersioningService::publish() weiterreichen muessen.');
        } catch (RuntimeException) {
            // erwartet: "Nur eine Version im Review-Status kann veroeffentlicht werden."
        }

        // ActivityContentApplier::apply() lief bereits (schreibt Lesson->title
        // = 'Neu'), bevor ContentVersioningService::publish() geworfen hat --
        // die Transaktion muss diesen Teilschritt trotzdem zuruecknehmen.
        $this->assertSame('Alt', $lesson->fresh()->title['de']);
        $this->assertSame('draft', $version->fresh()->status);
        $this->assertFalse($version->fresh()->is_current);
    }

    public function test_publish_applies_and_flips_the_version_atomically_on_success(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'title' => ['de' => 'Alt'], 'body' => 'Alt.']);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $reviewer = User::factory()->reviewer()->create();

        $version = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => $this->lessonDraftPayload('Frisch veroeffentlicht.'),
            'is_current' => false, 'created_by' => $reviewer->id,
        ]);

        $issues = app(ContentPublishingService::class)->publish($version, $reviewer);

        $this->assertSame([], $issues);
        $lesson->refresh();
        $this->assertSame('Neu', $lesson->title['de']);
        $this->assertSame('Alt.', $lesson->body, 'body bleibt unangetastet -- keine zwei schreibenden Sources of Truth.');
        $this->assertSame('Frisch veroeffentlicht.', $lesson->rich_content['content'][0]['content'][0]['text']);
        $version->refresh();
        $this->assertSame('published', $version->status);
        $this->assertTrue($version->is_current);
        $this->assertSame($reviewer->id, $version->reviewed_by);
    }

    /**
     * Betreiber-Vorgabe: `restoreVersion()`s neue Version zeichnet den
     * Ausfuehrenden als Autor UND Pruefer -- nicht den historischen Autor
     * der wiederhergestellten Fassung (der urspruengliche `rollback()`-Fund).
     */
    public function test_restore_version_uses_the_performer_as_author_and_reviewer_not_the_historical_author(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'rich_content' => $this->richContent('Aktuell.')]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $historicalAuthor = User::factory()->create();
        $performer = User::factory()->reviewer()->create();

        $current = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published', 'payload' => $this->lessonDraftPayload('Aktuell.'),
            'is_current' => true, 'created_by' => $historicalAuthor->id, 'reviewed_by' => $historicalAuthor->id,
        ]);
        $historical = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published', 'payload' => $this->lessonDraftPayload('Historisch.'),
            'is_current' => false, 'created_by' => $historicalAuthor->id, 'reviewed_by' => $historicalAuthor->id,
        ]);

        $restored = app(ContentPublishingService::class)->restoreVersion($historical, $performer);

        $this->assertSame($performer->id, $restored->created_by);
        $this->assertSame($performer->id, $restored->reviewed_by);
        $this->assertSame($historical->id, $restored->restored_from_version_id);
        $this->assertTrue($restored->is_current);
        $this->assertFalse($current->fresh()->is_current);
        // Die historische Version selbst bleibt unveraendert stehen -- sie
        // wird nie rueckwirkend umgeschrieben.
        $this->assertSame($historicalAuthor->id, $historical->fresh()->created_by);
        $this->assertSame('Historisch.', $lesson->fresh()->rich_content['content'][0]['content'][0]['text']);
    }

    /**
     * Erstes Abnahmekriterium der Betreiber-Liste woertlich: eine Legacy-
     * `body`-Revision (vor dem Cutover veroeffentlicht) wird wiederhergestellt
     * und dabei zu einer NEUEN Rich-Content-Revision -- der Lernende sieht
     * anschliessend den wiederhergestellten Text ueber den Rich-Content-Pfad,
     * nicht ueber den Markdown-Fallback.
     */
    public function test_restoring_a_legacy_body_revision_produces_a_new_rich_content_revision_the_learner_sees(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create([
            'track_id' => $track->id,
            'lesson_id' => '9.1',
            'body' => 'Aktueller Text, der ueberschrieben wird.',
            'rich_content' => null,
        ]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $historicalAuthor = User::factory()->create();
        $performer = User::factory()->reviewer()->create();

        $legacyVersion = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published',
            'payload' => [
                'title' => 'Wiederhergestellt', 'teaser' => 'Neu', 'level' => 'einsteiger', 'duration_minutes' => 5,
                'tools' => [], 'requires' => [], 'glossary_terms' => [], 'objectives' => ['Ziel'],
                'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
                'related_node' => ['node' => null, 'optional' => true],
                // Kein rich_content-Schluessel -- eine echte Vor-Cutover-Revision.
                // Codeblock + Leseanleitung noetig, damit die HEUTIGE
                // Validierung (checkRichContentExampleRule(), nach der
                // Konvertierung durch den Normalizer) besteht.
                'body' => "Wiederhergestellter Legacy-Text.\n\n```\necho 'ok'\n```\n\n**Was du daran abliest:** Beispiel.\n",
            ],
            'is_current' => false, 'created_by' => $historicalAuthor->id, 'reviewed_by' => $historicalAuthor->id,
        ]);

        $restored = app(ContentPublishingService::class)->restoreVersion($legacyVersion, $performer);

        $this->assertArrayHasKey('rich_content', $restored->payload);
        $this->assertArrayNotHasKey('body', $restored->payload);

        $lesson->refresh();
        $this->assertNotNull($lesson->rich_content);
        $this->assertSame('Aktueller Text, der ueberschrieben wird.', $lesson->body, 'body bleibt unangetastet.');

        $learner = User::factory()->create();
        $this->actingAs($learner)
            ->get('/de/lessons/9.1')
            ->assertInertia(fn ($page) => $page
                ->where('elements.0.type', 'content')
                ->where('elements.0.body_html', fn (string $html) => str_contains($html, 'Wiederhergestellter Legacy-Text.')),
            );
    }

    /**
     * Betreiber-Review vor #126: der VOR 7d.3 gueltige `LessonEditorController`
     * speicherte in `payload.body` ausdruecklich nur `QuizContent::
     * splitBody(...)['before']` -- der Nach-Quiz-Fusstext (`after`, z. B.
     * "Als Naechstes: ...") war ueber KEINE UI editierbar und kam beim
     * Rendern immer LIVE aus `Lesson::body` dazu. Ein Restore, das das
     * historische `body` als komplettes Dokument interpretiert, wuerde
     * `after` deshalb unbemerkt verlieren -- `LessonPayloadNormalizer`
     * muss stattdessen das historische `body` als `before` behandeln und
     * das LIVE `after` (aus der aktuellen `Lesson::body`-Spalte) anhaengen.
     */
    public function test_restoring_a_legacy_lesson_revision_with_nonempty_after_keeps_the_after_text(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create([
            'track_id' => $track->id,
            'lesson_id' => '9.4',
            // Die LIVE Prosa vor dem Quiz ist irrelevant fuer den Restore
            // (die kommt aus der historischen Version) -- nur `after`
            // (nach dem abschliessenden "---") muss erhalten bleiben.
            'body' => "Aktuelle Vor-Quiz-Prosa (wird beim Restore NICHT verwendet).\n\n## Quiz\n\n**q1 — Frage?**\n1. A\n2. B\n\n---\n\n**Als Nächstes:** Lektion 2 wartet.",
            'rich_content' => null,
        ]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $historicalAuthor = User::factory()->create();
        $performer = User::factory()->reviewer()->create();

        $legacyVersion = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published',
            'payload' => [
                'title' => 'Alt', 'teaser' => 'Alt', 'level' => 'einsteiger', 'duration_minutes' => 5,
                'tools' => [], 'requires' => [], 'glossary_terms' => [], 'objectives' => ['Ziel'],
                'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
                'related_node' => ['node' => null, 'optional' => true],
                // Historisches Legacy-Payload: NUR `before`, wie der
                // Vor-7d.3-Editor es tatsaechlich gespeichert hat.
                'body' => "Historische Vor-Quiz-Prosa.\n\n```\necho 'ok'\n```\n\n**Was du daran abliest:** Beispiel.",
            ],
            'is_current' => false, 'created_by' => $historicalAuthor->id, 'reviewed_by' => $historicalAuthor->id,
        ]);

        $restored = app(ContentPublishingService::class)->restoreVersion($legacyVersion, $performer);

        $restoredText = json_encode($restored->payload['rich_content'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Historische Vor-Quiz-Prosa.', $restoredText);
        $this->assertStringContainsString('Als Nächstes', $restoredText);
        $this->assertStringContainsString('Lektion 2 wartet.', $restoredText);

        $lesson->refresh();
        $liveText = json_encode($lesson->rich_content);
        $this->assertStringContainsString('Historische Vor-Quiz-Prosa.', $liveText);
        $this->assertStringContainsString('Lektion 2 wartet.', $liveText);
    }

    /**
     * Betreiber-Review vor #126: die Route ist generisch, das `publish`-
     * Gate allein erzwingt nicht, welchen Status `source` hat -- ohne
     * serverseitige Pruefung koennte ein Reviewer einen `draft`/`review`
     * direkt als neue veroeffentlichte Version "wiederherstellen" und den
     * normalen draft -> review -> publish-Pfad umgehen.
     */
    public function test_restore_of_a_non_published_version_is_rejected(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'title' => ['de' => 'Alt']]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $performer = User::factory()->reviewer()->create();

        $draftVersion = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => $this->lessonDraftPayload(),
            'is_current' => false, 'created_by' => $performer->id,
        ]);

        $this->expectException(RuntimeException::class);

        try {
            app(ContentPublishingService::class)->restoreVersion($draftVersion, $performer);
        } finally {
            $this->assertSame('Alt', $lesson->fresh()->title['de']);
            $this->assertSame('review', $draftVersion->fresh()->status);
            $this->assertSame(1, ContentVersion::where('activity_id', $activity->id)->count());
        }
    }

    /**
     * Kehrseite von oben: eine echte, veroeffentlichte historische Version
     * laesst sich weiterhin normal wiederherstellen -- die neue Pruefung
     * blockiert nur `draft`/`review`, nicht den eigentlichen Restore-Zweck.
     */
    public function test_restore_of_a_published_historical_version_still_succeeds(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'rich_content' => $this->richContent('Aktuell.')]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $historicalAuthor = User::factory()->create();
        $performer = User::factory()->reviewer()->create();

        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published', 'payload' => $this->lessonDraftPayload('Aktuell.'),
            'is_current' => true, 'created_by' => $historicalAuthor->id, 'reviewed_by' => $historicalAuthor->id,
        ]);
        $historical = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published', 'payload' => $this->lessonDraftPayload('Historisch.'),
            'is_current' => false, 'created_by' => $historicalAuthor->id, 'reviewed_by' => $historicalAuthor->id,
        ]);

        $restored = app(ContentPublishingService::class)->restoreVersion($historical, $performer);

        $this->assertTrue($restored->is_current);
        $this->assertSame('Historisch.', $lesson->fresh()->rich_content['content'][0]['content'][0]['text']);
    }

    /**
     * CMS-7d.4 (Betreiber-Vorgabe): sobald fuer diese Lesson eine ECHTE,
     * im Rich-Content-Editor gespeicherte Autorenrevision veroeffentlicht
     * wurde, gibt es keine belastbare Grenze zwischen "before" und
     * "after" im aktuellen Dokument mehr -- ein Legacy-Restore wird
     * deshalb serverseitig abgelehnt statt zu raten, welchen Teil des
     * heutigen Dokuments er ersetzen darf.
     */
    public function test_legacy_restore_is_blocked_after_a_native_rich_content_publish(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'title' => ['de' => 'Aktuell'], 'rich_content' => $this->validRichContent('Aktuell.')]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $author = User::factory()->create();
        $performer = User::factory()->reviewer()->create();

        // Eine ECHTE (nicht per Restore erzeugte) veroeffentlichte
        // Rich-Content-Revision -- das allein macht die Lektion "nativ
        // veroeffentlicht".
        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published', 'payload' => $this->lessonDraftPayload('Aktuell.'),
            'is_current' => true, 'created_by' => $author->id, 'reviewed_by' => $author->id,
        ]);

        $legacyHistorical = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published',
            'payload' => [
                'title' => 'Legacy', 'teaser' => 'Legacy', 'level' => 'einsteiger', 'duration_minutes' => 5,
                'tools' => [], 'requires' => [], 'glossary_terms' => [], 'objectives' => ['Ziel'],
                'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
                'related_node' => ['node' => null, 'optional' => true],
                'body' => "Legacy-Text.\n\n```\necho 'ok'\n```\n\n**Was du daran abliest:** Beispiel.",
            ],
            'is_current' => false, 'created_by' => $author->id, 'reviewed_by' => $author->id,
        ]);

        try {
            app(ContentPublishingService::class)->restoreVersion($legacyHistorical, $performer);
            $this->fail('restoreVersion() haette werfen muessen.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('alten Inhaltsformat', $exception->getMessage());
        }

        $this->assertSame('Aktuell', $lesson->fresh()->title['de']);
        $this->assertSame(2, ContentVersion::where('activity_id', $activity->id)->count(), 'kein Schreibvorgang, keine neue Version.');
    }

    /**
     * Dieselbe Sperre gilt fuer publish(), nicht nur restoreVersion(): ein
     * vor dem Cutover angelegter, nie neu gespeicherter Draft/Review kann
     * ebenfalls noch payload.body ohne rich_content tragen. publish()
     * WIRFT dabei NICHT (anders als restoreVersion()) -- der Controller
     * behandelt eine Ablehnung als zurueckgegebene ContentIssue[], sonst
     * produziert der Schutz im Browser einen 500er statt einer normalen
     * Autoren-Fehlermeldung.
     */
    public function test_legacy_publish_is_blocked_after_a_native_rich_content_publish(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'title' => ['de' => 'Aktuell'], 'rich_content' => $this->validRichContent('Aktuell.')]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $author = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published', 'payload' => $this->lessonDraftPayload('Aktuell.'),
            'is_current' => true, 'created_by' => $author->id, 'reviewed_by' => $author->id,
        ]);

        $stalePendingDraft = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review',
            'payload' => [
                'title' => 'Alter Entwurf', 'teaser' => 'Alt', 'level' => 'einsteiger', 'duration_minutes' => 5,
                'tools' => [], 'requires' => [], 'glossary_terms' => [], 'objectives' => ['Ziel'],
                'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
                'related_node' => ['node' => null, 'optional' => true],
                'body' => "Alter Entwurfstext.\n\n```\necho 'ok'\n```\n\n**Was du daran abliest:** Beispiel.",
            ],
            'is_current' => false, 'created_by' => $author->id,
        ]);

        $issues = app(ContentPublishingService::class)->publish($stalePendingDraft, $reviewer);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('nicht mehr automatisch veroeffentlicht', (string) $issues[0]);
        $this->assertSame('Aktuell', $lesson->fresh()->title['de']);
        $this->assertSame('review', $stalePendingDraft->fresh()->status);
        $this->assertFalse($stalePendingDraft->fresh()->is_current);
    }

    /**
     * Eine `restoreVersion()`-erzeugte Version zaehlt selbst NICHT als
     * native Autorenrevision (`whereNull('restored_from_version_id')`) --
     * sonst wuerde der ERSTE Legacy-Restore jeden weiteren sofort
     * blockieren, obwohl niemand das Dokument im neuen Editor je
     * angefasst hat.
     */
    public function test_a_legacy_restore_itself_does_not_count_as_a_native_publish(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'body' => 'Alt.', 'rich_content' => null]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $author = User::factory()->create();
        $performer = User::factory()->reviewer()->create();

        $legacyPayload = fn (string $text) => [
            'title' => 'Legacy', 'teaser' => 'Legacy', 'level' => 'einsteiger', 'duration_minutes' => 5,
            'tools' => [], 'requires' => [], 'glossary_terms' => [], 'objectives' => ['Ziel'],
            'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
            'related_node' => ['node' => null, 'optional' => true],
            'body' => "{$text}\n\n```\necho 'ok'\n```\n\n**Was du daran abliest:** Beispiel.",
        ];

        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published', 'payload' => $legacyPayload('Aktuell.'),
            'is_current' => true, 'created_by' => $author->id, 'reviewed_by' => $author->id,
        ]);
        $olderLegacy = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published', 'payload' => $legacyPayload('Alt.'),
            'is_current' => false, 'created_by' => $author->id, 'reviewed_by' => $author->id,
        ]);

        // Erster Restore -- erzeugt eine neue, veroeffentlichte Version MIT
        // rich_content, aber mit restored_from_version_id gesetzt.
        $firstRestore = app(ContentPublishingService::class)->restoreVersion($olderLegacy, $performer);
        $this->assertNotNull($firstRestore->restored_from_version_id);

        // Ein zweiter Legacy-Restore muss trotzdem noch erlaubt sein --
        // der erste Restore war selbst keine Autoren-Bearbeitung.
        $secondRestore = app(ContentPublishingService::class)->restoreVersion($olderLegacy, $performer);
        $this->assertTrue($secondRestore->is_current);
    }

    /**
     * Ein prae-7d.3-Draft, der ERST NACH dem Cutover veroeffentlicht wird,
     * zaehlt ebenfalls nicht als native Autorenrevision -- er traegt als
     * gespeicherte Version weiterhin `payload.body`
     * (`ContentVersioningService::publish()` ersetzt das Payload nicht).
     */
    public function test_publishing_a_pre_cutover_draft_after_the_cutover_does_not_count_as_native(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'body' => 'Alt.', 'rich_content' => null]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $author = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        $legacyDraft = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review',
            'payload' => [
                'title' => 'Nachtraeglich veroeffentlicht', 'teaser' => 'Alt', 'level' => 'einsteiger', 'duration_minutes' => 5,
                'tools' => [], 'requires' => [], 'glossary_terms' => [], 'objectives' => ['Ziel'],
                'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
                'related_node' => ['node' => null, 'optional' => true],
                'body' => "Alter Entwurfstext.\n\n```\necho 'ok'\n```\n\n**Was du daran abliest:** Beispiel.",
            ],
            'is_current' => false, 'created_by' => $author->id,
        ]);

        $issues = app(ContentPublishingService::class)->publish($legacyDraft, $reviewer);
        $this->assertSame([], $issues, 'ohne vorherigen nativen Publish muss dieser Legacy-Publish noch erlaubt sein.');
        $this->assertArrayNotHasKey('rich_content', $legacyDraft->fresh()->payload, 'die gespeicherte Version bleibt payload.body -- publish() schreibt das Payload nicht um.');

        // Ein weiterer Legacy-Restore muss trotzdem noch moeglich sein --
        // dieser nachtraegliche Legacy-Publish zaehlt nicht als native
        // Autorenrevision.
        $anotherLegacyHistorical = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published',
            'payload' => [
                'title' => 'Noch aelter', 'teaser' => 'Alt', 'level' => 'einsteiger', 'duration_minutes' => 5,
                'tools' => [], 'requires' => [], 'glossary_terms' => [], 'objectives' => ['Ziel'],
                'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
                'related_node' => ['node' => null, 'optional' => true],
                'body' => "Noch aelterer Text.\n\n```\necho 'ok'\n```\n\n**Was du daran abliest:** Beispiel.",
            ],
            'is_current' => false, 'created_by' => $author->id, 'reviewed_by' => $author->id,
        ]);

        $restored = app(ContentPublishingService::class)->restoreVersion($anotherLegacyHistorical, $reviewer);
        $this->assertTrue($restored->is_current);
    }

    /**
     * Race-Sicherheit (Betreiber-Review): die Legacy-Kompatibilitaetspruefung
     * darf sich nicht auf einen Zustand von VOR der Transaktion verlassen --
     * ein nativer Publish, der "waehrend" eines Restores committet, muss
     * trotzdem gesehen werden. Simuliert per `DB::listen()` (analog dem
     * `RichContentMigrateTest`-Muster): eine native Rich-Content-Version
     * "erscheint" exakt zwischen dem Sperren der Activity-Zeile und der
     * eigentlichen Legacy-Pruefung.
     */
    public function test_restore_sees_a_native_publish_that_appears_between_locking_and_checking(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'body' => 'Alt.', 'rich_content' => null]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $author = User::factory()->create();
        $performer = User::factory()->reviewer()->create();

        $legacyHistorical = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published',
            'payload' => [
                'title' => 'Legacy', 'teaser' => 'Legacy', 'level' => 'einsteiger', 'duration_minutes' => 5,
                'tools' => [], 'requires' => [], 'glossary_terms' => [], 'objectives' => ['Ziel'],
                'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
                'related_node' => ['node' => null, 'optional' => true],
                'body' => "Legacy-Text.\n\n```\necho 'ok'\n```\n\n**Was du daran abliest:** Beispiel.",
            ],
            'is_current' => false, 'created_by' => $author->id, 'reviewed_by' => $author->id,
        ]);

        $fired = false;
        DB::listen(function ($query) use (&$fired, $activity, $author): void {
            if ($fired || stripos($query->sql, 'select') !== 0 || ! str_contains($query->sql, 'activities')) {
                return;
            }

            $fired = true;

            // Simuliert einen nativen Publish, der genau zwischen dem
            // Activity-Lock und der Legacy-Pruefung committet.
            DB::table('content_versions')->insert([
                'activity_id' => $activity->id,
                'status' => 'published',
                'payload' => json_encode(['title' => 'Nativ', 'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []]]),
                'is_current' => false,
                'created_by' => $author->id,
                'reviewed_by' => $author->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            app(ContentPublishingService::class)->restoreVersion($legacyHistorical, $performer);
            $this->fail('restoreVersion() haette die zwischenzeitlich erschienene native Publish sehen und ablehnen muessen.');
        } catch (RuntimeException $exception) {
            $this->assertTrue($fired, 'die simulierte native Publish haette waehrend des Laufs feuern muessen');
            $this->assertStringContainsString('alten Inhaltsformat', $exception->getMessage());
        }
    }

    /**
     * `restoreVersion()` wirft statt still zu ueberspringen, wenn die
     * historische Fassung gegen die HEUTIGEN Regeln nicht mehr gueltig ist
     * (Betreiber-Vorgabe: "kein normaler, still abzufangender Ausgang") --
     * weder die Live-Ressource noch die Versionshistorie duerfen sich dabei
     * aendern.
     */
    public function test_restore_throws_and_writes_nothing_when_the_historical_payload_fails_todays_validation(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'title' => ['de' => 'Alt']]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $performer = User::factory()->reviewer()->create();

        $invalidPayload = $this->lessonDraftPayload();
        $invalidPayload['rich_content'] = $this->richContent('Ohne Codeblock.');

        $historical = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published', 'payload' => $invalidPayload,
            'is_current' => false, 'created_by' => $performer->id, 'reviewed_by' => $performer->id,
        ]);

        $this->expectException(RuntimeException::class);

        try {
            app(ContentPublishingService::class)->restoreVersion($historical, $performer);
        } finally {
            $this->assertSame('Alt', $lesson->fresh()->title['de']);
            $this->assertSame(1, ContentVersion::where('activity_id', $activity->id)->count());
        }
    }

    /**
     * Betreiber-Abnahmekriterium: die Vorschau eines ungespeicherten
     * Entwurfs zeigt exakt dasselbe HTML wie die Lernenden-Ansicht nach
     * dessen Veroeffentlichung -- kein zweiter Renderer (CMS-7d.3 Phase 6).
     */
    public function test_lesson_preview_renders_the_same_html_as_the_learner_view_after_publishing(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'lesson_id' => '9.2', 'body' => 'Alt.']);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);
        $reviewer = User::factory()->reviewer()->create();

        $payload = $this->lessonDraftPayload('Vorschau und Live muessen gleich aussehen.');
        $version = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => $payload,
            'is_current' => false, 'created_by' => $author->id,
        ]);

        $previewHtml = null;
        $this->actingAs($author)
            ->get('/de/author/lessons/9.2/edit/preview')
            ->assertInertia(function ($page) use (&$previewHtml) {
                $previewHtml = $page->toArray()['props']['elements'][0]['body_html'];
            });

        app(ContentPublishingService::class)->publish($version, $reviewer);

        $learner = User::factory()->create();
        $this->actingAs($learner)
            ->get('/de/lessons/9.2')
            ->assertInertia(function ($page) use (&$previewHtml) {
                $liveHtml = $page->toArray()['props']['elements'][0]['body_html'];
                $this->assertNotNull($previewHtml);
                $this->assertSame($previewHtml, $liveHtml);
            });
    }

    /**
     * Analoges Abnahmekriterium fuer Node: Briefing/Hinweise/Write-up
     * (Draft -> Review -> Publish -> Learner), inklusive Hint-ID-
     * Konsistenz-Nebenbedingung, die schon beim Speichern des Entwurfs
     * durchgesetzt wird.
     */
    public function test_publishing_a_node_draft_writes_rich_content_and_the_learner_sees_briefing_and_write_up(): void
    {
        $themenfeld = Themenfeld::factory()->create();
        // 'body' => '' wie eine echte, per Studio angelegte Node
        // (StudioNodeController::store()) -- NodeController::show()s
        // 404-Torwaechter prueft weiterhin `body !== null` (Legacy-Gate,
        // unangetastet von CMS-7d.3), eine leere-aber-nicht-null Node
        // besteht ihn wie in der echten Anlage auch.
        $node = Node::factory()->create(['slug' => 'restore-test-node', 'themenfeld_id' => $themenfeld->id, 'status' => 'published', 'body' => '']);
        $activity = Activity::factory()->create(['type' => 'node', 'key' => 'restore-test-node']);
        $reviewer = User::factory()->reviewer()->create();

        $payload = [
            'title' => 'Titel', 'scenario_title' => 'Szenario', 'difficulty' => 'easy',
            'points' => 10, 'category' => 'netzwerk', 'interaction' => 'terminal',
            'estimated_minutes' => 15, 'skills' => [], 'related_lessons' => [],
            'hints' => [['id' => 'h1', 'cost' => 1]],
            'rich_content' => [
                'type' => 'node_content', 'version' => 1,
                'briefing' => $this->validRichContent('Briefing-Text.'),
                'hints' => ['h1' => $this->richContent('Hinweis-Text.')],
                'write_up' => $this->richContent('Write-up-Text.'),
            ],
        ];

        $version = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => $payload,
            'is_current' => false, 'created_by' => $reviewer->id,
        ]);

        $issues = app(ContentPublishingService::class)->publish($version, $reviewer);
        $this->assertSame([], $issues);

        $node->refresh();
        $this->assertSame('Briefing-Text.', $node->rich_content['briefing']['content'][0]['content'][0]['text']);

        Http::fake([
            '*/v1/sessions' => Http::response(['session_id' => 'sess-1', 'state' => $this->engineState()], 201),
            '*/v1/sessions/sess-1/state' => Http::response($this->engineState()),
        ]);

        $learner = User::factory()->create();
        $this->actingAs($learner)
            ->get('/de/nodes/restore-test-node')
            ->assertInertia(fn ($page) => $page
                ->where('briefing_html', fn (string $html) => str_contains($html, 'Briefing-Text.')),
            );
    }

    /**
     * CMS-8a, Betreiber-Review vor #128: ein Lab-Entwurf laeuft durch
     * denselben atomaren Publish-Pfad wie Lesson/Node -- LabContentPublisher
     * wird ueber ActivityContentApplier erreicht, nicht direkt aufgerufen.
     */
    public function test_publishing_a_lab_draft_writes_it_via_content_publishing_service_and_the_learner_sees_the_briefing(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $lab = Lab::factory()->create(['slug' => 'c-echo-connectivity', 'status' => 'published']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $reviewer = User::factory()->reviewer()->create();

        $payload = [
            'title' => 'C-ECHO Connectivity Lab', 'scenario_title' => 'Verbindung pruefen',
            'difficulty' => 'easy', 'points' => 10, 'estimated_minutes' => 10,
            'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu']],
            'rich_content' => $this->richContent('Briefing-Text.'),
        ];

        $version = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => $payload,
            'is_current' => false, 'created_by' => $reviewer->id,
        ]);

        $issues = app(ContentPublishingService::class)->publish($version, $reviewer);
        $this->assertSame([], $issues);

        $lab->refresh();
        $this->assertSame('C-ECHO Connectivity Lab', $lab->title['de']);
        $this->assertSame('Briefing-Text.', $lab->rich_content['content'][0]['content'][0]['text']);

        $learner = User::factory()->create();
        $this->actingAs($learner)
            ->get('/de/labs/c-echo-connectivity')
            ->assertInertia(fn ($page) => $page
                ->where('briefing_html', fn (string $html) => str_contains($html, 'Briefing-Text.')),
            );
    }

    /**
     * CMS-8c, Betreiber-Korrektur: ein Draft ohne runtime_template/dataset/
     * assertions bleibt jederzeit speicherbar (ContentVersioningService::
     * createDraft() validiert nicht), aber publish() liefert Issues zurueck
     * und veroeffentlicht NICHTS -- die Publish-Domain-Grenze aus
     * LabActivity::validate() gilt genauso wie fuer jeden anderen Fehler.
     */
    public function test_publishing_an_incomplete_lab_draft_returns_issues_and_changes_nothing(): void
    {
        $lab = Lab::factory()->create(['slug' => 'c-echo-connectivity', 'status' => 'published', 'title' => ['de' => 'Alt']]);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $reviewer = User::factory()->reviewer()->create();

        $incompletePayload = [
            'title' => 'Neu', 'scenario_title' => 'Szenario', 'difficulty' => 'easy',
            'points' => 10, 'estimated_minutes' => 10,
            'runtime_template' => null, 'dataset' => null, 'assertions' => [],
            'rich_content' => $this->richContent('Text.'),
        ];

        $version = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => $incompletePayload,
            'is_current' => false, 'created_by' => $reviewer->id,
        ]);

        $issues = app(ContentPublishingService::class)->publish($version, $reviewer);

        $this->assertNotEmpty($issues);
        $this->assertSame('Alt', $lab->fresh()->title['de']);
        $this->assertSame('review', $version->fresh()->status);
        $this->assertFalse($version->fresh()->is_current);
    }

    /**
     * CMS-8a, Betreiber-Review vor #128: dieselbe Wiederherstellungs-Logik
     * wie fuer Lesson/Node -- restoreVersion() normalisiert (hier ein
     * No-Op, da Lab nichts normalisiert), validiert erneut gegen die
     * heutigen Regeln und wendet ueber denselben ActivityContentApplier an.
     */
    public function test_restoring_a_published_lab_version_reapplies_it_via_content_publishing_service(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'status' => 'published']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $historicalAuthor = User::factory()->create();
        $performer = User::factory()->reviewer()->create();

        $currentPayload = [
            'title' => 'Aktuell', 'scenario_title' => 'Szenario', 'difficulty' => 'easy',
            'points' => 10, 'estimated_minutes' => 10,
            'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu']],
            'rich_content' => $this->richContent('Aktuell.'),
        ];
        $historicalPayload = [
            'title' => 'Historisch', 'scenario_title' => 'Szenario', 'difficulty' => 'easy',
            'points' => 10, 'estimated_minutes' => 10,
            'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu']],
            'rich_content' => $this->richContent('Historisch.'),
        ];

        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published', 'payload' => $currentPayload,
            'is_current' => true, 'created_by' => $historicalAuthor->id, 'reviewed_by' => $historicalAuthor->id,
        ]);
        $historical = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'published', 'payload' => $historicalPayload,
            'is_current' => false, 'created_by' => $historicalAuthor->id, 'reviewed_by' => $historicalAuthor->id,
        ]);

        $restored = app(ContentPublishingService::class)->restoreVersion($historical, $performer);

        $this->assertTrue($restored->is_current);
        $this->assertSame($performer->id, $restored->created_by);
        $this->assertSame($performer->id, $restored->reviewed_by);
        $this->assertSame('Historisch', Lab::where('slug', 'c-echo-connectivity')->value('title')['de'] ?? null);
    }

    /**
     * Reproduziert exakt den Fund aus PR #178 (Lesson 4.1, ContentVersion
     * #22/#23): ein Reviewer hatte einen aelteren, inhaltlich ueberholten
     * Review-Entwurf uebersehen koennen und ihn versehentlich AUCH NACH der
     * Veroeffentlichung der korrigierten Fassung noch veroeffentlichen
     * koennen -- `ContentVersioningService::publish()` supersediert seit
     * diesem Fix jede andere offene draft-/review-Version derselben
     * Aktivitaet in derselben Transaktion, in der die ausgewaehlte Version
     * veroeffentlicht wird.
     */
    public function test_publishing_a_lesson_draft_supersedes_an_older_stale_review_version_of_the_same_lesson(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['track_id' => $track->id, 'title' => ['de' => 'Alt'], 'body' => 'Alt.']);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => $lesson->lesson_id]);
        $author = User::factory()->author()->create();
        $reviewer = User::factory()->reviewer()->create();

        $staleReview = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review',
            'payload' => $this->lessonDraftPayload('Veraltete, ueberholte Fassung.'),
            'is_current' => false, 'created_by' => $author->id,
        ]);
        $correctedReview = ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review',
            'payload' => $this->lessonDraftPayload('Korrigierte, finale Fassung.'),
            'is_current' => false, 'created_by' => $author->id,
        ]);

        $issues = app(ContentPublishingService::class)->publish($correctedReview, $reviewer);

        $this->assertSame([], $issues);
        $this->assertSame('published', $correctedReview->fresh()->status);
        $this->assertTrue($correctedReview->fresh()->is_current);
        $this->assertSame('superseded', $staleReview->fresh()->status, 'die veraltete Fassung darf nach der Veroeffentlichung der korrigierten nicht mehr publizierbar sein.');
        $this->assertSame('Korrigierte, finale Fassung.', $lesson->fresh()->rich_content['content'][0]['content'][0]['text']);

        // Requirement 3: eine superseded Version laesst sich nicht mehr
        // veroeffentlichen -- selbst wenn jemand sie danach noch anklickt.
        // `ContentVersioningService::publish()` wirft dabei (derselbe
        // Fehlerpfad wie fuer einen 'draft'-Status, siehe
        // test_publish_rolls_back_the_live_write_when_the_version_flip_fails_afterwards
        // oben) -- die Transaktion rollt den zwischenzeitlichen
        // `ActivityContentApplier::apply()`-Schreibvorgang deshalb zurueck.
        try {
            app(ContentPublishingService::class)->publish($staleReview->fresh(), $reviewer);
            $this->fail('publish() einer superseded Version haette werfen muessen.');
        } catch (RuntimeException) {
            // erwartet: "Nur eine Version im Review-Status kann veroeffentlicht werden."
        }

        $this->assertSame('superseded', $staleReview->fresh()->status, 'bleibt superseded, nicht published.');
        $this->assertSame('Korrigierte, finale Fassung.', $lesson->fresh()->rich_content['content'][0]['content'][0]['text'], 'die veraltete Fassung darf die korrigierte nicht mehr ueberschreiben koennen.');
    }

    private function engineState(): array
    {
        return [
            'node_slug' => 'restore-test-node',
            'hosts' => ['workstation' => ['ip' => '10.0.0.50', 'role' => 'shell']],
            'hints_used' => [],
            'write_up_seen' => false,
            'solved' => false,
            'points' => 10,
            'stuck' => false,
            'created_at' => now()->toIso8601String(),
        ];
    }
}

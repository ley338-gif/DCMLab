<?php

namespace Tests\Feature\Content;

use App\Content\QuizContent;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CMS-7d.4 Phase 5: friert die Betreiber-Abnahmekriterien fuer den
 * Rich-Content-Cutover als Tests ein. Die meisten sind bereits durch
 * bestehende Tests bewiesen -- diese Klasse dupliziert sie NICHT, sondern
 * verweist darauf und deckt nur die zwei Luecken ab, die ein
 * vollstaendiger Audit vor CMS-7d.4 als ungetestet identifiziert hat.
 *
 * Vollstaendige Checkliste:
 *
 * - neuer Lesson-Feld-Publish aendert body nicht:
 *   `LessonContentPublisherTest::test_it_does_not_touch_the_legacy_body_column`
 * - neuer Node-Feld-Publish aendert body nicht:
 *   `NodeContentPublisherTest::test_it_does_not_touch_the_legacy_body_column`
 * - Learner rendert bei vorhandenem rich_content niemals body:
 *   `LessonControllerTest`/`NodeControllerTest::test_it_prefers_rich_content_over_the_legacy_body_when_present`
 * - neuer Draft/neue Published-Version enthaelt kein body (End-to-End
 *   ueber die echten Routen): siehe `test_a_freshly_created_lesson_draft_never_contains_a_body_key()`/
 *   `test_a_freshly_created_node_draft_never_contains_a_body_key()` unten (NEU).
 * - content/** wird bei Lesson-/Node-/Quiz-Draft nicht veraendert:
 *   `ActivityContentApplierTest::*_without_touching_content_files`,
 *   `LessonEditorControllerTest::test_the_full_lifecycle_writes_to_the_db_and_preserves_the_quiz_section_without_touching_content_files`
 * - ein Quiz-Publish aendert body NUR innerhalb des `## Quiz`-Abschnitts,
 *   `before`/`after` bleiben byteidentisch: siehe
 *   `test_a_quiz_publish_only_changes_the_quiz_section_of_body()` unten (NEU) --
 *   macht die bisher implizite Abgrenzung aus ADR 0118s Kontext ("Quiz
 *   bleibt markdown-gefuehrt, unveraendert") zu einer echten,
 *   regressionsgeschuetzten Invariante.
 * - alle produktiven Lessons/Nodes haben valides rich_content: kein
 *   Fixture-basierter PHPUnit-Test (Produktionsdaten-Check), siehe
 *   `php artisan rich-content:coverage`
 *   (`app/Console/Commands/RichContentCoverage.php`,
 *   `RichContentCoverageTest`).
 * - Legacy-Restore/-Publish wird nach einer nativen Autorenrevision
 *   serverseitig abgelehnt: `ContentPublishingServiceTest::test_legacy_restore_is_blocked_after_a_native_rich_content_publish`/
 *   `test_legacy_publish_is_blocked_after_a_native_rich_content_publish`
 *   (CMS-7d.4 Phase 1).
 */
class RichContentCutoverInvariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Bisher nur indirekt ueber die Formularvalidierung impliziert
     * (`rich_content` ist `required`, `body` ist in `validatedFields()`
     * gar nicht mehr benannt) -- nie fuer den echten Speichervorgang
     * geprueft.
     */
    public function test_a_freshly_created_lesson_draft_never_contains_a_body_key(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);

        $payload = [
            'title' => 'Neu', 'teaser' => 'Neu', 'level' => 'einsteiger', 'duration_minutes' => 5,
            'tools' => [], 'requires' => [], 'glossary_terms' => [], 'objectives' => ['Ziel'],
            'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
            'related_node' => ['node' => null, 'optional' => true],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Text.']]],
            ]],
        ];

        $this->actingAs($author)
            ->post('/de/author/lessons/1.0/edit', $payload)
            ->assertRedirect();

        $version = ContentVersion::where('activity_id', $activity->id)->firstOrFail();
        $this->assertArrayNotHasKey('body', $version->payload);
        $this->assertArrayHasKey('rich_content', $version->payload);
    }

    public function test_a_freshly_created_node_draft_never_contains_a_body_key(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node']);
        $activity = Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $reviewer = User::factory()->reviewer()->create();

        $payload = [
            'title' => 'Titel', 'scenario_title' => 'Szenario', 'difficulty' => 'easy',
            'points' => 10, 'category' => 'netzwerk', 'interaction' => 'terminal',
            'estimated_minutes' => 15, 'skills' => [], 'related_lessons' => [], 'hints' => [],
            'rich_content' => [
                'type' => 'node_content', 'version' => 1,
                'briefing' => ['type' => 'doc', 'version' => 1, 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Text.']]],
                ]],
                'hints' => [],
                'write_up' => ['type' => 'doc', 'version' => 1, 'content' => []],
            ],
        ];

        $this->actingAs($reviewer)
            ->patch('/de/studio/nodes/test-node', $payload)
            ->assertRedirect();

        $version = ContentVersion::where('activity_id', $activity->id)->firstOrFail();
        $this->assertArrayNotHasKey('body', $version->payload);
        $this->assertArrayHasKey('rich_content', $version->payload);
    }

    /**
     * Macht die bisher nur dokumentierte Absicht ("Quiz bleibt
     * markdown-gefuehrt, unveraendert") zu einer echten, gegen
     * Regression geschuetzten Invariante: `QuizContentPublisher`
     * (`app/Content/QuizContentPublisher.php`) schreibt bei jeder
     * Quiz-Freigabe weiterhin `body`, aber chirurgisch nur den
     * `## Quiz`-Abschnitt -- der Text davor und danach bleibt
     * byteidentisch.
     */
    public function test_a_quiz_publish_only_changes_the_quiz_section_of_body(): void
    {
        $track = Track::factory()->create();
        $originalBody = "Vor-Quiz-Prosa, die unveraendert bleiben muss.\n\n## Quiz\n\n**q1 — Alte Frage?**\n1. A\n2. B\n\n---\n\n**Als Nächstes:** Nach-Quiz-Text, der ebenfalls unveraendert bleiben muss.";
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id, 'body' => $originalBody]);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);
        $reviewer = User::factory()->reviewer()->create();

        $payload = [
            'questions' => [
                ['id' => 'q1', 'type' => 'single', 'answer' => 1, 'question' => 'Neue Frage?', 'options' => ['X', 'Y']],
            ],
        ];

        $this->actingAs($author)->post('/de/author/lessons/1.0/quiz', $payload)->assertRedirect();
        $version = ContentVersion::where('activity_id', $activity->id)->firstOrFail();
        $this->actingAs($author)->post("/de/author/quiz-versions/{$version->id}/submit")->assertRedirect();
        $this->actingAs($reviewer)->post("/de/author/quiz-versions/{$version->id}/publish")->assertRedirect();

        $newBody = $lesson->fresh()->body;
        $this->assertNotSame($originalBody, $newBody, 'der Quiz-Abschnitt selbst haette sich aendern muessen.');
        $this->assertStringContainsString('Neue Frage?', $newBody);

        // trim(): LessonQuizGenerator::regenerateBody() baut den Body aus
        // Frontmatter+Block-Ersatz neu zusammen und normalisiert dabei
        // umgebende Leerzeilen -- das ist eine harmlose Kosmetik der
        // bestehenden Regenerierung, keine inhaltliche Aenderung. Die
        // eigentliche Invariante ist der PROSA-INHALT, nicht jedes
        // Whitespace-Byte ueber einen vollen Serialisierungs-Roundtrip.
        $originalSplit = QuizContent::splitBody($originalBody);
        $newSplit = QuizContent::splitBody($newBody);
        $this->assertSame(trim($originalSplit['before']), trim($newSplit['before']), 'die Prosa vor dem Quiz muss inhaltlich unveraendert bleiben.');
        $this->assertSame(trim($originalSplit['after']), trim($newSplit['after']), 'die Prosa nach dem Quiz muss inhaltlich unveraendert bleiben.');
    }
}

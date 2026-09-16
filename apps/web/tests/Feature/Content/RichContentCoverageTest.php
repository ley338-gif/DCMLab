<?php

namespace Tests\Feature\Content;

use App\Models\Lesson;
use App\Models\Node;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * `rich-content:coverage` (CMS-7d.4): ein leichtgewichtiger Healthcheck,
 * kein Migrations-Preflight -- liest nur `rich_content`, das bereits
 * gesetzt ist, und prueft es gegen `RichContentValidator`. "present"
 * (nicht NULL) und "valid" (besteht die Validierung) sind bewusst zwei
 * getrennte Zahlen: eine Invariante wie "alle produktiven Lessons/Nodes
 * haben VALIDES rich_content" ist staerker als "ist nicht NULL".
 */
class RichContentCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function validDoc(string $text): array
    {
        return ['type' => 'doc', 'version' => 1, 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        ]];
    }

    public function test_reports_full_coverage_when_every_lesson_and_node_has_valid_rich_content(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['track_id' => $track->id, 'rich_content' => $this->validDoc('Prosa.')]);
        Node::factory()->create(['rich_content' => [
            'type' => 'node_content', 'version' => 1,
            'briefing' => $this->validDoc('Briefing.'),
            'hints' => ['h1' => $this->validDoc('Hint.')],
            'write_up' => $this->validDoc('Write-up.'),
        ]]);

        Artisan::call('rich-content:coverage');
        $output = Artisan::output();

        $this->assertStringContainsString('rich_content present: 1/1', $output);
        $this->assertStringContainsString('rich_content valid:   1/1', $output);
    }

    public function test_distinguishes_present_from_valid(): void
    {
        $track = Track::factory()->create();
        // Vorhanden, aber strukturell ungueltig (falscher doc.type).
        Lesson::factory()->create(['track_id' => $track->id, 'rich_content' => ['type' => 'not-a-doc', 'version' => 1, 'content' => []]]);
        Lesson::factory()->create(['track_id' => $track->id, 'rich_content' => null]);

        // Node mit einem ungueltigen Hint -- die ganze Node zaehlt dadurch
        // nicht als "valid", auch wenn briefing/write_up fuer sich allein
        // bestehen wuerden.
        Node::factory()->create(['rich_content' => [
            'type' => 'node_content', 'version' => 1,
            'briefing' => $this->validDoc('Briefing.'),
            'hints' => ['h1' => ['type' => 'not-a-doc']],
            'write_up' => $this->validDoc('Write-up.'),
        ]]);

        Artisan::call('rich-content:coverage');
        $output = Artisan::output();

        $this->assertStringContainsString('rich_content present: 1/2', $output);
        $this->assertStringContainsString('rich_content valid:   0/2', $output);
        $this->assertStringContainsString('rich_content present: 1/1', $output);
        $this->assertStringContainsString('rich_content valid:   0/1', $output);
    }
}

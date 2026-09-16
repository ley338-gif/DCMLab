<?php

namespace Tests\Unit\Content;

use App\Content\LabContentPublisher;
use App\Models\Activity;
use App\Models\Lab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CMS-8a: wendet einen Lab-Entwurf direkt auf die DB an -- kein Datei-
 * Schreibvorgang, kein content:sync (ein Lab hat kein content/**-Pendant).
 */
class LabContentPublisherTest extends TestCase
{
    use RefreshDatabase;

    private function richContent(string $text): array
    {
        return ['type' => 'doc', 'version' => 1, 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        ]];
    }

    public function test_it_applies_every_field_directly_to_the_lab_row(): void
    {
        $lab = Lab::factory()->create([
            'slug' => 'c-echo-connectivity',
            'title' => ['de' => 'Alt'],
            'status' => 'draft',
        ]);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);

        (new LabContentPublisher)->publish($activity, [
            'title' => 'C-ECHO Connectivity Lab',
            'scenario_title' => 'Verbindung pruefen',
            'difficulty' => 'easy',
            'points' => 10,
            'estimated_minutes' => 10,
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => null,
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu']],
            'rich_content' => $this->richContent('Anleitungstext.'),
        ]);

        $lab->refresh();
        $this->assertSame('published', $lab->status);
        $this->assertSame('C-ECHO Connectivity Lab', $lab->title['de']);
        $this->assertSame('Verbindung pruefen', $lab->scenario_title['de']);
        $this->assertSame('easy', $lab->difficulty);
        $this->assertSame(10, $lab->points);
        $this->assertSame(10, $lab->estimated_minutes);
        $this->assertSame('dicom-basic-tools', $lab->runtime_template);
        $this->assertNull($lab->dataset);
        $this->assertSame([['type' => 'command_executed', 'prefix' => 'echoscu']], $lab->assertions);
        $this->assertSame('Anleitungstext.', $lab->rich_content['content'][0]['content'][0]['text']);
    }

    public function test_it_does_not_unarchive_a_lab_through_publishing(): void
    {
        $lab = Lab::factory()->create(['slug' => 'test-lab', 'status' => 'archived']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'test-lab']);

        (new LabContentPublisher)->publish($activity, [
            'title' => 'Neu', 'scenario_title' => 'Szenario', 'difficulty' => 'easy',
            'points' => 10, 'estimated_minutes' => 10, 'assertions' => [],
            'rich_content' => $this->richContent('Text.'),
        ]);

        $this->assertSame('archived', $lab->fresh()->status);
    }

    public function test_it_keeps_the_activity_row_title_in_sync(): void
    {
        $lab = Lab::factory()->create(['slug' => 'test-lab']);
        $activity = Activity::factory()->create([
            'type' => 'lab',
            'key' => 'test-lab',
            'title' => ['de' => 'Alt'],
            'source_hash' => 'alt',
        ]);

        (new LabContentPublisher)->publish($activity, [
            'title' => 'Neuer Aktivitaetstitel', 'scenario_title' => 'Szenario', 'difficulty' => 'easy',
            'points' => 10, 'estimated_minutes' => 10, 'assertions' => [],
            'rich_content' => $this->richContent('Text.'),
        ]);

        $activity->refresh();
        $this->assertSame('Neuer Aktivitaetstitel', $activity->title['de']);
        $this->assertNotSame('alt', $activity->source_hash);
    }
}

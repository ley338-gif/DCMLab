<?php

namespace Tests\Unit\Content;

use App\Content\NodeContentPublisher;
use App\Models\Activity;
use App\Models\Node;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR 0108 (CMS-6d Teil 2): wendet einen Node-Entwurf direkt auf die DB an
 * -- kein Datei-Schreibvorgang, kein content:sync.
 */
class NodeContentPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_every_field_directly_to_the_node_row(): void
    {
        $node = Node::factory()->create([
            'slug' => 'test-node',
            'title' => ['de' => 'Alt'],
            'scenario_title' => ['de' => 'Alt'],
            'difficulty' => 'easy',
            'points' => 10,
            'body' => 'Alter Text.',
        ]);
        $activity = Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);

        (new NodeContentPublisher)->publish($activity, [
            'title' => 'Neu',
            'scenario_title' => 'Neues Szenario',
            'difficulty' => 'medium',
            'points' => 25,
            'category' => 'sicherheit',
            'interaction' => 'terminal',
            'estimated_minutes' => 30,
            'skills' => ['netzwerk', 'sicherheit'],
            'related_lessons' => ['1.1'],
            'hints' => [['id' => 'h1', 'cost' => 1]],
            'body' => 'Neuer Text.',
        ]);

        $node->refresh();
        $this->assertSame('Neu', $node->title['de']);
        $this->assertSame('Neues Szenario', $node->scenario_title['de']);
        $this->assertSame('medium', $node->difficulty);
        $this->assertSame(25, $node->points);
        $this->assertSame('sicherheit', $node->category);
        $this->assertSame('terminal', $node->interaction);
        $this->assertSame(30, $node->estimated_minutes);
        $this->assertSame(['netzwerk', 'sicherheit'], $node->skills);
        $this->assertSame(['1.1'], $node->related_lessons);
        $this->assertSame([['id' => 'h1', 'cost' => 1]], $node->hints);
        $this->assertSame('Neuer Text.', $node->body);
    }

    public function test_it_keeps_the_activity_row_title_in_sync(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node']);
        $activity = Activity::factory()->create([
            'type' => 'node',
            'key' => 'test-node',
            'title' => ['de' => 'Alt'],
            'source_hash' => 'alt',
        ]);

        (new NodeContentPublisher)->publish($activity, [
            'title' => 'Neuer Aktivitaetstitel', 'scenario_title' => 'Szenario', 'difficulty' => 'easy',
            'points' => 10, 'category' => 'netzwerk', 'interaction' => 'terminal', 'estimated_minutes' => 15,
            'skills' => [], 'related_lessons' => [], 'hints' => [], 'body' => 'Text.',
        ]);

        $activity->refresh();
        $this->assertSame('Neuer Aktivitaetstitel', $activity->title['de']);
        $this->assertNotSame('alt', $activity->source_hash);
    }
}

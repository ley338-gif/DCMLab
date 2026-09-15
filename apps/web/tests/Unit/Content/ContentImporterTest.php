<?php

namespace Tests\Unit\Content;

use App\Activities\LessonActivity;
use App\Content\ContentImporter;
use App\Content\ContentRepository;
use App\Models\Lesson;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_draft_returns_the_activitys_normalized_fields(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        $activity = new LessonActivity($lesson, new ContentRepository(base_path('tests/Fixtures/content-real')));

        $draft = (new ContentImporter)->importDraft($activity);

        $this->assertSame('1.0', $draft['lesson_id']);
        $this->assertSame('fundamente', $draft['track']);
    }

    public function test_import_all_indexes_drafts_by_activity_key(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $content = new ContentRepository(base_path('tests/Fixtures/content-real'));
        $activities = [
            new LessonActivity(Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]), $content),
            new LessonActivity(Lesson::factory()->create(['lesson_id' => '1.1', 'track_id' => $track->id]), $content),
        ];

        $drafts = (new ContentImporter)->importAll($activities);

        $this->assertSame(['1.0', '1.1'], array_keys($drafts));
        $this->assertSame('1.1', $drafts['1.1']['lesson_id']);
    }
}

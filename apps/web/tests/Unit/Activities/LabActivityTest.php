<?php

namespace Tests\Unit\Activities;

use App\Activities\LabActivity;
use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\SandboxTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_supports_declares_a_gradeable_container_backed_activity(): void
    {
        $activity = $this->makeActivity();
        $supports = $activity->supports();

        $this->assertTrue($supports->isGraded);
        $this->assertTrue($supports->tracksCompletion);
        $this->assertTrue($supports->needsContainer);
        $this->assertTrue($supports->authorable);
        $this->assertTrue($supports->freelyPlaceable);
        $this->assertSame('container', $supports->runtimeType);
        $this->assertTrue($supports->versionable);
        $this->assertTrue($supports->reusable);
    }

    public function test_activity_type_and_key_come_from_the_lab_slug(): void
    {
        $activity = $this->makeActivity(['slug' => 'c-echo-connectivity']);

        $this->assertSame('lab', $activity->activityType());
        $this->assertSame('c-echo-connectivity', $activity->key());
    }

    public function test_learner_view_reports_not_started_without_an_attempt(): void
    {
        $activity = $this->makeActivity();
        $user = User::factory()->create();

        $view = $activity->learnerView($user);

        $this->assertSame('not_started', $view['status']);
        $this->assertSame('lab', $view['type']);
    }

    public function test_learner_view_reflects_an_existing_attempt(): void
    {
        [$activity, $labModel, $activityModel] = $this->makeActivityWithModel();
        $user = User::factory()->create();

        LabAttempt::create([
            'user_id' => $user->id,
            'activity_id' => $activityModel->id,
            'status' => 'started',
            'started_at' => now(),
        ]);

        $this->assertSame('started', $activity->learnerView($user)['status']);
    }

    public function test_validate_rejects_a_draft_missing_required_fields(): void
    {
        $activity = $this->makeActivity();

        $issues = $activity->validate([
            'title' => '',
            'scenario_title' => 'Szenario',
            'difficulty' => 'easy',
            'points' => 10,
            'estimated_minutes' => 10,
            'assertions' => [],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
        ]);

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'title'),
        ));
    }

    public function test_validate_rejects_an_assertion_without_a_type(): void
    {
        $activity = $this->makeActivity();

        $issues = $activity->validate([
            'title' => 'Titel', 'scenario_title' => 'Szenario', 'difficulty' => 'easy',
            'points' => 10, 'estimated_minutes' => 10,
            'assertions' => [['prefix' => 'echoscu']],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
        ]);

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'assertions[0].type'),
        ));
    }

    public function test_validate_passes_a_well_formed_draft(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $activity = $this->makeActivity();

        $issues = $activity->validate([
            'title' => 'C-ECHO Connectivity Lab', 'scenario_title' => 'Verbindung pruefen',
            'difficulty' => 'easy', 'points' => 10, 'estimated_minutes' => 10,
            'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu']],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
        ]);

        $this->assertSame([], $issues);
    }

    public function test_validate_rejects_a_difficulty_outside_the_closed_catalog(): void
    {
        $activity = $this->makeActivity();

        $issues = $activity->validate($this->validPayload(['difficulty' => 'nightmare']));

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'difficulty'),
        ));
    }

    public function test_validate_rejects_a_missing_runtime_template(): void
    {
        $activity = $this->makeActivity();

        $issues = $activity->validate($this->validPayload(['runtime_template' => null]));

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'runtime_template'),
        ));
    }

    public function test_validate_rejects_an_unknown_runtime_template_slug(): void
    {
        $activity = $this->makeActivity();

        $issues = $activity->validate($this->validPayload(['runtime_template' => 'does-not-exist']));

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'runtime_template'),
        ));
    }

    public function test_validate_rejects_an_unpublished_runtime_template_slug(): void
    {
        SandboxTemplate::factory()->create(['slug' => 'draft-template', 'status' => 'draft']);
        $activity = $this->makeActivity();

        $issues = $activity->validate($this->validPayload(['runtime_template' => 'draft-template']));

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'runtime_template'),
        ));
    }

    public function test_validate_rejects_a_missing_dataset(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $activity = $this->makeActivity();

        $issues = $activity->validate($this->validPayload(['dataset' => null]));

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'dataset'),
        ));
    }

    public function test_validate_rejects_an_unknown_dataset_slug(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $activity = $this->makeActivity();

        $issues = $activity->validate($this->validPayload(['dataset' => 'does-not-exist']));

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'dataset'),
        ));
    }

    public function test_validate_rejects_an_empty_assertions_list(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $activity = $this->makeActivity();

        $issues = $activity->validate($this->validPayload(['assertions' => []]));

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'assertions: muss mindestens einen Eintrag'),
        ));
    }

    public function test_validate_rejects_an_unknown_assertion_type(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $activity = $this->makeActivity();

        $issues = $activity->validate($this->validPayload([
            'assertions' => [['type' => 'c_store_received', 'sop_class' => 'CTImageStorage']],
        ]));

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'assertions[0].type') && str_contains($issue->message, 'unbekannt'),
        ));
    }

    public function test_validate_rejects_a_command_executed_assertion_without_a_prefix(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        $activity = $this->makeActivity();

        $issues = $activity->validate($this->validPayload([
            'assertions' => [['type' => 'command_executed']],
        ]));

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'assertions[0].prefix'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'C-ECHO Connectivity Lab', 'scenario_title' => 'Verbindung pruefen',
            'difficulty' => 'easy', 'points' => 10, 'estimated_minutes' => 10,
            'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu']],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
        ], $overrides);
    }

    public function test_serialize_never_writes_files(): void
    {
        $activity = $this->makeActivity();

        $this->assertSame([], $activity->serialize());
        $this->assertSame([], $activity->serialize(['title' => 'Egal']));
    }

    public function test_deserialize_returns_the_current_lab_fields(): void
    {
        $activity = $this->makeActivity([
            'title' => ['de' => 'C-ECHO Connectivity Lab'],
            'scenario_title' => ['de' => 'Verbindung pruefen'],
            'difficulty' => 'easy',
            'points' => 10,
            'estimated_minutes' => 10,
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu']],
        ]);

        $draft = $activity->deserialize();

        $this->assertSame('C-ECHO Connectivity Lab', $draft['title']);
        $this->assertSame('Verbindung pruefen', $draft['scenario_title']);
        $this->assertSame([['type' => 'command_executed', 'prefix' => 'echoscu']], $draft['assertions']);
    }

    public function test_result_is_null_without_an_attempt(): void
    {
        $activity = $this->makeActivity();
        $user = User::factory()->create();

        $this->assertNull($activity->result($user));
    }

    public function test_result_reflects_a_solved_attempt(): void
    {
        [$activity, $labModel, $activityModel] = $this->makeActivityWithModel(['points' => 25]);
        $user = User::factory()->create();

        LabAttempt::create([
            'user_id' => $user->id,
            'activity_id' => $activityModel->id,
            'status' => 'solved',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $result = $activity->result($user);

        $this->assertNotNull($result);
        $this->assertTrue($result->completed);
        $this->assertSame(25, $result->score);
        $this->assertSame(25, $result->maxScore);
    }

    private function makeActivity(array $labAttributes = []): LabActivity
    {
        return $this->makeActivityWithModel($labAttributes)[0];
    }

    /**
     * @return array{0: LabActivity, 1: Lab, 2: Activity}
     */
    private function makeActivityWithModel(array $labAttributes = []): array
    {
        $lab = Lab::factory()->create($labAttributes);
        $activityModel = Activity::factory()->create(['type' => 'lab', 'key' => $lab->slug]);

        return [new LabActivity($activityModel, $lab, app(ContentRepository::class)), $lab, $activityModel];
    }
}

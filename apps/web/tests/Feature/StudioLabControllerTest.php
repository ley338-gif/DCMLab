<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lab;
use App\Models\SandboxTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lab-Verwaltung in Studio (CMS-8c, nach StudioNodeControllerTest-Vorbild):
 * store()/archive()/restore() sind strukturelle Eingriffe (LabPolicy),
 * edit()/update() bearbeiten den Content-Entwurf (ActivityPolicy).
 */
class StudioLabControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_learner_cannot_view_the_lab_list(): void
    {
        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/de/studio/labs')->assertForbidden();
    }

    public function test_an_author_can_view_but_not_manage_labs(): void
    {
        $author = User::factory()->author()->create();
        Lab::factory()->create(['title' => ['de' => 'C-ECHO Connectivity']]);

        $this->actingAs($author)
            ->get('/de/studio/labs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Studio/Labs/Index')
                ->where('can_manage', false)
                ->has('labs', 1)
                ->where('labs.0.title', 'C-ECHO Connectivity')
            );
    }

    public function test_an_author_cannot_create_a_lab(): void
    {
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->post('/de/studio/labs', ['slug' => 'neues-lab', 'title' => 'Neues Lab', 'difficulty' => 'easy'])
            ->assertForbidden();

        $this->assertSame(0, Lab::query()->count());
    }

    public function test_a_reviewer_can_create_a_lab_with_its_activity_atomically(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post('/de/studio/labs', ['slug' => 'c-echo-connectivity', 'title' => 'C-ECHO Connectivity', 'difficulty' => 'medium'])
            ->assertRedirect('/de/studio/labs/c-echo-connectivity');

        $lab = Lab::query()->where('slug', 'c-echo-connectivity')->first();
        $this->assertNotNull($lab);
        $this->assertSame('draft', $lab->status);
        $this->assertSame('C-ECHO Connectivity', $lab->title['de']);
        $this->assertSame([], $lab->assertions);
        // Betreiber-Korrektur (CMS-8c): RichContentEditor.vue erwartet ein
        // echtes Dokument, nie null.
        $this->assertSame(['type' => 'doc', 'version' => 1, 'content' => []], $lab->rich_content);

        $activity = Activity::query()->where('type', 'lab')->where('key', 'c-echo-connectivity')->first();
        $this->assertNotNull($activity);
        $this->assertSame('draft', $activity->status);
    }

    public function test_a_duplicate_slug_is_rejected(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post('/de/studio/labs', ['slug' => 'c-echo-connectivity', 'title' => 'Duplikat', 'difficulty' => 'easy'])
            ->assertSessionHasErrors('slug');
    }

    public function test_can_manage_and_can_publish_reflect_the_same_rules_as_the_activity_policy(): void
    {
        $lab = Lab::factory()->create(['slug' => 'test-lab']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'test-lab']);

        $reviewer = User::factory()->reviewer()->create();
        $unassignedAuthor = User::factory()->author()->create();
        $assignedAuthor = User::factory()->author()->create();
        $activity->authorUsers()->attach($assignedAuthor);

        $this->actingAs($reviewer)
            ->get("/de/studio/labs/{$lab->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_manage', true)->where('can_publish', true));

        $this->actingAs($unassignedAuthor)
            ->get("/de/studio/labs/{$lab->slug}")
            ->assertForbidden();

        $this->actingAs($assignedAuthor)
            ->get("/de/studio/labs/{$lab->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_manage', false)->where('can_publish', false));
    }

    public function test_edit_shows_the_current_db_state_when_there_is_no_pending_draft(): void
    {
        $lab = Lab::factory()->create(['slug' => 'test-lab', 'title' => ['de' => 'Alt']]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'test-lab']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get("/de/studio/labs/{$lab->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('fields.title', 'Alt')
                ->where('pending_version', null)
            );
    }

    public function test_edit_shows_the_pending_draft_payload_instead_of_the_db_state(): void
    {
        $lab = Lab::factory()->create(['slug' => 'test-lab', 'title' => ['de' => 'Alt']]);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'test-lab']);
        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'draft',
            'payload' => ['title' => 'Entwurfstitel'], 'is_current' => false,
            'created_by' => User::factory()->create()->id,
        ]);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get("/de/studio/labs/{$lab->slug}")
            ->assertInertia(fn ($page) => $page->where('fields.title', 'Entwurfstitel'));
    }

    /**
     * Betreiber-Korrektur (CMS-8c, UX): ein archiviertes/geloeschtes, aber
     * noch im Lab gesetztes Runtime-Template darf nicht kommentarlos aus
     * dem Katalog verschwinden.
     */
    public function test_edit_exposes_the_currently_selected_runtime_template_even_when_no_longer_published(): void
    {
        SandboxTemplate::factory()->create(['slug' => 'stale-template', 'name' => 'Veraltete Vorlage', 'status' => 'draft']);
        SandboxTemplate::factory()->published()->create(['slug' => 'current-template', 'name' => 'Aktuelle Vorlage']);
        $lab = Lab::factory()->create(['slug' => 'test-lab', 'runtime_template' => 'stale-template']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'test-lab']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get("/de/studio/labs/{$lab->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('sandbox_templates', [
                    ['slug' => 'current-template', 'name' => 'Aktuelle Vorlage', 'available' => true],
                    ['slug' => 'stale-template', 'name' => 'Veraltete Vorlage', 'available' => false],
                ])
            );
    }

    public function test_edit_exposes_the_currently_selected_dataset_even_when_no_longer_in_the_catalog(): void
    {
        $lab = Lab::factory()->create(['slug' => 'test-lab', 'dataset' => 'does-not-exist-anymore']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'test-lab']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get("/de/studio/labs/{$lab->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('datasets', fn ($datasets) => collect($datasets)->contains([
                    'slug' => 'does-not-exist-anymore', 'available' => false,
                ]))
            );
    }

    /**
     * Betreiber-Korrektur (CMS-8c): ein Entwurf ohne runtime_template/
     * dataset/assertions bleibt speicherbar -- nur das PUBLISH (getestet in
     * ContentPublishingServiceTest) verlangt sie.
     */
    public function test_saving_an_incomplete_draft_creates_a_content_version_without_touching_the_lab_row(): void
    {
        $lab = Lab::factory()->create(['slug' => 'test-lab', 'title' => ['de' => 'Alt']]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'test-lab']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->patch("/de/studio/labs/{$lab->slug}", $this->validDraftPayload([
                'title' => 'Neuer Entwurfstitel', 'runtime_template' => null, 'dataset' => null, 'assertions' => [],
            ]))
            ->assertRedirect();

        $this->assertSame('Alt', $lab->fresh()->title['de']);
        $this->assertSame(1, ContentVersion::query()->count());
        $this->assertSame('draft', ContentVersion::query()->first()->status);
    }

    public function test_an_unassigned_author_cannot_save_a_draft(): void
    {
        $lab = Lab::factory()->create(['slug' => 'test-lab']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'test-lab']);
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->patch("/de/studio/labs/{$lab->slug}", $this->validDraftPayload())
            ->assertForbidden();

        $this->assertSame(0, ContentVersion::query()->count());
    }

    public function test_the_full_archive_lifecycle(): void
    {
        $lab = Lab::factory()->create(['status' => 'draft']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)->post("/de/studio/labs/{$lab->slug}/archive")->assertRedirect();
        $this->assertSame('archived', $lab->fresh()->status);

        $this->actingAs($reviewer)->post("/de/studio/labs/{$lab->slug}/restore")->assertRedirect();
        $this->assertSame('draft', $lab->fresh()->status);
    }

    public function test_an_author_cannot_archive_a_lab(): void
    {
        $lab = Lab::factory()->create(['status' => 'draft']);
        $author = User::factory()->author()->create();

        $this->actingAs($author)->post("/de/studio/labs/{$lab->slug}/archive")->assertForbidden();
        $this->assertSame('draft', $lab->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validDraftPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Titel', 'scenario_title' => 'Szenario', 'difficulty' => 'easy',
            'points' => 10, 'estimated_minutes' => 15,
            'runtime_template' => 'dicom-basic-tools', 'dataset' => 'ct-thorax-60',
            'assertions' => [['type' => 'command_executed', 'prefix' => 'echoscu']],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
        ], $overrides);
    }
}

<?php

namespace Tests\Feature;

use App\Models\SandboxTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Erste echte Studio-Ressourcenseite (ADR 0099, CMS-3b): der
 * sandbox_templates-Katalog (ADR 0096) hatte bisher keine Oberflaeche.
 */
class StudioSandboxTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_learner_cannot_view_the_catalog(): void
    {
        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/de/studio/sandbox-templates')->assertForbidden();
    }

    public function test_an_author_can_view_but_not_manage_the_catalog(): void
    {
        $author = User::factory()->author()->create();
        SandboxTemplate::factory()->create(['name' => 'DICOM Basic Tools']);

        $this->actingAs($author)
            ->get('/de/studio/sandbox-templates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Studio/SandboxTemplates')
                ->where('can_manage', false)
                ->has('templates', 1)
                ->where('templates.0.name', 'DICOM Basic Tools')
            );
    }

    public function test_an_author_cannot_create_a_template(): void
    {
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->post('/de/studio/sandbox-templates', [
                'slug' => 'new-template', 'name' => 'Neue Vorlage',
            ])
            ->assertForbidden();

        $this->assertSame(0, SandboxTemplate::query()->count());
    }

    public function test_a_reviewer_can_create_a_template_as_a_draft(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post('/de/studio/sandbox-templates', [
                'slug' => 'dicom-advanced-tools',
                'name' => 'DICOM Advanced Tools',
                'description' => 'Zweite Werkzeugsammlung.',
            ])
            ->assertRedirect();

        $template = SandboxTemplate::query()->where('slug', 'dicom-advanced-tools')->first();
        $this->assertNotNull($template);
        $this->assertSame('draft', $template->status);
        $this->assertSame('docker', $template->runtime_provider);
    }

    public function test_a_duplicate_slug_is_rejected(): void
    {
        SandboxTemplate::factory()->create(['slug' => 'dicom-basic-tools']);
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post('/de/studio/sandbox-templates', [
                'slug' => 'dicom-basic-tools', 'name' => 'Duplikat',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_an_administrator_can_publish_a_template(): void
    {
        $administrator = User::factory()->administrator()->create();
        $template = SandboxTemplate::factory()->create(['status' => 'draft']);

        $this->actingAs($administrator)
            ->patch("/de/studio/sandbox-templates/{$template->id}", [
                'name' => $template->name,
                'description' => $template->description,
                'status' => 'published',
            ])
            ->assertRedirect();

        $this->assertSame('published', $template->fresh()->status);
    }

    public function test_an_author_cannot_update_a_template(): void
    {
        $author = User::factory()->author()->create();
        $template = SandboxTemplate::factory()->create(['status' => 'draft']);

        $this->actingAs($author)
            ->patch("/de/studio/sandbox-templates/{$template->id}", [
                'name' => 'Umbenannt', 'status' => 'published',
            ])
            ->assertForbidden();

        $this->assertSame('draft', $template->fresh()->status);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $template = SandboxTemplate::factory()->create();

        $this->actingAs($reviewer)
            ->patch("/de/studio/sandbox-templates/{$template->id}", [
                'name' => $template->name, 'status' => 'archived',
            ])
            ->assertSessionHasErrors('status');
    }
}

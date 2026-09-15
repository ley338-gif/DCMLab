<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Der Achievement-Editor (ADR 0071/0083/0088, W6.4): ein einzelnes
 * Achievement ohne Kommandozeile anlegen oder bearbeiten, denselben
 * Kreislauf wie die vorherigen Editoren (ADR 0080/0081/0082)
 * wiederverwendend. Der Bild-Upload (ADR 0088) schreibt direkt nach
 * `public/images/achievements/`, siehe Klassendoc von
 * AchievementEditorController.
 */
class AchievementEditorControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    /** @var list<string> */
    private array $uploadedTestImages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->contentDir = storage_path('framework/testing/achievement-editor-'.Str::random(12));
        File::ensureDirectoryExists($this->contentDir);
        File::put(
            $this->contentDir.'/achievements.yml',
            "- slug: first-blood\n  name: First Blood\n  description: Alte Beschreibung.\n  image: first-blood.png\n  category: labs\n  points: 0\n  is_hidden: false\n  sort_order: 10\n",
        );
        $this->app->instance(ContentRepository::class, new ContentRepository($this->contentDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        foreach ($this->uploadedTestImages as $filename) {
            File::delete(public_path('images/achievements/'.$filename));
        }

        parent::tearDown();
    }

    public function test_a_learner_cannot_open_the_editor(): void
    {
        $this->makeActivity();
        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/de/author/achievements/first-blood/edit')->assertForbidden();
    }

    public function test_an_assigned_author_sees_the_current_fields_of_an_existing_achievement(): void
    {
        [, $author] = $this->makeActivity();

        $this->actingAs($author)
            ->get('/de/author/achievements/first-blood/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Author/AchievementEditor')
                ->where('is_new', false)
                ->where('fields.name', 'First Blood')
                ->where('fields.description', 'Alte Beschreibung.')
            );
    }

    public function test_a_slug_that_does_not_exist_yet_starts_with_defaults(): void
    {
        [, $author] = $this->makeActivity();

        $this->actingAs($author)
            ->get('/de/author/achievements/brand-new/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('is_new', true)
                ->where('fields.slug', 'brand-new')
                ->where('fields.name', '')
            );
    }

    public function test_the_full_lifecycle_edits_the_named_slug_and_preserves_the_other_entry(): void
    {
        [$activity, $author] = $this->makeActivity();
        File::append(
            $this->contentDir.'/achievements.yml',
            "\n- slug: echo-heard\n  name: Echo Heard\n  description: Test.\n  image: echo-heard.png\n  category: dicom\n  points: 0\n  is_hidden: false\n  sort_order: 30\n",
        );
        $reviewer = User::factory()->reviewer()->create();

        $payload = [
            'name' => 'Neuer Name',
            'description' => 'Neue Beschreibung.',
            'image' => 'first-blood.png',
            'category' => 'labs',
            'points' => 5,
            'is_hidden' => false,
            'sort_order' => 10,
            'unlock_when' => ['type' => 'first_solve', 'activity_type' => 'node'],
        ];

        $this->actingAs($author)
            ->postJson('/de/author/achievements/first-blood/edit/validate', $payload)
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('issues', [])->etc());

        $this->actingAs($author)
            ->post('/de/author/achievements/first-blood/edit', $payload)
            ->assertRedirect();

        $version = ContentVersion::where('activity_id', $activity->id)->firstOrFail();

        $this->actingAs($author)->post("/de/author/quiz-versions/{$version->id}/submit")->assertRedirect();
        $this->actingAs($reviewer)->post("/de/author/quiz-versions/{$version->id}/publish")->assertRedirect();

        $this->assertSame('published', $version->refresh()->status);

        $written = File::get($this->contentDir.'/achievements.yml');
        $this->assertStringContainsString('Neuer Name', $written);
        $this->assertStringContainsString('echo-heard', $written, 'Das andere Achievement muss erhalten bleiben.');
        $this->assertStringContainsString('Echo Heard', $written, 'Das andere Achievement muss erhalten bleiben.');
    }

    public function test_a_learner_cannot_upload_an_image(): void
    {
        $this->makeActivity();
        $learner = User::factory()->create();
        $file = UploadedFile::fake()->image('badge.png', 10, 10);

        $this->actingAs($learner)
            ->post('/de/author/achievements/first-blood/edit/image', ['image' => $file])
            ->assertForbidden();
    }

    public function test_an_assigned_author_can_upload_a_valid_image(): void
    {
        [, $author] = $this->makeActivity();
        $slug = 'test-upload-'.Str::lower(Str::random(8));
        $this->uploadedTestImages[] = "{$slug}.png";
        $file = UploadedFile::fake()->image('badge.png', 10, 10);

        $response = $this->actingAs($author)
            ->post("/de/author/achievements/{$slug}/edit/image", ['image' => $file])
            ->assertOk();

        $response->assertJson(['filename' => "{$slug}.png"]);
        $this->assertFileExists(public_path("images/achievements/{$slug}.png"));
    }

    public function test_uploading_a_non_image_file_is_rejected(): void
    {
        [, $author] = $this->makeActivity();
        $file = UploadedFile::fake()->create('not-an-image.pdf', 10);

        $this->actingAs($author)
            ->post('/de/author/achievements/first-blood/edit/image', ['image' => $file])
            ->assertSessionHasErrors('image');
    }

    public function test_uploading_an_oversized_image_is_rejected(): void
    {
        [, $author] = $this->makeActivity();
        $file = UploadedFile::fake()->create('too-big.png', 600, 'image/png');

        $this->actingAs($author)
            ->post('/de/author/achievements/first-blood/edit/image', ['image' => $file])
            ->assertSessionHasErrors('image');
    }

    public function test_the_uploaded_filename_ignores_the_client_supplied_name_and_uses_the_slug(): void
    {
        [, $author] = $this->makeActivity();
        $slug = 'test-upload-'.Str::lower(Str::random(8));
        $this->uploadedTestImages[] = "{$slug}.png";
        $file = UploadedFile::fake()->image('../../evil.png', 10, 10);

        $this->actingAs($author)
            ->post("/de/author/achievements/{$slug}/edit/image", ['image' => $file])
            ->assertJson(['filename' => "{$slug}.png"]);
    }

    /**
     * @return array{0: Activity, 1: User}
     */
    private function makeActivity(): array
    {
        $activity = Activity::factory()->create(['type' => 'achievement', 'key' => 'catalog']);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);

        return [$activity, $author];
    }
}

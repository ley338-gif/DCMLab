<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\Lesson;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Oeffentliches Glossar: alphabetische Uebersicht ueber content/glossary/de.yml,
 * inklusive der bisher ungenutzten see_also-/lesson-Rueckverweise.
 */
class GlossaryControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->contentDir = storage_path('framework/testing/glossary-content-'.uniqid());
        File::ensureDirectoryExists($this->contentDir.'/glossary');
        File::put($this->contentDir.'/glossary/de.yml', <<<'YAML'
        scu:
          term: SCU
          expansion: Service Class User
          short: Die Seite, die eine Anfrage stellt.
          see_also: [scp, unbekannt]
          lesson: "1.5"
        scp:
          term: SCP
          expansion: Service Class Provider
          short: Die Seite, die antwortet.
          see_also: [scu]
        dicom:
          term: DICOM
          expansion: Digital Imaging and Communications in Medicine
          short: Format und Protokoll zugleich.
        YAML
        );
        $this->app->instance(ContentRepository::class, new ContentRepository($this->contentDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_it_is_publicly_visible_without_login(): void
    {
        $this->get('/de/glossar')->assertOk();
    }

    public function test_it_lists_terms_sorted_alphabetically_by_term(): void
    {
        $response = $this->get('/de/glossar');

        $response->assertInertia(fn ($page) => $page
            ->component('Glossary/Index')
            ->where('terms.0.term', 'DICOM')
            ->where('terms.1.term', 'SCP')
            ->where('terms.2.term', 'SCU'),
        );
    }

    public function test_see_also_only_includes_existing_slugs_and_resolves_terms(): void
    {
        $response = $this->get('/de/glossar');

        $response->assertInertia(fn ($page) => $page
            ->where('terms.2.term', 'SCU')
            ->where('terms.2.see_also.0.slug', 'scp')
            ->where('terms.2.see_also.0.term', 'SCP')
            ->has('terms.2.see_also', 1),
        );
    }

    public function test_it_resolves_the_lesson_backreference_when_the_lesson_exists(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create([
            'track_id' => $track->id,
            'lesson_id' => '1.5',
            'title' => ['de' => 'SCU und SCP'],
        ]);

        $response = $this->get('/de/glossar');

        $response->assertInertia(fn ($page) => $page
            ->where('terms.2.term', 'SCU')
            ->where('terms.2.lesson.lesson_id', '1.5')
            ->where('terms.2.lesson.title', 'SCU und SCP'),
        );
    }

    public function test_it_omits_the_lesson_backreference_when_the_lesson_does_not_exist_yet(): void
    {
        $response = $this->get('/de/glossar');

        $response->assertInertia(fn ($page) => $page
            ->where('terms.2.term', 'SCU')
            ->where('terms.2.lesson', null),
        );
    }
}

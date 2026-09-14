<?php

namespace Tests\Feature\Content;

use App\Content\ContentRepository;
use App\Content\FlagNormalizer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * content:build (Abschnitt 5.2): der Flag-Klartext steht nie im Repo, nur
 * sein Hash. Diese Tests pruefen ausschliesslich den Hash-Mechanismus, nie
 * gegen echten Content -- der Klartext von silent-ct bleibt hier ungenannt.
 */
class ContentBuildTest extends TestCase
{
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            File::deleteDirectory($dir);
        }

        parent::tearDown();
    }

    public function test_it_writes_the_normalized_hash_into_node_yml(): void
    {
        $dir = $this->buildContentDir();

        $result = $this->build($dir);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('1 Hash(es) geschrieben', $result['output']);

        $written = File::get($dir.'/nodes/sample/node.yml');
        $expectedHash = FlagNormalizer::hash('Test-Serie', caseSensitive: false);

        $this->assertStringContainsString('hash: "sha256:'.$expectedHash.'"', $written);
        $this->assertStringNotContainsString('<platzhalter>', $written);
    }

    public function test_it_normalizes_whitespace_and_case_before_hashing(): void
    {
        $dir = $this->buildContentDir([
            'datasets.yml' => str_replace('["Test-Serie"]', '["  Test-Serie   Extra  "]', $this->validDatasets()),
        ]);

        $this->build($dir);

        $written = File::get($dir.'/nodes/sample/node.yml');
        $expectedHash = FlagNormalizer::hash('test-serie extra', caseSensitive: false);

        $this->assertStringContainsString('hash: "sha256:'.$expectedHash.'"', $written);
    }

    public function test_it_is_idempotent(): void
    {
        $dir = $this->buildContentDir();

        $this->build($dir);
        $result = $this->build($dir);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('0 Hash(es) geschrieben, 1 bereits aktuell', $result['output']);
    }

    public function test_it_preserves_comments_and_surrounding_yaml(): void
    {
        $dir = $this->buildContentDir();

        $this->build($dir);

        $written = File::get($dir.'/nodes/sample/node.yml');

        $this->assertStringContainsString('# Association-Pruefung', $written);
        $this->assertStringContainsString('case_sensitive: false', $written);
        $this->assertStringContainsString('stuck_timeout_minutes: 10', $written);
    }

    public function test_unknown_source_tag_fails_loudly(): void
    {
        $dir = $this->buildContentDir([
            'nodes/sample/node.yml' => str_replace('"0008,103E"', '"0010,0010"', $this->validNodeDef()),
        ]);

        $result = $this->build($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('kein Resolver fuer flag.source_tag "0010,0010"', $result['output']);
    }

    public function test_unknown_dataset_fails_loudly(): void
    {
        $dir = $this->buildContentDir([
            'nodes/sample/node.yml' => str_replace('dataset: test-set', 'dataset: does-not-exist', $this->validNodeDef()),
        ]);

        $result = $this->build($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Datensatz "does-not-exist" nicht in datasets.yml gefunden', $result['output']);
    }

    public function test_dataset_with_more_than_one_series_fails_loudly(): void
    {
        $dir = $this->buildContentDir([
            'datasets.yml' => str_replace('series: ["Test-Serie"]', 'series: ["Serie A", "Serie B"]', $this->validDatasets()),
        ]);

        $result = $this->build($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('nicht genau eine Serie', $result['output']);
    }

    public function test_scenario_source_tag_hashes_the_reveal_value_without_a_dataset(): void
    {
        $dir = $this->buildContentDir([
            'nodes/sample/node.yml' => $this->validScenarioNodeDef(),
        ]);
        // Kein datasets.yml noetig -- eine Szenario-Node hat kein
        // environment.dataset, siehe ContentBuild::resolveTagValue().
        File::delete($dir.'/datasets.yml');

        $result = $this->build($dir);

        $this->assertSame(0, $result['exitCode']);

        $written = File::get($dir.'/nodes/sample/node.yml');
        $expectedHash = FlagNormalizer::hash('schweigepflicht-gewahrt', caseSensitive: false);

        $this->assertStringContainsString('hash: "sha256:'.$expectedHash.'"', $written);
    }

    public function test_scenario_without_correct_outcome_fails_loudly(): void
    {
        $dir = $this->buildContentDir([
            'nodes/sample/node.yml' => str_replace('outcome: correct', 'outcome: wrong', $this->validScenarioNodeDef()),
        ]);
        File::delete($dir.'/datasets.yml');

        $result = $this->build($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('kein terminal step mit outcome "correct"', $result['output']);
    }

    private function validScenarioNodeDef(): string
    {
        return <<<'YAML'
        slug: sample
        difficulty: easy
        points: 10
        category: security
        interaction: scenario
        skills: [security]
        related_lessons: ["1.0"]
        estimated_minutes: 10

        scenario:
          start: frage
          steps:
            frage:
              prompt: Testfrage.
              options:
                - id: richtig
                  label: Richtige Antwort.
                  next: erfolg
            erfolg:
              terminal: true
              outcome: correct
              reveal: schweigepflicht-gewahrt

        flag:
          type: exact
          source_tag: scenario
          hash: "sha256:<platzhalter>"
          case_sensitive: false

        hints:
          - id: h1
            cost: 1

        stuck_timeout_minutes: 10
        status: draft
        updated: "2026-09-12"
        YAML;
    }

    private function build(string $path): array
    {
        $this->app->instance(ContentRepository::class, new ContentRepository($path));

        $exitCode = Artisan::call('content:build');

        return ['exitCode' => $exitCode, 'output' => Artisan::output()];
    }

    private function buildContentDir(array $overrides = []): string
    {
        $dir = storage_path('framework/testing/content-build-'.Str::random(12));
        $this->tempDirs[] = $dir;

        $files = array_merge([
            'datasets.yml' => $this->validDatasets(),
            'nodes/sample/node.yml' => $this->validNodeDef(),
            'nodes/sample/de.md' => $this->validNodeMarkdown(),
        ], $overrides);

        foreach ($files as $relative => $content) {
            $target = $dir.'/'.$relative;
            File::ensureDirectoryExists(dirname($target));
            File::put($target, $content);
        }

        return $dir;
    }

    private function validDatasets(): string
    {
        return <<<'YAML'
        test-set:
          patient: "MUSTER^ERIKA"
          patient_id: "4711"
          study: "Test-Study"
          series: ["Test-Serie"]
          file_count: 1
          path: "daten/test-set/"
        YAML;
    }

    private function validNodeDef(): string
    {
        return <<<'YAML'
        slug: sample
        difficulty: easy
        points: 10
        category: netzwerk
        skills: [netzwerk]
        related_lessons: ["1.0"]
        estimated_minutes: 10

        environment:
          engine: simulated
          hosts: []
          tools: [dcmdump]
          dataset: test-set

        # Association-Pruefung, in dieser Reihenfolge:
        #   1. Host unbekannt -> TCP Initialization Error

        flag:
          type: tag_value
          source_tag: "0008,103E"
          hash: "sha256:<platzhalter>"
          case_sensitive: false

        hints:
          - id: h1
            cost: 1

        stuck_timeout_minutes: 10
        status: draft
        updated: "2026-09-12"
        YAML;
    }

    private function validNodeMarkdown(): string
    {
        return <<<'MD'
        ---
        title: Sample
        scenario_title: Sample
        ---

        ## Briefing

        Text.

        ## Hints

        ### h1

        Text.

        ## Write-up

        Text.
        MD;
    }
}

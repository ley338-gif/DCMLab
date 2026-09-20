<?php

namespace Tests\Unit\Content;

use App\Content\ContentIssue;
use App\Content\ContentValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ADR 0071/0073, W1-DoD: "Der Service ist ohne Dateisystem gegen ein Array
 * aufrufbar und liefert dieselben Befunde." Kein ContentRepository, keine
 * Datenbank, keine temporaeren Dateien -- reine Arrays rein, ContentIssue[]
 * raus. Das eigentliche Verhalten (alle Regelkategorien) bleibt in
 * tests/Feature/Content/ContentValidateTest.php abgedeckt, das unveraendert
 * gegen das Artisan-Command laeuft.
 */
class ContentValidatorTest extends TestCase
{
    public function test_it_validates_against_plain_arrays_without_any_filesystem_access(): void
    {
        $lessons = [
            '1.0' => [
                'id' => '1.0',
                'meta' => ['objectives_count' => 0, 'requires' => [], 'tools' => []],
                'meta_file' => 'lessons/1.0/meta.yml',
                'meta_raw' => "objectives_count: 0\nrequires: []\ntools: []\n",
                'md_file' => 'lessons/1.0/de.md',
                'md_raw' => null,
                'frontmatter' => null,
                'body' => null,
                'body_start_line' => 1,
            ],
        ];

        $issues = (new ContentValidator)->validate(
            themenfelder: [],
            tracks: [],
            achievements: [],
            lessons: $lessons,
            nodes: [],
            exams: [],
            tools: [],
            toolsRaw: null,
            glossary: [],
            datasets: [],
            skills: [],
        );

        $this->assertCount(1, $issues);
        $this->assertSame('lessons/1.0/meta.yml', $issues[0]->file);
        $this->assertStringContainsString('meta.yml ohne de.md', $issues[0]->message);
    }

    public function test_it_returns_no_issues_for_a_structurally_complete_minimal_set(): void
    {
        $issues = (new ContentValidator)->validate(
            themenfelder: [],
            tracks: [],
            achievements: [],
            lessons: [],
            nodes: [],
            exams: [],
            tools: [],
            toolsRaw: null,
            glossary: [],
            datasets: [],
            skills: [],
        );

        $this->assertSame([], $issues);
    }

    public function test_repeated_calls_do_not_leak_issues_between_invocations(): void
    {
        $validator = new ContentValidator;
        $broken = [
            '1.0' => [
                'id' => '1.0',
                'meta' => ['objectives_count' => 0, 'requires' => [], 'tools' => []],
                'meta_file' => 'lessons/1.0/meta.yml',
                'meta_raw' => '',
                'md_file' => 'lessons/1.0/de.md',
                'md_raw' => null,
                'frontmatter' => null,
                'body' => null,
                'body_start_line' => 1,
            ],
        ];

        $first = $validator->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $broken, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );
        $second = $validator->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: [], nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertNotEmpty($first);
        $this->assertSame([], $second);
    }

    // -----------------------------------------------------------------
    // CMS-7d.3: dieselben drei Regeln (Leseanleitung, Glossarbegriffe,
    // Werkzeug-Nutzung), aber gegen ein RichContentDocument statt gegen
    // rohen Markdown-Text -- ein Lesson-/Node-Eintrag mit `rich_content`
    // hat keinen Body mehr, gegen den die alten Regexe laufen koennten.
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $richContent
     * @param  array<string, mixed>  $metaOverrides
     * @return array<string, array<string, mixed>>
     */
    private function lessonWithRichContent(array $richContent, array $metaOverrides = []): array
    {
        return [
            '1.0' => [
                'id' => '1.0',
                'meta' => array_merge(['objectives_count' => 0, 'requires' => [], 'tools' => []], $metaOverrides),
                'meta_file' => 'lessons/1.0/meta.yml',
                'meta_raw' => '',
                'md_file' => 'lessons/1.0/de.md',
                'md_raw' => null,
                'frontmatter' => null,
                'body' => null,
                'body_start_line' => 1,
                'rich_content' => $richContent,
            ],
        ];
    }

    /**
     * @param  list<ContentIssue>  $issues
     * @return list<string>
     */
    private function messages(array $issues): array
    {
        return array_map(fn ($issue) => $issue->message, $issues);
    }

    public function test_rich_content_lesson_without_any_code_block_fails(): void
    {
        $lessons = $this->lessonWithRichContent([
            'type' => 'doc', 'version' => 1,
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Nur Text.']]]],
        ]);

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'keinen einzigen Codeblock')));
    }

    public function test_rich_content_code_block_without_leseanleitung_fails(): void
    {
        $lessons = $this->lessonWithRichContent([
            'type' => 'doc', 'version' => 1,
            'content' => [
                ['type' => 'code_block', 'attrs' => ['variant' => 'terminal'], 'text' => '$ dcmdump datei.dcm'],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Kein fester Wortlaut.']]],
            ],
        ]);

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Was du daran abliest')));
    }

    public function test_rich_content_code_block_with_leseanleitung_in_the_next_block_passes(): void
    {
        $lessons = $this->lessonWithRichContent([
            'type' => 'doc', 'version' => 1,
            'content' => [
                ['type' => 'code_block', 'attrs' => ['variant' => 'terminal'], 'text' => '$ dcmdump datei.dcm'],
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => 'Was du daran abliest:', 'marks' => [['type' => 'bold']]],
                    ['type' => 'text', 'text' => ' Test.'],
                ]],
            ],
        ]);

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Was du daran abliest')));
    }

    public function test_rich_content_diagram_code_block_is_excluded_from_the_leseanleitung_rule(): void
    {
        $lessons = $this->lessonWithRichContent([
            'type' => 'doc', 'version' => 1,
            'content' => [
                ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => 'A -> B'],
            ],
        ]);

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Was du daran abliest') || str_contains($m, 'Codeblock')));
    }

    public function test_rich_content_unknown_glossary_term_fails(): void
    {
        $lessons = $this->lessonWithRichContent([
            'type' => 'doc', 'version' => 1,
            'content' => [
                ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => 'x'],
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'glossary_term', 'attrs' => ['slug' => 'nicht-vorhanden']],
                ]],
            ],
        ]);

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: ['dicom' => []], datasets: [], skills: [],
        );

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, '{{term:nicht-vorhanden}} existiert nicht')));
    }

    public function test_rich_content_glossary_term_nested_inside_a_callout_is_found(): void
    {
        $lessons = $this->lessonWithRichContent([
            'type' => 'doc', 'version' => 1,
            'content' => [
                ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => 'x'],
                ['type' => 'callout', 'attrs' => ['kind' => 'info'], 'content' => [
                    ['type' => 'paragraph', 'content' => [
                        ['type' => 'glossary_term', 'attrs' => ['slug' => 'nicht-vorhanden']],
                    ]],
                ]],
            ],
        ]);

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, '{{term:nicht-vorhanden}} existiert nicht')));
    }

    public function test_rich_content_tool_used_but_not_declared_fails(): void
    {
        $lessons = $this->lessonWithRichContent(
            [
                'type' => 'doc', 'version' => 1,
                'content' => [
                    ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => '$ dcmdump datei.dcm'],
                ],
            ],
            ['tools' => []],
        );

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: $lessons, nodes: [], exams: [],
            tools: ['dcmdump' => ['purpose' => 'x']], toolsRaw: '', glossary: [], datasets: [], skills: [],
        );

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Werkzeug "dcmdump" wird benutzt')));
    }

    public function test_rich_content_node_envelope_counts_sections_together_for_the_code_block_rule(): void
    {
        $nodes = [
            'sample' => [
                'slug' => 'sample',
                'def' => null,
                'def_file' => 'nodes/sample/node.yml',
                'def_raw' => null,
                'md_file' => 'nodes/sample/de.md',
                'md_raw' => null,
                'frontmatter' => null,
                'body' => null,
                'body_start_line' => 1,
                'rich_content' => [
                    'type' => 'node_content',
                    'version' => 1,
                    'briefing' => ['type' => 'doc', 'version' => 1, 'content' => [
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Briefing.']]],
                    ]],
                    'hints' => [
                        'h1' => ['type' => 'doc', 'version' => 1, 'content' => [
                            ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => 'x'],
                        ]],
                    ],
                    'write_up' => ['type' => 'doc', 'version' => 1, 'content' => []],
                ],
            ],
        ];

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: [], nodes: $nodes, exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertFalse(
            collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'keinen einzigen Codeblock')),
            'der Codeblock im Hint sollte fuer die gesamte Node zaehlen',
        );
    }

    /**
     * Regressionstest fuer eine beim Schreiben von CMS-7d.3-Feature-Tests
     * gefundene Luecke: `checkNodeStructure()`s "### h1"-Abschnittspruefung
     * lief bisher UNABHAENGIG von `rich_content` -- fuer eine rein per
     * Studio angelegte Node (ADR 0109, kein `content/nodes/**` und damit
     * kein `de.md` mit Ueberschriften) meldete sie faelschlich einen
     * fehlenden Markdown-Abschnitt, obwohl der Hint-Text laengst in
     * `rich_content.hints` steht. `NodeActivity::checkHintIdConsistency()`
     * ist die zeitgemaesse Entsprechung dieser Pruefung.
     */
    public function test_a_rich_content_node_does_not_need_a_hint_heading_in_markdown(): void
    {
        $nodes = [
            'sample' => [
                'slug' => 'sample',
                'def' => ['hints' => [['id' => 'h1', 'cost' => 1]]],
                'def_file' => 'nodes/sample/node.yml',
                'def_raw' => '',
                'md_file' => 'nodes/sample/de.md',
                'md_raw' => '',
                'frontmatter' => null,
                'body' => null,
                'body_start_line' => 1,
                'rich_content' => [
                    'type' => 'node_content',
                    'version' => 1,
                    'briefing' => ['type' => 'doc', 'version' => 1, 'content' => [
                        ['type' => 'code_block', 'attrs' => ['variant' => 'diagram'], 'text' => 'x'],
                    ]],
                    'hints' => [
                        'h1' => ['type' => 'doc', 'version' => 1, 'content' => [
                            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hinweistext.']]],
                        ]],
                    ],
                    'write_up' => ['type' => 'doc', 'version' => 1, 'content' => []],
                ],
            ],
        ];

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: [], nodes: $nodes, exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertFalse(
            collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Abschnitt in de.md')),
            'eine rich_content-Node braucht keinen Markdown-Hint-Abschnitt mehr',
        );
    }

    /**
     * Kehrseite: eine Node OHNE rich_content (Legacy-Datei-Pfad,
     * `content:validate` gegen `content/**`) behaelt die alte Pruefung
     * unveraendert -- kein Bruch fuer den Datei-Pfad.
     */
    public function test_a_legacy_node_without_rich_content_still_requires_the_hint_heading(): void
    {
        $nodes = [
            'sample' => [
                'slug' => 'sample',
                'def' => ['hints' => [['id' => 'h1', 'cost' => 1]]],
                'def_file' => 'nodes/sample/node.yml',
                'def_raw' => '',
                'md_file' => 'nodes/sample/de.md',
                'md_raw' => "## Briefing\n\nText.\n",
                'frontmatter' => null,
                'body' => "## Briefing\n\nText.\n",
                'body_start_line' => 1,
            ],
        ];

        $issues = (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: [], nodes: $nodes, exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );

        $this->assertTrue(
            collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Hint "h1" aus node.yml hat keinen "### h1"-Abschnitt in de.md')),
        );
    }

    // -----------------------------------------------------------------
    // ADR 0120, 9.3: PACS-Routing -- Schema-/Referenzfehler in
    // environment.hosts[].services[]/routes[], nie fachliche Korrektheit.
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $environment
     * @return array<string, array<string, mixed>>
     */
    private function nodeWithEnvironment(array $environment): array
    {
        return [
            'sample' => [
                'slug' => 'sample',
                'def' => ['environment' => $environment],
                'def_file' => 'nodes/sample/node.yml',
                'def_raw' => "environment:\n  hosts: []\n",
                'md_file' => 'nodes/sample/de.md',
                'md_raw' => "## Briefing\n\nText.\n",
                'frontmatter' => null,
                'body' => "## Briefing\n\nText.\n",
                'body_start_line' => 1,
            ],
        ];
    }

    private function validateNodes(array $nodes): array
    {
        return (new ContentValidator)->validate(
            themenfelder: [], tracks: [], achievements: [], lessons: [], nodes: $nodes, exams: [],
            tools: [], toolsRaw: null, glossary: [], datasets: [], skills: [],
        );
    }

    public function test_a_structurally_valid_route_raises_no_routing_issue(): void
    {
        $nodes = $this->nodeWithEnvironment([
            'hosts' => [
                [
                    'name' => 'pacs', 'ip' => '10.20.0.10',
                    'dicom' => ['calling_ae' => 'RAD-PACS'],
                    'services' => [['id' => 'pacs-store', 'port' => 104, 'ae_title' => 'RAD-ARCHIV']],
                    'routes' => [[
                        'id' => 'CT-TO-DOSE',
                        'destination' => ['host' => 'dose-scp', 'service' => 'dose-store'],
                        'match' => ['modality' => 'CT'],
                    ]],
                ],
                [
                    'name' => 'dose-scp', 'ip' => '10.20.0.30',
                    'services' => [['id' => 'dose-store', 'port' => 104, 'ae_title' => 'DOSE-SCP']],
                ],
            ],
        ]);

        $issues = $this->validateNodes($nodes);

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Route') || str_contains($m, 'destination') || str_contains($m, 'match')));
    }

    public function test_a_route_to_an_unknown_host_is_rejected(): void
    {
        $nodes = $this->nodeWithEnvironment([
            'hosts' => [[
                'name' => 'pacs', 'ip' => '10.20.0.10',
                'dicom' => ['calling_ae' => 'RAD-PACS'],
                'routes' => [[
                    'id' => 'CT-TO-DOSE',
                    'destination' => ['host' => 'nowhere', 'service' => 'dose-store'],
                    'match' => ['modality' => 'CT'],
                ]],
            ]],
        ]);

        $issues = $this->validateNodes($nodes);

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'destination.host "nowhere" existiert nicht')));
    }

    public function test_a_route_to_an_unknown_service_on_a_known_host_is_rejected(): void
    {
        $nodes = $this->nodeWithEnvironment([
            'hosts' => [
                [
                    'name' => 'pacs', 'ip' => '10.20.0.10',
                    'dicom' => ['calling_ae' => 'RAD-PACS'],
                    'routes' => [[
                        'id' => 'CT-TO-DOSE',
                        'destination' => ['host' => 'dose-scp', 'service' => 'nope'],
                        'match' => ['modality' => 'CT'],
                    ]],
                ],
                ['name' => 'dose-scp', 'ip' => '10.20.0.30', 'services' => [['id' => 'dose-store', 'port' => 104]]],
            ],
        ]);

        $issues = $this->validateNodes($nodes);

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'destination.service "nope" ist keine services[].id')));
    }

    public function test_a_bare_destination_host_without_a_service_is_rejected(): void
    {
        $nodes = $this->nodeWithEnvironment([
            'hosts' => [
                [
                    'name' => 'pacs', 'ip' => '10.20.0.10',
                    'dicom' => ['calling_ae' => 'RAD-PACS'],
                    'routes' => [[
                        'id' => 'CT-TO-DOSE',
                        'destination' => ['host' => 'dose-scp'],
                        'match' => ['modality' => 'CT'],
                    ]],
                ],
                ['name' => 'dose-scp', 'ip' => '10.20.0.30', 'services' => [['id' => 'dose-store', 'port' => 104]]],
            ],
        ]);

        $issues = $this->validateNodes($nodes);

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'destination braucht sowohl host als auch service')));
    }

    public function test_a_host_with_routes_but_no_calling_ae_is_rejected(): void
    {
        $nodes = $this->nodeWithEnvironment([
            'hosts' => [
                [
                    'name' => 'pacs', 'ip' => '10.20.0.10',
                    'routes' => [[
                        'id' => 'CT-TO-DOSE',
                        'destination' => ['host' => 'dose-scp', 'service' => 'dose-store'],
                        'match' => ['modality' => 'CT'],
                    ]],
                ],
                ['name' => 'dose-scp', 'ip' => '10.20.0.30', 'services' => [['id' => 'dose-store', 'port' => 104]]],
            ],
        ]);

        $issues = $this->validateNodes($nodes);

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'hat routes, aber kein dicom.calling_ae')));
    }

    public function test_duplicate_route_ids_within_a_node_are_rejected(): void
    {
        $route = [
            'id' => 'CT-TO-DOSE',
            'destination' => ['host' => 'dose-scp', 'service' => 'dose-store'],
            'match' => ['modality' => 'CT'],
        ];
        $nodes = $this->nodeWithEnvironment([
            'hosts' => [
                ['name' => 'pacs', 'ip' => '10.20.0.10', 'dicom' => ['calling_ae' => 'RAD-PACS'], 'routes' => [$route, $route]],
                ['name' => 'dose-scp', 'ip' => '10.20.0.30', 'services' => [['id' => 'dose-store', 'port' => 104]]],
            ],
        ]);

        $issues = $this->validateNodes($nodes);

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Route-ID "CT-TO-DOSE" ist innerhalb dieser Node mehrfach vergeben')));
    }

    public function test_duplicate_service_ids_on_the_same_host_are_rejected(): void
    {
        $nodes = $this->nodeWithEnvironment([
            'hosts' => [[
                'name' => 'pacs', 'ip' => '10.20.0.10',
                'services' => [
                    ['id' => 'pacs-store', 'port' => 104],
                    ['id' => 'pacs-store', 'port' => 105],
                ],
            ]],
        ]);

        $issues = $this->validateNodes($nodes);

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Service-ID "pacs-store" ist mehrfach vergeben')));
    }

    public function test_an_unknown_match_field_is_rejected_as_a_typo_guard(): void
    {
        $nodes = $this->nodeWithEnvironment([
            'hosts' => [
                [
                    'name' => 'pacs', 'ip' => '10.20.0.10',
                    'dicom' => ['calling_ae' => 'RAD-PACS'],
                    'routes' => [[
                        'id' => 'CT-TO-DOSE',
                        'destination' => ['host' => 'dose-scp', 'service' => 'dose-store'],
                        'match' => ['moddality' => 'CT'],
                    ]],
                ],
                ['name' => 'dose-scp', 'ip' => '10.20.0.30', 'services' => [['id' => 'dose-store', 'port' => 104]]],
            ],
        ]);

        $issues = $this->validateNodes($nodes);

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'match-Feld "moddality" ist nicht erlaubt')));
    }

    public function test_an_unknown_match_operator_is_rejected(): void
    {
        $nodes = $this->nodeWithEnvironment([
            'hosts' => [
                [
                    'name' => 'pacs', 'ip' => '10.20.0.10',
                    'dicom' => ['calling_ae' => 'RAD-PACS'],
                    'routes' => [[
                        'id' => 'CT-TO-DOSE',
                        'destination' => ['host' => 'dose-scp', 'service' => 'dose-store'],
                        'match' => ['all' => [['field' => 'modality', 'op' => 'matches', 'value' => 'CT']]],
                    ]],
                ],
                ['name' => 'dose-scp', 'ip' => '10.20.0.30', 'services' => [['id' => 'dose-store', 'port' => 104]]],
            ],
        ]);

        $issues = $this->validateNodes($nodes);

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'match-Operator "matches" ist unbekannt')));
    }

    public function test_op_in_without_a_values_list_is_rejected(): void
    {
        $nodes = $this->nodeWithEnvironment([
            'hosts' => [
                [
                    'name' => 'pacs', 'ip' => '10.20.0.10',
                    'dicom' => ['calling_ae' => 'RAD-PACS'],
                    'routes' => [[
                        'id' => 'CT-TO-DOSE',
                        'destination' => ['host' => 'dose-scp', 'service' => 'dose-store'],
                        'match' => ['all' => [['field' => 'modality', 'op' => 'in', 'value' => 'CT']]],
                    ]],
                ],
                ['name' => 'dose-scp', 'ip' => '10.20.0.30', 'services' => [['id' => 'dose-store', 'port' => 104]]],
            ],
        ]);

        $issues = $this->validateNodes($nodes);

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'op "in" erfordert eine nicht-leere values-Liste')));
    }

    public function test_hosts_without_any_routes_need_no_calling_ae(): void
    {
        $nodes = $this->nodeWithEnvironment([
            'hosts' => [
                ['name' => 'workstation', 'ip' => '10.0.0.50', 'role' => 'shell'],
                ['name' => 'archive', 'ip' => '10.0.0.10', 'services' => [['port' => 104, 'ae_title' => 'ARCHIV']]],
            ],
        ]);

        $issues = $this->validateNodes($nodes);

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'calling_ae')));
    }

    // -----------------------------------------------------------------
    // ADR 0120, Phase-A-Review: Kurzform ist ein Authoring-Vertrag fuer
    // GENAU EIN Feld -- kein implizites Multi-Key-'all'.
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $match
     */
    private function nodeWithRouteMatch(array $match): array
    {
        return $this->nodeWithEnvironment([
            'hosts' => [
                [
                    'name' => 'pacs', 'ip' => '10.20.0.10',
                    'dicom' => ['calling_ae' => 'RAD-PACS'],
                    'routes' => [[
                        'id' => 'CT-TO-DOSE',
                        'destination' => ['host' => 'dose-scp', 'service' => 'dose-store'],
                        'match' => $match,
                    ]],
                ],
                ['name' => 'dose-scp', 'ip' => '10.20.0.30', 'services' => [['id' => 'dose-store', 'port' => 104]]],
            ],
        ]);
    }

    public function test_single_key_shorthand_match_is_valid(): void
    {
        $issues = $this->validateNodes($this->nodeWithRouteMatch(['modality' => 'CT']));

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'match')));
    }

    public function test_multi_key_shorthand_match_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithRouteMatch([
            'modality' => 'CT', 'sop_class' => '1.2.840.10008.5.1.4.1.1.2',
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'match muss entweder Kurzform mit genau einem Feld')));
    }

    public function test_canonical_all_with_multiple_conditions_is_valid(): void
    {
        $issues = $this->validateNodes($this->nodeWithRouteMatch(['all' => [
            ['field' => 'modality', 'op' => 'equals', 'value' => 'CT'],
            ['field' => 'sop_class', 'op' => 'equals', 'value' => '1.2.840.10008.5.1.4.1.1.2'],
        ]]));

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'match')));
    }

    public function test_canonical_any_is_valid(): void
    {
        $issues = $this->validateNodes($this->nodeWithRouteMatch([
            'any' => [['field' => 'modality', 'op' => 'equals', 'value' => 'CT']],
        ]));

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'match')));
    }

    public function test_shorthand_mixed_with_all_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithRouteMatch([
            'all' => [['field' => 'modality', 'op' => 'equals', 'value' => 'CT']],
            'sop_class' => '1.2.840.10008.5.1.4.1.1.2',
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'match muss entweder Kurzform mit genau einem Feld')));
    }

    public function test_exists_with_a_value_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithRouteMatch([
            'all' => [['field' => 'modality', 'op' => 'exists', 'value' => 'CT']],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'op "exists" erlaubt weder value noch values')));
    }

    public function test_in_with_an_extra_value_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithRouteMatch([
            'all' => [['field' => 'modality', 'op' => 'in', 'values' => ['CT'], 'value' => 'CT']],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'op "in" erlaubt kein zusaetzliches value')));
    }

    public function test_equals_with_an_extra_values_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithRouteMatch([
            'all' => [['field' => 'modality', 'op' => 'equals', 'value' => 'CT', 'values' => ['CT']]],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'op "equals" erlaubt kein zusaetzliches values')));
    }

    // -----------------------------------------------------------------
    // ADR 0120, Phase-A-Review: `enabled` muss ein echtes Boolean sein.
    // -----------------------------------------------------------------

    private function nodeWithRouteEnabled(mixed $enabled): array
    {
        $route = [
            'id' => 'CT-TO-DOSE',
            'destination' => ['host' => 'dose-scp', 'service' => 'dose-store'],
            'match' => ['modality' => 'CT'],
        ];

        if ($enabled !== 'absent') {
            $route['enabled'] = $enabled;
        }

        return $this->nodeWithEnvironment([
            'hosts' => [
                ['name' => 'pacs', 'ip' => '10.20.0.10', 'dicom' => ['calling_ae' => 'RAD-PACS'], 'routes' => [$route]],
                ['name' => 'dose-scp', 'ip' => '10.20.0.30', 'services' => [['id' => 'dose-store', 'port' => 104]]],
            ],
        ]);
    }

    public function test_a_route_without_enabled_raises_no_issue(): void
    {
        $issues = $this->validateNodes($this->nodeWithRouteEnabled('absent'));

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'enabled')));
    }

    public function test_enabled_true_raises_no_issue(): void
    {
        $issues = $this->validateNodes($this->nodeWithRouteEnabled(true));

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'enabled')));
    }

    public function test_enabled_false_raises_no_issue(): void
    {
        $issues = $this->validateNodes($this->nodeWithRouteEnabled(false));

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'enabled')));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function nonBooleanEnabledProvider(): array
    {
        return [
            'string false' => ['false'],
            'string true' => ['true'],
            'int zero' => [0],
            'int one' => [1],
            'string yes' => ['yes'],
        ];
    }

    #[DataProvider('nonBooleanEnabledProvider')]
    public function test_a_non_boolean_enabled_is_rejected(mixed $enabled): void
    {
        $issues = $this->validateNodes($this->nodeWithRouteEnabled($enabled));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'enabled muss Boolean sein')));
    }

    // -----------------------------------------------------------------
    // Phase D.1: generischer Hands-on-Solve-Contract (`solve.requires`) --
    // rein strukturelle Pruefung, nie fachliche Korrektheit.
    // -----------------------------------------------------------------

    /**
     * Ein-Route-Umgebung (workstation -> pacs -> dose-scp), damit
     * Requirement-Tests echte Host-/Route-Referenzen validieren koennen.
     *
     * @param  list<array<string, mixed>>  $requires
     * @return array<string, array<string, mixed>>
     */
    private function nodeWithSolveRequires(array $requires): array
    {
        return [
            'sample' => [
                'slug' => 'sample',
                'def' => [
                    'environment' => [
                        'hosts' => [
                            ['name' => 'workstation', 'ip' => '10.0.0.50', 'role' => 'shell'],
                            [
                                'name' => 'pacs', 'ip' => '10.0.0.10',
                                'dicom' => ['calling_ae' => 'RAD-PACS'],
                                'routes' => [[
                                    'id' => 'PACS-TO-DOSE',
                                    'destination' => ['host' => 'dose-scp', 'service' => 'dose-store'],
                                    'match' => ['modality' => 'CT'],
                                ]],
                            ],
                            ['name' => 'dose-scp', 'ip' => '10.0.0.30', 'services' => [['id' => 'dose-store', 'port' => 104]]],
                        ],
                    ],
                    'solve' => ['requires' => $requires],
                ],
                'def_file' => 'nodes/sample/node.yml',
                'def_raw' => "solve:\n  requires: []\n",
                'md_file' => 'nodes/sample/de.md',
                'md_raw' => "## Briefing\n\nText.\n",
                'frontmatter' => null,
                'body' => "## Briefing\n\nText.\n",
                'body_start_line' => 1,
            ],
        ];
    }

    private function validRdsrRequirement(): array
    {
        return ['type' => 'object_exists', 'as' => 'rdsr', 'where' => ['modality' => 'SR']];
    }

    /**
     * Wie {@see nodeWithSolveRequires()}, ersetzt aber den kompletten
     * `solve`-Wert -- fuer Tests, die einen strukturell ungueltigen
     * `solve`-Block selbst pruefen (falscher Typ, Tippfehler-Feld), nicht
     * nur eine einzelne Bedingung darin.
     */
    private function nodeWithRawSolve(mixed $solve): array
    {
        $node = $this->nodeWithSolveRequires([]);
        $node['sample']['def']['solve'] = $solve;

        return $node;
    }

    public function test_a_structurally_valid_solve_contract_raises_no_issue(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            $this->validRdsrRequirement(),
            ['type' => 'presence', 'object' => 'rdsr', 'host' => 'pacs', 'present' => true],
            ['type' => 'presence', 'object' => 'rdsr', 'host' => 'dose-scp', 'present' => false],
            ['type' => 'job_not_exists', 'where' => ['route_id' => 'PACS-TO-DOSE', 'object' => 'rdsr']],
            ['type' => 'job_exists', 'where' => ['route_id' => 'PACS-TO-DOSE', 'status' => 'sent']],
            [
                'type' => 'event_exists',
                'where' => [
                    'type' => 'route.evaluated', 'route_id' => 'PACS-TO-DOSE', 'object' => 'rdsr',
                    'matched' => false, 'field' => 'modality', 'operator' => 'equals',
                    'expected' => 'CT', 'actual' => 'SR',
                ],
            ],
        ]));

        // Nur solve-bezogene Befunde pruefen -- die minimale Fixture loest
        // bewusst nicht jede unabhaengige Regel (Beispielregel/Themenfeld)
        // aus, das ist hier nicht der Pruefgegenstand.
        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'solve') || str_contains($m, 'requires')));
    }

    public function test_no_solve_key_raises_no_issue(): void
    {
        $nodes = $this->nodeWithEnvironment([
            'hosts' => [['name' => 'workstation', 'ip' => '10.0.0.50', 'role' => 'shell']],
        ]);

        $issues = $this->validateNodes($nodes);

        $this->assertFalse(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'solve')));
    }

    public function test_empty_requires_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'solve.requires muss eine nicht-leere Liste sein')));
    }

    public function test_solve_as_a_string_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithRawSolve('foo'));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'solve muss ein Objekt sein')));
    }

    public function test_solve_as_a_list_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithRawSolve(['foo']));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'solve muss ein Objekt sein')));
    }

    public function test_solve_with_a_typo_key_instead_of_requires_is_rejected(): void
    {
        // "require" statt "requires" -- ohne fail-closed-Pruefung wuerde der
        // Validator (und die Engine) das schlicht als "kein Solve Contract"
        // lesen, obwohl der Autor eindeutig ein Gate definieren wollte.
        $issues = $this->validateNodes($this->nodeWithRawSolve(['require' => [$this->validRdsrRequirement()]]));
        $messages = collect($this->messages($issues));

        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'solve: unbekanntes Feld "require"')));
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'solve.requires muss eine nicht-leere Liste sein')));
    }

    public function test_solve_with_an_unsupported_top_level_field_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithRawSolve([
            'requires' => [$this->validRdsrRequirement()],
            'typo' => true,
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'solve: unbekanntes Feld "typo"')));
    }

    public function test_unknown_condition_type_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            ['type' => 'totally_unknown_type'],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'unbekannter Typ "totally_unknown_type"')));
    }

    public function test_object_exists_without_alias_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            ['type' => 'object_exists', 'where' => ['modality' => 'SR']],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'as (Alias-Name) fehlt')));
    }

    public function test_duplicate_alias_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            $this->validRdsrRequirement(),
            ['type' => 'object_exists', 'as' => 'rdsr', 'where' => ['modality' => 'CT']],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'Alias "rdsr" ist mehrfach vergeben')));
    }

    public function test_unknown_alias_reference_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            ['type' => 'presence', 'object' => 'never-declared', 'host' => 'pacs', 'present' => true],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'unbekannter Alias "never-declared"')));
    }

    public function test_presence_with_unknown_host_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            $this->validRdsrRequirement(),
            ['type' => 'presence', 'object' => 'rdsr', 'host' => 'nowhere', 'present' => true],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'unbekannter Host "nowhere"')));
    }

    public function test_presence_with_non_boolean_present_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            $this->validRdsrRequirement(),
            ['type' => 'presence', 'object' => 'rdsr', 'host' => 'pacs', 'present' => 'true'],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'present muss ein Boolean sein')));
    }

    public function test_job_requirement_with_unknown_route_id_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            ['type' => 'job_exists', 'where' => ['route_id' => 'NOPE']],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'unbekannte Route-ID "NOPE"')));
    }

    public function test_job_requirement_with_unknown_status_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            ['type' => 'job_exists', 'where' => ['status' => 'success']],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'unbekannter Job-Status "success"')));
    }

    public function test_event_requirement_with_unknown_type_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            ['type' => 'event_exists', 'where' => ['type' => 'totally.made.up']],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'unbekannter Event-Typ "totally.made.up"')));
    }

    public function test_event_requirement_with_non_boolean_matched_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            ['type' => 'event_exists', 'where' => ['type' => 'route.evaluated', 'matched' => 'false']],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'matched muss ein Boolean sein')));
    }

    public function test_requirement_with_unsupported_top_level_field_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            ['type' => 'presence', 'object' => 'rdsr', 'host' => 'pacs', 'present' => true, 'extra' => 'nope'],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'presence: unbekanntes Feld "extra"')));
    }

    public function test_requirement_with_unsupported_where_field_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            ['type' => 'object_exists', 'as' => 'x', 'where' => ['manufacturer' => 'Acme']],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'unbekanntes where-Feld "manufacturer"')));
    }

    public function test_requirement_without_where_is_rejected(): void
    {
        $issues = $this->validateNodes($this->nodeWithSolveRequires([
            ['type' => 'job_exists'],
        ]));

        $this->assertTrue(collect($this->messages($issues))->contains(fn ($m) => str_contains($m, 'where muss ein nicht-leeres Objekt sein')));
    }
}

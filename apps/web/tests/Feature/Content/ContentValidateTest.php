<?php

namespace Tests\Feature\Content;

use App\Content\ContentRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * content:validate gegen Abschnitt 4.7. Jede kaputte Regel bekommt ihre
 * eigene, minimale Fixture -- so bleibt sichtbar, welche Regel genau prueft.
 */
class ContentValidateTest extends TestCase
{
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            File::deleteDirectory($dir);
        }

        parent::tearDown();
    }

    public function test_real_lessons_1_1_and_1_5_pass_validation(): void
    {
        // 1.0 liegt nur bei, weil 1.1 sie in requires nennt -- 1.0 selbst hat
        // bekannte Luecken (siehe docs/content-todo.md) und ist nicht Teil
        // dieser Zusage. 1.1 und 1.5 duerfen dort keine eigenen Verstoesse haben.
        $result = $this->validate(base_path('tests/Fixtures/content-real'));

        $relevantFiles = ['lessons/1.1/', 'lessons/1.5/', 'tools/de.yml', 'glossary/de.yml', 'datasets.yml', 'tracks.yml'];
        $ownIssues = array_filter(
            explode("\n", $result['output']),
            fn (string $line) => Str::contains($line, $relevantFiles),
        );

        $this->assertSame([], array_values($ownIssues), $result['output']);
    }

    public function test_lesson_meta_without_markdown_fails(): void
    {
        $dir = $this->buildContentDir(['lessons/1.0/de.md' => null]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('meta.yml ohne de.md', $result['output']);
    }

    public function test_lesson_markdown_without_meta_fails(): void
    {
        $dir = $this->buildContentDir(['lessons/1.0/meta.yml' => null]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('de.md ohne meta.yml', $result['output']);
    }

    public function test_objectives_count_mismatch_fails(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => str_replace('objectives_count: 1', 'objectives_count: 2', $this->validLessonMeta()),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('objectives_count ist 2', $result['output']);
    }

    public function test_unknown_requires_id_fails(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => str_replace('requires: []', 'requires: ["9.9"]', $this->validLessonMeta()),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('unbekannte Lektion "9.9"', $result['output']);
    }

    public function test_unknown_glossary_term_fails(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/de.md' => str_replace('{{term:dicom}}', '{{term:nicht-vorhanden}}', $this->validLessonMarkdown()),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('{{term:nicht-vorhanden}} existiert nicht', $result['output']);
    }

    public function test_missing_codeblock_fails(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/de.md' => "---\ntitle: Test\nteaser: Test\nobjectives:\n  - Eins\n---\n\n## Intro\n\nNur Text, kein Beispiel.\n",
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('keinen einzigen Codeblock', $result['output']);
    }

    public function test_codeblock_without_leseanleitung_fails(): void
    {
        $broken = <<<'MD'
        ---
        title: Test
        teaser: Test
        objectives:
          - Eins
        ---

        ## Intro

        ```
        $ dcmdump datei.dcm
        (0008,0060) CS [CT]
        ```

        Kein fester Wortlaut hier, nur Prosa.
        MD;

        $dir = $this->buildContentDir(['lessons/1.0/de.md' => $broken]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('ohne "**Was du daran abliest:**"', $result['output']);
    }

    public function test_kein_beispiel_marker_excludes_block_from_leseanleitung_rule(): void
    {
        $diagram = <<<'MD'
        ---
        title: Test
        teaser: Test
        objectives:
          - Eins
        ---

        ## Intro

        {{term:dicom}}

        ```
        $ dcmdump datei.dcm
        (0008,0060) CS [CT]
        ```

        **Was du daran abliest:** Test.

        <!-- kein-beispiel -->
        ```
        Nur ein Diagramm, kein echter Befehl.
        ```
        MD;

        $dir = $this->buildContentDir(['lessons/1.0/de.md' => $diagram]);

        $result = $this->validate($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
    }

    public function test_unknown_tool_slug_fails(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => str_replace('tools: [dcmdump]', 'tools: [dcmdump, faketool]', $this->validLessonMeta()),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Werkzeug "faketool" existiert nicht', $result['output']);
    }

    public function test_more_than_four_tools_fails(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => str_replace(
                'tools: [dcmdump]',
                'tools: [dcmdump, dcmdump, dcmdump, dcmdump, dcmdump]',
                $this->validLessonMeta(),
            ),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('mehr als vier Werkzeuge', $result['output']);
    }

    public function test_exempt_tool_limit_skips_the_four_tools_rule(): void
    {
        $meta = str_replace(
            'tools: [dcmdump]',
            'tools: [dcmdump, dcmdump, dcmdump, dcmdump, dcmdump]',
            $this->validLessonMeta(),
        ).PHP_EOL.'exempt_tool_limit: true'.PHP_EOL;

        $dir = $this->buildContentDir(['lessons/1.0/meta.yml' => $meta]);

        $result = $this->validate($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
    }

    public function test_tool_used_without_being_declared_fails(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => str_replace('tools: [dcmdump]', 'tools: []', $this->validLessonMeta()),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('wird benutzt, ist aber nicht in tools deklariert', $result['output']);
    }

    public function test_missing_tools_checked_fails(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => preg_replace('/tools_checked:.*\n/', '', $this->validLessonMeta()),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('tools_checked fehlt', $result['output']);
    }

    public function test_stale_tools_checked_fails(): void
    {
        $stale = Carbon::now()->subMonths(13)->toDateString();
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => preg_replace('/tools_checked: ".*"/', "tools_checked: \"{$stale}\"", $this->validLessonMeta()),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('ist aelter als 12 Monate', $result['output']);
    }

    public function test_unknown_dataset_fails(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => str_replace('dataset: test-set', 'dataset: nicht-vorhanden', $this->validLessonMeta()),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Datensatz "nicht-vorhanden" existiert nicht', $result['output']);
    }

    public function test_tool_purpose_too_long_fails(): void
    {
        $longPurpose = str_repeat('x', 95);
        $dir = $this->buildContentDir([
            'tools/de.yml' => str_replace('Kippt den Inhalt aus.', $longPurpose, $this->validTools()),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('nicht einzeilig oder laenger als 90 Zeichen', $result['output']);
    }

    public function test_node_hint_without_section_fails(): void
    {
        $dir = $this->buildContentDir($this->nodeFiles([
            'nodes/sample/de.md' => <<<'MD'
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

            ```
            $ dcmdump datei.dcm
            (0008,0060) CS [CT]
            ```

            **Was du daran abliest:** Test.
            MD,
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Hint "h2" aus node.yml hat keinen', $result['output']);
    }

    public function test_node_related_lesson_unknown_fails(): void
    {
        $dir = $this->buildContentDir($this->nodeFiles([
            'nodes/sample/node.yml' => str_replace('related_lessons: ["1.0"]', 'related_lessons: ["9.9"]', $this->validNodeDef()),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('unbekannte Lektion "9.9"', $result['output']);
    }

    public function test_node_flag_hash_without_sha256_prefix_fails(): void
    {
        $dir = $this->buildContentDir($this->nodeFiles([
            'nodes/sample/node.yml' => str_replace('hash: "sha256:<platzhalter>"', 'hash: "MUSTER^ERIKA CT Thorax"', $this->validNodeDef()),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('sieht nach Flag-Klartext', $result['output']);
    }

    public function test_node_placeholder_without_template_fails(): void
    {
        $dir = $this->buildContentDir($this->nodeFiles([
            'nodes/sample/node.yml' => str_replace('placeholders: [ZIEL_AE]', 'placeholders: [ZIEL_AE, UNBENUTZT]', $this->validNodeDef()),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Platzhalter "UNBENUTZT" kommt in keinem templates-Eintrag vor', $result['output']);
    }

    public function test_valid_node_passes(): void
    {
        $dir = $this->buildContentDir($this->nodeFiles());

        $result = $this->validate($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
    }

    /**
     * Regressionstest fuer PR #159: ein `scenario:`-Baum ohne explizites
     * `interaction: scenario` wurde bislang von content:validate nicht
     * bemaengelt, obwohl ContentSync in diesem Fall auf den Default
     * "terminal" faellt und der Node damit am falschen Engine-Client
     * landet (siehe EngineClientResolver).
     */
    public function test_node_scenario_tree_without_interaction_scenario_fails(): void
    {
        $dir = $this->buildContentDir($this->nodeFiles([
            'nodes/sample/node.yml' => $this->validNodeDef()."\n".<<<'YAML'
            scenario:
              start: frage
              steps:
                frage:
                  terminal: true
                  outcome: correct
                  reveal: test-flag
            YAML,
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('deklariert aber nicht "interaction: scenario"', $result['output']);
    }

    public function test_node_scenario_tree_with_interaction_scenario_passes(): void
    {
        $dir = $this->buildContentDir($this->nodeFiles([
            'nodes/sample/node.yml' => str_replace('category: netzwerk', "category: netzwerk\ninteraction: scenario", $this->validNodeDef())."\n".<<<'YAML'
            scenario:
              start: frage
              steps:
                frage:
                  terminal: true
                  outcome: correct
                  reveal: test-flag
            YAML,
        ]));

        $result = $this->validate($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
    }

    /**
     * Achievement-System (Auftrag Abschnitt 7): node.yml darf optional
     * Achievement-Slugs deklarieren, aber nur solche, die in der zentralen
     * Registry existieren.
     */
    public function test_node_unknown_achievement_slug_fails(): void
    {
        $dir = $this->buildContentDir($this->nodeFiles([
            'nodes/sample/node.yml' => str_replace(
                'skills: [netzwerk]',
                "skills: [netzwerk]\nachievements: [does-not-exist]",
                $this->validNodeDef(),
            ),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('unbekanntes Achievement "does-not-exist"', $result['output']);
    }

    public function test_node_known_achievement_slug_passes(): void
    {
        $dir = $this->buildContentDir($this->nodeFiles([
            'nodes/sample/node.yml' => str_replace(
                'skills: [netzwerk]',
                "skills: [netzwerk]\nachievements: [echo-heard]",
                $this->validNodeDef(),
            ),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
    }

    public function test_valid_quiz_passes(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => $this->validLessonMeta().PHP_EOL.$this->validQuizMeta(),
            'lessons/1.0/de.md' => $this->validLessonMarkdown().PHP_EOL.PHP_EOL.$this->validQuizMarkdown(),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
    }

    public function test_quiz_question_without_matching_heading_fails(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => $this->validLessonMeta().PHP_EOL.$this->validQuizMeta(),
            'lessons/1.0/de.md' => $this->validLessonMarkdown(),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Quiz-Frage "q1" aus meta.yml hat keinen', $result['output']);
    }

    public function test_quiz_single_answer_index_out_of_range_fails(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => $this->validLessonMeta().PHP_EOL.str_replace('answer: 1', 'answer: 9', $this->validQuizMeta()),
            'lessons/1.0/de.md' => $this->validLessonMarkdown().PHP_EOL.PHP_EOL.$this->validQuizMarkdown(),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Quiz-Frage "q1": answer-Index liegt ausserhalb', $result['output']);
    }

    public function test_quiz_multi_answer_must_be_a_non_empty_list(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => $this->validLessonMeta().PHP_EOL.str_replace('answer: [0, 2]', 'answer: []', $this->validQuizMeta()),
            'lessons/1.0/de.md' => $this->validLessonMarkdown().PHP_EOL.PHP_EOL.$this->validQuizMarkdown(),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('answer muss bei type multi eine nicht-leere Liste sein', $result['output']);
    }

    public function test_quiz_input_answer_must_not_be_empty(): void
    {
        $dir = $this->buildContentDir([
            'lessons/1.0/meta.yml' => $this->validLessonMeta().PHP_EOL.str_replace('answer: "dcmdump datei.dcm"', 'answer: ""', $this->validQuizMeta()),
            'lessons/1.0/de.md' => $this->validLessonMarkdown().PHP_EOL.PHP_EOL.$this->validQuizMarkdown(),
        ]);

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('answer muss bei type input ein nicht-leerer String sein', $result['output']);
    }

    public function test_valid_exam_passes(): void
    {
        $dir = $this->buildContentDir($this->examFiles());

        $result = $this->validate($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
    }

    public function test_exam_question_without_matching_heading_fails(): void
    {
        $dir = $this->buildContentDir($this->examFiles([
            'exams/fundamente/de.md' => str_replace('### f01 —', '### fXX —', $this->validExamMarkdown()),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Pruefungsfrage "f01" aus exam.yml hat keinen', $result['output']);
    }

    public function test_exam_question_without_explanation_fails(): void
    {
        $dir = $this->buildContentDir($this->examFiles([
            'exams/fundamente/de.md' => str_replace(
                '**Erklärung:** Testerklaerung eins.',
                'Keine Erklaerung hier.',
                $this->validExamMarkdown(),
            ),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('braucht genau eine "**Erklärung:**"-Zeile', $result['output']);
    }

    public function test_exam_unknown_type_fails(): void
    {
        $dir = $this->buildContentDir($this->examFiles([
            'exams/fundamente/exam.yml' => str_replace('type: single', 'type: exotic', $this->validExamYml()),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('unbekannter type "exotic"', $result['output']);
    }

    public function test_exam_review_with_dead_anchor_fails(): void
    {
        $dir = $this->buildContentDir($this->examFiles([
            'exams/fundamente/exam.yml' => str_replace('anchor: "intro"', 'anchor: "nicht-vorhanden"', $this->validExamYml()),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('ist keine Ueberschrift in Lektion', $result['output']);
    }

    public function test_exam_needs_at_least_four_questions_per_lesson(): void
    {
        $dir = $this->buildContentDir($this->examFiles([
            'exams/fundamente/exam.yml' => str_replace(
                <<<'YAML'
                  - id: f04
                    type: truefalse
                    answer: true
                    lesson: "1.0"
                    review: { lesson: "1.0", anchor: "intro" }
                    difficulty: 1
                    tags: [netzwerk]

                YAML,
                '',
                $this->validExamYml(),
            ),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('weniger als 4 Poolfragen', $result['output']);
    }

    public function test_exam_min_per_lesson_accepts_a_lower_override(): void
    {
        // Sonderfall Troubleshooting (P10.64): min_per_lesson senkt die
        // 4-Fragen-Quote bewusst ab, statt jede Lektion darauf zu zwingen.
        // Auf 4 gesetzt (dem tatsaechlichen Bestand der Fixture) darf das
        // keinen neuen Verstoss erzeugen.
        $dir = $this->buildContentDir($this->examFiles([
            'exams/fundamente/exam.yml' => str_replace('pass_percent: 80', "pass_percent: 80\nmin_per_lesson: 4", $this->validExamYml()),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
    }

    public function test_exam_min_per_lesson_must_be_a_non_negative_integer(): void
    {
        $dir = $this->buildContentDir($this->examFiles([
            'exams/fundamente/exam.yml' => str_replace('pass_percent: 80', "pass_percent: 80\nmin_per_lesson: -1", $this->validExamYml()),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('min_per_lesson muss eine nicht-negative Ganzzahl sein', $result['output']);
    }

    public function test_exam_tag_not_in_skills_fails(): void
    {
        $dir = $this->buildContentDir($this->examFiles([
            'exams/fundamente/exam.yml' => str_replace('tags: [netzwerk]', 'tags: [nichtvorhanden]', $this->validExamYml()),
        ]));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('existiert nicht in skills.yml', $result['output']);
    }

    // -----------------------------------------------------------------
    // Fragenbank: ref-Eintraege (ADR 0071/0079, W5)
    // -----------------------------------------------------------------

    public function test_valid_exam_ref_question_passes_without_its_own_markdown_block(): void
    {
        $dir = $this->buildContentDir(array_merge(
            $this->examFiles([
                'exams/fundamente/exam.yml' => str_replace(
                    "  - id: f01\n    type: single\n    answer: 0\n    lesson: \"1.0\"",
                    "  - id: f01\n    ref: { lesson: \"1.0\", question: \"q1\" }\n    lesson: \"1.0\"",
                    $this->validExamYml(),
                ),
                'exams/fundamente/de.md' => str_replace(
                    "### f01 — Testfrage eins?\n\n1. Richtig\n2. Falsch\n\n**Erklärung:** Testerklaerung eins.\n\n",
                    '',
                    $this->validExamMarkdown(),
                ),
            ]),
            [
                'lessons/1.0/meta.yml' => str_replace(
                    'glossary_terms: [dicom]',
                    "glossary_terms: [dicom]\nquiz:\n  - id: q1\n    type: single\n    answer: 0",
                    $this->validLessonMeta(),
                ),
                'lessons/1.0/de.md' => $this->validLessonMarkdown()."\n\n## Quiz\n\n**q1 — Testfrage eins?**\n1. Richtig\n2. Falsch\n",
            ],
        ));

        $result = $this->validate($dir);

        $this->assertSame(0, $result['exitCode'], $result['output']);
    }

    public function test_exam_ref_to_unknown_question_fails(): void
    {
        $dir = $this->buildContentDir(array_merge(
            $this->examFiles([
                'exams/fundamente/exam.yml' => str_replace(
                    "  - id: f01\n    type: single\n    answer: 0\n    lesson: \"1.0\"",
                    "  - id: f01\n    ref: { lesson: \"1.0\", question: \"q99\" }\n    lesson: \"1.0\"",
                    $this->validExamYml(),
                ),
            ]),
            [
                'lessons/1.0/meta.yml' => str_replace(
                    'glossary_terms: [dicom]',
                    "glossary_terms: [dicom]\nquiz:\n  - id: q1\n    type: single\n    answer: 0",
                    $this->validLessonMeta(),
                ),
                'lessons/1.0/de.md' => $this->validLessonMarkdown()."\n\n## Quiz\n\n**q1 — Testfrage eins?**\n1. Richtig\n2. Falsch\n",
            ],
        ));

        $result = $this->validate($dir);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('ref.question "q99" existiert nicht im quiz-Block von Lektion "1.0"', $result['output']);
    }

    // -----------------------------------------------------------------
    // Fixture-Aufbau
    // -----------------------------------------------------------------

    /**
     * @return array{exitCode: int, output: string}
     */
    private function validate(string $path): array
    {
        $this->app->instance(ContentRepository::class, new ContentRepository($path));

        $exitCode = Artisan::call('content:validate');

        return ['exitCode' => $exitCode, 'output' => Artisan::output()];
    }

    /**
     * Baut eine minimale, gueltige content/-Struktur mit genau einer Lektion
     * (1.0) und wendet dann Ueberschreibungen an ($path => Inhalt, oder null
     * zum Loeschen der Datei) -- so bleibt jeder Testfall auf eine Regel
     * fokussiert.
     */
    private function buildContentDir(array $overrides = []): string
    {
        $dir = storage_path('framework/testing/content-'.Str::random(12));
        $this->tempDirs[] = $dir;

        $files = [
            'tracks.yml' => $this->validTracks(),
            'themenfelder.yml' => $this->validThemenfelder(),
            'tools/de.yml' => $this->validTools(),
            'glossary/de.yml' => $this->validGlossary(),
            'datasets.yml' => $this->validDatasets(),
            'lessons/1.0/meta.yml' => $this->validLessonMeta(),
            'lessons/1.0/de.md' => $this->validLessonMarkdown(),
        ];

        foreach ($overrides as $path => $content) {
            $files[$path] = $content;
        }

        foreach ($files as $relative => $content) {
            $target = $dir.'/'.$relative;
            File::ensureDirectoryExists(dirname($target));

            if ($content !== null) {
                File::put($target, $content);
            }
        }

        return $dir;
    }

    /**
     * Ergaenzt die Basis-Fixture um eine Node "sample", die auf Lektion 1.0
     * verweist -- fuer alle Node-spezifischen Regeln.
     */
    private function nodeFiles(array $overrides = []): array
    {
        return array_merge([
            'nodes/sample/node.yml' => $this->validNodeDef(),
            'nodes/sample/de.md' => $this->validNodeMarkdown(),
        ], $overrides);
    }

    private function today(): string
    {
        return Carbon::now()->toDateString();
    }

    private function validTracks(): string
    {
        return <<<'YAML'
        - slug: fundamente
          themenfeld: dicom
          order: 1
          title_key: track.fundamente.title
          level: einsteiger
          hours: 1
          status: published
        YAML;
    }

    private function validThemenfelder(): string
    {
        return <<<'YAML'
        - slug: dicom
          order: 1
          title_key: themenfeld.dicom.title
          status: published
        YAML;
    }

    private function validTools(): string
    {
        return <<<'YAML'
        dcmdump:
          name: dcmdump
          suite: dcmtk
          kind: datei
          purpose: Kippt den Inhalt aus.
          example: "dcmdump datei.dcm"
          lesson: "1.0"
          anchor: x
          needs_sandbox: false
        YAML;
    }

    private function validGlossary(): string
    {
        return <<<'YAML'
        dicom:
          term: DICOM
          expansion: Digital Imaging and Communications in Medicine
          short: Testbegriff.
          see_also: []
          lesson: "1.0"
        YAML;
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

    private function validLessonMeta(): string
    {
        $today = $this->today();

        return <<<YAML
        id: "1.0"
        track: fundamente
        order: 0
        duration_minutes: 5
        level: einsteiger
        objectives_count: 1
        requires: []
        tools: [dcmdump]
        sandbox:
          required: true
          dataset: test-set
        lab:
          node: null
          optional: true
        glossary_terms: [dicom]
        tools_checked: "{$today}"
        status: draft
        updated: "{$today}"
        YAML;
    }

    private function validQuizMeta(): string
    {
        return <<<'YAML'
        quiz:
          - id: q1
            type: single
            answer: 1
          - id: q2
            type: multi
            answer: [0, 2]
          - id: q3
            type: input
            answer: "dcmdump datei.dcm"
        YAML;
    }

    private function validQuizMarkdown(): string
    {
        return <<<'MD'
        ## Quiz

        **q1 — Frage eins?**
        1. Falsch
        2. Richtig
        3. Auch falsch

        **q2 — Frage zwei?** *(Mehrfachauswahl)*
        1. Richtig eins
        2. Falsch
        3. Richtig zwei

        **q3 — Frage drei?** *(Freitext)*

        ---

        **Als Nächstes:** Weiter geht's.
        MD;
    }

    private function validLessonMarkdown(): string
    {
        return <<<'MD'
        ---
        title: Test
        teaser: Test
        objectives:
          - Eins
        ---

        ## Intro

        {{term:dicom}} ist wichtig.

        ```
        $ dcmdump datei.dcm
        (0008,0060) CS [CT]
        ```

        **Was du daran abliest:** Test.
        MD;
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
          templates:
            - label_key: tpl.echo
              command: "echoscu -aet WS -aec ZIEL_AE 10.0.0.1 104"
          placeholders: [ZIEL_AE]

        flag:
          type: tag_value
          source_tag: "0008,103E"
          hash: "sha256:<platzhalter>"
          case_sensitive: false

        hints:
          - id: h1
            cost: 1
          - id: h2
            cost: 2

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

        ### h2

        Text.

        ## Write-up

        ```
        $ dcmdump datei.dcm
        (0008,0060) CS [CT]
        ```

        **Was du daran abliest:** Test.
        MD;
    }

    /**
     * Ergaenzt die Basis-Fixture um eine Track-Abschlusspruefung fuer
     * "fundamente" mit 4 Fragen zu Lektion 1.0 und 4 cross-Fragen -- gerade
     * genug, um jede Aggregatregel (Poolgroesse je Lektion, cross-Minimum,
     * Typmischung, difficulty-3-Anteil) am unteren Rand real zu erfuellen.
     */
    private function examFiles(array $overrides = []): array
    {
        return array_merge([
            'skills.yml' => $this->validSkills(),
            'exams/fundamente/exam.yml' => $this->validExamYml(),
            'exams/fundamente/de.md' => $this->validExamMarkdown(),
        ], $overrides);
    }

    private function validSkills(): string
    {
        return <<<'YAML'
        - slug: netzwerk
          label: Netzwerk
        - slug: datenmodell
          label: Datenmodell
        YAML;
    }

    private function validExamYml(): string
    {
        return <<<'YAML'
        track: fundamente
        title_key: exam.fundamente.title
        pass_percent: 80
        draw: 6
        duration_minutes: 10
        shuffle: true

        questions:
          - id: f01
            type: single
            answer: 0
            lesson: "1.0"
            review: { lesson: "1.0", anchor: "intro" }
            difficulty: 1
            tags: [netzwerk]

          - id: f02
            type: single
            answer: 1
            lesson: "1.0"
            review: { lesson: "1.0", anchor: "intro" }
            difficulty: 2
            tags: [netzwerk]

          - id: f03
            type: multi
            answer: [0, 1]
            lesson: "1.0"
            review: { lesson: "1.0", anchor: "intro" }
            difficulty: 2
            tags: [datenmodell]

          - id: f04
            type: truefalse
            answer: true
            lesson: "1.0"
            review: { lesson: "1.0", anchor: "intro" }
            difficulty: 1
            tags: [netzwerk]

          - id: f05
            type: single
            answer: 0
            lesson: "cross"
            review:
              - { lesson: "1.0", anchor: "intro" }
            difficulty: 3
            tags: [netzwerk]

          - id: f06
            type: multi
            answer: [0, 1]
            lesson: "cross"
            review:
              - { lesson: "1.0", anchor: "intro" }
            difficulty: 3
            tags: [datenmodell]

          - id: f07
            type: truefalse
            answer: false
            lesson: "cross"
            review:
              - { lesson: "1.0", anchor: "intro" }
            difficulty: 2
            tags: [netzwerk]

          - id: f08
            type: input
            answer: "test"
            lesson: "cross"
            review:
              - { lesson: "1.0", anchor: "intro" }
            difficulty: 2
            tags: [datenmodell]
        YAML;
    }

    private function validExamMarkdown(): string
    {
        return <<<'MD'
        ---
        title: Testpruefung
        intro: Test.
        ---

        ### f01 — Testfrage eins?

        1. Richtig
        2. Falsch

        **Erklärung:** Testerklaerung eins.

        ### f02 — Testfrage zwei?

        1. Falsch
        2. Richtig

        **Erklärung:** Testerklaerung zwei.

        ### f03 — Testfrage drei? *(Mehrfachauswahl)*

        1. Richtig eins
        2. Richtig zwei
        3. Falsch

        **Erklärung:** Testerklaerung drei.

        ### f04 — Testaussage vier.

        **Richtig / Falsch**

        **Erklärung:** Testerklaerung vier.

        ### f05 — Testfrage fuenf (uebergreifend)?

        1. Richtig
        2. Falsch

        **Erklärung:** Testerklaerung fuenf.

        ### f06 — Testfrage sechs (uebergreifend)? *(Mehrfachauswahl)*

        1. Richtig eins
        2. Richtig zwei
        3. Falsch

        **Erklärung:** Testerklaerung sechs.

        ### f07 — Testaussage sieben (uebergreifend).

        **Richtig / Falsch**

        **Erklärung:** Testerklaerung sieben.

        ### f08 — Testfrage acht (uebergreifend)? *(Freitext)*

        **Erklärung:** Testerklaerung acht.
        MD;
    }
}

<?php

namespace Tests\Unit\Content;

use App\Content\LabAssertionEvaluator;
use App\Models\Lab;
use Tests\TestCase;

/**
 * CMS-8d: wertet `Lab::assertions` gegen `RuntimeSessionService::events()`
 * aus -- geschlossener `command_executed`-Zweig, keine DB nötig (reine
 * Auswertungslogik gegen ein in-memory `Lab`-Modell).
 */
class LabAssertionEvaluatorTest extends TestCase
{
    public function test_command_executed_matches_an_exact_command_with_no_extra_arguments(): void
    {
        $lab = $this->labWith([['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1 4242']]);
        $events = $this->eventsWith(['echoscu 127.0.0.1 4242', 0]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertTrue($result->allSatisfied);
        $this->assertSame(['command_executed:echoscu 127.0.0.1 4242'], $result->passed);
    }

    public function test_command_executed_matches_a_prefix_followed_by_extra_arguments(): void
    {
        $lab = $this->labWith([['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1 4242']]);
        $events = $this->eventsWith(['echoscu 127.0.0.1 4242 -aec ORTHANC', 0]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertTrue($result->allSatisfied);
        $this->assertSame(['command_executed:echoscu 127.0.0.1 4242'], $result->passed);
    }

    public function test_command_executed_is_not_satisfied_by_a_non_matching_prefix(): void
    {
        $lab = $this->labWith([['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1 4242']]);
        $events = $this->eventsWith(['dcmdump foo.dcm', 0]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
        $this->assertSame([], $result->passed);
    }

    public function test_command_executed_is_not_satisfied_by_a_nonzero_exit_code(): void
    {
        $lab = $this->labWith([['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1 4242']]);
        $events = $this->eventsWith(['echoscu 127.0.0.1 4242 -aec ORTHANC', 1]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
    }

    public function test_an_unknown_assertion_type_is_never_satisfied(): void
    {
        $lab = $this->labWith([['type' => 'c_store_received', 'sop_class' => 'CTImageStorage']]);
        $events = $this->eventsWith(['echoscu 127.0.0.1 4242 -aec ORTHANC', 0]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
    }

    /**
     * Betreiber-Korrektur: der Exec-Fact selbst ist vertrauenswürdig, aber
     * die Shell liefert exit_code=0 fuer eine Verkettung, selbst wenn der
     * eigentliche Befehl fehlschlaegt -- str_starts_with()+exit_code===0
     * allein waere dadurch aushebelbar.
     */
    public function test_command_executed_rejects_shell_chaining_after_a_matching_prefix(): void
    {
        $lab = $this->labWith([['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1 4242 -aec ORTHANC']]);

        $chained = [
            'echoscu 127.0.0.1 4242 -aec ORTHANC || true',
            'echoscu 127.0.0.1 4242 -aec ORTHANC; true',
            'echoscu 127.0.0.1 4242 -aec ORTHANC && true',
            'echoscu 127.0.0.1 4242 -aec ORTHANC | cat',
            'echoscu 127.0.0.1 4242 -aec ORTHANC `true`',
            'echoscu 127.0.0.1 4242 -aec ORTHANC $(true)',
            // Betreiber-Korrektur: /bin/sh -c akzeptiert auch ein einzelnes
            // "&" (Hintergrundausfuehrung) sowie Zeilenumbrueche als
            // Befehlssteuerung -- die urspruengliche Liste (nur ;, &&, ||,
            // |, `, $() liess genau diese drei Faelle durch.
            'echoscu 127.0.0.1 4242 -aec ORTHANC & true',
            "echoscu 127.0.0.1 4242 -aec ORTHANC\ntrue",
            "echoscu 127.0.0.1 4242 -aec ORTHANC\rtrue",
        ];

        foreach ($chained as $command) {
            $result = (new LabAssertionEvaluator)->evaluate($lab, $this->eventsWith([$command, 0]), []);

            $this->assertFalse($result->allSatisfied, "sollte '{$command}' ablehnen");
        }
    }

    /**
     * Betreiber-Korrektur: `str_starts_with()` allein verlangt keine
     * Token-Grenze -- ein kurzer Präfix würde damit ein voellig anderes
     * Programm matchen, das nur zufaellig denselben Anfang hat.
     */
    public function test_command_executed_rejects_a_prefix_collision_without_a_token_boundary(): void
    {
        $lab = $this->labWith([['type' => 'command_executed', 'prefix' => 'echoscu']]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $this->eventsWith(['echoscuXYZ --harmless', 0]), []);

        $this->assertFalse($result->allSatisfied);
    }

    /**
     * Monotonie ueber Runtime-Neustarts: /events's Exec-Facts leben in
     * Redis pro SandboxSession (CMS-8b) -- nach einem Neustart ist die
     * Exec-Historie leer, eine bereits bestandene Assertion darf trotzdem
     * nicht verloren gehen.
     */
    public function test_an_already_passed_assertion_stays_passed_even_if_events_no_longer_show_it(): void
    {
        $lab = $this->labWith([['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1 4242']]);
        $alreadyPassed = ['command_executed:echoscu 127.0.0.1 4242'];
        $emptyEvents = ['exec' => [], 'orthanc' => ['new_instances' => []]];

        $result = (new LabAssertionEvaluator)->evaluate($lab, $emptyEvents, $alreadyPassed);

        $this->assertTrue($result->allSatisfied);
        $this->assertSame($alreadyPassed, $result->passed);
    }

    /**
     * Betreiber-Korrektur: eine veraltete ID (Assertion wurde im Editor
     * geaendert/entfernt) darf NICHT einfach mitgezaehlt werden -- sonst
     * koennte count($passed) === count($lab->assertions) faelschlich
     * "vollstaendig erfuellt" ergeben.
     */
    public function test_a_stale_passed_id_no_longer_in_the_current_assertions_is_discarded(): void
    {
        $lab = $this->labWith([['type' => 'command_executed', 'prefix' => 'storescu 127.0.0.1 4242']]);
        $stalePassed = ['command_executed:echoscu 127.0.0.1 4242'];
        $emptyEvents = ['exec' => [], 'orthanc' => ['new_instances' => []]];

        $result = (new LabAssertionEvaluator)->evaluate($lab, $emptyEvents, $stalePassed);

        $this->assertFalse($result->allSatisfied);
        $this->assertSame([], $result->passed);
    }

    /**
     * Dediziert von der Stale-ID-Normalisierung getrennt: hier stimmt die
     * ANZAHL zufaellig ueberein (zwei alte IDs, zwei aktuelle Assertions) --
     * ein Evaluator, der `count($passed) === count($lab->assertions)` statt
     * eines echten ID-Abgleichs prueft, wuerde das faelschlich als
     * vollstaendig erfuellt werten.
     */
    public function test_all_satisfied_checks_the_actual_ids_not_just_the_passed_count(): void
    {
        $lab = $this->labWith([
            ['type' => 'command_executed', 'prefix' => 'echoscu 127.0.0.1 4242'],
            ['type' => 'command_executed', 'prefix' => 'storescu 127.0.0.1 4242'],
        ]);
        $stalePassed = [
            'command_executed:findscu 127.0.0.1 4242',
            'command_executed:movescu 127.0.0.1 4242',
        ];
        $emptyEvents = ['exec' => [], 'orthanc' => ['new_instances' => []]];

        $result = (new LabAssertionEvaluator)->evaluate($lab, $emptyEvents, $stalePassed);

        $this->assertFalse($result->allSatisfied);
        $this->assertSame([], $result->passed);
    }

    /**
     * PR #150 (Assertion-/Grading-Audit): `dicom_instance_received` liest
     * ausschliesslich `events['orthanc']['new_instances']`, Serverwahrheit
     * ueber Orthanc statt eines Command-Prefix.
     */
    public function test_dicom_instance_received_matches_a_single_received_instance_without_any_filter(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received']]);
        $events = $this->orthancEventsWith([$this->dicomInstance()]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertTrue($result->allSatisfied);
    }

    public function test_dicom_instance_received_is_not_satisfied_without_any_instance(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received']]);
        $events = $this->orthancEventsWith([]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
    }

    public function test_dicom_instance_received_rejects_a_wrong_sop_class(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received', 'sop_class' => '1.2.840.10008.5.1.4.1.1.2']]);
        $events = $this->orthancEventsWith([$this->dicomInstance(['sop_class' => '1.2.840.10008.5.1.4.1.1.7'])]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
    }

    public function test_dicom_instance_received_rejects_a_wrong_patient_id(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received', 'patient_id' => 'LAB-0042']]);
        $events = $this->orthancEventsWith([$this->dicomInstance(['patient_id' => 'LAB-9999'])]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
    }

    public function test_dicom_instance_received_rejects_a_wrong_study_instance_uid(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received', 'study_instance_uid' => '1.2.3.4.5']]);
        $events = $this->orthancEventsWith([$this->dicomInstance(['study_instance_uid' => '9.9.9.9.9'])]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
    }

    public function test_dicom_instance_received_rejects_a_wrong_modality(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received', 'modality' => 'CT']]);
        $events = $this->orthancEventsWith([$this->dicomInstance(['modality' => 'MR'])]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
    }

    /**
     * Betreiber-Vorgabe: Modality normalisieren (z. B. Grossschreibung) statt
     * exakt byteweise zu vergleichen -- weder Autor noch Orthanc garantieren
     * dieselbe Schreibweise.
     */
    public function test_dicom_instance_received_normalizes_modality_case(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received', 'modality' => 'ct']]);
        $events = $this->orthancEventsWith([$this->dicomInstance(['modality' => 'CT'])]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertTrue($result->allSatisfied);
    }

    public function test_dicom_instance_received_is_satisfied_once_min_instances_is_reached(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received', 'min_instances' => 3]]);
        $events = $this->orthancEventsWith([
            $this->dicomInstance(['instance_id' => 'inst-1']),
            $this->dicomInstance(['instance_id' => 'inst-2']),
            $this->dicomInstance(['instance_id' => 'inst-3']),
        ]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertTrue($result->allSatisfied);
    }

    public function test_dicom_instance_received_is_not_satisfied_below_min_instances(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received', 'min_instances' => 3]]);
        $events = $this->orthancEventsWith([
            $this->dicomInstance(['instance_id' => 'inst-1']),
            $this->dicomInstance(['instance_id' => 'inst-2']),
        ]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
    }

    /**
     * Mehrere Filter gleichzeitig (AND): eine Instanz mit richtiger
     * Patient-ID, aber falscher Modality zaehlt NICHT fuer min_instances.
     */
    public function test_dicom_instance_received_combines_multiple_filters_with_and(): void
    {
        $lab = $this->labWith([[
            'type' => 'dicom_instance_received',
            'patient_id' => 'LAB-0042',
            'modality' => 'CT',
            'min_instances' => 2,
        ]]);
        $events = $this->orthancEventsWith([
            $this->dicomInstance(['instance_id' => 'inst-1', 'patient_id' => 'LAB-0042', 'modality' => 'CT']),
            $this->dicomInstance(['instance_id' => 'inst-2', 'patient_id' => 'LAB-0042', 'modality' => 'MR']),
            $this->dicomInstance(['instance_id' => 'inst-3', 'patient_id' => 'LAB-0042', 'modality' => 'CT']),
        ]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertTrue($result->allSatisfied);
    }

    public function test_dicom_instance_received_treats_missing_optional_fields_as_no_match_without_crashing(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received', 'sop_class' => '1.2.840.10008.5.1.4.1.1.2']]);
        $events = $this->orthancEventsWith([
            ['instance_id' => 'inst-1'],
        ]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
    }

    /**
     * Robustheit gegen fehlende Struktur, nicht nur fehlende Einzelfelder --
     * `orthanc` selbst kann fehlen (z. B. ein sehr alter Events-Payload).
     */
    public function test_dicom_instance_received_is_not_satisfied_when_orthanc_facts_are_entirely_missing(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received']]);
        $events = ['exec' => []];

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
    }

    /**
     * Betreiber-Vorgabe: die fachliche Frage ist "wie viele Instanzen sind
     * angekommen", nicht "wie viele Change-Events wurden erzeugt" -- ein
     * doppelt gemeldetes `instance_id` zaehlt nur einmal.
     */
    public function test_dicom_instance_received_deduplicates_repeated_instance_ids(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received', 'min_instances' => 2]]);
        $events = $this->orthancEventsWith([
            $this->dicomInstance(['instance_id' => 'inst-1']),
            $this->dicomInstance(['instance_id' => 'inst-1']),
        ]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
    }

    public function test_a_dicom_instance_received_assertion_that_already_passed_stays_passed_without_new_instances(): void
    {
        $lab = $this->labWith([['type' => 'dicom_instance_received', 'patient_id' => 'LAB-0042']]);
        $identifier = LabAssertionEvaluator::identifierFor($lab->assertions[0]);
        $events = $this->orthancEventsWith([]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, [$identifier]);

        $this->assertTrue($result->allSatisfied);
        $this->assertSame([$identifier], $result->passed);
    }

    public function test_multiple_dicom_instance_received_assertions_get_distinct_identifiers(): void
    {
        $a = LabAssertionEvaluator::identifierFor(['type' => 'dicom_instance_received', 'patient_id' => 'LAB-01']);
        $b = LabAssertionEvaluator::identifierFor(['type' => 'dicom_instance_received', 'modality' => 'CT', 'min_instances' => 10]);

        $this->assertNotSame($a, $b);
    }

    public function test_two_dicom_instance_received_assertions_in_the_same_lab_are_tracked_independently(): void
    {
        $lab = $this->labWith([
            ['type' => 'dicom_instance_received', 'patient_id' => 'LAB-01'],
            ['type' => 'dicom_instance_received', 'modality' => 'CT', 'min_instances' => 10],
        ]);
        $events = $this->orthancEventsWith([$this->dicomInstance(['patient_id' => 'LAB-01', 'modality' => 'MR'])]);

        $result = (new LabAssertionEvaluator)->evaluate($lab, $events, []);

        $this->assertFalse($result->allSatisfied);
        $this->assertCount(1, $result->passed);
        $this->assertStringContainsString('LAB-01', $result->passed[0]);
    }

    /**
     * Betreiber-Vorgabe: dieselbe Assertion mit anderer JSON-Schluessel-
     * reihenfolge im Autoren-Editor muss dieselbe ID ergeben, sonst
     * wuerde ein rein kosmetischer erneuter Speichervorgang
     * `assertions_passed` unnoetig zuruecksetzen.
     */
    public function test_identifier_for_a_dicom_instance_received_assertion_is_independent_of_key_order(): void
    {
        $a = LabAssertionEvaluator::identifierFor(['type' => 'dicom_instance_received', 'patient_id' => 'X', 'modality' => 'ct']);
        $b = LabAssertionEvaluator::identifierFor(['modality' => 'CT', 'type' => 'dicom_instance_received', 'patient_id' => 'X']);

        $this->assertSame($a, $b);
    }

    public function test_identifier_for_a_dicom_instance_received_assertion_treats_default_and_explicit_min_instances_as_equal(): void
    {
        $a = LabAssertionEvaluator::identifierFor(['type' => 'dicom_instance_received', 'patient_id' => 'X']);
        $b = LabAssertionEvaluator::identifierFor(['type' => 'dicom_instance_received', 'patient_id' => 'X', 'min_instances' => 1]);

        $this->assertSame($a, $b);
    }

    /**
     * Gemischt mit `command_executed` im selben Lab: die bestehende
     * AND-Semantik (allSatisfied erst, wenn ALLE aktuellen IDs erfuellt
     * sind) bleibt unveraendert -- keine Sonderbehandlung im Evaluator
     * fuer die Typ-Mischung noetig.
     */
    public function test_dicom_instance_received_combined_with_command_executed_requires_both(): void
    {
        $lab = $this->labWith([
            ['type' => 'command_executed', 'prefix' => 'storescu 127.0.0.1 4242'],
            ['type' => 'dicom_instance_received', 'patient_id' => '4711'],
        ]);

        $execOnly = [
            'exec' => [['command' => 'storescu 127.0.0.1 4242 -aec ORTHANC file.dcm', 'exit_code' => 0, 'timestamp' => 't1', 'stdout_preview' => '']],
            'orthanc' => ['new_instances' => []],
        ];

        $result = (new LabAssertionEvaluator)->evaluate($lab, $execOnly, []);
        $this->assertFalse($result->allSatisfied);
        $this->assertCount(1, $result->passed);

        $both = [
            'exec' => $execOnly['exec'],
            'orthanc' => ['new_instances' => [$this->dicomInstance(['patient_id' => '4711'])]],
        ];

        $result2 = (new LabAssertionEvaluator)->evaluate($lab, $both, $result->passed);
        $this->assertTrue($result2->allSatisfied);
        $this->assertCount(2, $result2->passed);
    }

    /**
     * @param  list<array<string, mixed>>  $assertions
     */
    private function labWith(array $assertions): Lab
    {
        return new Lab(['assertions' => $assertions]);
    }

    /**
     * @param  array{0: string, 1: int}  $execEvent  [command, exit_code]
     * @return array<string, mixed>
     */
    private function eventsWith(array $execEvent): array
    {
        return [
            'exec' => [
                ['command' => $execEvent[0], 'exit_code' => $execEvent[1], 'timestamp' => 't1', 'stdout_preview' => ''],
            ],
            'orthanc' => ['new_instances' => []],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $instances
     * @return array<string, mixed>
     */
    private function orthancEventsWith(array $instances): array
    {
        return [
            'exec' => [],
            'orthanc' => ['new_instances' => $instances],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function dicomInstance(array $overrides = []): array
    {
        return array_merge([
            'instance_id' => 'inst-1',
            'sop_class' => '1.2.840.10008.5.1.4.1.1.2',
            'transfer_syntax' => '1.2.840.10008.1.2.1',
            'patient_id' => '4711',
            'study_instance_uid' => '1.2.3.4.5',
            'modality' => 'CT',
        ], $overrides);
    }
}

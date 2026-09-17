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
}

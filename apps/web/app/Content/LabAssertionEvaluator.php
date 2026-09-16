<?php

namespace App\Content;

use App\Models\Lab;

/**
 * Wertet `Lab::assertions` gegen die Fakten eines `RuntimeSessionService::
 * events()`-Aufrufs aus (CMS-8d). Stil wie `AnswerGrader`: eine kleine,
 * zustandslose Klasse mit einem `match()`-Dispatcher, kein DSL/Registry --
 * ein zweiter Assertion-Typ (`c_store_received`, CMS-8e/später) kommt als
 * weiterer Zweig in `isSatisfied()` dazu, keine Umstrukturierung.
 */
final class LabAssertionEvaluator
{
    /**
     * Verbietet Shell-Verkettung/Substitution irgendwo im Befehl (nicht nur
     * nach dem Präfix -- ein kurzer Präfix würde sonst auch
     * "echoscu; rm -rf /" matchen) -- ohne das wäre `command_executed` durch
     * z. B. "echoscu ... -aec ORTHANC || true" aushebelbar: der Exec-Fact
     * selbst ist vertrauenswürdig (die Toolbox führt aus, was der Lernende
     * tatsächlich eingegeben hat), aber die Shell liefert für eine solche
     * Verkettung `exit_code=0`, selbst wenn `echoscu` fehlschlägt.
     *
     * Betreiber-Korrektur: `&&`/`||` sind absichtlich NICHT mehr einzeln
     * aufgeführt -- ein einzelnes `&`/`|` verbietet sie automatisch mit,
     * schließt aber zusätzlich `/bin/sh -c`-Steuerzeichen, die die
     * ursprüngliche Liste übersehen hat: ein einzelnes `&` (Hintergrund-
     * Ausführung, `echoscu ... & true` liefert ebenfalls `exit_code=0` für
     * die Shell) sowie `\n`/`\r` (ein zweiter Befehl in derselben
     * Exec-Payload).
     */
    private const FORBIDDEN_SHELL_OPERATORS = [';', '&', '|', '`', '$(', "\n", "\r"];

    /**
     * @param  array<string, mixed>  $events  Rückgabe von RuntimeSessionService::events()
     * @param  list<string>  $alreadyPassed  bisheriger LabAttempt::assertions_passed-Stand
     */
    public function evaluate(Lab $lab, array $events, array $alreadyPassed): LabEvaluationResult
    {
        // Gegen die AKTUELLEN Assertions normalisieren, nicht blind die
        // alte Menge weiterschleppen -- sonst könnte ein später geändertes
        // Lab (Assertion entfernt/ausgetauscht) fälschlich als "vollständig
        // erfüllt" durchgehen, weil die Anzahl zufällig wieder übereinstimmt.
        $currentIds = array_values(array_unique(array_map(
            self::identifierFor(...),
            $lab->assertions,
        )));

        $passed = array_values(array_intersect(array_unique($alreadyPassed), $currentIds));

        foreach ($lab->assertions as $assertion) {
            $id = self::identifierFor($assertion);

            if (in_array($id, $passed, true)) {
                continue;
            }

            if ($this->isSatisfied($assertion, $events)) {
                $passed[] = $id;
            }
        }

        return new LabEvaluationResult(
            passed: $passed,
            allSatisfied: collect($currentIds)->every(fn (string $id): bool => in_array($id, $passed, true)),
        );
    }

    /**
     * @param  array<string, mixed>  $assertion
     * @param  array<string, mixed>  $events
     */
    private function isSatisfied(array $assertion, array $events): bool
    {
        /** @var list<array<string, mixed>> $execEvents */
        $execEvents = $events['exec'];

        return match ($assertion['type']) {
            'command_executed' => collect($execEvents)->contains(
                fn (array $event): bool => $event['exit_code'] === 0
                    && ! $this->containsShellOperator($event['command'])
                    && $this->matchesPrefix($event['command'], $assertion['prefix']),
            ),
            default => false,
        };
    }

    private function containsShellOperator(string $command): bool
    {
        foreach (self::FORBIDDEN_SHELL_OPERATORS as $operator) {
            if (str_contains($command, $operator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Betreiber-Korrektur: `str_starts_with()` allein verlangt keine
     * Token-Grenze -- ein Präfix "echoscu" würde damit auch "echoscuXYZ"
     * matchen, ein ganz anderes Programm. Der Präfix muss stattdessen exakt
     * enden (kein weiterer Befehl) oder von einem Leerzeichen/Tab gefolgt
     * sein (echte Argumenttrennung).
     */
    private function matchesPrefix(string $command, string $prefix): bool
    {
        $command = trim($command);
        $prefix = trim($prefix);

        return $command === $prefix
            || str_starts_with($command, $prefix.' ')
            || str_starts_with($command, $prefix."\t");
    }

    /**
     * Baut die ID aus `type` + `prefix`, wenn vorhanden -- `prefix` ist nur
     * fuer `command_executed` garantiert. Ein zukuenftiger Typ ohne
     * `prefix` (z. B. `c_store_received`s `sop_class`) darf hier nicht mit
     * einem Undefined-Array-Key-Fehler abstuerzen, auch wenn er heute
     * durch `LabActivity::validate()` beim Publish ohnehin nie in
     * `$lab->assertions` landen sollte.
     *
     * @param  array<string, mixed>  $assertion
     */
    public static function identifierFor(array $assertion): string
    {
        return ($assertion['type'] ?? '').':'.($assertion['prefix'] ?? '');
    }
}

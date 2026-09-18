<?php

namespace App\Content;

use App\Models\Lab;

/**
 * Wertet `Lab::assertions` gegen die Fakten eines `RuntimeSessionService::
 * events()`-Aufrufs aus (CMS-8d). Stil wie `AnswerGrader`: eine kleine,
 * zustandslose Klasse mit einem `match()`-Dispatcher, kein DSL/Registry --
 * ein weiterer Assertion-Typ kommt als weiterer Zweig in `isSatisfied()`
 * dazu, keine Umstrukturierung. Seit PR #150 zwei Typen: `command_executed`
 * (prueft einen Exec-Fact -- "wurde ein passender Befehl erfolgreich
 * ausgefuehrt") und `dicom_instance_received` (prueft einen Orthanc-Fact --
 * "ist die erwartete DICOM-Instanz tatsaechlich im PACS angekommen",
 * werkzeugunabhaengig, Assertion-/Grading-Audit Abschnitt 6).
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
            'dicom_instance_received' => $this->isDicomInstanceReceivedSatisfied($assertion, $events),
            default => false,
        };
    }

    /**
     * PR #150 (Assertion-/Grading-Audit): liest AUSSCHLIESSLICH
     * `events['orthanc']['new_instances']` (Orthancs eigene Serverwahrheit
     * ueber `docker_ops.collect_orthanc_facts()`, CMS-8b) -- nie
     * `events['exec']`. Anders als `command_executed` ist dieser Typ damit
     * werkzeugunabhaengig: nicht "wurde `storescu` ausgefuehrt", sondern
     * "ist die erwartete Instanz tatsaechlich im PACS angekommen", egal mit
     * welchem Befehl.
     *
     * Robust gegen fehlendes `orthanc`/`new_instances`/einzelne Felder
     * (Betreiber-Vorgabe) -- jede strukturelle Abweichung ergibt schlicht
     * `false`, nie einen Fehler.
     *
     * @param  array<string, mixed>  $assertion
     * @param  array<string, mixed>  $events
     */
    private function isDicomInstanceReceivedSatisfied(array $assertion, array $events): bool
    {
        $instances = $events['orthanc']['new_instances'] ?? null;

        if (! is_array($instances)) {
            return false;
        }

        $minInstances = self::normalizedMinInstances($assertion);
        $seenInstanceIds = [];
        $matching = 0;

        foreach ($instances as $instance) {
            if (! is_array($instance)) {
                continue;
            }

            // Betreiber-Vorgabe: die fachliche Frage ist "wie viele
            // Instanzen sind angekommen", nicht "wie viele Change-Events
            // wurden erzeugt" -- nach instance_id deduplizieren, BEVOR
            // gegen die Filter gezaehlt wird.
            $instanceId = $instance['instance_id'] ?? null;

            if (! is_string($instanceId) || $instanceId === '' || isset($seenInstanceIds[$instanceId])) {
                continue;
            }

            $seenInstanceIds[$instanceId] = true;

            if ($this->matchesDicomFilters($assertion, $instance)) {
                $matching++;
            }
        }

        return $matching >= $minInstances;
    }

    /**
     * Alle angegebenen Filter muessen gleichzeitig zutreffen (AND, keine
     * Autoren-Pflicht fuer irgendeinen Filter -- eine Assertion ganz ohne
     * Tag-Filter ist bewusst KEIN Always-True-Fall, siehe
     * `isDicomInstanceReceivedSatisfied()`s `min_instances`-Zaehlung
     * oben, sondern verlangt weiterhin mindestens eine tatsaechlich
     * empfangene Instanz).
     *
     * @param  array<string, mixed>  $assertion
     * @param  array<string, mixed>  $instance
     */
    private function matchesDicomFilters(array $assertion, array $instance): bool
    {
        foreach (['sop_class', 'patient_id', 'study_instance_uid'] as $key) {
            $expected = $assertion[$key] ?? null;

            if (! is_string($expected) || $expected === '') {
                continue;
            }

            $actual = $instance[$key] ?? null;

            if (! is_string($actual) || $actual !== $expected) {
                return false;
            }
        }

        $expectedModality = $assertion['modality'] ?? null;

        if (is_string($expectedModality) && $expectedModality !== '') {
            $actualModality = $instance['modality'] ?? null;

            if (! is_string($actualModality) || strtoupper($actualModality) !== strtoupper($expectedModality)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $assertion
     */
    private static function normalizedMinInstances(array $assertion): int
    {
        $value = $assertion['min_instances'] ?? 1;

        return is_int($value) && $value >= 1 ? $value : 1;
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
     * Baut die ID aus `type` + typ-spezifischen Parametern. `command_executed`
     * bleibt bei `type:prefix` (unveraendert). `dicom_instance_received`
     * (PR #150) hat kein `prefix` -- stattdessen eine kanonische,
     * sortiert-stabile Signatur seiner Filter-Parameter (`canonicalParams()`),
     * damit zwei Assertions desselben Typs mit unterschiedlichen Parametern
     * innerhalb eines Labs unterscheidbare, aber ueber wiederholte
     * `evaluate()`-Aufrufe und Editor-Speicherungen hinweg STABILE IDs
     * bekommen (`assertions_passed` verwendet genau diese ID).
     *
     * @param  array<string, mixed>  $assertion
     */
    public static function identifierFor(array $assertion): string
    {
        $type = (string) ($assertion['type'] ?? '');

        return match ($type) {
            'dicom_instance_received' => $type.':'.self::canonicalParams($assertion),
            default => $type.':'.($assertion['prefix'] ?? ''),
        };
    }

    /**
     * Sortiert nach Schluessel (Betreiber-Vorgabe: dieselbe Assertion mit
     * anderer JSON-Schluesselreihenfolge im Autoren-Editor muss dieselbe ID
     * ergeben) und normalisiert `modality` wie `matchesDicomFilters()`
     * (Gross-/Kleinschreibung darf die ID nicht beeinflussen). `min_instances`
     * ist IMMER Teil der Signatur, auch wenn der Autor sie nicht gesetzt hat
     * -- der EFFEKTIVE (defaultete) Wert zaehlt, sonst wuerden "kein Filter
     * gesetzt" und "min_instances: 1 explizit gesetzt" faelschlich zwei
     * verschiedene IDs fuer dieselbe fachliche Assertion ergeben.
     *
     * @param  array<string, mixed>  $assertion
     */
    private static function canonicalParams(array $assertion): string
    {
        $params = ['min_instances' => self::normalizedMinInstances($assertion)];

        foreach (['sop_class', 'patient_id', 'study_instance_uid'] as $key) {
            $value = $assertion[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $params[$key] = $value;
            }
        }

        $modality = $assertion['modality'] ?? null;

        if (is_string($modality) && $modality !== '') {
            $params['modality'] = strtoupper($modality);
        }

        ksort($params);

        return json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
}

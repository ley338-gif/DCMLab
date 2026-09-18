<?php

namespace App\Content;

/**
 * Ergebnis einer `LabAssertionEvaluator::evaluate()`-Auswertung (CMS-8d).
 */
final readonly class LabEvaluationResult
{
    /**
     * PR #153: `$progress` ist reine Beobachtung/UX, kein Grading -- keyed
     * wie `$passed` ueber `identifierFor()`. Enthaelt nie einen Eintrag fuer
     * eine (bereits vorher oder gerade eben) bestandene Assertion, siehe
     * `LabAssertionEvaluator::evaluate()`.
     *
     * @param  list<string>  $passed  `LabAssertionEvaluator::identifierFor()`-Werte
     * @param  array<string, array{current: int, required: int, unit: string}>  $progress
     */
    public function __construct(
        public array $passed,
        public bool $allSatisfied,
        public array $progress = [],
    ) {}
}

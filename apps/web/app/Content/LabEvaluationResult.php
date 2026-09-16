<?php

namespace App\Content;

/**
 * Ergebnis einer `LabAssertionEvaluator::evaluate()`-Auswertung (CMS-8d).
 */
final readonly class LabEvaluationResult
{
    /**
     * @param  list<string>  $passed  `LabAssertionEvaluator::identifierFor()`-Werte
     */
    public function __construct(
        public array $passed,
        public bool $allSatisfied,
    ) {}
}

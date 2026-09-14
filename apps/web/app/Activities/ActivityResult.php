<?php

namespace App\Activities;

use Carbon\CarbonImmutable;

/**
 * Das dauerhafte Ergebnis eines Nutzers auf einer Aktivitaet (ADR 0072) --
 * die Fachdaten hinter einer `activity_progress`-Zeile. Laufender
 * Versuchszustand (Engine-Sitzung, gezogene Pruefungsfragen, ...) ist nicht
 * Teil dieses Objekts, das bleibt typspezifisch.
 */
final readonly class ActivityResult
{
    /**
     * @param  list<string>  $skills  Belegte Skill-Kategorien aus ProfileService::SKILL_CATEGORIES
     */
    public function __construct(
        public bool $completed,
        public ?int $score = null,
        public ?int $maxScore = null,
        public array $skills = [],
        public ?CarbonImmutable $completedAt = null,
    ) {}
}

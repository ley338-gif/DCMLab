<?php

namespace App\Activities;

use App\Models\Activity;
use Closure;
use RuntimeException;

/**
 * Loest einen `Activity`-Verzeichniseintrag in seine ActivityContract-Instanz
 * auf (ADR 0072). Ein neuer Aktivitaetstyp registriert sich hier mit seinem
 * eigenen Type-String und einer Fabrikfunktion -- der Kern (ProfileService,
 * AchievementService, Dashboard) fragt nie nach dem konkreten Typ, nur nach
 * dem Vertrag. Nach demselben Muster wie EngineClientResolver, nur ueber
 * beliebig viele statt zwei Implementierungen.
 */
final class ActivityRegistry
{
    /**
     * @var array<string, Closure(Activity): ActivityContract>
     */
    private array $factories = [];

    public function register(ActivityType|string $type, Closure $factory): void
    {
        $this->factories[$this->key($type)] = $factory;
    }

    public function resolve(Activity $activity): ActivityContract
    {
        if (! isset($this->factories[$activity->type])) {
            throw new RuntimeException("Kein Aktivitaetstyp registriert fuer \"{$activity->type}\".");
        }

        return ($this->factories[$activity->type])($activity);
    }

    /**
     * @return list<string>
     */
    public function registeredTypes(): array
    {
        return array_keys($this->factories);
    }

    private function key(ActivityType|string $type): string
    {
        return $type instanceof ActivityType ? $type->value : $type;
    }
}

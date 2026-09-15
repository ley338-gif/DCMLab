<?php

namespace App\Services;

use App\Achievements\AchievementUnlockResult;
use App\Models\AchievementDefinition;
use App\Models\AchievementUnlock;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Generisches Achievement-System (Auftrag "Achievement-System"): eine
 * zentrale Unlock-Schicht statt verstreuter `if ($slug === ...)`-Bloecke in
 * Controllern. Bewusst kein Event/Listener-Aufbau -- das Projekt hat davon
 * an keiner Stelle Gebrauch gemacht (alles laeuft synchron ueber
 * Service-Aufrufe direkt aus Controllern, z. B. ProfileService), diese
 * Konvention wird hier fortgesetzt.
 */
final class AchievementService
{
    /**
     * Race-sicher wie ProfileService::maybeAwardFirstBlood(): der jeweils
     * passende Unique-Index laesst bei gleichzeitigen Unlock-Versuchen nur
     * den ersten Insert durch, der zweite faengt die Exception ab und
     * liefert already_unlocked.
     *
     * Bei `scope: global` (ADR 0077) ist die Vergabe nicht je Nutzer
     * begrenzt, sondern je Aktivitaet einmalig, unabhaengig davon, welcher
     * Nutzer zuerst gewinnt -- deshalb ist `$activity` dafuer Pflicht und
     * der Pruef-/Unique-Schluessel ist (achievement_definition_id,
     * activity_id) statt (user_id, achievement_definition_id).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function unlock(User $user, string $slug, array $metadata = [], ?Activity $activity = null): AchievementUnlockResult
    {
        $definition = AchievementDefinition::query()->where('slug', $slug)->first();

        if ($definition === null) {
            return AchievementUnlockResult::notFound();
        }

        $isGlobal = $definition->scope === 'global';

        if ($isGlobal && $activity === null) {
            throw new InvalidArgumentException("Global-scoped Achievement \"{$slug}\" braucht eine Aktivitaet.");
        }

        $existing = $isGlobal
            ? AchievementUnlock::query()->where('achievement_definition_id', $definition->id)->where('activity_id', $activity->id)->first()
            : AchievementUnlock::query()->where('user_id', $user->id)->where('achievement_definition_id', $definition->id)->first();

        if ($existing !== null) {
            return AchievementUnlockResult::alreadyUnlocked($definition);
        }

        try {
            $unlock = AchievementUnlock::create([
                'user_id' => $user->id,
                'achievement_definition_id' => $definition->id,
                'activity_id' => $isGlobal ? $activity->id : null,
                'unlocked_at' => now(),
                'metadata' => $metadata,
            ]);
        } catch (UniqueConstraintViolationException) {
            return AchievementUnlockResult::alreadyUnlocked($definition);
        }

        return AchievementUnlockResult::newlyUnlocked($definition, $unlock);
    }

    /**
     * Achievement-Daten fertig fuers Frontend (Auftrag Abschnitt 4/17):
     * keine Achievement-Definitionen im Vue-Code, nur fertige Objekte.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listForUser(User $user): Collection
    {
        $unlockedByDefinitionId = AchievementUnlock::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('achievement_definition_id');

        return AchievementDefinition::query()
            ->orderBy('sort_order')
            ->get()
            ->map(function (AchievementDefinition $definition) use ($unlockedByDefinitionId) {
                /** @var AchievementUnlock|null $unlock */
                $unlock = $unlockedByDefinitionId->get($definition->id);

                return $this->toArray($definition, $unlock);
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(AchievementDefinition $definition, ?AchievementUnlock $unlock): array
    {
        return [
            'slug' => $definition->slug,
            'name' => $definition->name,
            'description' => $definition->description,
            'image' => "/images/achievements/{$definition->image}",
            'category' => $definition->category,
            'rarity' => $definition->rarity,
            'unlocked' => $unlock !== null,
            'unlocked_at' => $unlock?->unlocked_at->toIso8601String(),
        ];
    }
}

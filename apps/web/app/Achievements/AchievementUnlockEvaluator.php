<?php

namespace App\Achievements;

use App\Activities\ActivityResult;
use App\Models\AchievementDefinition;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\User;
use App\Services\AchievementService;

/**
 * Wertet `achievement_definitions.unlock_when` gegen eine gerade
 * abgeschlossene Aktivitaet aus (ADR 0071/0077, W4): ein Achievement, das
 * nur als Dateneintrag in content/achievements.yml entsteht, wird beim
 * Abschluss der zugeordneten Aktivitaet vergeben, ohne dass dafuer eine
 * Zeile Controller-Code noetig ist. Aufgerufen von
 * App\Activities\ActivityProgressRecorder, direkt nachdem es
 * `activity_progress` fortgeschrieben hat.
 *
 * Bekannte Kriterientypen:
 * - `activity_completed` (optional `activity_type`, optional `key`): die
 *   gerade abgeschlossene Aktivitaet passt auf Typ/Schluessel. Ohne `key`
 *   passt jede Aktivitaet dieses Typs -- so wird ein global-scoped
 *   Achievement zum "wer zuerst eine beliebige Node abschliesst"-Kriterium.
 * - `track_passed` (`track`): die gerade abgeschlossene Pruefung ist die
 *   des angegebenen Tracks und wurde bestanden.
 * - `first_solve` (optional `activity_type`): dies ist der erste jemals
 *   abgeschlossene Datensatz dieses Nutzers (optional eingegrenzt auf einen
 *   Aktivitaetstyp) in `activity_progress`.
 */
final class AchievementUnlockEvaluator
{
    public function __construct(
        private readonly AchievementService $achievements,
    ) {}

    /**
     * @return list<array<string, mixed>> neu freigeschaltete Achievements, fertig fuers Frontend
     */
    public function evaluate(Activity $activity, User $user, ActivityResult $result): array
    {
        if (! $result->completed) {
            return [];
        }

        $unlocked = [];

        $definitions = AchievementDefinition::query()->whereNotNull('unlock_when')->get();

        foreach ($definitions as $definition) {
            if (! $this->matches($definition, $activity, $user)) {
                continue;
            }

            $unlockResult = $this->achievements->unlock(
                $user,
                $definition->slug,
                ['activity_type' => $activity->type, 'activity_key' => $activity->key, 'source' => 'activity_completed'],
                $definition->scope === 'global' ? $activity : null,
            );

            if ($unlockResult->isNewlyUnlocked() && $unlockResult->definition !== null) {
                $unlocked[] = $this->achievements->toArray($unlockResult->definition, $unlockResult->unlock);
            }
        }

        return $unlocked;
    }

    private function matches(AchievementDefinition $definition, Activity $activity, User $user): bool
    {
        /** @var array<string, mixed> $criterion */
        $criterion = $definition->unlock_when;

        return match ($criterion['type'] ?? null) {
            'activity_completed' => $this->matchesActivityCompleted($criterion, $activity),
            'track_passed' => $this->matchesTrackPassed($criterion, $activity),
            'first_solve' => $this->matchesFirstSolve($criterion, $activity, $user),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $criterion
     */
    private function matchesActivityCompleted(array $criterion, Activity $activity): bool
    {
        if (isset($criterion['activity_type']) && $criterion['activity_type'] !== $activity->type) {
            return false;
        }

        if (isset($criterion['key']) && $criterion['key'] !== $activity->key) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $criterion
     */
    private function matchesTrackPassed(array $criterion, Activity $activity): bool
    {
        return $activity->type === 'exam' && ($criterion['track'] ?? null) === $activity->key;
    }

    /**
     * @param  array<string, mixed>  $criterion
     */
    private function matchesFirstSolve(array $criterion, Activity $activity, User $user): bool
    {
        $activityType = $criterion['activity_type'] ?? null;

        if ($activityType !== null && $activityType !== $activity->type) {
            return false;
        }

        $completedCount = ActivityProgress::query()
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->when(
                $activityType !== null,
                fn ($query) => $query->whereHas('activity', fn ($activityQuery) => $activityQuery->where('type', $activityType)),
            )
            ->count();

        return $completedCount === 1;
    }
}

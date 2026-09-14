<?php

namespace Database\Seeders;

use App\Achievements\AchievementRegistry;
use App\Models\AchievementDefinition;
use Illuminate\Database\Seeder;

/**
 * Idempotent ueber `updateOrCreate(slug, ...)`: erneutes Seeden erzeugt
 * keine Duplikate und aktualisiert vorhandene Definitionen (z. B. nach
 * einer Text-/Bildkorrektur in der Registry), ohne bestehende
 * achievement_unlocks-Zeilen zu beruehren.
 */
class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AchievementRegistry::all() as $definition) {
            AchievementDefinition::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                $definition,
            );
        }
    }
}

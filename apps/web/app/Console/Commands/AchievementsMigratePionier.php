<?php

namespace App\Console\Commands;

use App\Models\Achievement;
use App\Models\AchievementDefinition;
use App\Models\AchievementUnlock;
use App\Models\Activity;
use App\Models\TrackBadge;
use Illuminate\Console\Command;

/**
 * Einmaliger Backfill fuer die Migration des "Pionier"-Systems (ADR
 * 0090b): uebertraegt die bestehenden `achievements` (type=first_blood)
 * und `track_badges`-Zeilen unveraendert nach `achievement_unlocks`, unter
 * den neuen deklarativen Slugs "trailblazer" und "track-<trackslug>".
 *
 * Bewusst NICHT ueber ActivityBackfillProgress/AchievementUnlockEvaluator
 * neu ausgewertet: first_blood/TrackBadge wurden live vergeben, ihr
 * `awarded_at`/`user_id` ist bereits der korrekte historische Gewinner.
 * Eine Neuauswertung aus `node_attempts`/`exam_attempts` wuerde stattdessen
 * nach Zeilen-Erstellungsreihenfolge sortieren (chunkById), die von der
 * echten Loese-Chronologie abweichen kann (z. B. ein frueh gestarteter,
 * spaet geloester Versuch) -- ein falscher "Pionier" waere die Folge.
 *
 * Idempotent ueber die bestehenden Unique-Constraints von
 * achievement_unlocks: erneutes Ausfuehren erzeugt keine Duplikate.
 *
 * Bewusst KEINE Migration, die `achievements`/`track_badges` droppt: die
 * Quelldaten muessen fuer diesen Befehl erhalten bleiben, bis der
 * Operator ihn in jeder echten Umgebung ausgefuehrt und bestaetigt hat --
 * das automatische `migrate --force` beim Deploy darf sie nicht vorher
 * loeschen. Das Droppen der beiden Alt-Tabellen ist ein bewusst
 * getrennter, spaeterer Schritt (siehe docs/offene-fragen.md).
 */
class AchievementsMigratePionier extends Command
{
    protected $signature = 'achievements:migrate-pionier';

    protected $description = 'Backfill: alte achievements/track_badges (Pionier-System) nach achievement_unlocks (ADR 0090b)';

    public function handle(): int
    {
        $trailblazer = AchievementDefinition::where('slug', 'trailblazer')->first();

        if ($trailblazer === null) {
            $this->error('Achievement-Definition "trailblazer" fehlt -- zuerst `php artisan db:seed --class=AchievementSeeder` ausfuehren.');

            return self::FAILURE;
        }

        $firstBloods = 0;
        Achievement::query()->where('type', 'first_blood')->with('node')->chunkById(200, function ($rows) use ($trailblazer, &$firstBloods) {
            foreach ($rows as $row) {
                if ($row->node === null) {
                    continue;
                }

                $activity = Activity::query()->where('type', 'node')->where('key', $row->node->slug)->first();

                if ($activity === null) {
                    $this->warn("Keine Activity fuer Node \"{$row->node->slug}\" -- ueberspringe first_blood-Zeile #{$row->id} (erst `content:sync` ausfuehren?).");

                    continue;
                }

                AchievementUnlock::query()->updateOrCreate(
                    ['achievement_definition_id' => $trailblazer->id, 'activity_id' => $activity->id],
                    ['user_id' => $row->user_id, 'unlocked_at' => $row->awarded_at, 'metadata' => ['source' => 'pionier_backfill']],
                );
                $firstBloods++;
            }
        });

        $trackBadges = 0;
        TrackBadge::query()->with('track')->chunkById(200, function ($rows) use (&$trackBadges) {
            foreach ($rows as $row) {
                if ($row->track === null) {
                    continue;
                }

                $definition = AchievementDefinition::where('slug', "track-{$row->track->slug}")->first();

                if ($definition === null) {
                    $this->warn("Keine Achievement-Definition \"track-{$row->track->slug}\" -- ueberspringe TrackBadge-Zeile #{$row->id}.");

                    continue;
                }

                AchievementUnlock::query()->updateOrCreate(
                    ['user_id' => $row->user_id, 'achievement_definition_id' => $definition->id],
                    ['unlocked_at' => $row->awarded_at, 'metadata' => ['source' => 'pionier_backfill']],
                );
                $trackBadges++;
            }
        });

        $this->info("achievements:migrate-pionier — {$firstBloods} Trailblazer-Zeilen, {$trackBadges} Track-Badges uebertragen.");

        return self::SUCCESS;
    }
}

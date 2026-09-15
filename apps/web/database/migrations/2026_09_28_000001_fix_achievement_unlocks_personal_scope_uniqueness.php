<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Behebt eine Luecke aus der urspruenglichen achievement_unlocks-
     * Migration (2026_09_18): `unique(user_id, achievement_definition_id)`
     * wurde nie auf persoenliche Achievements eingeschraenkt, als
     * `add_activity_id_to_achievement_unlocks_table` (2026_09_25) globale
     * Achievements einfuehrte. Ergebnis: ein Nutzer, der ein global-
     * scoped Achievement (z. B. "trailblazer", ADR 0090b) auf einer
     * zweiten Aktivitaet erneut gewinnt, scheitert an dieser Alt-
     * Constraint -- AchievementService::unlock() faengt die
     * UniqueConstraintViolationException ab und meldet faelschlich
     * "bereits freigeschaltet", ohne die zweite Zeile zu schreiben.
     *
     * Ersetzt die unbedingte Constraint durch einen partiellen Unique-
     * Index (`WHERE activity_id IS NULL`), der nur persoenliche
     * Achievements (activity_id immer NULL) betrifft -- global-scoped
     * bleiben ausschliesslich durch `unique(achievement_definition_id,
     * activity_id)` geschuetzt, das schon einen eigenen Gewinner pro
     * Aktivitaet erlaubt. SQLite und Postgres unterstuetzen beide
     * partielle Indizes mit identischer Syntax.
     */
    public function up(): void
    {
        Schema::table('achievement_unlocks', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'achievement_definition_id']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX achievement_unlocks_personal_scope_unique '.
            'ON achievement_unlocks (user_id, achievement_definition_id) '.
            'WHERE activity_id IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX achievement_unlocks_personal_scope_unique');

        Schema::table('achievement_unlocks', function (Blueprint $table) {
            $table->unique(['user_id', 'achievement_definition_id']);
        });
    }
};

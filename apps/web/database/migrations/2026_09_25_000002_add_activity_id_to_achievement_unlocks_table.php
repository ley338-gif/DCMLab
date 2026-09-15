<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traegt bei global-scoped Achievements (ADR 0077) die Aktivitaet, fuer
     * die dieser Datensatz der einmalige globale Gewinner ist -- bei
     * persoenlichen Achievements bleibt sie NULL, die bestehende
     * unique(user_id, achievement_definition_id) reicht dort weiterhin
     * (siehe AchievementUnlock Klassendoc, ADR 0077).
     *
     * `unique(achievement_definition_id, activity_id)` schuetzt global-
     * scoped Achievements race-sicher gegen einen zweiten Gewinner
     * *derselben* Aktivitaet: SQL behandelt NULL nie als gleich zu NULL,
     * daher wirkt dieser Constraint fuer persoenliche Achievements
     * (activity_id immer NULL) nie einschraenkend.
     */
    public function up(): void
    {
        Schema::table('achievement_unlocks', function (Blueprint $table) {
            $table->foreignId('activity_id')->nullable()->after('achievement_definition_id')->constrained()->nullOnDelete();

            $table->unique(['achievement_definition_id', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::table('achievement_unlocks', function (Blueprint $table) {
            $table->dropUnique(['achievement_definition_id', 'activity_id']);
            $table->dropConstrainedForeignId('activity_id');
        });
    }
};

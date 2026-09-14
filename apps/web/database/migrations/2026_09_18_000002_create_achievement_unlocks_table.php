<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ein Unlock pro Nutzer und Achievement-Definition (analog zu
     * track_badges). unique(user_id, achievement_definition_id) sorgt fuer
     * Idempotenz -- AchievementService::unlock() verlaesst sich darauf, um
     * gleichzeitige Requests race-sicher abzufangen (gleiches Muster wie
     * ProfileService::maybeAwardFirstBlood()).
     */
    public function up(): void
    {
        Schema::create('achievement_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_definition_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'achievement_definition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_unlocks');
    }
};

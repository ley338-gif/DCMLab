<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Einzige Quelle fuer abgeschlossene Aktivitaeten (ADR 0072): Punkte,
     * Rang, Skill-Radar und Achievement-Ausloeser rechnen ausschliesslich
     * gegen diese Tabelle, nicht mehr verstreut gegen node_attempts,
     * track_badges und lesson_progress einzeln. Der laufende
     * Versuchszustand (engine_session_id, hints_used, gezogene
     * Pruefungsfragen, ...) bleibt bewusst typspezifisch in den
     * bestehenden Tabellen -- hier landet nur das dauerhafte Ergebnis.
     */
    public function up(): void
    {
        Schema::create('activity_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('score')->nullable();
            $table->unsignedInteger('max_score')->nullable();
            $table->json('skills')->default('[]'); // belegte Skill-Kategorien dieses Abschlusses
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_progress');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Oeffentliches Profil pro Nutzer (Abschnitt 7): Rang und Skill-Radar
     * sind bewusst denormalisiert (nicht bei jedem Seitenaufruf aus
     * node_attempts neu berechnet) und werden von ProfileService bei jedem
     * geloesten Flag aktualisiert -- sonst muesste jede Profilanzeige ueber
     * alle Node-Versuche summieren.
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('public_slug')->unique();
            $table->string('rank')->default('novice');
            $table->integer('points')->default(0);
            $table->json('skill_vector')->default('{}');
            $table->boolean('leaderboard_opt_in')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};

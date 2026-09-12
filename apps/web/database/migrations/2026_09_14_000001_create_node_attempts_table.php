<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ein Versuch pro Nutzer und Node (Abschnitt 7). Der eigentliche
     * Sitzungszustand (Hosts, Zaehler, Konfig) lebt in der Engine
     * (engine_session_id verweist darauf) -- hier steht nur, was Laravel
     * fuer Uebersicht/Punkte/Profil braucht.
     */
    public function up(): void
    {
        Schema::create('node_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->uuid('engine_session_id');
            $table->string('status')->default('started'); // started | solved
            $table->json('hints_used')->default('[]');
            $table->unsignedSmallInteger('points')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('flag_submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_attempts');
    }
};

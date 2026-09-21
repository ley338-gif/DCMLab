<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ephemere Sitzung fuer die autorisierte Draft-Node-Vorschau (siehe
     * `NodeController::previewSessionFor()`): strukturell dieselben Felder
     * wie `node_attempts`, aber bewusst eine eigene, komplett isolierte
     * Tabelle statt eines Zusatzfeldes dort. `node_attempts` hat
     * `unique(['user_id','node_id'])` -- eine Draft-Vorschau darf diesen
     * einzigen Datensatz je Nutzer/Node nie belegen, sonst wuerde derselbe
     * Nutzer beim spaeteren echten Spielen (nach Veroeffentlichung) direkt
     * auf einem bereits "geloesten" Attempt landen. Keine Stelle im System
     * (ProfileService, ActivityProgressRecorder, Leaderboard, Index-
     * Badges) liest `node_preview_sessions` -- das ist die eigentliche
     * Absicherung, kein zusaetzliches Flag/Filter an jeder Leseseite.
     */
    public function up(): void
    {
        Schema::create('node_preview_sessions', function (Blueprint $table) {
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
        Schema::dropIfExists('node_preview_sessions');
    }
};

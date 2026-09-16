<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ein Versuch pro Nutzer und Lab-Activity (analog `node_attempts`).
     * `current_sandbox_session_id` ist NUR ein Komfortzeiger auf die zuletzt
     * gestartete Runtime-Sitzung -- die eigentliche, historische Beziehung
     * ist umgekehrt (`sandbox_sessions.lab_attempt_id`, CMS-8b): ein Attempt
     * kann im Lauf der Zeit mehrere Sessions haben (Neustart nach TTL-
     * Ablauf), die Spalte hier zeigt nur auf die aktuell aktive.
     */
    public function up(): void
    {
        Schema::create('lab_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('started'); // started | solved | abandoned
            $table->json('assertions_passed')->default('[]');
            $table->foreignId('current_sandbox_session_id')->nullable()
                ->constrained('sandbox_sessions')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_attempts');
    }
};

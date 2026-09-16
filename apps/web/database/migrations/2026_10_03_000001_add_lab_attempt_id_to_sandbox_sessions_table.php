<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CMS-8b: die eigentliche, historische Beziehung zwischen einem
     * LabAttempt und seinen Runtime-Sitzungen ist umgekehrt zu
     * `lab_attempts.current_sandbox_session_id` (nur ein Komfortzeiger auf
     * die aktuell aktive Sitzung, CMS-8a) -- ein Attempt kann im Lauf der
     * Zeit mehrere Sessions haben (Neustart nach TTL-Ablauf). Nullable,
     * weil eine Sandbox-Sitzung (Lesson-eingebettete Spielwiese) weiterhin
     * kein Attempt-Konzept hat und `lab_attempt_id` fuer sie immer `null`
     * bleibt.
     */
    public function up(): void
    {
        Schema::table('sandbox_sessions', function (Blueprint $table) {
            $table->foreignId('lab_attempt_id')->nullable()
                ->after('activity_id')
                ->constrained('lab_attempts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sandbox_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lab_attempt_id');
        });
    }
};

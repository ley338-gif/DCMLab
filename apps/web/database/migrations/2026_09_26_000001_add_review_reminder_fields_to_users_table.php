<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * W7 (Wiederholungs-Erinnerungen, ADR 0084): abschaltbar im Profil,
     * Standard an. `review_reminder_sent_at` traegt den Zeitpunkt der
     * zuletzt verschickten Erinnerung -- der Abstand zwischen zwei Mails
     * wird dagegen geprueft, nicht gegen einen festen Wochentag, damit
     * "genau eine Mail pro Intervall" unabhaengig davon gilt, wie oft der
     * Scheduler tatsaechlich laeuft.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('review_reminders_enabled')->default(true)->after('email_verified_at');
            $table->timestamp('review_reminder_sent_at')->nullable()->after('review_reminders_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['review_reminders_enabled', 'review_reminder_sent_at']);
        });
    }
};

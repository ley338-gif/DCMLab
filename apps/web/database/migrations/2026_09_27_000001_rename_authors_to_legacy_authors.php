<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Loest die offene Frage "Herkunft von `authors` beim Import"
     * (docs/offene-fragen.md): der Bestandscontent traegt in `authors`
     * reinen Freitext (z. B. "ley338") ohne Kontobezug, geschrieben von
     * `ContentSync`, nie aufgeloest auf ein echtes Nutzerkonto. Die echte
     * Rechteprüfung (ADR 0071, W3) laeuft ausschliesslich ueber die
     * `activity_authors`-Pivot-Tabelle (`Activity::authorUsers()`) -- diese
     * Spalte war schon vorher nur Historie, nie eine Berechtigungsquelle
     * (siehe Docblock-Kommentar an `Activity::$authors` vor dieser
     * Migration). Die Umbenennung macht das strukturell unmissverstaendlich,
     * statt es nur im Kommentar zu behaupten.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->renameColumn('authors', 'legacy_authors');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->renameColumn('authors', 'legacy_authors');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->renameColumn('legacy_authors', 'authors');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->renameColumn('legacy_authors', 'authors');
        });
    }
};

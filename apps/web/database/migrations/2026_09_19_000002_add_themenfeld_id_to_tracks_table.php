<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable statt NOT NULL: bestehende tracks-Zeilen haben beim Anwenden
     * dieser Migration noch keinen Wert (Produktion faehrt migrate und
     * content:sync als getrennte Schritte), und die Testsuite laeuft gegen
     * SQLite, das kein ALTER COLUMN ... SET NOT NULL kennt. content:sync
     * befuellt die Spalte sofort danach (Abschnitt 13).
     */
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->foreignId('themenfeld_id')->nullable()->after('slug')->constrained('themenfelder');
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('themenfeld_id');
        });
    }
};

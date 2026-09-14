<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable statt NOT NULL, aus denselben Gruenden wie bei
     * tracks.themenfeld_id (siehe 2026_09_19_000002): migrate und
     * content:sync laufen als getrennte Schritte, SQLite in der
     * Testsuite kennt kein ALTER COLUMN. content:sync befuellt die
     * Spalte sofort danach (Abschnitt 13).
     */
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->foreignId('themenfeld_id')->nullable()->after('category')->constrained('themenfelder');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('themenfeld_id');
        });
    }
};

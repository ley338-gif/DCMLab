<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Erster Schritt von CMS-5 (ADR 0101): `title`/`teaser` sind seit ADR
     * 0071 bereits DB-Spalten, `body`/`objectives` fehlten noch --
     * `LessonController::show()` musste dafuer bisher immer live aus
     * `content/lessons/<id>/de.md` lesen (der zentrale, im CMS-0-Audit
     * dokumentierte Befund). `content:sync` befuellt diese Spalten jetzt
     * zusaetzlich aus derselben Datei, die es ohnehin schon fuer
     * title/teaser liest -- keine neue Datenquelle, nur eine zusaetzliche
     * Spalte je bereits gelesenem Feld.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->text('body')->nullable()->after('teaser');
            $table->json('objectives')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['body', 'objectives']);
        });
    }
};

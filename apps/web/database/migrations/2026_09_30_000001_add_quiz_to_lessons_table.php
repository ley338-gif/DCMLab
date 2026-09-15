<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quiz wird DB-basiert (ADR 0104, CMS-6a) -- dieselbe Bewegung wie
     * ADR 0101/0102 fuer die Lektion selbst, nur fuer die Fragen-Metadaten
     * (`id`/`type`/`answer`, dasselbe, was bisher `meta.yml`s `quiz:`-Block
     * trug). Der eigentliche Fragetext/Optionen stecken bereits im
     * `body`-Feld (seit ADR 0101, als Teil des vollstaendigen Markdowns
     * inklusive `## Quiz`-Abschnitt) -- kein zweiter Speicherort dafuer.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->json('quiz')->nullable()->after('objectives');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('quiz');
        });
    }
};

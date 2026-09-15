<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Erster Schritt von CMS-6d (ADR 0107): Node ist bisher "ausdruecklich
     * nur ein Index ueber content/nodes/<slug>/" (Node-Klassendoc) -- wie
     * bei Lesson vor ADR 0101 fehlt der eigentliche Fliesstext als
     * DB-Spalte. `body` traegt denselben vollstaendigen Markdown-Text, den
     * `NodeSections::parse()` ohnehin schon in Briefing/Hints/Write-up
     * zerlegt (reine Text-Funktion, kein Datei-I/O -- funktioniert
     * unveraendert gegen den DB-Wert). `hints` sind die Metadaten
     * (id/cost) aus `node.yml`s `hints:`-Block, analog zu `lessons.quiz`
     * fuer Fragen-Metadaten (ADR 0104) -- Hint-TEXT steckt bereits in
     * `body`, nur id/cost braucht eine eigene Spalte.
     *
     * Bewusst NICHT Teil dieser Migration: Runtime-/Sicherheitsparameter
     * (Sandbox-Template, Dataset, Engine-Konfiguration, Flag-Validierung,
     * Netzwerk-/Ressourcenlimits) -- die bleiben Admin-/System-Sache, kein
     * normales Autorenfeld (siehe ADR 0107).
     */
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->text('body')->nullable()->after('scenario_title');
            $table->json('hints')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['body', 'hints']);
        });
    }
};

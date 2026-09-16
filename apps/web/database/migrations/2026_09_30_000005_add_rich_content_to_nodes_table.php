<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CMS-7d.1 (ADR 0115), Gegenstueck zu
     * `2026_09_30_000004_add_rich_content_to_lessons_table`. Anders als bei
     * Lesson traegt diese Spalte kein einzelnes RichContentDocument, sondern
     * den `node_content`-Umschlag (Briefing/Hints je Id/Write-up einzeln,
     * siehe ADR 0115) -- `body`s drei Funktionsbereiche bleiben getrennte
     * Editorfelder, statt sich weiterhin unsichtbar ueber H2/H3-Ueberschriften
     * zu definieren. Rein additiv, `body` bleibt bis CMS-7d.4 die
     * Rendering-Quelle.
     */
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->jsonb('rich_content')->nullable()->after('hints');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('rich_content');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CMS-8a: `lessons.lab` (Node-Verweis im Lektions-Toolbar, ADR 0089)
     * kollidiert im Namen mit dem neuen `Lab`-Activity-Typ (CMS-8) --
     * benennt die Spalte um, der Inhalt (`{node, optional}`) bleibt
     * unveraendert.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->renameColumn('lab', 'related_node');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->renameColumn('related_node', 'lab');
        });
    }
};

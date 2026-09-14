<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * interaction waehlt das Frontend/Engine-Paar einer Node (Abschnitt
     * 13, PoC "Datenschutz"): terminal (Standard, services/engine) oder
     * scenario (services/scenario-engine). Default deckt alle
     * bestehenden Nodes ab, ohne dass ihr node.yml das Feld braucht.
     */
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('interaction')->default('terminal')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('interaction');
        });
    }
};

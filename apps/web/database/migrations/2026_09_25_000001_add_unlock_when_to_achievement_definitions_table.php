<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deklaratives Auslösekriterium (ADR 0071/0077, W4): ein Achievement,
     * das nur als Dateneintrag in content/achievements.yml entsteht, soll
     * beim Abschluss der zugeordneten Aktivität vergeben werden, ohne
     * Controller-Code. `scope` unterscheidet persönliche Abzeichen
     * (Default, unique je Nutzer) von global-einmaligen (unique je
     * Aktivität, unabhängig vom Nutzer) -- ein Feld statt einer zweiten
     * Tabelle, siehe ADR 0077.
     */
    public function up(): void
    {
        Schema::table('achievement_definitions', function (Blueprint $table) {
            $table->string('scope')->default('personal')->after('slug'); // personal | global
            $table->json('unlock_when')->nullable()->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('achievement_definitions', function (Blueprint $table) {
            $table->dropColumn(['scope', 'unlock_when']);
        });
    }
};

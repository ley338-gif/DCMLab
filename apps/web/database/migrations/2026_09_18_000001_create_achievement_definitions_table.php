<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registry-Zeilen des neuen, generischen Achievement-Systems. Bewusst
     * eine eigene Tabelle statt Erweiterung von `achievements`: jene Tabelle
     * gehoert dem aelteren First-Blood-Feature (globaler Wettlauf pro Node,
     * unique(node_id, type), siehe ADR 0009) und hat eine andere Bedeutung
     * als "eine von mehreren generischen, pro Nutzer freischaltbaren
     * Auszeichnungen". Aus dem Seeder befuellt (AchievementSeeder), aus der
     * Registry-Klasse App\Achievements\AchievementRegistry gespeist.
     */
    public function up(): void
    {
        Schema::create('achievement_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('image');
            $table->string('category');
            $table->string('rarity')->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->boolean('is_hidden')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_definitions');
    }
};

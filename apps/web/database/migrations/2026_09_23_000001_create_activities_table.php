<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verzeichnis aller Aktivitaeten (ADR 0072): ein Registereintrag pro
     * Lektion, Node und Track-Pruefung, unabhaengig vom jeweiligen
     * Speicherort ihrer eigentlichen Nutzdaten. `type` + `key` bilden den
     * fachlichen Schluessel (`key` ist der jeweilige natuerliche Schluessel:
     * lesson_id, Node-Slug oder Track-Slug) -- diese Tabelle dupliziert
     * bewusst keine Prosa oder Fachdaten, sondern nur das, was fuer
     * Sortierung, Sichtbarkeit und Zuordnung zu `activity_progress` noetig
     * ist. `content:sync` befuellt sie zusaetzlich zu `lessons`/`nodes` (siehe
     * ContentSync::syncActivities()).
     *
     * Bewusst (noch) keine physische Zusammenfuehrung von `lessons`/`nodes`/
     * Pruefungsdefinitionen in diese Tabelle -- siehe ADR 0073.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // lesson | quiz | exam | node | sandbox (ActivityType)
            $table->string('key'); // lesson_id | node.slug | track.slug
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('order')->default(0);
            $table->string('status')->default('draft');
            $table->json('authors')->default('[]');
            $table->json('title')->nullable();
            $table->json('teaser')->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['type', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};

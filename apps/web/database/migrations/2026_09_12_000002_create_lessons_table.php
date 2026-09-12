<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * lessons ist ein Index UEBER content/lessons/ (Abschnitt 7): Slug, Titel,
     * Reihenfolge, Punkte-relevante Metadaten und ein Hash der Quelldateien.
     * Die Prosa selbst bleibt im Dateisystem -- content:sync liest sie nur,
     * um Titel/Teaser fuer Uebersichtsseiten hier abzulegen.
     */
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->string('lesson_id')->unique(); // z.B. "1.5" -- IMMER String
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('order');
            $table->string('level');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('objectives_count');
            $table->json('requires');
            $table->json('tools');
            $table->json('sandbox')->nullable();
            $table->json('lab')->nullable();
            $table->json('glossary_terms');
            $table->date('tools_checked')->nullable();
            $table->string('status');
            $table->json('authors');
            $table->date('content_updated_at')->nullable();
            $table->json('title'); // {"de": "..."} -- Abschnitt 7: uebersetzbare Felder als JSONB
            $table->json('teaser');
            $table->string('source_hash', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};

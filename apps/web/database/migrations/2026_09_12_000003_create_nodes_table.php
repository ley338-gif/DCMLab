<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * nodes ist ein Index UEBER content/nodes/ (Abschnitt 7). Umgebung, Flag
     * und Hints bleiben im Dateisystem/bei der Engine -- hier stehen nur die
     * Angaben, die Uebersichtsseiten und node_attempts (spaeter) brauchen.
     */
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('difficulty');
            $table->unsignedSmallInteger('points');
            $table->string('category');
            $table->json('skills');
            $table->json('related_lessons');
            $table->unsignedSmallInteger('estimated_minutes');
            $table->string('status');
            $table->date('content_updated_at')->nullable();
            $table->json('title'); // {"de": "..."}
            $table->json('scenario_title');
            $table->string('source_hash', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `authors` wird eine echte Beziehung auf `users` (ADR 0071, W3) statt
     * der Freitextliste in `activities.authors`/`lessons.authors`. Diese
     * Pivot-Tabelle ist die neue Quelle fuer Berechtigungspruefungen
     * (ActivityPolicy); die Freitextspalten bleiben unangetastet und
     * weiterhin von `content:sync` gepflegt, bis der Bestandsimport
     * entscheidet, wie sich Freitext auf echte Konten abbildet (siehe
     * docs/offene-fragen.md).
     */
    public function up(): void
    {
        Schema::create('activity_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['activity_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_authors');
    }
};

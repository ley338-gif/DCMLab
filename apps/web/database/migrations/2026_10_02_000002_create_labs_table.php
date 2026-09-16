<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CMS-8a: `labs` ist der DB-native Index fuer den neuen Lab-Activity-Typ
     * (Entscheidung C, siehe ADR-Nachtrag) -- anders als `nodes` gibt es kein
     * content/labs/**-Dateipendant, ein Lab existiert von Anfang an nur in
     * der DB (Autoren-Editor CMS-8c, Publish direkt ueber LabContentPublisher).
     * Runtime-/Assertion-Konfiguration (`runtime_template`, `dataset`,
     * `assertions`) ist bewusst Teil dieser Tabelle, nicht einer eigenen --
     * sie ist reine, mit dem Rest der Lab-Felder gemeinsam versionierte
     * Autorenkonfiguration (CMS-8c), keine Laufzeit- oder Lernfortschritts-
     * Buchfuehrung (die lebt in `lab_attempts`/`sandbox_sessions`).
     */
    public function up(): void
    {
        Schema::create('labs', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('difficulty');
            $table->unsignedSmallInteger('points');
            $table->unsignedSmallInteger('estimated_minutes');
            $table->string('runtime_template')->nullable();
            $table->string('dataset')->nullable();
            $table->json('assertions');
            $table->string('status');
            $table->json('title'); // {"de": "..."}
            $table->json('scenario_title');
            $table->json('rich_content')->nullable();
            $table->string('source_hash', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labs');
    }
};

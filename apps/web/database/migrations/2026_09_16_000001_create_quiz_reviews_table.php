<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wiederholungs-Zustand pro Nutzer und Quiz-Frage (Abschnitt 4.7, "quiz:").
     * Die Frage selbst (Text, Optionen, richtige Antwort) bleibt in
     * content/lessons/<id>/meta.yml + de.md -- diese Tabelle speichert nur den
     * SM-2-artigen Terminplan, nicht den Inhalt (Abschnitt 7: Dateien sind die
     * Wahrheit, die DB nur ein Index/Zustand darueber). Zeilen entstehen erst
     * bei der ersten Beantwortung, kein Eager-Seeding.
     */
    public function up(): void
    {
        Schema::create('quiz_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('question_id'); // z.B. "q1" -- eindeutig nur innerhalb der Lektion
            $table->unsignedSmallInteger('repetitions')->default(0);
            $table->decimal('ease_factor', 4, 2)->default(2.5);
            $table->unsignedInteger('interval_days')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->string('last_result')->nullable(); // correct | incorrect
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_reviews');
    }
};

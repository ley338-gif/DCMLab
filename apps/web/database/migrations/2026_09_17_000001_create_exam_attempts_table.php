<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ein Versuch einer Track-Abschlusspruefung (P10.60). Die Fragen selbst
     * bleiben in content/exams/<track>/{exam.yml,de.md} -- diese Tabelle
     * speichert nur den Zustand eines laufenden/abgeschlossenen Versuchs:
     * welche Fragen gezogen wurden (feste Reihenfolge, damit ein Reload
     * mitten in der Pruefung nichts verliert), wie weit der Nutzer ist, und
     * das Ergebnis. Absichtlich kein zweiter Kartenstapel: jede beantwortete
     * Frage fliesst zusaetzlich in quiz_reviews (ExamAttemptService ruft
     * QuizSchedulerService::recordAnswer() mit der f-ID als question_id).
     */
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('in_progress'); // in_progress | completed
            $table->json('question_ids'); // gezogene f-IDs, feste Reihenfolge
            $table->unsignedTinyInteger('current_index')->default(0);
            $table->json('answers'); // {"f01": {"submitted": ..., "correct": true}, ...}
            $table->unsignedTinyInteger('score_correct')->nullable();
            $table->unsignedTinyInteger('score_total')->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};

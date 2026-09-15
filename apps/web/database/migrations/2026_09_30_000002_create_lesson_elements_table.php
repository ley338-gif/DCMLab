<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die geordnete Elementsequenz einer Lektion (ADR 0105, CMS-6b) --
     * beantwortet "was ist ein Lesson Element", bevor ein Rich-Content-
     * Editor (CMS-7) die Architektur diktiert. Bewusst kein vollkommen
     * beliebiges Polymorphie-Feld ("type" kennt jeden zukuenftigen
     * Modultyp") -- nur zwei Werte:
     *
     *   type = content  -> zeigt (vorerst) auf lessons.body/objectives,
     *                       `content_block_id` bleibt bis CMS-7 ungenutzt
     *                       (ein Content-Element wird dort in einen
     *                       strukturierten Dokumentbaum zerlegt).
     *   type = activity -> `activity_id` verweist auf eine echte
     *                       Activity-Zeile (Sandbox/Quiz/Node/...);
     *                       WELCHE Art Activity es ist, sagt die Activity
     *                       selbst (`activities.type`) -- lesson_elements
     *                       kennt keinen einzigen Modultyp namentlich.
     *
     * `content_block_id` hat bewusst keinen Fremdschluessel-Constraint --
     * es gibt noch keine `content_blocks`-Tabelle, die Spalte reserviert
     * nur die Form.
     */
    public function up(): void
    {
        Schema::create('lesson_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // content | activity
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('content_block_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_elements');
    }
};

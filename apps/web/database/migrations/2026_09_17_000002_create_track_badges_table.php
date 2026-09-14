<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ein Badge pro Nutzer und bestandenem Track (P10.60). Bewusst eine
     * eigene Tabelle statt Wiederverwendung von `achievements`: dessen
     * unique(node_id, type) ist auf "wer war global zuerst" zugeschnitten
     * (first_blood, ADR 0009), waehrend hier jeder Nutzer, der besteht,
     * sein eigenes Abzeichen bekommt. unique(user_id, track_id) verhindert
     * Doppel-Vergabe bei wiederholtem Bestehen desselben Tracks.
     */
    public function up(): void
    {
        Schema::create('track_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->unique(['user_id', 'track_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_badges');
    }
};
